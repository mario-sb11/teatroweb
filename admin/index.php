<?php
include 'auth.php';
include '../includes/config.php';

$config = $pdo->query("SELECT * FROM configuracion")->fetchAll(PDO::FETCH_KEY_PAIR);

// Filtramos las obras para que solo salgan las visibles
$obras_query = $pdo->query("SELECT * FROM obras WHERE visible = 1");

// --- 1. CONSULTAS DE TARJETAS (KPIs) ---

// 1. Espectáculos Activos (Funciones de hoy en adelante)
$stmt = $pdo->query("SELECT COUNT(*) FROM funciones WHERE fecha >= CURDATE()");
$total_espectaculos = $stmt->fetchColumn();

// 2. Entradas Pendientes
$stmt = $pdo->query("
    SELECT COUNT(ri.asiento_id)
    FROM reservas r
    JOIN reserva_items ri ON ri.reserva_id = r.id
    JOIN funciones f ON r.funcion_id = f.id
    WHERE r.estado = 'pendiente' AND f.fecha >= CURDATE()
");
$resultado_pendientes = $stmt->fetchColumn();

if ($resultado_pendientes > 0) {
    $reservas_pendientes = $resultado_pendientes;
} else {
    $reservas_pendientes = 0;
}

// 3. Entradas Vendidas (Butacas de reservas CONFIRMADAS en funciones futuras)
$stmt = $pdo->query("
    SELECT COUNT(ri.asiento_id)
    FROM reservas r
    JOIN reserva_items ri ON ri.reserva_id = r.id
    JOIN funciones f ON r.funcion_id = f.id
    WHERE r.estado = 'confirmada' AND f.fecha >= CURDATE()
");
$ventas_confirmadas = $stmt->fetchColumn();

// 4. Ingresos Reales (Dinero de funciones futuras ya cobrado)
$stmt = $pdo->query("
    SELECT SUM(COALESCE(f.precio, o.precio, 0))
    FROM reservas r
    JOIN reserva_items ri ON ri.reserva_id = r.id
    JOIN funciones f ON r.funcion_id = f.id
    JOIN obras o ON f.obra_id = o.id
    WHERE r.estado = 'confirmada' AND f.fecha >= CURDATE()
");
$resultado_ingresos = $stmt->fetchColumn();

if ($resultado_ingresos > 0) {
    $ingresos_reales = $resultado_ingresos;
} else {
    $ingresos_reales = 0;
}

// 5. Butacas Libres 
$stmt = $pdo->query("
    SELECT COUNT(*) FROM asientos a
    JOIN funciones f ON a.funcion_id = f.id
    WHERE a.estado = 'libre' AND f.fecha >= CURDATE()
");
$butacas_disponibles = $stmt->fetchColumn();

// --- 2. ACTIVIDAD RECIENTE ---
$sql_recientes = "
    SELECT r.nombre, r.fecha_reserva, o.titulo, r.id
    FROM reservas r
    JOIN funciones f ON r.funcion_id = f.id
    JOIN obras o ON f.obra_id = o.id
    ORDER BY r.id DESC LIMIT 5
";
$recientes = $pdo->query($sql_recientes)->fetchAll();

// --- 3. ESTADÍSTICAS DEL CUADRO (Resumen Histórico unificado) ---
function getStats($periodo, $pdo) {
    // Definimos la base de la consulta
    $where_periodo = "";
    switch($periodo) {
        case 'hoy': $where_periodo = "AND DATE(r.fecha_reserva) = CURDATE()"; break;
        case 'ayer': $where_periodo = "AND DATE(r.fecha_reserva) = SUBDATE(CURDATE(),1)"; break;
        case 'mes': $where_periodo = "AND MONTH(r.fecha_reserva) = MONTH(CURDATE()) AND YEAR(r.fecha_reserva) = YEAR(CURDATE())"; break;
        case 'total': $where_periodo = ""; break;
    }

    // 1. Contar asientos de reservas pendientes en ese periodo
    $stmtP = $pdo->query("
        SELECT COUNT(ri.asiento_id) FROM reservas r 
        JOIN reserva_items ri ON r.id = ri.reserva_id 
        WHERE r.estado = 'pendiente' $where_periodo");
    $resP = $stmtP->fetchColumn();
    
    if ($resP > 0) {
        $pendientes = $resP;
    } else {
        $pendientes = 0;
    }

    // 2. Contar asientos de reservas confirmadas en ese periodo
    $stmtV = $pdo->query("
        SELECT COUNT(ri.asiento_id) FROM reservas r 
        JOIN reserva_items ri ON r.id = ri.reserva_id 
        WHERE r.estado = 'confirmada' $where_periodo");
    $resV = $stmtV->fetchColumn();
    
    if ($resV > 0) {
        $vendidos = $resV;
    } else {
        $vendidos = 0;
    }
    // 3. Sumar dinero de esas confirmadas
    $stmtD = $pdo->query("
        SELECT SUM(COALESCE(f.precio, o.precio, 0)) 
        FROM reservas r
        JOIN reserva_items ri ON r.id = ri.reserva_id
        JOIN funciones f ON r.funcion_id = f.id
        JOIN obras o ON f.obra_id = o.id
        WHERE r.estado = 'confirmada' $where_periodo");
    $resD = $stmtD->fetchColumn();
    
    if ($resD > 0) {
        $dinero = $resD;
    } else {
        $dinero = 0;
    }
    return [
        'pendientes' => $pendientes,
        'vendidos' => $vendidos,
        'dinero' => $dinero
    ];
}

$stats_hoy = getStats('hoy', $pdo);
$stats_ayer = getStats('ayer', $pdo);
$stats_mes = getStats('mes', $pdo);
$stats_total = getStats('total', $pdo);
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Principal - Admin Teatro</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #557996;
            --sidebar-bg: #34495e;
            --bg-color: #f0f2f5;
            --text-color: #333;
            --accent-color: #27ae60;
            --warning-color: #f39c12;
            --card-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Roboto', sans-serif; }
        body { display: flex; min-height: 100vh; background-color: var(--bg-color); color: var(--text-color); }

        /* Sidebar */
        .sidebar { width: 250px; background-color: var(--sidebar-bg); color: white; display: flex; flex-direction: column; padding: 20px 0; position: fixed; height: 100%; }
        .sidebar-header { padding: 0 20px 30px; font-weight: bold; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; text-align: center; }
        .sidebar-menu a { display: block; padding: 15px 25px; color: #bdc3c7; text-decoration: none; transition: 0.3s; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background-color: var(--primary-color); color: white; border-left: 4px solid white; }
        .sidebar-menu i { margin-right: 10px; width: 20px; text-align: center; }
        .logout { margin-top: auto; border-top: 1px solid rgba(255,255,255,0.1); }

        /* Contenido Principal */
        .main-content { flex: 1; margin-left: 250px; padding: 40px; }
        .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .top-header h1 { font-size: 26px; color: #2c3e50; font-weight: 700; }

        .btn-csv { background: var(--accent-color); color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-size: 14px; font-weight: 500; transition: 0.3s; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn-csv:hover { background: #219150; transform: translateY(-1px); }

        /* Tarjetas Superiores */
        .cards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .card { background: white; padding: 25px; border-radius: 10px; box-shadow: var(--card-shadow); border-left: 5px solid var(--primary-color); position: relative; overflow: hidden; }
        .card h3 { font-size: 12px; color: #888; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .card .number { font-size: 28px; font-weight: 700; color: #2c3e50; margin-top: 5px; }
        .card-icon { position: absolute; right: 20px; top: 20px; font-size: 35px; color: rgba(0,0,0,0.05); }

        /* Estilos específicos tarjetas */
        .card-reservas { border-left-color: var(--warning-color); }
        .card-ventas { border-left-color: var(--accent-color); }
        
        /* Secciones (Tabla y Recientes) */
        .dashboard-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
        @media (max-width: 1000px) { .dashboard-grid { grid-template-columns: 1fr; } }

        .section-box { background: white; padding: 25px; border-radius: 10px; box-shadow: var(--card-shadow); margin-bottom: 30px; }
        .section-title { font-size: 18px; margin-bottom: 20px; color: #2c3e50; font-weight: 600; border-bottom: 1px solid #eee; padding-bottom: 15px; }

        /* Tabla Estilizada */
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { text-align: left; padding: 12px; color: #7f8c8d; font-weight: 500; font-size: 13px; text-transform: uppercase; }
        td { padding: 15px 12px; border-bottom: 1px solid #f1f1f1; color: #2c3e50; }
        tr:last-child td { border-bottom: none; }
        .row-highlight { background-color: #f8fcf9; font-weight: 600; }

        /* Actividad Reciente */
        .activity-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f5f5f5; }
        .activity-item:last-child { border-bottom: none; }
        .user-info strong { display: block; color: #2c3e50; font-size: 14px; margin-bottom: 3px; }
        .user-info span { font-size: 12px; color: #7f8c8d; }
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .badge-success { background-color: #e8f5e9; color: #27ae60; }

        /* Estilos para el Formulario de Configuración al fondo */
        .config-form-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .input-group { margin-bottom: 15px; }
        .input-group label { display: block; font-size: 12px; color: #7f8c8d; margin-bottom: 5px; font-weight: 600; text-transform: uppercase; }
        .input-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; color: #2c3e50; }
        .btn-save-config { background: var(--primary-color); color: white; border: none; padding: 12px 25px; border-radius: 6px; cursor: pointer; font-weight: bold; margin-top: 10px; transition: 0.3s; }
        .btn-save-config:hover { background: #34495e; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-header">AYUNTAMIENTO DE<br>VILLAMARTÍN</div>
        <nav class="sidebar-menu">
            <a href="index.php" class="active"><i class="fas fa-home"></i> Panel Principal</a>
            <a href="admin_eventos.php"><i class="fas fa-calendar-alt"></i> Espectáculos</a>
            <a href="admin_reservas.php"><i class="fas fa-users"></i> Reservas</a>
            <a href="admin_disponibilidad.php"><i class="fas fa-chart-pie"></i> Disponibilidad</a>
            <a href="admin_qr.php"><i class="fas fa-qrcode"></i> Listado QR</a>
            <a href="scanner.php" style="background: #27ae60; color: white; margin-top: 10px; border-radius: 6px;"><i class="fas fa-camera"></i> ESCANEAR</a>
            
            <div class="logout">
                <a href="../index.php"><i class="fas fa-sign-out-alt"></i> Ver Web Pública</a>
            </div>
        </nav>
    </aside>

    <main class="main-content">
        <div class="top-header">
            <h1>Panel de Control</h1>
            <a href="exportar_asistentes.php" class="btn-csv"><i class="fas fa-file-download"></i> Exportar Todo</a>
        </div>

        <?php if(isset($_GET['status']) && $_GET['status'] == 'ok'): ?>
            <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #c3e6cb; font-size: 14px;">
                <i class="fas fa-check-circle"></i> Los ajustes del portal se han actualizado correctamente.
            </div>
        <?php endif; ?>

        <div class="cards-grid">
            <div class="card">
                <h3>Espectáculos Activos</h3>
                <div class="number"><?php echo $total_espectaculos; ?></div>
                <i class="fas fa-theater-masks card-icon"></i>
            </div>
            
            <div class="card card-reservas">
                <h3>Reservas Pendientes</h3>
                <div class="number"><?php echo $reservas_pendientes; ?></div>
                <i class="fas fa-clock card-icon"></i>
            </div>

            <div class="card card-ventas">
                <h3>Entradas Vendidas</h3>
                <div class="number"><?php echo $ventas_confirmadas; ?></div>
                <i class="fas fa-check-circle card-icon"></i>
            </div>

             <div class="card card-ventas">
                <h3>Ingresos Reales</h3>
                <div class="number"><?php echo number_format($ingresos_reales, 2, ',', '.'); ?>€</div>
                <i class="fas fa-euro-sign card-icon"></i>
            </div>

            <div class="card">
                <h3>Butacas Libres</h3>
                <div class="number"><?php echo $butacas_disponibles; ?></div>
                <i class="fas fa-chair card-icon"></i>
            </div>
        </div>

        <div class="dashboard-grid">
            
            <section class="section-box">
                <h2 class="section-title">Resumen de Ventas (Confirmadas)</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Periodo</th>
                            <th>Reservas (Pend.)</th>
                            <th>Ventas (Conf.)</th>
                            <th>Recaudado</th>
                        </tr>
                    </thead>
                   <tbody>
                        <tr>
                            <td><strong>Hoy</strong></td>
                            <td><?php echo $stats_hoy['pendientes']; ?></td> <td><?php echo $stats_hoy['vendidos']; ?></td>
                            <td style="color: var(--accent-color); font-weight:bold;"><?php echo number_format($stats_hoy['dinero'], 2, ',', '.'); ?>€</td>
                        </tr>
                        <tr>
                            <td><strong>Ayer</strong></td>
                            <td><?php echo $stats_ayer['pendientes']; ?></td> <td><?php echo $stats_ayer['vendidos']; ?></td>
                            <td style="color: var(--accent-color); font-weight:bold;"><?php echo number_format($stats_ayer['dinero'], 2, ',', '.'); ?>€</td>
                        </tr>
                        <tr>
                            <td><strong>Este Mes</strong></td>
                            <td><?php echo $stats_mes['pendientes']; ?></td> <td><?php echo $stats_mes['vendidos']; ?></td>
                            <td style="color: var(--accent-color); font-weight:bold;"><?php echo number_format($stats_mes['dinero'], 2, ',', '.'); ?>€</td>
                        </tr>
                        <tr class="row-highlight">
                            <td>TOTAL HISTÓRICO</td>
                            <td><?php echo $stats_total['pendientes']; ?></td> <td><?php echo $stats_total['vendidos']; ?></td>
                            <td style="color: #155724; font-size: 15px;"><?php echo number_format($stats_total['dinero'], 2, ',', '.'); ?>€</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="section-box">
                <h2 class="section-title">Últimas Interacciones</h2>
                <?php if(empty($recientes)): ?>
                    <p style="color:#777; text-align:center; padding:20px;">Sin actividad reciente.</p>
                <?php else: ?>
                    <?php foreach($recientes as $r): ?>
                        <div class="activity-item">
                            <div class="user-info">
                                <strong><?php echo htmlspecialchars($r['nombre']); ?> <small style="color:#aaa;">#<?php echo $r['id']; ?></small></strong>
                                <span><?php echo htmlspecialchars($r['titulo']); ?></span>
                            </div>
                            <div class="time-info">
                                <span class="badge badge-success">Nueva</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <div style="margin-top:20px; text-align:center;">
                    <a href="admin_reservas.php" style="color:var(--primary-color); text-decoration:none; font-size:13px; font-weight:500;">Ver detalle &rarr;</a>
                </div>
            </section>
        </div>

        <section class="section-box">
            <h2 class="section-title"><i class="fas fa-cogs"></i> Ajustes Generales del Portal (Información y Footer)</h2>
            <form action="guardar_config.php" method="POST">
                <div class="config-form-container">
                    <div>
                        <h4 style="font-size:14px; color:var(--primary-color); margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:5px;">Avisos en Cartelera</h4>
                        <div class="input-group">
                            <label>Mensaje Línea 1</label>
                            <input type="text" name="config[info_linea_1]" value="<?php echo htmlspecialchars($config['info_linea_1'] ?? ''); ?>">
                        </div>
                        <div class="input-group">
                            <label>Mensaje Línea 2</label>
                            <input type="text" name="config[info_linea_2]" value="<?php echo htmlspecialchars($config['info_linea_2'] ?? ''); ?>">
                        </div>
                    </div>

                    <div>
                        <h4 style="font-size:14px; color:var(--primary-color); margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:5px;">Datos de Contacto</h4>
                        <div class="input-group">
                            <label>Teléfono de Atención</label>
                            <input type="text" name="config[contacto_telefono]" value="<?php echo htmlspecialchars($config['contacto_telefono'] ?? ''); ?>">
                        </div>
                        <div class="input-group">
                            <label>Email Municipal</label>
                            <input type="email" name="config[contacto_correo]" value="<?php echo htmlspecialchars($config['contacto_correo'] ?? ''); ?>">
                        </div>
                    </div>

                    <div>
                        <h4 style="font-size:14px; color:var(--primary-color); margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:5px;">Horario de Taquilla</h4>
                        <div class="input-group">
                            <label>Horario Principal</label>
                            <input type="text" name="config[horario_linea_1]" value="<?php echo htmlspecialchars($config['horario_linea_1'] ?? ''); ?>" placeholder="Ej: Lunes a Viernes 09:00 - 14:00">
                        </div>
                        <div class="input-group">
                            <label>Horario Secundario</label>
                            <input type="text" name="config[horario_linea_2]" value="<?php echo htmlspecialchars($config['horario_linea_2'] ?? ''); ?>" placeholder="Ej: Sábados 10:00 - 13:00">
                        </div>
                    </div>
                </div>
                
                <div style="text-align:right; margin-top:20px; border-top:1px solid #eee; padding-top:20px;">
                    <button type="submit" class="btn-save-config">
                        <i class="fas fa-save"></i> ACTUALIZAR INFORMACIÓN DEL PORTAL
                    </button>
                </div>
            </form>
        </section>

    </main>
</body>
</html>