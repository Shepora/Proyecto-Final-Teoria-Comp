<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

require_once 'db.php';

// Obtener todas las ventas confirmadas
$sql = "SELECT v.id, v.cliente, v.fecha_venta, v.cantidad, p.precio_unitario, (v.cantidad * p.precio_unitario) AS total
        FROM ventas v
        JOIN productos p ON v.producto_id = p.id
        WHERE v.confirmada = 1
        ORDER BY v.fecha_venta DESC";

$stmt = $pdo->query($sql);
$ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"/>
    <title>Panel de Facturación - Gestión de Papas</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f7f9fc; margin:0; padding:20px;}
        h1{text-align:center;color:#333;}
        .container {max-width:900px;margin:auto;background:#fff; padding:25px 30px; border-radius:8px;box-shadow:0 0 15px rgba(0,0,0,0.1);}
        table {width:100%; border-collapse:collapse; margin-top:10px;}
        th, td {padding:12px 15px; border-bottom:1px solid #ddd; text-align:left;}
        th {background:#4CAF50; color:#fff;}
        tr:hover {background:#f4fef7;}
        button {background:#4CAF50;border:none;padding:12px 20px;color:#fff;font-weight:bold;border-radius:6px;cursor:pointer;transition:background-color 0.3s ease;}
        button:hover {background:#45a049;}
        .back-btn {display:inline-block; margin-top:20px; background:#6c757d; color:#fff; text-decoration:none; padding:10px 16px; border-radius:6px; transition: background-color 0.3s ease;}
        .back-btn:hover {background:#5a6268;}
    </style>
</head>
<body>

<div class="container">
    <h1>Panel de Facturación</h1>

    <table>
        <thead>
            <tr>
                <th>ID de Venta</th>
                <th>Nombre del Cliente</th>
                <th>Fecha de Facturación</th>
                <th>Precio Total</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($ventas) === 0): ?>
                <tr><td colspan="5" style="text-align:center; font-style:italic;">No hay ventas confirmadas.</td></tr>
            <?php else: ?>
                <?php foreach ($ventas as $venta): ?>
                <tr>
                    <td><?=htmlspecialchars($venta['id'])?></td>
                    <td><?=htmlspecialchars($venta['cliente'])?></td>
                    <td><?=htmlspecialchars($venta['fecha_venta'])?></td>
                    <td>$<?=number_format($venta['total'], 2)?></td>
                    <td>
                        <button onclick="generarRecibo(<?=htmlspecialchars($venta['id'])?>)">Generar Recibo</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <a href="dashboard.php" class="back-btn">Volver al Dashboard</a>
</div>

<script>
function generarRecibo(id) {
    window.open('recibo.php?id=' + encodeURIComponent(id), '_blank', 'width=600,height=700');
}
</script>

</body>
</html>