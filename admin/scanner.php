<?php 
/**
 * ARCHIVO: scanner.php
 * DESCRIPCIÓN: Interfaz móvil para escanear códigos QR y ver estadísticas en vivo de acceso.
 */
include 'auth.php'; 
include '../includes/config.php';

// Si recibimos un ID de función por GET, estamos en "Modo Escáner"
if (isset($_GET['f_id'])) {
    $funcion_activa_id = (int)$_GET['f_id'];
} else {
    $funcion_activa_id = 0;
}

$funcion_info = null;

if ($funcion_activa_id > 0) {
    // Obtener detalles de la función seleccionada
    $stmt = $pdo->prepare("SELECT f.fecha, f.hora, o.titulo FROM funciones f JOIN obras o ON f.obra_id = o.id WHERE f.id = ?");
    $stmt->execute([$funcion_activa_id]);
    $funcion_info = $stmt->fetch();
} else {
    // Si no hay función seleccionada, listamos las funciones de HOY o futuras cercanas
    $stmt = $pdo->query("SELECT f.id, f.fecha, f.hora, o.titulo FROM funciones f JOIN obras o ON f.obra_id = o.id WHERE f.fecha >= CURDATE() ORDER BY f.fecha ASC, f.hora ASC LIMIT 10");
    $funciones_disponibles = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Escáner Pro - Teatro</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://unpkg.com/html5-qrcode"></script>
    <style>
        :root { --sidebar-bg: #34495e; --primary-color: #557996; --bg-color: #f0f2f5; --success: #27ae60; --error: #e74c3c; --warning: #f39c12; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Roboto', sans-serif; }
        
        /* Fondo negro por defecto para el modo escáner */
        body { display: flex; min-height: 100vh; background-color: #000; color: white; } 
        body.mode-select { background-color: var(--bg-color); color: #333; } /* Fondo claro para la selección */

        /* SIDEBAR */
        .sidebar { width: 250px; background-color: var(--sidebar-bg); color: white; display: flex; flex-direction: column; padding: 20px 0; position: fixed; height: 100%; z-index: 1000; }
        .sidebar-header { padding: 0 20px 30px; font-weight: bold; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; text-align: center;}
        .sidebar-menu a { display: block; padding: 15px 25px; color: #bdc3c7; text-decoration: none; }
        .sidebar-menu a.active { background-color: var(--primary-color); color: white; border-left: 4px solid white; }
        .sidebar-menu i { margin-right: 10px; width: 20px; text-align: center; }

        .main-content { flex: 1; margin-left: 250px; display: flex; flex-direction: column; position: relative; }
        @media (max-width: 768px) { 
            .sidebar { display: none; } 
            .main-content { margin-left: 0 !important; } 
        }

        /* --- VISTA 1: SELECCIÓN DE FUNCIÓN --- */
        .select-view { padding: 30px; max-width: 800px; margin: 0 auto; width: 100%; }
        .function-card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; cursor: pointer; border: 2px solid transparent; transition: 0.2s;}
        .function-card:hover { border-color: var(--primary-color); }
        .function-info h3 { color: #2c3e50; margin-bottom: 5px; }
        .function-info p { color: #7f8c8d; font-size: 0.9rem; }
        .btn-select { background: var(--primary-color); color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: bold; }

        /* --- VISTA 2: ESCÁNER Y ESTADÍSTICAS --- */
        .scanner-header { background: #222; padding: 15px; text-align: center; border-bottom: 1px solid #444; }
        .scanner-header h2 { font-size: 1.2rem; margin-bottom: 5px; color: var(--warning); }
        .scanner-header p { font-size: 0.85rem; color: #aaa; }
        .btn-back { position: absolute; top: 15px; left: 15px; color: white; text-decoration: none; font-size: 1.2rem; }

        /* Estadísticas en vivo */
        .live-stats { display: flex; justify-content: space-around; background: #111; padding: 15px 0; border-bottom: 2px solid #333; }
        .stat-box { text-align: center; flex: 1; }
        .stat-box:not(:last-child) { border-right: 1px solid #333; }
        .stat-value { font-size: 1.8rem; font-weight: bold; margin-bottom: 5px; }
        .stat-label { font-size: 0.75rem; text-transform: uppercase; color: #888; letter-spacing: 1px; }
        .color-success { color: var(--success); }
        .color-warning { color: var(--warning); }

        /* Contenedor Cámara */
        .camera-container { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 20px; }
        #reader { width: 100%; max-width: 500px; border: none !important; border-radius: 15px; overflow: hidden; }
        #reader__dashboard { background: rgba(255,255,255,0.9) !important; padding: 10px !important; border-radius: 0 0 15px 15px;}
        #reader__dashboard button { background: var(--primary-color) !important; color: white !important; border: none; padding: 8px 15px; border-radius: 4px;}

        /* Overlay Resultados */
        .result-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            z-index: 2000; justify-content: center; align-items: center; flex-direction: column; text-align: center; color: white; padding: 20px;
        }
        .status-success { background: var(--success); }
        .status-error { background: var(--error); }
        .result-overlay h1 { font-size: 2.5rem; margin-bottom: 10px; }
        .result-overlay p { font-size: 1.2rem; margin-bottom: 30px; }
        .btn-next { background: white; color: #333; font-size: 1.1rem; padding: 15px 30px; border:none; border-radius: 8px; font-weight:bold; cursor:pointer;}
    </style>
</head>
<body class="<?php echo $funcion_activa_id > 0 ? 'mode-scanner' : 'mode-select'; ?>">

    <aside class="sidebar">
        <div class="sidebar-header">AYUNTAMIENTO DE<br>VILLAMARTÍN</div>
        <nav class="sidebar-menu">
            <a href="index.php"><i class="fas fa-home"></i> Panel Principal</a>
            <a href="admin_eventos.php"><i class="fas fa-calendar-alt"></i> Espectáculos</a>
            <a href="admin_reservas.php"><i class="fas fa-users"></i> Reservas</a>
            <a href="admin_disponibilidad.php"><i class="fas fa-chart-pie"></i> Disponibilidad</a>
            <a href="admin_qr.php"><i class="fas fa-qrcode"></i> Listado QR</a>
            <a href="scanner.php" class="active"><i class="fas fa-camera"></i> ESCANEAR</a>
        </nav>
    </aside>

    <main class="main-content">
        
        <?php if ($funcion_activa_id == 0): ?>
            <div class="select-view">
                <h1 style="color: #2c3e50; margin-bottom: 5px;"><i class="fas fa-camera"></i> Modo Escáner</h1>
                <p style="color: #666; margin-bottom: 30px;">Seleccione la función que desea validar en puerta.</p>

                <?php if(empty($funciones_disponibles)): ?>
                    <p style="text-align:center; padding: 30px; background: white; border-radius:8px;">No hay funciones programadas para hoy o fechas futuras.</p>
                <?php else: ?>
                    <?php foreach($funciones_disponibles as $f): ?>
                        <div class="function-card" onclick="window.location.href='scanner.php?f_id=<?php echo $f['id']; ?>'">
                            <div class="function-info">
                                <h3><?php echo htmlspecialchars($f['titulo']); ?></h3>
                                <p><i class="far fa-calendar"></i> <?php echo date('d/m/Y', strtotime($f['fecha'])); ?> | <i class="far fa-clock"></i> <?php echo date('H:i', strtotime($f['hora'])); ?></p>
                            </div>
                            <a href="scanner.php?f_id=<?php echo $f['id']; ?>" class="btn-select">SELECCIONAR</a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div class="scanner-header">
                <a href="scanner.php" class="btn-back"><i class="fas fa-arrow-left"></i></a>
                <h2><?php echo htmlspecialchars($funcion_info['titulo']); ?></h2>
                <p><?php echo date('d/m/Y', strtotime($funcion_info['fecha'])); ?> - <?php echo date('H:i', strtotime($funcion_info['hora'])); ?></p>
            </div>

            <div class="live-stats">
                <div class="stat-box">
                    <div class="stat-value color-success" id="stat-entrados">--</div>
                    <div class="stat-label">Han Entrado</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value color-warning" id="stat-pendientes">--</div>
                    <div class="stat-label">Faltan por entrar</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value" id="stat-total">--</div>
                    <div class="stat-label">Entradas Vendidas</div>
                </div>
            </div>

            <div class="camera-container">
                <div id="reader"></div>
                <p style="color: #888; margin-top: 20px; font-size: 0.9rem;"><i class="fas fa-info-circle"></i> Enfoque el código QR de la entrada</p>
            </div>

            <div id="overlay" class="result-overlay">
                <div id="icon-result" style="font-size: 70px; margin-bottom: 15px;"></div>
                <h1 id="msg-titulo"></h1>
                <p id="msg-detalle"></p>
                <button class="btn-next" onclick="resetScanner()">ESCANEAR SIGUIENTE</button>
            </div>

            <script>
                const funcionId = <?php echo $funcion_activa_id; ?>;
                const html5QrCode = new Html5Qrcode("reader");
                const config = { fps: 10, qrbox: { width: 250, height: 250 } };

                // Función para actualizar los contadores en vivo
                function actualizarEstadisticas() {
                    fetch(`api_stats_acceso.php?f_id=${funcionId}`)
                        .then(res => res.json())
                        .then(data => {
                            if(data.success) {
                                document.getElementById('stat-total').style.fontSize = '1.8rem';
                                document.getElementById('stat-entrados').innerText = data.entrados;
                                document.getElementById('stat-pendientes').innerText = data.pendientes;
                                document.getElementById('stat-total').innerText = data.total;
                            } else {
                                const cajaError = document.getElementById('stat-total');
                                cajaError.style.fontSize = '0.8rem';
                                cajaError.style.color = '#e74c3c';
                                cajaError.innerText = data.error; 
                            }
                        })
                        .catch(err => {
                            document.getElementById('stat-total').innerText = "Error red";
                        });
                }

                // Cargar estadísticas iniciales y refrescar cada 5 segundos
                actualizarEstadisticas();
                setInterval(actualizarEstadisticas, 5000);

                // Lógica de validación del QR
                function onScanSuccess(decodedText) {
                    html5QrCode.pause(); 
                    
                    // Modificamos el endpoint para pasar también el ID de la función activa
                    fetch(`procesar_qr.php?codigo=${encodeURIComponent(decodedText)}&f_id=${funcionId}`)
                        .then(response => response.json())
                        .then(data => {
                            const overlay = document.getElementById('overlay');
                            const titulo = document.getElementById('msg-titulo');
                            const detalle = document.getElementById('msg-detalle');
                            const icon = document.getElementById('icon-result');

                            overlay.style.display = 'flex';
                            if (data.status === 'success') {
                                overlay.className = 'result-overlay status-success';
                                icon.innerHTML = '<i class="fas fa-check-circle"></i>';
                                titulo.innerText = "¡ACCESO PERMITIDO!";
                                detalle.innerHTML = data.message;
                                actualizarEstadisticas(); // Refrescamos al instante si entró alguien
                            } else {
                                overlay.className = 'result-overlay status-error';
                                icon.innerHTML = '<i class="fas fa-times-circle"></i>';
                                titulo.innerText = "¡ALTO! ENTRADA INVÁLIDA";
                                detalle.innerHTML = data.message;
                            }
                        })
                        .catch(err => {
                            alert("Error de red. Comprueba la conexión Wi-Fi/Datos.");
                            resetScanner();
                        });
                }

                function resetScanner() {
                    document.getElementById('overlay').style.display = 'none';
                    html5QrCode.resume();
                }

                // Iniciar cámara trasera
                html5QrCode.start(
                    { facingMode: "environment" }, 
                    config, 
                    onScanSuccess
                ).catch(err => {
                    console.error("No se pudo iniciar la cámara:", err);
                    alert("Por favor, asegúrese de dar permisos de cámara al navegador y utilizar una conexión segura HTTPS.");
                });
            </script>
        <?php endif; ?>
    </main>

</body>
</html>