<?php
// ARCHIVO: includes/enviar_ticket.php

// Usamos __DIR__ para que las rutas a vendor sean siempre relativas a ESTE archivo
require_once __DIR__ . '/../vendor/phpmailer/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/SMTP.php';
require_once __DIR__ . '/../vendor/phpqrcode/qrlib.php';
require_once __DIR__ . '/../vendor/fpdf/fpdf.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function enviarEntradaEmail($emailCliente, $nombreCliente, $codigoQR, $obraTitulo, $id_reserva, $pdo) {
    $mail = new PHPMailer(true);

    // Fijamos la ruta del QR temporal siempre en la carpeta includes para evitar líos de carpetas
    $temp_qr_path = __DIR__ . '/temp_qr_' . $id_reserva . '.png';

    try {
        // --- 1. CONSULTA DE DATOS PARA EL PDF ---
        $stmt = $pdo->prepare("
            SELECT f.fecha, f.hora, COALESCE(f.precio, o.precio) as precio_unit,
                   (SELECT GROUP_CONCAT(a.codigo_asiento SEPARATOR ', ') FROM reserva_items ri JOIN asientos a ON ri.asiento_id = a.id WHERE ri.reserva_id = ?) as butacas,
                   (SELECT COUNT(*) FROM reserva_items WHERE reserva_id = ?) as total_butacas
            FROM reservas r
            JOIN funciones f ON r.funcion_id = f.id
            JOIN obras o ON f.obra_id = o.id
            WHERE r.id = ?
        ");
        $stmt->execute([$id_reserva, $id_reserva, $id_reserva]);
        $res = $stmt->fetch();

        $fecha = date('d/m/Y', strtotime($res['fecha']));
        $hora = date('H:i', strtotime($res['hora']));
        $precio_total = $res['precio_unit'] * $res['total_butacas'];

        // --- 2. CONFIGURACIÓN PHPMailer (Usando constantes de config.php) ---
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($emailCliente, $nombreCliente);

        // --- 3. GENERAR QR Y PDF ---
        // Generamos el QR en la ruta que fijamos arriba
        QRcode::png($codigoQR, $temp_qr_path, QR_ECLEVEL_L, 10, 2);

        $pdf = new FPDF('P', 'mm', 'A5');
        $pdf->AddPage();
        
        // Diseño del Ticket
        $pdf->SetFillColor(44, 62, 80);
        $pdf->Rect(0, 0, 148, 25, 'F');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Text(35, 15, utf8_decode('AYUNTAMIENTO DE VILLAMARTÍN'));

        $pdf->SetY(30);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Arial', 'B', 18);
        $pdf->MultiCell(0, 8, utf8_decode(strtoupper($obraTitulo)), 0, 'C');

        $pdf->SetY(50);
        $pdf->SetFillColor(245, 245, 245);
        $pdf->Rect(10, 50, 128, 15, 'F');
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 15, utf8_decode("FECHA: $fecha  -  HORA: $hora"), 0, 1, 'C');

        $pdf->SetY(70);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 5, utf8_decode('TITULAR DE LA ENTRADA:'), 0, 1, 'C');
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 7, utf8_decode(strtoupper($nombreCliente)), 0, 1, 'C');

        $pdf->Ln(2);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 5, utf8_decode('LOCALIDADES:'), 0, 1, 'C');
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->MultiCell(0, 6, utf8_decode($res['butacas']), 0, 'C');

        // Insertamos el QR usando la ruta absoluta
        $pdf->Image($temp_qr_path, 49, 95, 50, 50);

        // Justificante de Pago
        $pdf->SetY(150);
        $pdf->SetFillColor(230, 255, 230); 
        $pdf->Rect(20, 150, 108, 12, 'F');
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(0, 100, 0);
        $pdf->Cell(0, 12, utf8_decode("PAGADO: " . number_format($precio_total, 2, ',', '.') . " " . chr(128)), 0, 1, 'C');

        $pdf_content = $pdf->Output('S'); 

        // --- 4. ENVÍO ---
        $mail->addStringAttachment($pdf_content, 'Entrada_Villamartin.pdf');
        $mail->AddEmbeddedImage($temp_qr_path, 'qr_entrada');

        $mail->isHTML(true);
        $mail->Subject = "🎟️ Tu entrada para $obraTitulo";
        $mail->Body = "
            <div style='background:#f4f4f4; padding:20px; font-family:Arial, sans-serif;'>
                <div style='background:#fff; max-width:500px; margin:auto; padding:20px; border-radius:10px; border:1px solid #ddd; text-align:center;'>
                    <h1 style='color:#2c3e50;'>¡Pago Confirmado!</h1>
                    <p>Hola $nombreCliente, adjunto tienes tu entrada con el justificante de pago.</p>
                    <img src='cid:qr_entrada' width='200'>
                    <p><b>Espectáculo:</b> $obraTitulo <br> <b>Fecha:</b> $fecha a las $hora</p>
                </div>
            </div>";

        $mail->send();

        // Borramos el QR temporal después de enviar
        if (file_exists($temp_qr_path)) unlink($temp_qr_path);
        return true;

    } catch (Exception $e) {
        // Logueamos el error para que puedas verlo en php-error.log
        error_log("Error enviando email: " . $mail->ErrorInfo);
        if (isset($temp_qr_path) && file_exists($temp_qr_path)) unlink($temp_qr_path);
        return false;
    }
}