<?php
include 'auth.php';
include '../includes/config.php';

$filename = "informe_general_teatro_" . date('Y-m-d') . ".csv";

// Le decimos al navegador que esto es un archivo descargable, no una web
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');
// BOM para compatibilidad con acentos en Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// --- SECCIÓN 1: KPIs GLOBALES (Resumen superior) ---
fputcsv($output, array('RESUMEN GENERAL DEL SISTEMA (A fecha ' . date('d/m/Y') . ')'));
fputcsv($output, array('Indicador', 'Valor'));

// 1. Espectáculos Activos
$total_espectaculos = $pdo->query("SELECT COUNT(*) FROM funciones WHERE fecha >= CURDATE()")->fetchColumn();
fputcsv($output, array('Espectaculos Activos', $total_espectaculos));

// 2. Reservas Pendientes
$reservas_pendientes = $pdo->query("SELECT COUNT(*) FROM reservas r JOIN funciones f ON r.funcion_id = f.id WHERE r.estado = 'pendiente' AND f.fecha >= CURDATE()")->fetchColumn();
fputcsv($output, array('Reservas Pendientes (Futuras)', $reservas_pendientes));

// 3. Entradas Vendidas
$ventas_confirmadas = $pdo->query("SELECT COUNT(ri.asiento_id) FROM reservas r JOIN reserva_items ri ON ri.reserva_id = r.id JOIN funciones f ON r.funcion_id = f.id WHERE r.estado = 'confirmada' AND f.fecha >= CURDATE()")->fetchColumn();
fputcsv($output, array('Entradas Vendidas (Futuras)', $ventas_confirmadas));

// 4. Ingresos Reales
$res_ingresos = $pdo->query("SELECT SUM(COALESCE(f.precio, o.precio, 0)) FROM reservas r JOIN reserva_items ri ON ri.reserva_id = r.id JOIN funciones f ON r.funcion_id = f.id JOIN obras o ON f.obra_id = o.id WHERE r.estado = 'confirmada' AND f.fecha >= CURDATE()")->fetchColumn();


if ($res_ingresos) {
    $ingresos_reales = $res_ingresos;
} else {
    $ingresos_reales = 0;
}

fputcsv($output, array('Ingresos Totales (€)', number_format($ingresos_reales, 2, ',', '.')));

fputcsv($output, array()); // Línea en blanco separadora
fputcsv($output, array()); // Línea en blanco separadora

// --- SECCIÓN 2: ESTADÍSTICAS POR PERIODO (El cuadro de ventas) ---
fputcsv($output, array('ESTADÍSTICAS DE RENDIMIENTO'));
fputcsv($output, array('Periodo', 'Reservas (Pend)', 'Ventas (Conf)', 'Recaudado (€)'));

$periodos = ['hoy' => 'Hoy', 'ayer' => 'Ayer', 'mes' => 'Este Mes', 'total' => 'Total Histórico'];

foreach ($periodos as $clave => $nombre) {
    $where = "";
    if($clave == 'hoy') $where = "AND DATE(r.fecha_reserva) = CURDATE()";
    if($clave == 'ayer') $where = "AND DATE(r.fecha_reserva) = SUBDATE(CURDATE(),1)";
    if($clave == 'mes') $where = "AND MONTH(r.fecha_reserva) = MONTH(CURDATE()) AND YEAR(r.fecha_reserva) = YEAR(CURDATE())";

    $res_pend = $pdo->query("SELECT COUNT(ri.asiento_id) FROM reservas r JOIN reserva_items ri ON r.id = ri.reserva_id WHERE r.estado = 'pendiente' $where")->fetchColumn();
    if ($res_pend) {
        $pend = $res_pend;
    } else {
        $pend = 0;
    }

    $res_vend = $pdo->query("SELECT COUNT(ri.asiento_id) FROM reservas r JOIN reserva_items ri ON r.id = ri.reserva_id WHERE r.estado = 'confirmada' $where")->fetchColumn();
    if ($res_vend) {
        $vend = $res_vend;
    } else {
        $vend = 0;
    }

    $res_eur = $pdo->query("SELECT SUM(COALESCE(f.precio, o.precio, 0)) FROM reservas r JOIN reserva_items ri ON r.id = ri.reserva_id JOIN funciones f ON r.funcion_id = f.id JOIN obras o ON f.obra_id = o.id WHERE r.estado = 'confirmada' $where")->fetchColumn();
    if ($res_eur) {
        $eur = $res_eur;
    } else {
        $eur = 0;
    }

    fputcsv($output, array($nombre, $pend, $vend, number_format($eur, 2, ',', '.')));
}

fputcsv($output, array()); // Línea en blanco separadora
fputcsv($output, array()); // Línea en blanco separadora

// --- SECCIÓN 3: LISTADO DETALLADO DE ASISTENTES ---
fputcsv($output, array('LISTADO COMPLETO DE ASISTENTES Y RESERVAS'));
fputcsv($output, array('ID', 'Nombre', 'Email', 'Obra / Espectáculo', 'Código QR', '¿Acceso Realizado?', 'Fecha de Reserva'));

$sql = "SELECT r.id, r.nombre, r.email, o.titulo, r.codigo_qr, r.qr_usado, r.fecha_reserva 
        FROM reservas r 
        JOIN funciones f ON r.funcion_id = f.id 
        JOIN obras o ON f.obra_id = o.id 
        ORDER BY r.id DESC";

$query = $pdo->query($sql);

while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
    $row['qr_usado'] = ($row['qr_usado'] == 1) ? 'SÍ' : 'NO';
    fputcsv($output, $row);
}

fclose($output);
exit;