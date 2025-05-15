<?php
// db.php - Centralized database connection for XAMPP MySQL using PDO

// Database configuration - adjust as needed
$host = 'localhost';
$dbname = 'inventariopapas'; // name of your database
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    // If connection fails, stop script and display error message
    die("Error al conectar con la base de datos: " . $e->getMessage());
}
?>
