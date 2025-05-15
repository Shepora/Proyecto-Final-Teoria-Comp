<?php
session_start();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $correct_answer = 'perro'; 
    $user_answer = isset($_POST['security_answer']) ? trim(strtolower($_POST['security_answer'])) : '';

    if ($user_answer === $correct_answer) {
        $success = 'Respuesta correcta. Puedes ingresar tu contraseña.';
    } else {
        $error = 'Respuesta incorrecta. Por favor intenta nuevamente.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recuperar Contraseña - Gestión de Ventas e Inventario de Papas</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f7f7f7;
            display: flex;
            height: 100vh;
            justify-content: center;
            align-items: center;
            margin: 0;
        }
        .container {
            background-color: white;
            padding: 30px 40px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: 400px;
            text-align: center;
        }
        h2 {
            color: #333;
            margin-bottom: 20px;
        }
        label {
            font-weight: bold;
            color: #555;
            display: block;
            margin-bottom: 10px;
            text-align: left;
        }
        input[type="text"] {
            width: 100%;
            padding: 10px;
            margin: 0 auto 20px auto; 
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
            display: block;
        }
        input[type="submit"] {
            background-color: #007BFF;
            color: white;
            font-weight: bold;
            border: none;
            padding: 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            transition: background-color 0.3s ease;
        }
        input[type="submit"]:hover {
            background-color: #0056b3;
        }
        .message {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 4px;
            text-align: center;
        }
        .error {
            background-color: #ffdddd;
            color: #d8000c;
            border: 1px solid #d8000c;
        }
        .success {
            background-color: #ddffdd;
            color: #270;
            border: 1px solid #270;
        }
        a.back-link {
            display: inline-block;
            margin-top: 20px;
            color: #007BFF;
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s ease;
        }
        a.back-link:hover {
            color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Recuperar Contraseña</h2>
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($success): ?>
            <div class="message success"><?php echo htmlspecialchars($success); ?></div>
        <?php else: ?>
            <form action="forgot_password.php" method="post" autocomplete="off">
                <label for="security_question">¿Cuál es el nombre de tu primera mascota?</label>
                <input type="text" id="security_answer" name="security_answer" required autofocus>
                <input type="submit" value="Enviar respuesta">
            </form>
        <?php endif; ?>
        <a href="login.php" class="back-link">Volver al Login</a>
    </div>
</body>
</html>
