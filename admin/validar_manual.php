<?php
include 'auth.php';
include '../includes/config.php';

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // Marcamos como usado y registramos la hora actual
    $stmt = $pdo->prepare("UPDATE reservas SET qr_usado = 1, fecha_entrada = NOW() WHERE id = ?");
    $stmt->execute([$id]);
}

// Redirigir de vuelta al panel de tarjetas QR
header("Location: admin_qr.php?status=success");
exit();