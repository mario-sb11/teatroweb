<?php
/**
 * ARCHIVO: admin_eventos.php
 * DESCRIPCIÓN: Panel de gestión de obras y funciones.
 */

include_once 'auth.php';
include_once '../includes/config.php';
include_once '../includes/funciones_sistema.php';

$mensaje = "";

// --- LÓGICA DE GUARDADO ---
if (isset($_POST['guardar_todo'])) {
    
    // 1. REGISTRO DE OBRA (Nueva o Actualización de visibilidad/duración si fuera necesario)
    if ($_POST['tipo_ingreso'] == 'nueva') {
        $titulo = $_POST['titulo_nuevo'];
        $desc = $_POST['descripcion'];
        $precio_base = $_POST['precio_base'];
        $duracion = $_POST['duracion']; // Captura duración

        // Comprobamos si la casilla de "visible" está marcada
        if (isset($_POST['visible'])) {
            $visible = 1;
        } else {
            $visible = 0;
        }
        
        $nombre_imagen = "";
        
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
            $directorio = "../uploads/obras/";
            $nombre_imagen = time() . "_" . basename($_FILES["imagen"]["name"]);
            move_uploaded_file($_FILES["imagen"]["tmp_name"], $directorio . $nombre_imagen);
        }
        
        // Guardamos la nueva obra en la base de datos
        $stmt = $pdo->prepare("INSERT INTO obras (titulo, descripcion, imagen, precio, duracion, visible) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$titulo, $desc, $nombre_imagen, $precio_base, $duracion, $visible]);
        $obra_id = $pdo->lastInsertId();
    } else {
        // Si es existente, cogemos el ID que viene del desplegable
        $obra_id = $_POST['obra_existente'];
    }

    // 2. CREACIÓN DE LA FUNCIÓN (PASE)
    if ($obra_id) {
        $precio_especifico = (!empty($_POST['precio_especifico'])) ? $_POST['precio_especifico'] : null;

        $stmt = $pdo->prepare("INSERT INTO funciones (obra_id, fecha, hora, precio) VALUES (?, ?, ?, ?)");
        $stmt->execute([$obra_id, $_POST['fecha'], $_POST['hora'], $precio_especifico]);
        $nueva_id = $pdo->lastInsertId();
        
        prepararMapaAsientos($nueva_id, $pdo);
        $mensaje = "¡Espectáculo y asientos programados correctamente!";
    }
}

// Lógica de eliminación
if (isset($_GET['borrar'])) {
    $id_borrar = $_GET['borrar'];
    $pdo->prepare("DELETE FROM funciones WHERE id = ?")->execute([$id_borrar]);
    header("Location: admin_eventos.php");
    exit();
}

// --- CONSULTAS ---
$obras = $pdo->query("SELECT * FROM obras ORDER BY titulo ASC")->fetchAll();

// --- CONSULTA PARA EL LÍMITE DE ASIENTOS ---
$stmt_limite = $pdo->prepare("SELECT valor FROM configuracion WHERE clave = 'max_asientos_reserva'");
$stmt_limite->execute();
$limite_actual = $stmt_limite->fetchColumn() ?: 4;

// Listado principal de pases con los datos de sus obras correspondientes
$lista_eventos = $pdo->query("SELECT f.id, f.fecha, f.hora, f.precio as precio_especifico,
                                     o.titulo, o.precio as precio_base, o.duracion, o.visible
                              FROM funciones f
                              JOIN obras o ON f.obra_id = o.id
                              ORDER BY f.fecha ASC, f.hora ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Espectáculos - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --sidebar-bg: #34495e; --primary-color: #557996; --bg-color: #f0f2f5; --accent: #27ae60; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Roboto', sans-serif; }
        body { display: flex; min-height: 100vh; background-color: var(--bg-color); }
        
        /* Sidebar */
        .sidebar { width: 250px; background-color: var(--sidebar-bg); color: white; display: flex; flex-direction: column; padding: 20px 0; position: fixed; height: 100%; }
        .sidebar-header { padding: 0 20px 30px; font-weight: bold; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; text-align:center; }
        .sidebar-menu a { display: block; padding: 15px 25px; color: #bdc3c7; text-decoration: none; transition: 0.3s; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background-color: var(--primary-color); color: white; border-left: 4px solid white; }
        .sidebar-menu i { margin-right: 10px; width: 20px; text-align: center; }
        .logout { margin-top: auto; border-top: 1px solid rgba(255,255,255,0.1); }

        /* Contenido */
        .main-content { flex: 1; margin-left: 250px; padding: 30px; }
        .header-page { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header-page h1 { color: #2c3e50; font-size: 26px; }

        .btn-nuevo { background-color: var(--sidebar-bg); color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: 500; border: none; cursor: pointer; transition: 0.3s; }
        .btn-nuevo:hover { background-color: #2c3e50; transform: translateY(-1px); }

        /* Formulario */
        #form-container { display: none; background: white; padding: 30px; margin-bottom: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .form-section { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #eee; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: #555; font-weight: 500; }
        input, select, textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
        
        /* Tabla */
        .table-container { background: white; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        thead { background-color: var(--sidebar-bg); }
        th, td { padding: 18px 20px; text-align: left; }
        th { color: white; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; }
        tbody tr { border-bottom: 1px solid #eee; transition: 0.2s; }
        tbody tr:hover { background-color: #fcfcfc; }
        
        .actions a { margin-right: 15px; text-decoration: none; font-size: 14px; font-weight: 500; }
        .btn-edit { color: var(--primary-color); }
        .btn-delete { color: #e74c3c; }

        /* Etiquetas */
        .price-tag { font-weight: 700; color: #2c3e50; }
        .badge-especial { background: #fff3e0; color: #e67e22; padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; margin-left: 8px; border: 1px solid #ffe0b2; }
        .badge-visible { background: #e8f5e9; color: #2e7d32; padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; }
        .badge-invisible { background: #ffebee; color: #c62828; padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-header">AYUNTAMIENTO DE<br>VILLAMARTÍN</div>
        <nav class="sidebar-menu">
            <a href="index.php"><i class="fas fa-home"></i> Panel Principal</a>
            <a href="admin_eventos.php" class="active"><i class="fas fa-calendar-alt"></i> Espectáculos</a>
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
       <div class="header-page" style="flex-wrap: wrap; gap: 20px;">
        <div>
            <h1>Gestión de Espectáculos</h1>
            <div style="margin-top: 10px; background: white; padding: 10px 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: inline-flex; align-items: center; gap: 15px; border: 1px solid #ddd;">
                <form action="guardar_config.php" method="POST" style="display: flex; align-items: center; gap: 10px;">
                    <label style="font-size: 13px; font-weight: 500; color: #666;">Máximo asientos por reserva:</label>
                    <input type="number" name="config[max_asientos_reserva]" value="<?php echo $limite_actual; ?>" min="1" max="10" style="width: 60px; padding: 5px; text-align: center;">
                    <button type="submit" class="btn-nuevo" style="padding: 6px 12px; font-size: 12px; background: var(--accent);">APLICAR</button>
                </form>
                <?php if(isset($_GET['status']) && $_GET['status'] == 'ok'): ?>
                    <span style="color: var(--accent); font-size: 12px; font-weight: bold;"><i class="fas fa-check"></i> ¡Listo!</span>
                <?php endif; ?>
            </div>
        </div>
        <button onclick="toggleForm()" class="btn-nuevo"><i class="fas fa-plus"></i> Programar Función</button>
     </div>
        <?php if($mensaje): ?>
            <div style="background:#d4edda; color:#155724; padding:15px; margin-bottom:20px; border-radius:8px; border-left:5px solid #28a745;">
                <i class="fas fa-check-circle"></i> <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <div id="form-container">
            <h3 style="margin-bottom:25px; color:#2c3e50; border-bottom:1px solid #eee; padding-bottom:10px;">Nuevo Espectáculo / Función</h3>
            <form method="POST" enctype="multipart/form-data">
                
                <div class="form-section">
                    <label style="display:block; margin-bottom:10px; font-weight:bold;">1. INFORMACIÓN DE LA OBRA</label>
                    <div class="form-group">
                        <select name="tipo_ingreso" id="tipoSelect" onchange="mostrarCampos()" required>
                            <option value="existente">Seleccionar obra ya existente</option>
                            <option value="nueva">Registrar una obra nueva</option>
                        </select>
                    </div>

                    <div id="camposNueva" style="display:none; margin-top:15px;">
                        <div class="form-group"><input type="text" name="titulo_nuevo" placeholder="Título de la Obra"></div>
                        <div class="form-group"><textarea name="descripcion" placeholder="Descripción de la obra..." rows="3"></textarea></div>
                        
                        <div style="display:flex; gap:15px;">
                            <div style="flex:1;" class="form-group">
                                <label>Precio Base General (€):</label>
                                <input type="number" step="0.01" name="precio_base" placeholder="Ej: 12.00">
                            </div>
                            <div style="flex:1;" class="form-group">
                                <label>Duración (minutos/texto):</label>
                                <input type="text" name="duracion" placeholder="Ej: 90 min">
                            </div>
                        </div>

                        <div class="form-group" style="display:flex; align-items:center; gap:10px; margin-top:10px;">
                            <input type="checkbox" name="visible" id="visible" checked style="width:auto;">
                            <label for="visible" style="margin:0;">Hacer visible en la web pública inmediatamente</label>
                        </div>

                        <div class="form-group"><label>Imagen del Cartel:</label><input type="file" name="imagen"></div>
                    </div>

                    <div id="camposExistente" class="form-group">
                        <label>Obra:</label>
                        <select name="obra_existente">
                            <?php foreach($obras as $o): ?>
                                <option value="<?php echo $o['id']; ?>"><?php echo $o['titulo']; ?> (Base: <?php echo number_format($o['precio'], 2); ?>€)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-section" style="background: #ebf5fb;">
                    <label style="display:block; margin-bottom:10px; font-weight:bold;">2. DATOS DEL PASE (FECHA Y PRECIO)</label>
                    <div style="display:flex; gap:15px;">
                        <div style="flex:1;" class="form-group"><label>Fecha</label><input type="date" name="fecha" required></div>
                        <div style="flex:1;" class="form-group"><label>Hora</label><input type="time" name="hora" required></div>
                    </div>
                    <div class="form-group">
                        <label>Precio específico para este pase (€): <small style="color:#7f8c8d;">(Opcional, dejar vacío para usar el base)</small></label>
                        <input type="number" step="0.01" name="precio_especifico" placeholder="Ej: 5.00">
                    </div>
                </div>

                <div style="display:flex; gap:10px;">
                    <button type="submit" name="guardar_todo" class="btn-nuevo" style="background:var(--accent); flex:1;">CREAR ESPECTÁCULO</button>
                    <button type="button" onclick="toggleForm()" class="btn-nuevo" style="background:#95a5a6;">CANCELAR</button>
                </div>
            </form>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Espectáculo</th>
                        <th>Estado / Duración</th> <th>Fecha y Hora</th>
                        <th>Precio Aplicado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($lista_eventos as $ev): ?>
                    <tr>
                        <td>
                            <div style="font-weight:700; color:#2c3e50;"><?php echo htmlspecialchars($ev['titulo']); ?></div>
                            <small style="color:#7f8c8d;">ID Pase: #<?php echo $ev['id']; ?></small>
                        </td>
                        <td>
                            <?php if($ev['visible']): ?>
                                <span class="badge-visible"><i class="fas fa-eye"></i> Visible</span>
                            <?php else: ?>
                                <span class="badge-invisible"><i class="fas fa-eye-slash"></i> Oculto</span>
                            <?php endif; ?>
                            <div style="margin-top:5px; font-size:13px; color:#666;">
                                <i class="far fa-clock"></i> <?php echo !empty($ev['duracion']) ? htmlspecialchars($ev['duracion']) : 'N/D'; ?>
                            </div>
                        </td>
                        <td>
                            <i class="far fa-calendar-alt" style="color:var(--primary-color);"></i> <?php echo date('d/m/Y', strtotime($ev['fecha'])); ?><br>
                            <i class="far fa-clock" style="color:var(--primary-color);"></i> <?php echo date('H:i', strtotime($ev['hora'])); ?>h
                        </td>
                        <td>
                            <span class="price-tag">
                            <?php
                                if($ev['precio_especifico'] !== null) {
                                    echo number_format($ev['precio_especifico'], 2) . "€";
                                    echo "<span class='badge-especial'>ESPECIAL</span>";
                                } else {
                                    echo number_format($ev['precio_base'], 2) . "€";
                                }
                            ?>
                            </span>
                        </td>
                        <td class="actions">
                            <a href="editar_evento.php?id=<?php echo $ev['id']; ?>" class="btn-edit" title="Editar pase u obra"><i class="fas fa-edit"></i> Editar</a>
                            <a href="?borrar=<?php echo $ev['id']; ?>" class="btn-delete" onclick="return confirm('¿Estás seguro? Se borrarán todos los asientos y reservas de este pase.')" title="Eliminar"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        function toggleForm() {
            var f = document.getElementById('form-container');
            f.style.display = (f.style.display === 'none' || f.style.display === '') ? 'block' : 'none';
            window.scrollTo({top: 0, behavior: 'smooth'});
        }
        function mostrarCampos() {
            var tipo = document.getElementById('tipoSelect').value;
            document.getElementById('camposNueva').style.display = (tipo === 'nueva') ? 'block' : 'none';
            document.getElementById('camposExistente').style.display = (tipo === 'nueva') ? 'none' : 'block';
        }
    </script>
</body>
</html>
