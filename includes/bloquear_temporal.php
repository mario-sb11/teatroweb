<?php
include 'config.php';

// 1. Bloquear si intentan entrar escribiendo la URL
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit(json_encode(['error' => 'Acceso denegado']));
}

// 2. Verificar que la petición viene desde tu propia página web 
if (isset($_SERVER['HTTP_REFERER'])) {
    $referer = $_SERVER['HTTP_REFERER'];
} else {
    $referer = '';
}

// 'localhost' por dominio real
// if (strpos($referer, 'localhost') === false && strpos($referer, 'villamartin.es') === false) {
//     http_response_code(403);
//     exit(json_encode(['error' => 'Origen no autorizado']));
// }

// Leemos la entrada cruda del servidor (fetch de JS)
$data = json_decode(file_get_contents('php://input'), true);

// Si no hay datos válidos, terminamos la ejecución por seguridad
if (!$data || !isset($data['id']) || !isset($data['accion'])) {
    exit;
}

// Sanitización de entradas: Nos aseguramos de que el ID sea un número entero
$asiento_db_id = (int)$data['id'];
$accion = $data['accion'];

if ($accion == 'bloquear') {
    // Establecemos un margen de 10 minutos antes de que el asiento vuelva a quedar libre
    $limite = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    // Ejecutamos una actualización atómica: solo bloqueamos si el asiento está
    // realmente 'libre' o si el bloqueo anterior ya ha caducado.
    // Esto evita que dos personas ocupen la misma butaca al mismo tiempo.
    $stmt = $pdo->prepare("UPDATE asientos 
                            SET estado = 'en_proceso', bloqueado_hasta = ? 
                            WHERE id = ? 
                            AND (estado = 'libre' OR (estado = 'en_proceso' AND bloqueado_hasta < NOW()))");
    
    $stmt->execute([$limite, $asiento_db_id]);

    // Devolvemos éxito solo si la base de datos realmente modificó una fila
    echo json_encode(['success' => $stmt->rowCount() > 0]);

} else {
    // Acción para liberar: devolvemos la butaca al estado 'libre' 
    // siempre y cuando estuviera en estado 'en_proceso' (no tocamos reservas reales)
    $stmt = $pdo->prepare("UPDATE asientos 
                            SET estado = 'libre', bloqueado_hasta = NULL 
                            WHERE id = ? AND estado = 'en_proceso'");
    
    $stmt->execute([$asiento_db_id]);

    // Informamos al JS que la operación se ha completado
    echo json_encode(['success' => true]);
}