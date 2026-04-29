<?php
include 'auth.php';
include '../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['config'])) {
    
    // Preparamos la consulta SQL con "ON DUPLICATE KEY UPDATE"
    // Inserta la clave, pero si ya existe, actualiza su valor
    $stmt = $pdo->prepare("INSERT INTO configuracion (clave, valor) VALUES (?, ?) 
                           ON DUPLICATE KEY UPDATE valor = VALUES(valor)");

    // Recorremos el array que llega del formulario
    foreach ($_POST['config'] as $clave => $valor) {
        // Limpiamos el valor por seguridad (trim)
        $valor_limpio = htmlspecialchars(trim($valor));
        $stmt->execute([$clave, $valor_limpio]);
    }

    // Redireccionamos de vuelta al formulario con mensaje de éxito
    // 1. Averiguamos de qué página venía el usuario (por defecto index.php por si falla)   
    // 2. Quitamos los parámetros antiguos por si venía de un "?status=ok" anterior
    if (isset($_SERVER['HTTP_REFERER'])) {
        // <--- CAMBIO: Seguridad mejorada. Usamos parse_url para extraer solo la ruta del archivo. Esto evita redirecciones externas maliciosas y además elimina los "?status=ok" viejos sin necesidad de usar strpos().
        $pagina_origen = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH);
    } else {
        $pagina_origen = 'index.php';
    }

    // 3. Lo devolvemos exactamente a la página donde estaba, con el mensaje de éxito
    header("Location: " . $pagina_origen . "?status=ok");
    exit;
} else {
    // Si alguien intenta entrar aquí directamente sin enviar datos
    header("Location: index.php");
    exit;
}
?>