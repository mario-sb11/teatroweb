<?php
// Iniciamos sesión para gestionar mensajes de error y datos de la reserva
include 'includes/config.php';

// --- SEGURIDAD CSRF (Norma CCN-CERT ATZ-08) ---
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        // Registramos el ataque silenciosamente en el log del servidor
        error_log("Ataque CSRF bloqueado en confirmar.php. IP: " . $_SERVER['REMOTE_ADDR']);
        // Detenemos la ejecución al instante
        die("Error de seguridad: La petición ha caducado o el token es inválido. Regrese al inicio.");
    }
    // ---------------------------------------------------------------

// Verificamos que los datos vengan por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Captura y limpieza de datos del formulario para evitar XSS
    $funcion_id = (int)($_POST['funcion_id'] ?? 0);
    $nombre = htmlspecialchars(strip_tags($_POST['nombre'] ?? ''), ENT_QUOTES, 'UTF-8');
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $telefono = preg_replace('/[^0-9+ ]/', '', $_POST['telefono'] ?? '');

    // FILTRO DE DATOS:
    if (empty($nombre) || empty($email) || empty($telefono) || $funcion_id === 0) {
        echo "<script>alert('Error: Faltan datos obligatorios o la petición es inválida.'); window.history.back();</script>";
        exit;
    }

    // --- CARGAMOS EL LÍMITE DINÁMICO ---
    $res_config = $pdo->query("SELECT clave, valor FROM configuracion")->fetchAll(PDO::FETCH_KEY_PAIR);
    $max_asientos = isset($res_config['max_asientos_reserva']) ? (int)$res_config['max_asientos_reserva'] : 4;

    // Validamos que el campo de asientos no esté vacío
    if (empty($_POST['asientos_ids'])) {
        // En lugar de ir al index, volvemos atrás con un aviso
        echo "<script>alert('Debe seleccionar al menos 1 asiento para continuar.'); window.history.back();</script>";
        exit;
    }

    $asientos_ids = explode(',', $_POST['asientos_ids']);

    // Validamos el límite de asientos dinámico también en el servidor
    if (count($asientos_ids) > $max_asientos) {
        die("Error: Se ha superado el límite máximo de $max_asientos asientos por reserva.");
    }

    try {
        // Iniciamos transacción para asegurar que no se creen reservas incompletas
        $pdo->beginTransaction();

        // Verificación de integridad: Comprobamos que los asientos pertenecen a la función 
        // y siguen bloqueados por el proceso de selección (en_proceso)
        $placeholders = implode(',', array_fill(0, count($asientos_ids), '?'));
        $sql_check = "SELECT COUNT(*) FROM asientos WHERE id IN ($placeholders) AND funcion_id = ? AND estado = 'en_proceso'";
        $params = array_merge($asientos_ids, [$funcion_id]);
        
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute($params);
        
        if ($stmt_check->fetchColumn() != count($asientos_ids)) {
            throw new Exception("Algunos asientos ya no están disponibles. Por favor, selecciónelos de nuevo.");
        }

        // Insertamos la cabecera de la reserva con estado pendiente
        $stmt = $pdo->prepare("INSERT INTO reservas (funcion_id, nombre, email, telefono, estado, codigo_qr) VALUES (?, ?, ?, ?, 'pendiente', NULL)");        $stmt->execute([$funcion_id, $nombre, $email, $telefono]);
        $reserva_id = $pdo->lastInsertId();

        // Procesamos cada asiento de la reserva
        foreach ($asientos_ids as $id) {
            $id = (int)$id;
            // Registramos el asiento en el detalle de la reserva
            $pdo->prepare("INSERT INTO reserva_items (reserva_id, asiento_id) VALUES (?, ?)")->execute([$reserva_id, $id]);
            
            // Cambiamos el estado del asiento a 'reservado' de forma permanente
            $pdo->prepare("UPDATE asientos SET estado = 'reservado', bloqueado_hasta = NULL WHERE id = ?")->execute([$id]);
        }

        $pdo->commit();
        
        // Limpiamos flags de correos anteriores para permitir un nuevo envío
        unset($_SESSION['email_enviado']); 

        // Guardamos los datos necesarios para la pantalla final y el envío de mail
        $_SESSION['reserva_completada'] = [
            'id'     => $reserva_id,
            'nombre' => $nombre,
            'email'  => $email
        ];

        // Redireccionamos al éxito
        header("Location: reserva_finalizada.php");
        exit();

    } catch (Exception $e) {
        // Si algo falla, deshacemos cualquier cambio en la base de datos
        $pdo->rollBack();
        
        // Manejo amigable de errores para el usuario
        $mensaje_error = $e->getMessage();
        echo "<script>alert('Ha ocurrido un error: $mensaje_error'); window.history.back();</script>";
        exit;
    }
} else {
    // Si se accede directamente al archivo sin POST, redirigimos al inicio
    header("Location: index.php");
    exit();
}
