<?php
include 'auth.php';
include '../includes/config.php';

// Consulta para traer los datos de reserva, el título de la obra y el estado del QR
$stmt = $pdo->query("SELECT r.*, o.titulo 
                      FROM reservas r 
                      JOIN funciones f ON r.funcion_id = f.id 
                      JOIN obras o ON f.obra_id = o.id 
                      WHERE r.codigo_qr IS NOT NULL 
                      ORDER BY r.id DESC");
$qrs = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Códigos QR - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --sidebar-bg: #34495e; --primary-color: #557996; --bg-color: #f0f2f5; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Roboto', sans-serif; }
        body { display: flex; min-height: 100vh; background-color: var(--bg-color); }
        
        /* SIDEBAR */
        .sidebar { width: 250px; background-color: var(--sidebar-bg); color: white; display: flex; flex-direction: column; padding: 20px 0; position: fixed; height: 100%; }
        .sidebar-header { padding: 0 20px 30px; font-weight: bold; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; text-align: center; }
        .sidebar-menu a { display: block; padding: 15px 25px; color: #bdc3c7; text-decoration: none; transition: 0.3s; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background-color: var(--primary-color); color: white; border-left: 4px solid white; }
        .sidebar-menu i { margin-right: 10px; width: 20px; text-align: center; }
        .logout { margin-top: auto; border-top: 1px solid rgba(255,255,255,0.1); }

        /* CONTENIDO */
        .main-content { flex: 1; margin-left: 250px; padding: 30px; }
        .header-page { margin-bottom: 30px; }
        .header-page h1 { color: #333; font-size: 24px; }

        /* GRID DE TARJETAS QR */
        .qr-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        .qr-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: relative;
            border-top: 4px solid var(--primary-color);
            transition: transform 0.2s;
        }
        .qr-card:hover { transform: translateY(-5px); }
        .qr-card.usado { border-top-color: #bdc3c7; background-color: #fcfcfc; }
        
        .status-badge {
            position: absolute; top: 10px; right: 10px; padding: 4px 8px; border-radius: 4px;
            font-size: 10px; font-weight: bold;
        }
        .badge-disponible { background: #e3fcef; color: #00875a; }
        .badge-usado { background: #ffebe6; color: #de350b; }

        /* IMAGEN QR LOCAL */
        .qr-img { 
            width: 160px; height: 160px; 
            margin: 15px auto; 
            display: block;
            border: 1px solid #eee;
            padding: 5px;
            background: #fff;
        }
        
        .qr-info h4 { margin: 5px 0; color: #333; font-size: 16px; text-transform: capitalize; }
        .qr-info p { margin: 0; font-size: 13px; color: #777; font-style: italic; }
        .qr-token { 
            font-family: 'Courier New', monospace; 
            background: #f1f3f5; 
            padding: 6px; 
            display: inline-block; 
            margin-top: 10px; 
            font-size: 12px; 
            color: #444; 
            font-weight: bold;
            border-radius: 3px;
        }

        .btn-validar {
            margin-top: 15px; background: #27ae60; color: white; border: none;
            padding: 10px; width: 100%; border-radius: 4px; cursor: pointer; font-size: 12px;
            font-weight: bold; transition: 0.3s;
        }
        .btn-validar:hover { background: #219150; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-header">AYUNTAMIENTO DE<br>VILLAMARTÍN</div>
        <nav class="sidebar-menu">
            <a href="index.php"><i class="fas fa-home"></i> Panel Principal</a>
            <a href="admin_eventos.php"><i class="fas fa-calendar-alt"></i> Espectáculos</a>
            <a href="admin_reservas.php"><i class="fas fa-users"></i> Reservas</a>
            <a href="admin_disponibilidad.php"><i class="fas fa-chart-pie"></i> Disponibilidad</a>
            <a href="admin_qr.php" class="active"><i class="fas fa-qrcode"></i> Listado QR</a>
            <a href="scanner.php" style="background: #27ae60; color: white; margin-top: 10px; border-radius: 4px;"><i class="fas fa-camera"></i> ESCANEAR</a>
            
            <div class="logout">
                <a href="../index.php"><i class="fas fa-sign-out-alt"></i> Ver Web Pública</a>
            </div>
        </nav>
    </aside>

    <main class="main-content">
        <div class="header-page">
            <h1>Listado de Códigos QR</h1>
            <p style="color: #777;">Control visual de entradas emitidas (Generación local mediante PHPQRCode).</p>
        </div>

        <div class="qr-grid">
        <?php foreach ($qrs as $qr): 
            $usado = $qr['qr_usado'];
            $token = $qr['codigo_qr'];
            
            // Usamos nuestro generador local 'generar_qr.php'
            $url_qr_local = "generar_qr.php?codigo=" . urlencode($token);
        ?>
            <div class="qr-card <?php echo $usado ? 'usado' : ''; ?>">
                <span class="status-badge <?php echo $usado ? 'badge-usado' : 'badge-disponible'; ?>">
                    <?php echo $usado ? 'USADO' : 'ACTIVO'; ?>
                </span>

                <img src="<?php echo $url_qr_local; ?>" 
                     class="qr-img" 
                     style="<?php echo $usado ? 'filter: grayscale(1); opacity: 0.5;' : ''; ?>"
                     alt="QR <?php echo $token; ?>">
                    
                <div class="qr-info">
                    <h4><?php echo htmlspecialchars($qr['nombre']); ?></h4>
                    <p><?php echo htmlspecialchars($qr['titulo']); ?></p>
                    <div class="qr-token"><?php echo $token; ?></div>
                    
                    <?php if($usado): ?>
                        <p style="margin-top:10px; font-size:11px; color:#e74c3c; font-weight: bold;">
                            <i class="fas fa-history"></i> Validado:
                            <?php 
                            if (!empty($qr['fecha_entrada'])) {
                                echo date('d/m H:i', strtotime($qr['fecha_entrada']));
                            } else {
                                echo "Fecha desconocida";
                            }
                            ?>
                        </p>
                    <?php endif; ?>
                </div>

                <?php if (!$usado): ?>
                    <button class="btn-validar" onclick="if(confirm('¿Deseas marcar esta entrada como usada manualmente?')) window.location.href='validar_manual.php?id=<?php echo $qr['id']; ?>'">
                        <i class="fas fa-check-circle"></i> Validar Manualmente
                    </button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
    </main>

</body>
</html>