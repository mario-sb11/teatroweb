<?php
session_start();

/**
 * CONTROL DE ACCESO AL PANEL DE ADMINISTRACIÓN
 * Este archivo protege cada página del panel verificando la sesión activa 
 * y gestionando la seguridad del navegador.
 */

// 1. Verificación de identidad: Si no hay sesión válida, redirigimos al login
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// 2. Seguridad contra el botón "Atrás" del navegador:
// Evitamos que se muestren datos sensibles si el admin cierra sesión y alguien pulsa "Atrás".
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 3. Regeneración de ID de sesión:
// Cada vez que se carga una página, cambiamos el ID interno de la sesión.
if (!isset($_SESSION['ultima_regeneracion'])) {
    session_regenerate_id(true);
    $_SESSION['ultima_regeneracion'] = time();
} elseif (time() - $_SESSION['ultima_regeneracion'] > 300) { // Cada 5 minutos
    session_regenerate_id(true);
    $_SESSION['ultima_regeneracion'] = time();
}

// 4. Control de inactividad: Expulsión automática tras 30 minutos sin clics
$timeout = 1800; 
if (isset($_SESSION['ultimo_clic']) && (time() - $_SESSION['ultimo_clic'] > $timeout)) {
    session_unset();
    session_destroy();
    header("Location: login.php?error=sesion_expirada");
    exit();
}
$_SESSION['ultimo_clic'] = time(); // Actualizamos el marcador de tiempo
?>