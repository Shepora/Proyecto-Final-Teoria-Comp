<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

require_once 'db.php';

// Encryption key and iv
define('ENCRYPTION_KEY', 'your-secret-key-32charslength!1234567890');
define('ENCRYPTION_IV', '1234567890123456');

function encryptData($data) {
    return base64_encode(openssl_encrypt($data, 'AES-256-CBC', ENCRYPTION_KEY, 0, ENCRYPTION_IV));
}

function decryptData($data) {
    if (empty($data)) return '';
    $decrypted = openssl_decrypt(base64_decode($data), 'AES-256-CBC', ENCRYPTION_KEY, 0, ENCRYPTION_IV);
    return $decrypted === false ? '' : $decrypted;
}

$error = '';
$edit_mode = false;
$id = '';
$cliente = '';
$contacto = '';
$producto_id = '';
$cantidad = '';
$fecha_venta = '';
$confirmada = 0;

// Handle delete
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    // Only delete if not confirmed
    $stmtCheck = $pdo->prepare("SELECT confirmada FROM ventas WHERE id = ?");
    $stmtCheck->execute([$delete_id]);
    $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    if ($row && (int)$row['confirmada'] === 0) {
        $stmt = $pdo->prepare('DELETE FROM ventas WHERE id = ?');
        $stmt->execute([$delete_id]);
    }
    header('Location: ventas.php');
    exit();
}

// Load for edit
if (isset($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    $stmt = $pdo->prepare('SELECT * FROM ventas WHERE id = ?');
    $stmt->execute([$edit_id]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($venta) {
        $id = $venta['id'];
        if ((int)$venta['confirmada'] === 1) {
            $cliente = decryptData($venta['cliente']);
            $contacto = decryptData($venta['contacto']);
        } else {
            $cliente = $venta['cliente'];
            $contacto = $venta['contacto'];
        }
        $producto_id = $venta['producto_id'];
        $cantidad = $venta['cantidad'];
        $fecha_venta = $venta['fecha_venta'];
        $confirmada = (int)$venta['confirmada'];
        $edit_mode = true;
    } else {
        $error = "Venta no encontrada para editar.";
    }
}

// Add or update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $cliente = trim($_POST['cliente'] ?? '');
    $contacto = trim($_POST['contacto'] ?? '');
    $producto_id = $_POST['producto_id'] ?? '';
    $cantidad = $_POST['cantidad'] ?? '';
    $fecha_venta = $_POST['fecha_venta'] ?? '';
    $edit_mode = isset($_POST['edit_mode']) && $_POST['edit_mode'] === '1';
    $confirm_action = isset($_POST['confirmar']) && $_POST['confirmar'] === '1';

    if ($cliente === '' || $contacto === '' || $producto_id === '' || $cantidad === '' || $fecha_venta === '') {
        $error = 'Por favor complete todos los campos.';
    } elseif (!ctype_digit($cantidad) || intval($cantidad) <= 0) {
        $error = 'La cantidad debe ser un número entero mayor que 0.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_venta)) {
        $error = 'La fecha de venta debe tener el formato AAAA-MM-DD.';
    } else {
        if ($confirm_action && $edit_mode) {
            try {
                $pdo->beginTransaction();
        
                // Encriptar cliente y contacto
                $cliente_enc = encryptData($cliente);
                $contacto_enc = encryptData($contacto);
        
                // Actualizar la venta con confirmada = 1
                $stmtUpdate = $pdo->prepare('UPDATE ventas SET cliente = ?, contacto = ?, producto_id = ?, cantidad = ?, fecha_venta = ?, confirmada = 1 WHERE id = ?');
                $stmtUpdate->execute([$cliente_enc, $contacto_enc, $producto_id, $cantidad, $fecha_venta, $id]);
        
                // Descontar stock
                $stmtStock = $pdo->prepare('UPDATE productos SET cantidad = cantidad - ? WHERE id = ?');
                $stmtStock->execute([$cantidad, $producto_id]);
        
                // Obtener precio unitario para calcular total
                $stmtPrecio = $pdo->prepare("SELECT precio_unitario FROM productos WHERE id = ?");
                $stmtPrecio->execute([$producto_id]);
                $precioUnitario = $stmtPrecio->fetchColumn();
                $precioTotal = $cantidad * floatval($precioUnitario);
        
                // Insertar en facturacion con cliente y contacto encriptados
                $stmtFactura = $pdo->prepare("INSERT INTO facturacion (venta_id, cliente, contacto, fecha_facturacion, precio_total) VALUES (?, ?, ?, ?, ?)");
                $stmtFactura->execute([$id, $cliente_enc, $contacto_enc, $fecha_venta, $precioTotal]);
        
                $pdo->commit();
        
                header('Location: ventas.php');
                exit();
        
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Error al confirmar la venta: ' . $e->getMessage();
            }
        
        } else {
            if ($edit_mode) {
                // Update (allow editing only if not confirmed)
                $stmtCheck = $pdo->prepare("SELECT confirmada FROM ventas WHERE id = ?");
                $stmtCheck->execute([$id]);
                $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                if ($row && (int)$row['confirmada'] === 0) {
                    $stmt = $pdo->prepare('UPDATE ventas SET cliente = ?, contacto = ?, producto_id = ?, cantidad = ?, fecha_venta = ? WHERE id = ?');
                    try {
                        $stmt->execute([$cliente, $contacto, $producto_id, $cantidad, $fecha_venta, $id]);
                        header('Location: ventas.php');
                        exit();
                    } catch (PDOException $e) {
                        $error = 'Error al actualizar la venta: ' . $e->getMessage();
                    }
                } else {
                    $error = 'No se puede editar una venta confirmada.';
                }
            } else {
                // Insert new (confirmed=0)
                $stmt = $pdo->prepare('INSERT INTO ventas (cliente, contacto, producto_id, cantidad, fecha_venta, confirmada) VALUES (?, ?, ?, ?, ?, 0)');
                try {
                    $stmt->execute([$cliente, $contacto, $producto_id, $cantidad, $fecha_venta]);
                    header('Location: ventas.php');
                    exit();
                } catch (PDOException $e) {
                    $error = 'Error al agregar la venta: ' . $e->getMessage();
                }
            }
        }
    }
}

// Get products for select
$stmt = $pdo->query('SELECT id, nombre FROM productos ORDER BY nombre ASC');
$productosRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
$productos = [];
foreach ($productosRaw as $prod) {
    $productos[] = ['id' => $prod['id'], 'nombre' => decryptData($prod['nombre'])];
}

// Get all sales
$sql = "SELECT * FROM ventas ORDER BY fecha_venta DESC";
$stmt = $pdo->query($sql);
$ventasRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
$ventas = [];
foreach ($ventasRaw as $venta) {
    if ((int)$venta['confirmada'] === 1) {
        $venta['cliente'] = decryptData($venta['cliente']);
        $venta['contacto'] = decryptData($venta['contacto']);
    }
    // Find product name
    $nombreProd = '';
    foreach ($productos as $p) {
        if ($p['id'] == $venta['producto_id']) {
            $nombreProd = $p['nombre'];
            break;
        }
    }
    $venta['nombre_producto'] = $nombreProd;
    $ventas[] = $venta;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"/>
<title>Panel de Ventas - Gestión de Papas</title>
<style>
    /* same styles as before */
    body { font-family: Arial, sans-serif; background: #f7f9fc; margin:0; padding:20px;}
    h1{text-align:center;color:#333;}
    .container {max-width:900px;margin:auto;background:#fff; padding:25px 30px; border-radius:8px;box-shadow:0 0 15px rgba(0,0,0,0.1);}
    form {display:flex; flex-wrap:wrap; gap:15px; align-items:flex-end; margin-bottom:30px;}
    form > div{flex:1 1 200px; display:flex;flex-direction:column;}
    label{margin-bottom:5px; font-weight:bold;}
    input[type=text], input[type=date], input[type=number], select {padding:8px 10px;border:1px solid #ccc; border-radius:4px; font-size:14px;}
    button {background:#4CAF50;border:none;padding:12px 20px;color:#fff;font-weight:bold;border-radius:6px;cursor:pointer;transition:background-color 0.3s ease; flex:0 0 140px;}
    button:hover {background:#45a049;}
    button.confirm {background:#007bff;}
    button.confirm:hover {background:#0056b3;}
    .error {background:#ffdddd; color:#d8000c; border:1px solid #d8000c; padding:10px; margin-bottom:15px; border-radius:4px; text-align:center; font-weight:bold;}
    table {width:100%; border-collapse:collapse; margin-top:10px;}
    th, td {padding:12px 15px; border-bottom:1px solid #ddd; text-align:left;}
    th {background:#4CAF50; color:#fff;}
    tr:hover {background:#f4fef7;}
    .actions a{margin-right:8px; padding:5px 10px; border-radius:4px; color:#fff; text-decoration:none; font-size:14px;}
    .edit-btn{background:#2196F3;}
    .edit-btn:hover{background:#0b7dda;}
    .delete-btn{background:#f44336;}
    .delete-btn:hover{background:#da190b;}
    .back-btn{display:inline-block; margin-top:20px; background:#6c757d; color:#fff; text-decoration:none; padding:10px 16px; border-radius:6px; transition: background-color 0.3s ease;}
    .back-btn:hover{background:#5a6268;}
</style>
</head>
<body>

<div class="container">
    <h1>Panel de Ventas</h1>

    <?php if ($error): ?>
        <div class="error"><?=htmlspecialchars($error)?></div>
    <?php endif; ?>

    <form method="post" action="ventas.php">
        <?php if ($edit_mode): ?>
            <input type="hidden" name="edit_mode" value="1" />
            <input type="hidden" name="id" value="<?=htmlspecialchars($id)?>" />
        <?php endif; ?>

        <div>
            <label for="cliente">Nombre del Cliente</label>
            <input type="text" id="cliente" name="cliente" required value="<?=htmlspecialchars($cliente)?>" />
        </div>

        <div>
            <label for="contacto">Teléfono o Correo</label>
            <input type="text" id="contacto" name="contacto" required value="<?=htmlspecialchars($contacto)?>" />
        </div>

        <div>
            <label for="producto_id">Producto</label>
            <select id="producto_id" name="producto_id" required>
                <option value="">Seleccione un producto</option>
                <?php foreach ($productos as $producto): ?>
                    <option value="<?=htmlspecialchars($producto['id'])?>" <?=($producto['id']==$producto_id)?'selected':''?>>
                        <?=htmlspecialchars($producto['nombre'])?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="cantidad">Cantidad</label>
            <input type="number" id="cantidad" min="1" name="cantidad" required value="<?=htmlspecialchars($cantidad)?>" />
        </div>

        <div>
            <label for="fecha_venta">Fecha de Venta</label>
            <input type="date" id="fecha_venta" name="fecha_venta" required value="<?=htmlspecialchars($fecha_venta ?: date('Y-m-d'))?>" />
        </div>

        <div>
            <button type="submit"><?= $edit_mode ? 'Actualizar' : 'Agregar' ?></button>
            <?php if ($edit_mode && !$confirmada): ?>
                <button type="submit" name="confirmar" value="1" class="confirm" onclick="return confirm('¿Confirmar esta venta? Los datos se encriptarán y no podrán modificarse después.')">Confirmar Venta</button>
            <?php endif; ?>
        </div>
    </form>

    <table>
        <thead>
            <tr>
                <th>ID</th><th>Cliente</th><th>Contacto</th><th>Producto</th><th>Cantidad</th><th>Fecha</th><th>Estado</th><th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($ventas) === 0): ?>
                <tr><td colspan="8" style="text-align:center; font-style:italic;">No hay ventas registradas.</td></tr>
            <?php else: ?>
                <?php foreach ($ventas as $venta): ?>
                <tr>
                    <td><?=htmlspecialchars($venta['id'])?></td>
                    <td><?=htmlspecialchars($venta['cliente'])?></td>
                    <td><?=htmlspecialchars($venta['contacto'])?></td>
                    <td><?=htmlspecialchars($venta['nombre_producto'])?></td>
                    <td><?=htmlspecialchars($venta['cantidad'])?></td>
                    <td><?=htmlspecialchars($venta['fecha_venta'])?></td>
                    <td><?= ((int)$venta['confirmada'] === 1) ? 'Confirmada' : 'Pendiente' ?></td>
                    <td class="actions">
                        <?php if ((int)$venta['confirmada'] === 0): ?>
                            <a href="ventas.php?edit_id=<?=urlencode($venta['id'])?>" class="edit-btn">Editar</a>
                            <a href="ventas.php?delete_id=<?=urlencode($venta['id'])?>" class="delete-btn" onclick="return confirm('¿Está seguro que desea eliminar esta venta?');">Eliminar</a>
                        <?php else: ?>
                            <span style="color:#666; font-style:italic;">No editable</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <a href="dashboard.php" class="back-btn">Volver al Dashboard</a>
</div>

</body>
</html>