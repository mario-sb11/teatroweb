<?php
include 'auth.php';
include '../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['codigo']) || empty(trim($_GET['codigo']))) {
    echo json_encode(['status' => 'error', 'message' => 'Error: El lector no capturó el código']);
    exit;
}

$codigo = trim($_GET['codigo']);

// Capturamos el ID de la función en la que está el acomodador
if (isset($_GET['f_id'])) {
    $f_id = (int)$_GET['f_id'];
} else {
    $f_id = 0;
}

$stmt = $pdo->prepare("SELECT * FROM reservas WHERE codigo_qr = ?");
$stmt->execute([$codigo]);
$reserva = $stmt->fetch();

if ($reserva) {
    // --- FILTRO ANTI-FRAUDE CRUZADO ---
    // Si el QR es válido pero es para OTRO espectáculo distinto al seleccionado
    if ($f_id > 0 && $reserva['funcion_id'] != $f_id) {
        echo json_encode(['status' => 'error', 'message' => '¡CUIDADO! Esta entrada pertenece a otro espectáculo o fecha.']);
        exit;
    }

    // Comprobamos que la reserva esté pagada y no haya sido cancelada
    if ($reserva['estado'] !== 'confirmada') {
        echo json_encode(['status' => 'error', 'message' => '¡ENTRADA ANULADA O NO PAGADA!']);
        exit;
    }

    if ($reserva['qr_usado'] == 1) {
        // mostramos a qué hora se usó exactamente
        if (!empty($reserva['fecha_entrada'])) {
            $hora_uso = date('H:i:s', strtotime($reserva['fecha_entrada']));
        } else {
            $hora_uso = "una hora desconocida";
        }
        echo json_encode(['status' => 'error', 'message' => "¡ESTA ENTRADA YA SE USÓ a las $hora_uso!"]);
    } else {
        // Contamos cuántas personas son en esta reserva
        $stmt_asientos = $pdo->prepare("SELECT COUNT(*) FROM reserva_items WHERE reserva_id = ?");
        $stmt_asientos->execute([$reserva['id']]);
        $num_asientos = $stmt_asientos->fetchColumn();

        // Marcamos como usada y guardamos la hora
        if (!empty($reserva['id'])) {
            $update = $pdo->prepare("UPDATE reservas SET qr_usado = 1, fecha_entrada = NOW() WHERE id = ?");
            $update->execute([$reserva['id']]);
        } else {
            $update = $pdo->prepare("UPDATE reservas SET qr_usado = 1, fecha_entrada = NOW() WHERE codigo_qr = ?");
            $update->execute([$codigo]);
        }
        
        echo json_encode(['status' => 'success', 'message' => htmlspecialchars($reserva['nombre']) . "<br><strong style='font-size:1.3rem; display:block; margin-top:10px;'>Pasan: " . $num_asientos . " personas</strong>"]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Código no válido o no encontrado']);
}