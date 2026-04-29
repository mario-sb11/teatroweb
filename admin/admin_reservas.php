<?php
include 'auth.php';
include '../includes/config.php';
// --- 1. LÓGICA PARA CONFIRMAR PAGO ---
if (isset($_GET['confirmar_pago'])) {
    $id_reserva = (int)$_GET['confirmar_pago'];

    // Generamos un código único para el ticket (Ej: VILLA-2024-A1B2C3D4)
    $codigo_ticket = "VILLA-" . date('Y') . "-" . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
    
    // Actualizamos la reserva a pagada
    $stmt = $pdo->prepare("UPDATE reservas SET estado = 'confirmada', codigo_qr = ? WHERE id = ?");
    $stmt->execute([$codigo_ticket, $id_reserva]);

    // Bloqueamos los asientos para que nadie más pueda cogerlos
    $stmtAsientos = $pdo->prepare("UPDATE asientos SET estado = 'comprado' WHERE id IN (SELECT asiento_id FROM reserva_items WHERE reserva_id = ?)");
    $stmtAsientos->execute([$id_reserva]);
    
    // Sacamos los datos del cliente para enviarle el correo
    $res = $pdo->prepare("SELECT r.email, r.nombre, o.titulo FROM reservas r 
                          JOIN funciones f ON r.funcion_id = f.id 
                          JOIN obras o ON f.obra_id = o.id WHERE r.id = ?");
    $res->execute([$id_reserva]);
    $datos = $res->fetch();

    if ($datos) {
        include '../includes/enviar_ticket.php'; 
        enviarEntradaEmail($datos['email'], $datos['nombre'], $codigo_ticket, $datos['titulo'], $id_reserva, $pdo);
    }

    header("Location: admin_reservas.php?msj=confirmado");
    exit();
}

// --- 2. LÓGICA PARA REVERTIR PAGO ---
if (isset($_GET['revertir_pago'])) {
    $id_reserva = $_GET['revertir_pago'];
    $pdo->prepare("UPDATE reservas SET estado = 'pendiente', codigo_qr = NULL WHERE id = ?")->execute([$id_reserva]);
    $pdo->prepare("UPDATE asientos SET estado = 'reservado' WHERE id IN (SELECT asiento_id FROM reserva_items WHERE reserva_id = ?)")->execute([$id_reserva]);
    header("Location: admin_reservas.php?msj=revertido");
    exit();
}

// --- 3. LÓGICA DE CANCELACIÓN TOTAL ---
if (isset($_GET['cancelar'])) {
    $id_reserva = $_GET['cancelar'];
    $stmt = $pdo->prepare("UPDATE reservas SET estado = 'cancelada', codigo_qr = NULL WHERE id = ?");
    $stmt->execute([$id_reserva]);
    // Liberamos los asientos para que otro los pueda comprar
    $pdo->prepare("UPDATE asientos SET estado = 'libre' WHERE id IN (SELECT asiento_id FROM reserva_items WHERE reserva_id = ?)")->execute([$id_reserva]);
    header("Location: admin_reservas.php?msj=cancelado");
    exit();
}

// --- 4. LÓGICA DEL BUSCADOR ---
// Capturamos lo que el usuario haya escrito en el buscador
if (isset($_GET['buscar'])) {
    $termino_busqueda = $_GET['buscar'];
} else {
    $termino_busqueda = '';
}

$parametros = [];

$sql = "SELECT r.id, r.nombre, r.email, r.estado, f.fecha, o.titulo,
        GROUP_CONCAT(a.codigo_asiento SEPARATOR ', ') as asientos,
        (COUNT(ri.asiento_id) * COALESCE(f.precio, o.precio)) as importe_total
        FROM reservas r
        JOIN funciones f ON r.funcion_id = f.id
        JOIN obras o ON f.obra_id = o.id
        JOIN reserva_items ri ON r.id = ri.reserva_id
        JOIN asientos a ON ri.asiento_id = a.id";

// Si el usuario ha escrito algo en el buscador, añadimos la condición WHERE
if (!empty($termino_busqueda)) {
    $sql .= " WHERE r.id LIKE ? OR o.titulo LIKE ? OR r.email LIKE ?";
    $like_term = "%" . $termino_busqueda . "%";
    $parametros = [$like_term, $like_term, $like_term];
}

$sql .= " GROUP BY r.id ORDER BY r.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$reservas = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado de Reservas - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --sidebar-bg: #34495e; --primary-color: #557996; --bg-color: #f0f2f5; --accent-color: #27ae60; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Roboto', sans-serif; }
        body { display: flex; min-height: 100vh; background-color: var(--bg-color); }
        
        /* SIDEBAR */
        .sidebar { width: 250px; background-color: var(--sidebar-bg); color: white; display: flex; flex-direction: column; padding: 20px 0; position: fixed; height: 100%; }
        .sidebar-header { padding: 0 20px 30px; font-weight: bold; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; text-align: center; }
        .sidebar-menu a { display: block; padding: 15px 25px; color: #bdc3c7; text-decoration: none; transition: 0.3s; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background-color: var(--primary-color); color: white; border-left: 4px solid white; }
        .sidebar-menu i { margin-right: 10px; width: 20px; text-align: center; }
        .logout { margin-top: auto; border-top: 1px solid rgba(255,255,255,0.1); }

        .main-content { flex: 1; margin-left: 250px; padding: 40px; }
        .header-page { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        
        /* ESTILOS DEL BUSCADOR */
        .search-form { display: flex; gap: 10px; margin-bottom: 20px; background: white; padding: 15px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .search-input { flex: 1; padding: 10px 15px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; outline: none; transition: border-color 0.3s; }
        .search-input:focus { border-color: var(--primary-color); }
        .btn-search { background: var(--primary-color); color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 500; transition: opacity 0.3s; }
        .btn-search:hover { opacity: 0.9; }
        .btn-clear { background: #95a5a6; color: white; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-weight: 500; display: inline-flex; align-items: center; }

        /* TABLA Y BOTONES */
        .table-container { background: white; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        thead { background-color: var(--sidebar-bg); color: white; }
        th, td { padding: 15px 20px; text-align: left; border-bottom: 1px solid #f1f1f1; }
        
        .badge { padding: 5px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .badge-confirmed { background-color: #e8f5e9; color: #27ae60; }
        .badge-pending { background-color: #fff3cd; color: #856404; }
        .badge-canceled { background-color: #f8d7da; color: #721c24; }

        .btn-pay { background: var(--accent-color); color: white; padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: 500; display: inline-flex; align-items: center; gap: 5px; }
        .btn-pdf { background: #e74c3c; color: white; padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: 500; display: inline-flex; align-items: center; gap: 5px; margin-top: 5px; }
        .btn-cancel { color: #e74c3c; font-size: 12px; text-decoration: none; margin-left: 10px; }
        .btn-revert { color: #f39c12; font-size: 12px; text-decoration: none; margin-left: 10px; display: inline-block; }
        .btn-revert:hover { text-decoration: underline; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-header">AYUNTAMIENTO DE<br>VILLAMARTÍN</div>
        <nav class="sidebar-menu">
            <a href="index.php"><i class="fas fa-home"></i> Panel Principal</a>
            <a href="admin_eventos.php"><i class="fas fa-calendar-alt"></i> Espectáculos</a>
            <a href="admin_reservas.php" class="active"><i class="fas fa-users"></i> Reservas</a>
            <a href="admin_disponibilidad.php"><i class="fas fa-chart-pie"></i> Disponibilidad</a>
            <a href="admin_qr.php"><i class="fas fa-qrcode"></i> Listado QR</a>
            <a href="scanner.php" style="background: #27ae60; color: white; margin-top: 10px; border-radius: 6px;"><i class="fas fa-camera"></i> ESCANEAR</a>
            
            <div class="logout">
                <a href="../index.php"><i class="fas fa-sign-out-alt"></i> Ver Web Pública</a>
            </div>
        </nav>
    </aside>

    <main class="main-content">
        <div class="header-page">
            <h1>Gestión de Reservas</h1>
            <span style="color: #7f8c8d;"><i class="fas fa-user-shield"></i> Administrador Taquilla</span>
        </div>

        <?php if (isset($_GET['msj'])): ?>
            <?php if ($_GET['msj'] == 'confirmado'): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> El pago se ha confirmado y el ticket se ha enviado al cliente.</div>
            <?php elseif ($_GET['msj'] == 'revertido'): ?>
                <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Pago revertido. La reserva vuelve a estar pendiente.</div>
            <?php elseif ($_GET['msj'] == 'cancelado'): ?>
                <div class="alert alert-danger"><i class="fas fa-times-circle"></i> Reserva cancelada y asientos liberados.</div>
            <?php endif; ?>
        <?php endif; ?>

        <form method="GET" action="admin_reservas.php" class="search-form">
            <input type="text" name="buscar" class="search-input" placeholder="Buscar por ID, nombre del espectáculo o correo..." value="<?php echo htmlspecialchars($termino_busqueda); ?>">
            <button type="submit" class="btn-search"><i class="fas fa-search"></i> Buscar</button>
            <?php if (!empty($termino_busqueda)): ?>
                <a href="admin_reservas.php" class="btn-clear"><i class="fas fa-times" style="margin-right: 5px;"></i> Limpiar</a>
            <?php endif; ?>
        </form>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Espectáculo</th>
                        <th>Cliente</th>
                        <th>Butacas</th>
                        <th>Importe</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reservas)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 30px; color: #7f8c8d;">
                                <i class="fas fa-box-open" style="font-size: 24px; display: block; margin-bottom: 10px;"></i>
                                No se encontraron reservas que coincidan con tu búsqueda.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($reservas as $r): ?>
                            <tr>
                                <td><strong>#<?php echo str_pad($r['id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                                <td><?php echo $r['titulo']; ?></td>
                                <td><?php echo $r['nombre']; ?><br><small style="color:#7f8c8d;"><?php echo $r['email']; ?></small></td>
                                <td><span style="font-size: 0.9em;"><?php echo $r['asientos']; ?></span></td>
                                <td style="font-weight: bold; color: #2c3e50;">
                                    <?php echo number_format($r['importe_total'], 2, ',', '.'); ?>€
                                </td>
                                <td>
                                    <?php if($r['estado'] == 'confirmada'): ?>
                                        <span class="badge badge-confirmed">Pagado</span>
                                    <?php elseif($r['estado'] == 'cancelada'): ?>
                                        <span class="badge badge-canceled">Anulada</span>
                                    <?php else: ?>
                                        <span class="badge badge-pending">Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($r['estado'] == 'pendiente'): ?>
                                        <a href="?confirmar_pago=<?php echo $r['id']; ?>" class="btn-pay" onclick="return confirm('¿Confirmar cobro de <?php echo number_format($r['importe_total'], 2, ',', '.'); ?>€?')">
                                            <i class="fas fa-cash-register"></i> COBRAR
                                        </a>
                                        <a href="?cancelar=<?php echo $r['id']; ?>" class="btn-cancel" onclick="return confirm('¿Anular reserva y liberar asientos?')">Anular</a>
                                    <?php endif; ?>

                                    <?php if($r['estado'] == 'confirmada'): ?>
                                        <a href="ticket_pdf.php?id=<?php echo $r['id']; ?>" target="_blank" class="btn-pdf">
                                            <i class="fas fa-file-pdf"></i> TICKET
                                        </a>
                                        <a href="?revertir_pago=<?php echo $r['id']; ?>" class="btn-revert" onclick="return confirm('¿Error de cobro? Volverá a Pendiente pero los asientos seguirán ocupados.')">
                                            <i class="fas fa-undo"></i> Deshacer Pago
                                        </a>
                                        <a href="?cancelar=<?php echo $r['id']; ?>" class="btn-cancel" onclick="return confirm('¿Anular reserva y liberar asientos?')">Anular Todo</a>
                                    <?php endif; ?>

                                    <?php if($r['estado'] == 'cancelada'): ?>
                                        <span style="color: #aaa; font-style: italic; font-size: 12px;">Sin acciones</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>