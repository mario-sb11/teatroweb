<?php
include 'auth.php';
require_once '../vendor/phpqrcode/qrlib.php';

// Limpiamos cualquier error o espacio que se haya colado antes
while (ob_get_level()) {
    ob_end_clean();
}

if (isset($_GET['codigo']) && !empty($_GET['codigo'])) {
    
    $codigo_limpio = trim(strip_tags($_GET['codigo']));

    // Configuramos la cabecera para imagen PNG   
    header('Content-Type: image/png');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    
    // Forzamos a que el navegador NUNCA guarde el QR en su memoria caché.
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    
    // Generamos el QR (el parámetro false indica que se envíe al navegador)
    QRcode::png($_GET['codigo'], false, QR_ECLEVEL_L, 6, 2);
    exit;
} else {
    echo "Error: Parámetro 'codigo' no recibido.";
}