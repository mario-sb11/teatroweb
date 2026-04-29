<?php
require('../vendor/fpdf/fpdf.php');
require('../vendor/phpqrcode/qrlib.php');
include('auth.php');
include('../includes/config.php'); // Necesitamos la conexión para sacar los datos reales

// 1. Obtenemos el ID de reserva
if (isset($_GET['id'])) {
    $id_reserva = (int)$_GET['id'];
} else {
    die("Error: ID de reserva no facilitado en la URL.");
}

// 2. Sacamos todos los datos del cliente, butacas y el precio (ya sea el específico de la función o el general de la obra).
$stmt = $pdo->prepare("
    SELECT r.id, r.nombre, r.codigo_qr, o.titulo, f.fecha, f.hora,
           COALESCE(f.precio, o.precio) as precio_unit,
           (SELECT GROUP_CONCAT(a.codigo_asiento SEPARATOR ', ') 
            FROM reserva_items ri 
            JOIN asientos a ON ri.asiento_id = a.id 
            WHERE ri.reserva_id = r.id) as butacas,
           (SELECT COUNT(*) FROM reserva_items WHERE reserva_id = r.id) as total_butacas
    FROM reservas r
    JOIN funciones f ON r.funcion_id = f.id
    JOIN obras o ON f.obra_id = o.id
    WHERE r.id = ?
");
$stmt->execute([$id_reserva]);
$res = $stmt->fetch();

// Si por lo que sea no existe esa reserva, paramos todo
if (!$res) die("Reserva no encontrada");

// Preparamos las variables para pintar el PDF
$token = $res['codigo_qr'];
$nombre = $res['nombre'];
$obra = $res['titulo'];
$fecha = date('d/m/Y', strtotime($res['fecha']));
$hora = date('H:i', strtotime($res['hora']));
$precio_total = $res['precio_unit'] * $res['total_butacas'];

// Limpiamos cualquier espacio o texto invisible que haya soltado PHP antes de crear el PDF
while (ob_get_level()) {
    ob_end_clean();
}
// --- INICIO GENERACIÓN PDF ---
$pdf = new FPDF('P', 'mm', 'A5');
$pdf->AddPage();

// Cabecera institucional
$pdf->SetFillColor(44, 62, 80);
$pdf->Rect(0, 0, 148, 25, 'F');
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Text(35, 15, utf8_decode('AYUNTAMIENTO DE VILLAMARTÍN'));

// Título de la obra
$pdf->SetY(30);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', 'B', 18);
$pdf->MultiCell(0, 8, utf8_decode(strtoupper($obra)), 0, 'C');

// Recuadro de Info (Fecha y Hora)
$pdf->SetY(50);
$pdf->SetFillColor(245, 245, 245);
$pdf->Rect(10, 50, 128, 15, 'F');
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 15, utf8_decode("FECHA: $fecha  -  HORA: $hora"), 0, 1, 'C');

// Datos del asistente y Butacas
$pdf->SetY(70);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 5, utf8_decode('TITULAR DE LA ENTRADA:'), 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 7, utf8_decode(strtoupper($nombre)), 0, 1, 'C');

$pdf->Ln(2);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 5, utf8_decode('LOCALIDADES:'), 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 11);
$pdf->MultiCell(0, 6, utf8_decode($res['butacas']), 0, 'C');

// GENERAR QR
$archivo_qr = 'temp_qr_' . $id_reserva . '.png';
QRcode::png($token, $archivo_qr, QR_ECLEVEL_L, 10, 2);
$pdf->Image($archivo_qr, 49, 95, 50, 50); // QR centrado

// Justificante de Pago 
$pdf->SetY(150);
$pdf->SetFillColor(230, 255, 230); // Verde clarito
$pdf->Rect(20, 150, 108, 12, 'F');
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(0, 100, 0);
$pdf->Cell(0, 12, utf8_decode("PAGADO: " . number_format($precio_total, 2, ',', '.') . " " . chr(128)), 0, 1, 'C');

// ID de Seguridad
$pdf->SetTextColor(100);
$pdf->SetFont('Courier', '', 9);
$pdf->Cell(0, 8, 'LOCALIZADOR: ' . $token, 0, 1, 'C');

// Pie de página
$pdf->SetY(-25);
$pdf->SetFont('Arial', 'I', 7);
$pdf->SetTextColor(120);
$pdf->MultiCell(0, 3, utf8_decode("Este documento sirve como entrada y justificante de pago.\nConserve el código QR legible para el acceso.\nVillamartín - Cultura y Teatro."), 0, 'C');

// Limpieza
if(file_exists($archivo_qr)) unlink($archivo_qr);

$pdf->Output('I', 'Entrada_Villamartin_' . $id_reserva . '.pdf');