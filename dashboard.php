<?php
session_start();

// Logout functionality
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<title>Panel de Administración - Gestión de Papas</title>
<style>
    body {
        font-family: Arial, sans-serif;
        background-color: #eef2f7;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        min-height: 100vh;
        align-items: center;
        justify-content: center;
    }
    .container {
        background: white;
        padding: 40px 50px;
        border-radius: 8px;
        box-shadow: 0 0 15px rgba(0,0,0,0.1);
        text-align: center;
        width: 350px;
    }
    h1 {
        margin-bottom: 30px;
        color: #333;
    }
    .options {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    .option-btn {
        background-color: #4CAF50;
        color: white;
        border: none;
        padding: 15px 0;
        font-size: 18px;
        border-radius: 6px;
        cursor: pointer;
        text-decoration: none;
        display: block;
        transition: background-color 0.3s ease;
    }
    .option-btn:hover {
        background-color: #45a049;
    }
    .logout {
        margin-top: 30px;
        font-size: 14px;
        color: #666;
    }
    .logout a {
        color: #dc3545;
        text-decoration: none;
        font-weight: bold;
    }
    .logout a:hover {
        text-decoration: underline;
    }
</style>
</head>
<body>
<div class="container">
    <h1>Panel de Administración</h1>
    <div class="options">
        <a href="inventario.php" class="option-btn">Inventario</a>
        <a href="ventas.php" class="option-btn">Ventas</a>
        <a href="facturacion.php" class="option-btn">Facturación</a>
    </div>
    <div class="logout">
        <a href="dashboard.php?logout=1">Cerrar sesión</a>
    </div>
</div>
</body>
</html>
