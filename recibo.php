<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

require_once 'db.php';

$id_venta = $_GET['id'] ?? '';

if (!$id_venta || !ctype_digit($id_venta)) {
    die('ID de venta inválido.');
}

// Claves de encriptación - deben coincidir con las usadas para encriptar
define('ENCRYPTION_KEY', 'your-secret-key-32charslength!1234567890'); 
define('ENCRYPTION_IV', '1234567890123456');

function decryptData($data) {
    if (empty($data)) return '';
    $decrypted = openssl_decrypt(base64_decode($data), 'AES-256-CBC', ENCRYPTION_KEY, 0, ENCRYPTION_IV);
    return $decrypted === false ? '' : $decrypted;
}

// Obtener venta, producto y calcular total
$sql = "
    SELECT v.id, v.cliente, v.contacto, v.cantidad, v.fecha_venta, 
           p.nombre AS producto_nombre, p.precio_unitario, 
           (v.cantidad * p.precio_unitario) AS total
    FROM ventas v
    JOIN productos p ON v.producto_id = p.id
    WHERE v.id = ? AND v.confirmada = 1
    LIMIT 1
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_venta]);
$venta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$venta) {
    die('Venta no encontrada o no confirmada.');
}

// Desencriptar cliente y contacto para mostrar en recibo/PDF
$cliente = decryptData($venta['cliente']);
$contacto = decryptData($venta['contacto']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"/>
<title>Recibo de Venta #<?=htmlspecialchars($venta['id'])?></title>
<style>
    /* General styles */
    * {
        box-sizing: border-box;
    }
    body {
        font-family: Arial, sans-serif;
        padding: 20px;
        max-width: 600px;
        margin: 0 auto;
        background: #fff;
        overflow-x: hidden;
    }
    h1, h2 {
        text-align: center;
        color: #333;
        margin: 0 0 10px 0;
    }
    .recibo {
        border: 1px solid #ccc;
        padding: 20px;
        border-radius: 8px;
        width: 100%;
        box-sizing: border-box;
        overflow-wrap: break-word;
    }
    p {
        margin: 6px 0;
        word-wrap: break-word;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        table-layout: fixed;
        word-break: break-word;
    }
    th, td {
        border: 1px solid #aaa;
        padding: 8px 12px;
        text-align: left;
        vertical-align: middle;
        word-wrap: break-word;
    }
    th {
        background: #4CAF50;
        color: white;
    }
    .total {
        font-weight: bold;
        font-size: 1.2em;
    }
    .print-btn {
        display: block;
        width: 150px;
        margin: 20px auto 0 auto;
        padding: 10px;
        background-color: #4CAF50;
        color: white;
        border: none;
        font-weight: bold;
        border-radius: 6px;
        cursor: pointer;
        transition: background-color 0.3s ease;
        text-align: center;
        white-space: nowrap;
    }
    .print-btn:hover {
        background-color: #45a049;
    }

    /* Responsive adjustments */
    @media screen and (max-width: 640px) {
        body {
            padding: 10px;
            max-width: 100%;
        }
        .recibo {
            padding: 15px;
        }
        table th, table td {
            font-size: 14px;
            padding: 6px 8px;
        }
        .print-btn {
            width: 100%;
        }
    }
</style>
</head>
<body>

<div class="recibo">
    <h1>Recibo de Venta</h1>
    <h2>#<?=htmlspecialchars($venta['id'])?></h2>

    <p><strong>Cliente:</strong> <?=htmlspecialchars($cliente)?></p>
    <p><strong>Contacto:</strong> <?=htmlspecialchars($contacto)?></p>
    <p><strong>Fecha de Facturación:</strong> <?=htmlspecialchars($venta['fecha_venta'])?></p>

    <table>
        <thead>
            <tr>
                <th style="width: 40%;">Producto</th>
                <th style="width: 20%;">Cantidad</th>
                <th style="width: 20%;">Precio Unitario</th>
                <th style="width: 20%;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?=htmlspecialchars($venta['producto_nombre'])?></td>
                <td><?=htmlspecialchars($venta['cantidad'])?></td>
                <td>$<?=number_format($venta['precio_unitario'], 2)?></td>
                <td>$<?=number_format($venta['total'], 2)?></td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="total">Total</td>
                <td class="total">$<?=number_format($venta['total'], 2)?></td>
            </tr>
        </tfoot>
    </table>

    <button class="print-btn" onclick="window.print()">Imprimir Recibo</button>
</div>

</body>
</html>