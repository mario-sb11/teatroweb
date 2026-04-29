<?php 
/**
 * ARCHIVO: seleccion_hora.php
 * DESCRIPCIÓN: Selección de pase/función para una obra específica.
 * CAMBIOS: 
 * - Se añade lógica de precio dinámico: Prioriza precio de función sobre el de obra.
 * - Datos de contacto y horarios dinámicos.
 */

include 'includes/config.php'; 

// 1. CARGAR CONFIGURACIÓN GENERAL PARA EL FOOTER
$res_config = $pdo->query("SELECT clave, valor FROM configuracion")->fetchAll(PDO::FETCH_KEY_PAIR);

if (isset($_GET['obra'])) {
    $obra_id = (int)$_GET['obra'];
} else {
    $obra_id = 0;
}

// 2. OBTENER DATOS DE LA OBRA (Precio base)
$stmtObra = $pdo->prepare("SELECT titulo, precio FROM obras WHERE id = ?");
$stmtObra->execute([$obra_id]);
$obra = $stmtObra->fetch();

if (!$obra) {
    header("Location: index.php");
    exit;
}

// 3. OBTENER SUS FUNCIONES PRÓXIMAS (Incluimos el precio específico de la función)
$stmtFunc = $pdo->prepare("SELECT id, fecha, hora, precio FROM funciones WHERE obra_id = ? AND fecha >= CURDATE() ORDER BY fecha, hora");
$stmtFunc->execute([$obra_id]);
$funciones = $stmtFunc->fetchAll();

include 'includes/header.php';

?>
  <style>
    .btn-horario {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #557996;
        color: white;
        padding: 20px 30px;
        margin-bottom: 15px;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 700;
        font-size: 1.1rem;
        transition: background 0.3s;
    }
    .btn-horario:hover { background: #344d65; }
    .precio-label { background: rgba(255,255,255,0.2); padding: 5px 12px; border-radius: 4px; font-size: 1rem; }
  </style>
  <div class="white-container" style="text-align:center;">
    <div class="breadcrumbs" style="text-align:left; margin-bottom: 30px;">
        <a href="index.php" style="text-decoration:none; color:inherit;">INICIO</a> 
        <span class="divider">▶</span> 
        <span class="current"><?php echo strtoupper(htmlspecialchars($obra['titulo'])); ?></span>
    </div>

    <h1 class="main-title" style="font-family: 'Roboto Slab', serif; margin-bottom: 10px;">Seleccione el horario</h1>
    <h2 style="color: #666; font-size: 1.1rem; margin-bottom: 40px; font-weight: 400;">Funciones disponibles para esta obra</h2>
    
    <div style="max-width: 550px; margin: 0 auto;">
        <?php foreach ($funciones as $f): 
            // Lógica de Precio: Si la función tiene precio propio (!= 0), úsalo. Si no, usa el de la obra.
            $precioFinal = (!empty($f['precio']) && $f['precio'] > 0) ? $f['precio'] : $obra['precio'];
        ?>
            <a href="reserva.php?f=<?php echo $f['id']; ?>" class="btn-horario">
                <span>
                    <i class="far fa-clock"></i> <?php echo date('d/m/Y', strtotime($f['fecha'])) . " - " . date('H:i', strtotime($f['hora'])) . " hs"; ?>
                </span>
                <span class="precio-label">
                    <?php echo number_format($precioFinal, 2, ',', '.'); ?>€
                </span>
            </a>
        <?php endforeach; ?>
        
        <?php if(empty($funciones)): ?>
            <p style="padding: 20px; background: #f9f9f9; border-radius: 8px;">No hay horarios disponibles para esta obra actualmente.</p>
        <?php endif; ?>
    </div>
    
    <div style="margin-top:40px;">
        <a href="index.php" style="color:#557996; text-decoration:none; font-weight:700;">
           <i class="fas fa-arrow-left"></i> VOLVER A CARTELERA
        </a>
    </div>
  </div>

  <footer class="main-footer">
      <div class="footer-container">
          <div class="footer-section">
              <h4>Contacto</h4>
              <ul>
                  <li><i class="fas fa-map-marker-alt"></i> Plaza del Ayuntamiento, 1<br>11650 Villamartín (Cádiz)</li>
                  <li>
                      <i class="fas fa-phone"></i> 
                      <?php 
                        
                        if (isset($res_config['contacto_telefono'])) {
                            echo htmlspecialchars($res_config['contacto_telefono']);
                        } else {
                            echo '956 73 00 00';
                        }
                      ?>
                  </li>
                  <li>
                      <i class="fas fa-envelope"></i> 
                      <?php 
                                   
                        if (isset($res_config['contacto_correo'])) {
                            echo htmlspecialchars($res_config['contacto_correo']);
                        } else {
                            echo 'teatro@villamartin.es';
                        }
                      ?>
                  </li>
              </ul>
          </div>
          <div class="footer-section">
              <h4>Horario de Taquilla</h4>
              <ul>
                  <?php if(!empty($res_config['horario_linea_1'])): ?><li><i class="far fa-clock"></i> <?php echo htmlspecialchars($res_config['horario_linea_1']); ?></li><?php endif; ?>
                  <?php if(!empty($res_config['horario_linea_2'])): ?><li><i class="far fa-clock"></i> <?php echo htmlspecialchars($res_config['horario_linea_2']); ?></li><?php endif; ?>
                  <?php if(!empty($res_config['horario_linea_3'])): ?><li><i class="far fa-clock"></i> <?php echo htmlspecialchars($res_config['horario_linea_3']); ?></li><?php endif; ?>
              </ul>
          </div>
          <div class="footer-section">
              <h4>Enlaces</h4>
              <ul>
                  <li><a href="#" style="color:#bdc3c7; text-decoration:none;">Aviso Legal</a></li>
                  <li><a href="#" style="color:#bdc3c7; text-decoration:none;">Política de Privacidad</a></li>
              </ul>
          </div>
      </div>
      <div class="footer-bottom">
          <p>© 2026 Excmo. Ayuntamiento de Villamartín. Todos los derechos reservados.</p>
      </div>
  </footer>

</body>
</html>