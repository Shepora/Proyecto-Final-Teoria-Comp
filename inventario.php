<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Include centralized DB connection
require_once 'db.php';

// Define encryption key and iv - in real life, store securely (e.g. env vars)
define('ENCRYPTION_KEY', 'your-secret-key-32charslength!1234567890'); // 32 chars for AES-256
define('ENCRYPTION_IV', '1234567890123456'); // 16 bytes IV for AES-256-CBC

function encryptData($data) {
    return base64_encode(openssl_encrypt($data, 'AES-256-CBC', ENCRYPTION_KEY, 0, ENCRYPTION_IV));
}

function decryptData($data) {
    $decrypted = openssl_decrypt(base64_decode($data), 'AES-256-CBC', ENCRYPTION_KEY, 0, ENCRYPTION_IV);
    return $decrypted === false ? '' : $decrypted;
}

// Initialize variables for form values and errors
$id = '';
$nombre = '';
$cantidad = '';
$precio_unitario = '';
$proveedor = '';
$fecha_registro = '';
$error = '';
$edit_mode = false;

// Handle Delete operation
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    // Delete product
    $stmt = $pdo->prepare('DELETE FROM productos WHERE id = ?');
    $stmt->execute([$delete_id]);
    header('Location: inventario.php');
    exit();
}

// Handle Edit form population
if (isset($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    $stmt = $pdo->prepare('SELECT * FROM productos WHERE id = ?');
    $stmt->execute([$edit_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($product) {
        $id = $product['id'];
        // Decrypt sensitive fields
        $nombre = decryptData($product['nombre']);
        $cantidad = $product['cantidad'];
        $precio_unitario = $product['precio_unitario'];
        $proveedor = decryptData($product['proveedor']);
        $fecha_registro = $product['fecha_registro'];
        $edit_mode = true;
    } else {
        $error = "Producto no encontrado para editar.";
    }
}

// Handle Add or Update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // No longer getting id from user input; id is auto-assigned
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
    $cantidad = isset($_POST['cantidad']) ? trim($_POST['cantidad']) : '';
    $precio_unitario = isset($_POST['precio_unitario']) ? trim($_POST['precio_unitario']) : '';
    $proveedor = isset($_POST['proveedor']) ? trim($_POST['proveedor']) : '';
    $fecha_registro = isset($_POST['fecha_registro']) ? trim($_POST['fecha_registro']) : '';

    // Validate inputs
    if ($nombre === '' || $cantidad === '' || $precio_unitario === '' || $proveedor === '' || $fecha_registro === '') {
        $error = 'Por favor complete todos los campos.';
    } elseif (!ctype_digit($cantidad) || intval($cantidad) < 0) {
        $error = 'La cantidad debe ser un número entero mayor o igual a 0.';
    } elseif (!is_numeric($precio_unitario) || floatval($precio_unitario) < 0) {
        $error = 'El precio unitario debe ser un número válido mayor o igual a 0.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_registro)) {
        $error = 'La fecha de registro debe tener el formato AAAA-MM-DD.';
    } else {
        // Encrypt sensitive fields before storage
        $nombre_encrypted = encryptData($nombre);
        $proveedor_encrypted = encryptData($proveedor);

        if (isset($_POST['edit_mode']) && $_POST['edit_mode'] === '1') {
            $id = isset($_POST['id']) ? trim($_POST['id']) : '';
            // Update product
            try {
                $stmt = $pdo->prepare('UPDATE productos SET nombre = ?, cantidad = ?, precio_unitario = ?, proveedor = ?, fecha_registro = ? WHERE id = ?');
                $stmt->execute([$nombre_encrypted, $cantidad, $precio_unitario, $proveedor_encrypted, $fecha_registro, $id]);
                header('Location: inventario.php');
                exit();
            } catch (PDOException $e) {
                $error = "Error al actualizar el producto: " . $e->getMessage();
            }
        } else {
            // Insert new product: Do not assign id, it is AUTO_INCREMENT
            try {
                $stmt = $pdo->prepare('INSERT INTO productos (nombre, cantidad, precio_unitario, proveedor, fecha_registro) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$nombre_encrypted, $cantidad, $precio_unitario, $proveedor_encrypted, $fecha_registro]);
                header('Location: inventario.php');
                exit();
            } catch (PDOException $e) {
                $error = "Error al agregar el producto: " . $e->getMessage();
            }
        }
    }
}

// Fetch all products and decrypt sensitive fields for display
$stmt = $pdo->query('SELECT * FROM productos ORDER BY nombre ASC');
$productosEncrypted = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Decrypt nombre and proveedor fields before displaying
$productos = [];
foreach ($productosEncrypted as $producto) {
    $producto['nombre'] = decryptData($producto['nombre']);
    $producto['proveedor'] = decryptData($producto['proveedor']);
    $productos[] = $producto;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<title>Panel de Inventario - Gestión de Papas (Con Encriptación)</title>
<style>
    body {
        font-family: Arial, sans-serif;
        background-color: #f0f4f9;
        margin: 0;
        padding: 20px;
    }
    h1 {
        color: #333;
        text-align: center;
    }
    .container {
        max-width: 900px;
        margin: 0 auto;
        background: white;
        padding: 25px 30px;
        border-radius: 8px;
        box-shadow: 0 0 15px rgba(0,0,0,0.1);
    }
    form {
        margin-bottom: 30px;
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: flex-end;
    }
    form > div {
        flex: 1 1 150px;
        display: flex;
        flex-direction: column;
    }
    label {
        margin-bottom: 5px;
        font-weight: bold;
    }
    input[type="text"],
    input[type="number"],
    input[type="date"] {
        padding: 8px 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 14px;
    }
    button {
        background-color: #4CAF50;
        border: none;
        padding: 12px 20px;
        color: white;
        font-weight: bold;
        border-radius: 6px;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }
    button:hover {
        background-color: #45a049;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }
    th,
    td {
        padding: 12px 15px;
        border-bottom: 1px solid #ddd;
    }
    th {
        background-color: #4CAF50;
        color: white;
    }
    tr:hover {
        background-color: #f4fef7;
    }
    .actions {
        display: flex;
        gap: 10px;
    }
    .actions a {
        text-decoration: none;
        padding: 6px 12px;
        border-radius: 4px;
        font-size: 14px;
        color: white;
    }
    .edit-btn {
        background-color: #2196F3;
    }
    .edit-btn:hover {
        background-color: #0b7dda;
    }
    .delete-btn {
        background-color: #f44336;
    }
    .delete-btn:hover {
        background-color: #da190b;
    }
    .error {
        background-color: #ffdddd;
        color: #d8000c;
        border: 1px solid #d8000c;
        padding: 10px;
        margin-bottom: 15px;
        border-radius: 4px;
        text-align: center;
        font-weight: bold;
    }
    .back-btn {
        margin-top: 15px;
        display: inline-block;
        text-decoration: none;
        background-color: #6c757d;
        color: white;
        padding: 10px 18px;
        border-radius: 6px;
        transition: background-color 0.3s ease;
    }
    .back-btn:hover {
        background-color: #5a6268;
    }
</style>
</head>
<body>

<div class="container">
    <h1>Panel de Inventario</h1>

    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="post" action="inventario.php">
        <?php if ($edit_mode): ?>
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
        <?php endif; ?>
        <div>
            <label for="nombre">Nombre del Producto</label>
            <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($nombre); ?>" required>
        </div>

        <div>
            <label for="cantidad">Cantidad</label>
            <input type="number" id="cantidad" name="cantidad" min="0" value="<?php echo htmlspecialchars($cantidad); ?>" required>
        </div>

        <div>
            <label for="precio_unitario">Precio Unitario</label>
            <input type="text" id="precio_unitario" name="precio_unitario" pattern="^\d+(\.\d{1,2})?$" title="Número con hasta 2 decimales" value="<?php echo htmlspecialchars($precio_unitario); ?>" required>
        </div>

        <div>
            <label for="proveedor">Proveedor</label>
            <input type="text" id="proveedor" name="proveedor" value="<?php echo htmlspecialchars($proveedor); ?>" required>
        </div>

        <div>
            <label for="fecha_registro">Fecha de Registro</label>
            <input type="date" id="fecha_registro" name="fecha_registro" value="<?php echo htmlspecialchars($fecha_registro); ?>" required>
        </div>

        <div style="flex: 0 0 120px;">
            <button type="submit"><?php echo $edit_mode ? 'Actualizar' : 'Agregar'; ?></button>
            <?php if ($edit_mode): ?>
            <input type="hidden" name="edit_mode" value="1">
            <?php endif; ?>
        </div>
    </form>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre del Producto</th>
                <th>Cantidad</th>
                <th>Precio Unitario</th>
                <th>Proveedor</th>
                <th>Fecha de Registro</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($productos) === 0): ?>
            <tr>
                <td colspan="7" style="text-align: center; font-style: italic;">No hay productos en el inventario.</td>
            </tr>
            <?php else: ?>
                <?php foreach ($productos as $producto): ?>
                <tr>
                    <td><?php echo htmlspecialchars($producto['id']); ?></td>
                    <td><?php echo htmlspecialchars($producto['nombre']); ?></td>
                    <td><?php echo htmlspecialchars($producto['cantidad']); ?></td>
                    <td>$<?php echo number_format($producto['precio_unitario'], 2); ?></td>
                    <td><?php echo htmlspecialchars($producto['proveedor']); ?></td>
                    <td><?php echo htmlspecialchars($producto['fecha_registro']); ?></td>
                    <td class="actions">
                        <a href="inventario.php?edit_id=<?php echo urlencode($producto['id']); ?>" class="edit-btn">Editar</a>
                        <a href="inventario.php?delete_id=<?php echo urlencode($producto['id']); ?>" class="delete-btn" onclick="return confirm('¿Está seguro que desea eliminar este producto?');">Eliminar</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <a href="dashboard.php" class="back-btn">Volver al Panel</a>
</div>

</body>
</html>
