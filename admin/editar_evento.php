<?php
/**
 * ARCHIVO: editar_evento.php
 * DESCRIPCIÓN: Formulario de edición para obras y funciones existentes.
 */
include 'auth.php';
include '../includes/config.php';

if (isset($_GET['id'])) {
    $id_funcion = (int)$_GET['id'];
} else {
    $id_funcion = null;
}

if (!$id_funcion) { 
    header("Location: admin_eventos.php"); 
    exit; 
}

$mensaje = "";

// 1. CARGAR DATOS ACTUALES (Añadimos duracion y visible a la consulta)
$stmt = $pdo->prepare("SELECT f.*, o.titulo, o.descripcion, o.imagen, o.precio, o.id as obra_id, o.duracion, o.visible 
                       FROM funciones f JOIN obras o ON f.obra_id = o.id WHERE f.id = ?");
$stmt->execute([$id_funcion]);
$evento = $stmt->fetch();

// 2. LÓGICA DE GUARDADO
if (isset($_POST['actualizar'])) {
    $obra_id = $_POST['obra_id'];
    $titulo = $_POST['titulo'];
    $desc = $_POST['descripcion'];
    $precio = $_POST['precio'];
    $fecha = $_POST['fecha'];
    $hora = $_POST['hora'];
    $duracion = (int)$_POST['duracion']; // Captura duración
    $visible = isset($_POST['visible']) ? 1 : 0; // Captura checkbox visibilidad
    
    // Procesar Imagen si se sube una nueva
    $imagen_final = $evento['imagen'];
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
        $directorio = "../uploads/obras/";
        $nombre_archivo = time() . "_" . basename($_FILES["imagen"]["name"]);
        if (move_uploaded_file($_FILES["imagen"]["tmp_name"], $directorio . $nombre_archivo)) {
            $imagen_final = $nombre_archivo;
        }
    }

    try {
        $pdo->beginTransaction();
        
        // Actualizar Obra (Incluimos duracion y visible)
        $stmt1 = $pdo->prepare("UPDATE obras SET titulo = ?, descripcion = ?, precio = ?, imagen = ?, duracion = ?, visible = ? WHERE id = ?");
        $stmt1->execute([$titulo, $desc, $precio, $imagen_final, $duracion, $visible, $obra_id]);
        
        // Actualizar Función
        $stmt2 = $pdo->prepare("UPDATE funciones SET fecha = ?, hora = ? WHERE id = ?");
        $stmt2->execute([$fecha, $hora, $id_funcion]);
        
        $pdo->commit();
        $mensaje = "¡Evento actualizado correctamente!";
        
        // Recargamos datos para mostrar los cambios en el formulario
        header("Refresh:1; url=admin_eventos.php");
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensaje = "Error al actualizar: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Espectáculo</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --sidebar-bg: #34495e; --primary-color: #557996; --bg-color: #f0f2f5; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Roboto', sans-serif; }
        body { display: flex; min-height: 100vh; background-color: var(--bg-color); }
        .sidebar { width: 250px; background-color: var(--sidebar-bg); color: white; display: flex; flex-direction: column; padding: 20px 0; position: fixed; height: 100%; }
        .sidebar-header { padding: 0 20px 30px; font-weight: bold; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; }
        .sidebar-menu a { display: block; padding: 15px 25px; color: #bdc3c7; text-decoration: none; transition: 0.3s; }
        .sidebar-menu a:hover { background-color: var(--primary-color); color: white; }
        .main-content { flex: 1; margin-left: 250px; padding: 30px; }
        
        .edit-card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 800px; }
        .form-group { margin-bottom: 20px; }
        label { font-weight: 500; color: #444; display: block; margin-bottom: 8px; }
        input, textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 15px; }
        .btn-save { background: #27ae60; color: white; border: none; padding: 15px 30px; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold; }
        .current-img { width: 100px; height: 140px; object-fit: cover; border-radius: 4px; margin-bottom: 10px; border: 1px solid #ddd; }
        
        /* Estilo para el switch de visibilidad */
        .visible-switch { display: flex; align-items: center; gap: 10px; background: #f8f9fa; padding: 15px; border-radius: 6px; border: 1px solid #eee; margin-bottom: 20px; }
        .visible-switch input { width: auto; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-header">AYUNTAMIENTO DE<br>VILLAMARTÍN</div>
        <nav class="sidebar-menu">
            <a href="admin_eventos.php"><i class="fas fa-arrow-left"></i> Volver atrás</a>
        </nav>
    </aside>

    <main class="main-content">
        <h1>Editar Espectáculo: <?php echo htmlspecialchars($evento['titulo']); ?></h1>
        
        <?php if($mensaje): ?>
            <div style="background:#d4edda; color:#155724; padding:15px; margin: 20px 0; border-radius:4px; border-left: 5px solid #27ae60;">
                <i class="fas fa-check-circle"></i> <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="edit-card">
            <input type="hidden" name="obra_id" value="<?php echo $evento['obra_id']; ?>">
            
            <div class="visible-switch">
                <input type="checkbox" name="visible" id="visible" <?php echo ($evento['visible'] == 1) ? 'checked' : ''; ?>>
                <label for="visible" style="margin:0;">Evento visible al público en la web</label>
            </div>

            <div class="form-group">
                <label>Título de la Obra</label>
                <input type="text" name="titulo" value="<?php echo htmlspecialchars($evento['titulo']); ?>" required>
            </div>

            <div class="form-group">
                <label>Descripción</label>
                <textarea name="descripcion" rows="4"><?php echo htmlspecialchars($evento['descripcion']); ?></textarea>
            </div>

            <div style="display: flex; gap: 20px;">
                <div class="form-group" style="flex: 1;">
                    <label>Fecha</label>
                    <input type="date" name="fecha" value="<?php echo $evento['fecha']; ?>" required>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Hora</label>
                    <input type="time" name="hora" value="<?php echo $evento['hora']; ?>" required>
                </div>
            </div>

            <div style="display: flex; gap: 20px;">
                <div class="form-group" style="flex: 1;">
                    <label>Precio (€)</label>
                    <input type="number" step="0.01" name="precio" value="<?php echo $evento['precio']; ?>" required>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Duración (en minutos)</label>
                    <input type="number" name="duracion" value="<?php echo (int)$evento['duracion']; ?>" placeholder="Ej: 90">
                </div>
            </div>

            <div class="form-group">
                <label>Imagen del Cartel Actual</label>
                <?php 
                    $img_src = !empty($evento['imagen']) ? "../uploads/obras/" . $evento['imagen'] : "../assets/img/default-obra.jpg";
                ?>
                <img src="<?php echo $img_src; ?>" class="current-img" alt="Vista previa">
                <input type="file" name="imagen" accept="image/*">
                <small style="color: #888;">Sube una imagen nueva solo si deseas reemplazar la actual.</small>
            </div>

            <button type="submit" name="actualizar" class="btn-save">GUARDAR CAMBIOS</button>
        </form>
    </main>

</body>
</html>