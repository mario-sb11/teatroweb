<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'error' => 'Sesion caducada']);
    exit;
}

include_once '../includes/config.php';

if (isset($_GET['f_id'])) {
    $f_id = (int)$_GET['f_id'];
} else {
    $f_id = 0;
}

if ($f_id > 0) {
    try {
        // Calculamos el total de butacas que se han vendido en firme para este pase.
        $stmt_total = $pdo->prepare("SELECT COUNT(*) FROM reserva_items ri JOIN reservas r ON ri.reserva_id = r.id WHERE r.funcion_id = ? AND r.estado = 'confirmada'");
        $stmt_total->execute([$f_id]);
        $total_vendidos = (int)$stmt_total->fetchColumn();

        //  De las butacas vendidas, contamos cuántas han pasado ya por la puerta (qr_usado = 1).
        $stmt_entrados = $pdo->prepare("SELECT COUNT(*) FROM reserva_items ri JOIN reservas r ON ri.reserva_id = r.id WHERE r.funcion_id = ? AND r.estado = 'confirmada' AND r.qr_usado = 1");
        $stmt_entrados->execute([$f_id]);
        $han_entrado = (int)$stmt_entrados->fetchColumn();

        $pendientes = $total_vendidos - $han_entrado;

        echo json_encode([
            'success' => true,
            'total' => $total_vendidos,
            'entrados' => $han_entrado,
            'pendientes' => $pendientes
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'BD: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'ID invalido']);
}
