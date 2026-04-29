<?php
/**
 * ARCHIVO: admin_disponibilidad.php
 * DESCRIPCIÓN: Panel visual para ver la ocupación y disponibilidad de los espectáculos activos.
 */
include 'auth.php';
include '../includes/config.php';

// Consulta para obtener las funciones futuras y calcular su ocupación
$sql = "SELECT 
            f.id AS funcion_id, 
            f.fecha, 
            f.hora, 
            o.titulo, 
            COUNT(a.id) AS total_asientos,
            SUM(CASE WHEN a.estado IN ('reservado', 'comprado') THEN 1 ELSE 0 END) AS ocupados
        FROM funciones f
        JOIN obras o ON f.obra_id = o.id
        LEFT JOIN asientos a ON a.funcion_id = f.id
        WHERE f.fecha >= CURDATE()
        GROUP BY f.id
        ORDER BY f.fecha ASC, f.hora ASC";

$stmt = $pdo->query($sql);
$funciones = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Disponibilidad de Espectáculos</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --sidebar-bg: #34495e; --primary-color: #557996; --bg-color: #f0f2f5; --success: #27ae60; --danger: #e74c3c; --warning: #f1c40f; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Roboto', sans-serif; }
        body { display: flex; min-height: 100vh; background-color: var(--bg-color); }
        
        /* Sidebar estándar de tu admin */
        .sidebar { width: 250px; background-color: var(--sidebar-bg); color: white; display: flex; flex-direction: column; padding: 20px 0; position: fixed; height: 100%; }
        .sidebar-header { padding: 0 20px 30px; font-weight: bold; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; text-align: center; }
        .sidebar-menu a { display: block; padding: 15px 25px; color: #bdc3c7; text-decoration: none; transition: 0.3s; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background-color: var(--primary-color); color: white; }
        .sidebar-menu i { margin-right: 10px; width: 20px; text-align: center; }
        
        .main-content { flex: 1; margin-left: 250px; padding: 30px; }
        
        /* Estilos de las tarjetas de disponibilidad */
        .grid-disponibilidad { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 25px; margin-top: 20px; }
        .card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .card h3 { color: #2c3e50; font-size: 1.1rem; margin-bottom: 10px; border-bottom: 2px solid #eee; padding-bottom: 10px;}
        .card-meta { color: #7f8c8d; font-size: 0.9rem; margin-bottom: 15px; display: flex; gap: 15px; }
        
        /* Barra de progreso */
        .progress-container { background: #eee; border-radius: 10px; height: 20px; width: 100%; overflow: hidden; margin-bottom: 10px; position: relative;}
        .progress-bar { height: 100%; border-radius: 10px; transition: width 0.5s ease; }
        
        .stats { display: flex; justify-content: space-between; font-size: 0.85rem; font-weight: bold; color: #555; }
        .empty-msg { background: white; padding: 30px; border-radius: 8px; text-align: center; color: #777; }
    </style>
</head>
<body>

    <aside class="sidebar">
         <div class="sidebar-header">AYUNTAMIENTO DE<br>VILLAMARTÍN</div>
        <nav class="sidebar-menu">
            <a href="index.php"><i class="fas fa-home"></i> Panel Principal</a>
            <a href="admin_eventos.php"><i class="fas fa-calendar-alt"></i> Espectáculos</a>
            <a href="admin_reservas.php"><i class="fas fa-users"></i> Reservas</a>
            <a href="admin_disponibilidad.php" class="active"><i class="fas fa-chart-pie"></i> Disponibilidad</a>
            <a href="admin_qr.php"><i class="fas fa-qrcode"></i> Listado QR</a>
            <a href="scanner.php" style="background: #27ae60; color: white; margin-top: 10px; border-radius: 4px;"><i class="fas fa-camera"></i> ESCANEAR</a>
            
            <div class="logout">
                <a href="../index.php"><i class="fas fa-sign-out-alt"></i> Ver Web Pública</a>
            </div>
        </nav>
    </aside>

    <main class="main-content">
        <h1 style="color: #2c3e50;"><i class="fas fa-chart-pie"></i> Disponibilidad Actual</h1>
        <p style="color: #666; margin-bottom: 30px;">Estado de ocupación de las próximas funciones.</p>

        <?php if (empty($funciones)): ?>
            <div class="empty-msg">
                <i class="fas fa-calendar-times" style="font-size: 40px; margin-bottom: 15px; color: #ccc;"></i>
                <h2>No hay funciones programadas</h2>
                <p>Añade nuevas funciones desde el panel de eventos para ver su disponibilidad aquí.</p>
            </div>
        <?php else: ?>
            <div class="grid-disponibilidad">
                <?php foreach ($funciones as $f): 
                    $total = (int)$f['total_asientos'];
                    $ocupados = (int)$f['ocupados'];
                    $libres = $total - $ocupados;
                    
                    // Calcular porcentaje
                    if ($total > 0) {
                        $porcentaje = round(($ocupados / $total) * 100);
                    } else {
                        $porcentaje = 0;
                    }
                    
                    // Definir color de la barra según ocupación
                   if ($porcentaje > 90) {
                        $color_barra = 'var(--danger)'; // Rojo (Casi lleno o Sold Out)
                    } elseif ($porcentaje > 70) {
                        $color_barra = 'var(--warning)'; // Amarillo (Se está llenando)
                    } else {
                        $color_barra = 'var(--success)'; // Verde por defecto (mucha disponibilidad)
                    }
                ?>
                    <div class="card">
                        <h3><?php echo htmlspecialchars($f['titulo']); ?></h3>
                        <div class="card-meta">
                            <span><i class="far fa-calendar-alt"></i> <?php echo date('d/m/Y', strtotime($f['fecha'])); ?></span>
                            <span><i class="far fa-clock"></i> <?php echo date('H:i', strtotime($f['hora'])); ?></span>
                        </div>
                        
                        <div class="progress-container">
                            <div class="progress-bar" style="width: <?php echo $porcentaje; ?>%; background-color: <?php echo $color_barra; ?>;"></div>
                        </div>
                        
                        <div class="stats">
                            <span>Ocupados: <?php echo $ocupados; ?></span>
                            <span>Libres: <?php echo $libres; ?></span>
                            <span>Total: <?php echo $total; ?> (<?php echo $porcentaje; ?>%)</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

</body>
</html>