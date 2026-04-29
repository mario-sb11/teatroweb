<?php
/**
 * ARCHIVO: index.php
 * DESCRIPCIÓN: Página principal de la cartelera.
 */

include_once 'includes/config.php';

$res_config = $pdo->query("SELECT clave, valor FROM configuracion")->fetchAll(PDO::FETCH_KEY_PAIR);

// Buscamos todas las obras que tengan funciones futuras y que el administrador haya marcado como visibles.
$sql = "SELECT o.id, o.titulo, o.descripcion, o.imagen, o.duracion,
        GROUP_CONCAT(CONCAT(f.fecha, '|', f.hora) ORDER BY f.fecha ASC SEPARATOR '||') as pases
        FROM obras o
        JOIN funciones f ON o.id = f.obra_id
        WHERE f.fecha >= CURDATE() AND o.visible = 1
        GROUP BY o.id
        ORDER BY o.id DESC";

$stmt = $pdo->query($sql);
$obras = $stmt->fetchAll();

// Cargamos la cabecera
include 'includes/header.php';
?>

  <div class="white-container">
    <div class="breadcrumbs"><span>INICIO</span> <span class="divider">▶</span> <span class="current">TEATRO MUNICIPAL</span></div>
    
    <div class="banner-section">
        <img src="assets/img/banner-teatro.jpg" alt="Banner Teatro Villamartín">
    </div>

    <h1 class="main-title" style="text-align: center; font-family: 'Roboto Slab', serif; margin-bottom: 10px;">Teatro Municipal - Reserva de Entradas</h1>
    <h2 class="section-subtitle" style="text-align: center; color: #666; font-size: 1.1rem; margin-bottom: 40px;">Seleccione una obra para reservar</h2>

    <div class="obras-grid">
      <?php foreach ($obras as $obra): ?>
        <div class="obra-item">
          <a href="seleccion_hora.php?obra=<?php echo (int)$obra['id']; ?>" style="text-decoration:none; color:inherit; display:flex; flex-direction:column; height:100%;">
           <div class="img-wrapper">
                <?php
                // Si la obra no tiene foto, ponemos la imagen por defecto del teatro.
                if (!empty($obra['imagen'])) {
                    $ruta_imagen = 'uploads/obras/' . $obra['imagen'];
                } else {
                    $ruta_imagen = 'assets/img/default-obra.jpg';
                }
                ?>
                <img src="<?php echo $ruta_imagen; ?>" alt="<?php echo htmlspecialchars($obra['titulo']); ?>">
            </div>
            <div style="padding: 20px 15px 10px; flex-grow:1;">
                <h3 style="margin:0; font-size:1.25rem; color:#2c3e50; font-family: 'Roboto Slab', serif;"><?php echo htmlspecialchars($obra['titulo']); ?></h3>
                <p style="font-size:0.85rem; color:#7f8c8d; margin-top:10px; line-height:1.4;">
                   <?php
                    // Refactorizado el recorte de descripción. Si es muy larga, le ponemos puntos suspensivos para no romper el diseño de la tarjeta.
                    $descripcion = htmlspecialchars($obra['descripcion']);
                    if (strlen($descripcion) > 95) {
                        echo substr($descripcion, 0, 95) . '...';
                    } else {
                        echo $descripcion;
                    }
                    ?>
                </p>
            </div>
            
            <div class="obra-info-extra">
                <?php
                $pases = explode('||', $obra['pases']);
                foreach(array_slice($pases, 0, 2) as $p): 
                    list($f, $h) = explode('|', $p);
                ?>
                    <div class="pase-item">
                        <span><i class="far fa-calendar-alt"></i> <?php echo date('d/m/Y', strtotime($f)); ?></span>
                        <span><i class="far fa-clock" style="margin-left:5px;"></i> <?php echo date('H:i', strtotime($h)); ?></span>
                    </div>
                <?php endforeach; ?>

                <?php if(!empty($obra['duracion'])): ?>
                    <div class="duracion-tag">
                        <i class="fas fa-hourglass-half"></i> Duración: <?php echo htmlspecialchars($obra['duracion']); ?> min
                    </div>
                <?php endif; ?>
                
                <div style="margin-top:15px; background:#557996; color:white; text-align:center; padding:12px; border-radius:4px; font-weight:700; text-transform: uppercase; font-size: 0.8rem; transition: background 0.3s;">
                    Reservar entradas
                </div>
            </div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="info-box">
        <h3>Información Importante</h3>
        <ul>
            <?php if(!empty($res_config['info_linea_1'])): ?><li><?php echo htmlspecialchars($res_config['info_linea_1']); ?></li><?php endif; ?>
            <?php if(!empty($res_config['info_linea_2'])): ?><li><?php echo htmlspecialchars($res_config['info_linea_2']); ?></li><?php endif; ?>
            <?php if(!empty($res_config['info_linea_3'])): ?><li><?php echo htmlspecialchars($res_config['info_linea_3']); ?></li><?php endif; ?>
        </ul>
    </div>
  </div>

<?php
// Cargamos el pie de página
include_once 'includes/footer.php';
?>