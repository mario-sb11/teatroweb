<?php

include '../includes/config.php';

// 1. Si ya está logueado, lo mandamos al panel directamente
if (isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // --- SEGURIDAD CSRF (Norma CCN-CERT ATZ-08) ---
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        error_log("Ataque CSRF bloqueado en Login Admin. IP: " . $_SERVER['REMOTE_ADDR']);
        die("Error de seguridad: La petición no es válida. Recargue la página.");
    }
    // ---------------------------------------------------------------
    // Sanitizamos el nombre de usuario
   $user = trim($_POST['usuario'] ?? '');
   $pass = $_POST['password'] ?? '';

    // 2. Control de Fuerza Bruta simple
    // Si ha fallado muchas veces en esta sesión, lo bloqueamos temporalmente
    if (isset($_SESSION['intentos_fallidos']) && $_SESSION['intentos_fallidos'] >= 5) {
        if (time() - $_SESSION['ultimo_intento'] < 300) { // 5 minutos de bloqueo
            $error = "Demasiados intentos. Por seguridad, espere 5 minutos.";
        } else {
            // Resetear tras el tiempo de espera
            $_SESSION['intentos_fallidos'] = 0;
        }
    }

    if (empty($error)) {
        $stmt = $pdo->prepare("SELECT * FROM usuarios_admin WHERE usuario = ?");
        $stmt->execute([$user]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($pass, $usuario['password'])) {
            // LOGIN EXITOSO
            
            // 3. Regeneramos el ID para evitar "Session Fixation" (Secuestro de sesión)
            session_regenerate_id(true);
            
            $_SESSION['admin_id'] = $usuario['id'];
            $_SESSION['admin_nombre'] = $usuario['nombre'];
            $_SESSION['intentos_fallidos'] = 0; // Resetear contador
            
            header("Location: index.php");
            exit();
        } else {
            // LOGIN FALLIDO
            if (isset($_SESSION['intentos_fallidos'])) {
                $_SESSION['intentos_fallidos'] = $_SESSION['intentos_fallidos'] + 1;
            } else {
                $_SESSION['intentos_fallidos'] = 1;
            }
            
            $_SESSION['ultimo_intento'] = time();
            $error = "Usuario o contraseña incorrectos";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Administrativo - Teatro Villamartín</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&family=Roboto+Slab:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #557996;
            --dark-blue: #34495e;
            --bg-gradient: linear-gradient(135deg, #34495e 0%, #2c3e50 100%);
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Roboto', sans-serif;
            background: var(--bg-gradient);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .login-container {
            background: white;
            width: 100%;
            max-width: 400px;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
            text-align: center;
        }

        .logo-login {
            width: 80px;
            margin-bottom: 20px;
        }

        h2 {
            font-family: 'Roboto Slab', serif;
            color: var(--dark-blue);
            margin-bottom: 10px;
            font-size: 22px;
        }

        p {
            color: #7f8c8d;
            font-size: 14px;
            margin-bottom: 30px;
        }

        .form-group {
            position: relative;
            margin-bottom: 20px;
        }

        .form-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #95a5a6;
        }

        input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }

        input:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .btn-login {
            width: 100%;
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 14px;
            font-size: 16px;
            font-weight: bold;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s;
            margin-top: 10px;
        }

        .btn-login:hover {
            background-color: #44617a;
        }

        .error-msg {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid #f5c6cb;
        }

        .footer-link {
            margin-top: 25px;
            font-size: 13px;
        }

        .footer-link a {
            color: var(--primary-color);
            text-decoration: none;
        }
    </style>
</head>
<body>

<div class="login-container">
    <img src="../assets/img/logo.jpg" alt="Logo" class="logo-login" onerror="this.style.display='none'">
    <i class="fas fa-user-shield fa-3x" style="color: var(--primary-color); margin-bottom: 15px;"></i>
    
    <h2>Panel de Gestión</h2>
    <p>Inicie sesión para administrar el teatro</p>

    <?php if($error): ?>
        <div class="error-msg">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

        <div class="form-group">
            <i class="fas fa-user"></i>
            <input type="text" name="usuario" placeholder="Usuario" required autocomplete="username">
        </div>
        
        <div class="form-group">
            <i class="fas fa-lock"></i>
            <input type="password" name="password" placeholder="Contraseña" required autocomplete="current-password">
        </div>

        <button type="submit" class="btn-login">ACCEDER AL PANEL</button>
    </form>

    <div class="footer-link">
        <a href="../index.php"><i class="fas fa-arrow-left"></i> Volver a la web pública</a>
    </div>
</div>

</body>
</html>