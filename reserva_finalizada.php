<?php
session_start();
require_once 'includes/config.php';
// Importamos lo necesario de PHPMailer
require 'vendor/phpmailer/Exception.php';
require 'vendor/phpmailer/PHPMailer.php';
require 'vendor/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['reserva_completada'])) {
    header("Location: index.php");
    exit();
}

$reserva = $_SESSION['reserva_completada'];

// --- 1. CONSULTA DE DETALLES ---
// Recuperamos las butacas y calculamos el precio final (si la función tiene precio especial, manda sobre la obra).
$stmt = $pdo->prepare("
    SELECT 
        a.codigo_asiento, 
        o.titulo, 
        COALESCE(f.precio, o.precio) AS precio_aplicado, -- Si f.precio es NULL, usa o.precio
        f.fecha, 
        f.hora
    FROM reserva_items ri
    JOIN asientos a ON ri.asiento_id = a.id
    JOIN funciones f ON a.funcion_id = f.id
    JOIN obras o ON f.obra_id = o.id
    WHERE ri.reserva_id = ?
");
$stmt->execute([$reserva['id']]);
$detalles = $stmt->fetchAll();

$asientos_html = "";
$total = 0;
foreach($detalles as $d) {
    $asientos_html .= "<li>Butaca: {$d['codigo_asiento']}</li>";
    $obra_titulo = $d['titulo'];
    $fecha_obra = date('d/m/Y', strtotime($d['fecha']));
    $hora_obra = date('H:i', strtotime($d['hora']));
    $total += $d['precio_aplicado']; 
}

// --- 2. ENVÍO DE EMAIL ---
// --- 2. ENVÍO DE EMAIL ---
if (!isset($_SESSION['email_enviado'])) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME); // ✅ Cambiado
        $mail->addAddress($reserva['email'], $reserva['nombre']);

        // ... resto del código del Body ...

        $mail->isHTML(true);
        $mail->Subject = "📝 Reserva Pendiente #" . $reserva['id'] . " - $obra_titulo";
        
        $mail->Body = "
            <div style='background:#f4f4f4; padding:20px; font-family:Arial, sans-serif;'>
                <div style='background:#fff; max-width:500px; margin:auto; padding:20px; border-radius:10px; border:1px solid #ddd;'>
                    <h2 style='color:#e67e22; text-align:center;'>¡Reserva Solicitada con Éxito!</h2>
                    <p>Hola <b>{$reserva['nombre']}</b>, hemos recibido tu solicitud de reserva.</p>
                    <div style='background:#2c3e50; color:white; padding:15px; border-radius:5px; margin:20px 0;'>
                        <h3 style='margin:0; text-transform:uppercase;'>$obra_titulo</h3>
                        <p style='margin:5px 0 0 0;'>📅 $fecha_obra a las $hora_obra</p>
                    </div>
                    <p><b>Tus Localidades:</b></p>
                    <ul>$asientos_html</ul>
                    <div style='background:#f9f9f9; padding:15px; border:1px dashed #ccc; text-align:center; margin-top:20px;'>
                        <span style='font-size:18px;'>Total a pagar en taquilla:</span><br>
                        <b style='font-size:24px; color:#27ae60;'>" . number_format($total, 2, ',', '.') . "€</b>
                    </div>
                    <h3 style='color:#333; margin-top:25px; margin-bottom:10px; font-size:18px;'>¿Qué debo hacer ahora?</h3>

                    <div style='text-align:left; color:#444; line-height:1.6;'>
                        <p style='margin: 5px 0;'>1. Acude a la taquilla con el ID: <b>#{$reserva['id']}</b></p>
                        <p style='margin: 5px 0;'>2. Abona el importe antes de 5 días.</p>
                        <p style='margin: 5px 0;'>3. Tras el pago, te enviaremos otro email con tu <b>Entrada PDF y el código QR.</b></p>
                    </div>
                </div>
            </div>";

        $mail->send();
        $_SESSION['email_enviado'] = true;
    } catch (Exception $e) {}
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reserva Confirmada - Teatro Villamartín</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --primary: #557996; --accent: #e67e22; --success: #27ae60; }
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .container { background: white; max-width: 550px; width: 90%; padding: 40px; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); text-align: center; border-top: 8px solid var(--accent); }
        .icon-check { font-size: 50px; color: var(--success); margin-bottom: 20px; }
        .reserva-id { font-size: 20px; color: var(--primary); font-weight: bold; margin-bottom: 20px; display: block; }
        .info-box { background: #f9f9f9; padding: 20px; border-radius: 10px; text-align: left; margin: 25px 0; border: 1px solid #eee; }
        .price-tag { font-size: 24px; color: var(--success); font-weight: bold; }
        .btn-volver { display: inline-block; padding: 12px 25px; background: var(--primary); color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
    </style>
</head>
<body>

<div class="container">
    <i class="fas fa-calendar-check icon-check"></i>
    <h1>¡Reserva Solicitada!</h1>
    <span class="reserva-id">Localizador: #<?php echo $reserva['id']; ?></span>

    <p>Hola <strong><?php echo htmlspecialchars($reserva['nombre']); ?></strong>, hemos bloqueado tus butacas correctamente.</p>

    <div class="info-box">
        <h3>Detalles de la operación:</h3>
        <p><strong>Espectáculo:</strong> <?php echo $obra_titulo; ?></p>
        <p><strong>Fecha y Hora:</strong> <?php echo $fecha_obra; ?> a las <?php echo $hora_obra; ?></p>
        <p><strong>Butacas:</strong> <?php echo str_replace(['<li>', '</li>'], ['', ', '], $asientos_html); ?></p>
        <p style="margin-top:15px;"><strong>Total a pagar en taquilla:</strong> <span class="price-tag"><?php echo number_format($total, 2, ',', '.') . "€"; ?></span></p>
    </div>

    <div style="text-align: left; font-size: 15px; line-height: 1.6;">
        <strong>¿Qué debes hacer ahora?</strong>
        <ul style="padding-left: 20px;">
            <li>Presenta este número de localizador en la taquilla.</li>
            <li>Realiza el pago antes del día de la función.</li>
            <li>Recibirás tus <strong>entradas con QR</strong> por email tras el pago.</li>
        </ul>
    </div>

    <a href="index.php" class="btn-volver">Volver a la cartelera</a>
</div>

</body>
</html>