<?php
/**
 * ARCHIVO: reserva.php
 * DESCRIPCIÓN: Selección visual de butacas y formulario de datos del cliente.
 */
include 'includes/config.php';

if (isset($_GET['f'])) {
    $funcion_id = (int)$_GET['f'];
} else {
    // Si no viene ninguna función, redirigimos al inicio por seguridad
    header("Location: index.php");
    exit;
}

// --- 1. MANTENIMIENTO AUTOMÁTICO DEL TEATRO ---

// Limpieza de bloqueos temporales: los asientos que alguien empezó a comprar pero abandonó (caducidad de 10 min)
$pdo->exec("UPDATE asientos SET estado = 'libre', bloqueado_hasta = NULL WHERE estado = 'en_proceso' AND bloqueado_hasta < NOW()");

// Limpieza de reservas antiguas: cancelamos las que llevan 5 días 'pendiente' sin pagarse
$fecha_limite = date('Y-m-d H:i:s', strtotime('-5 days'));

try {
    // Iniciar transacción: o se liberan los asientos y se cancela la reserva, o no se hace nada
    $pdo->beginTransaction();

   // 1. Liberamos físicamente los asientos
    $sql_liberar = "UPDATE asientos SET estado = 'libre'
                    WHERE id IN (
                        SELECT ri.asiento_id
                        FROM reserva_items ri
                        JOIN reservas r ON ri.reserva_id = r.id
                        WHERE r.estado = 'pendiente'
                        AND (r.created_at < :limite1 OR r.fecha_reserva < :limite2)
                    )";
    
    $stmt_liberar = $pdo->prepare($sql_liberar);
    $stmt_liberar->execute([
        ':limite1' => $fecha_limite,
        ':limite2' => $fecha_limite
    ]);

    // 2. Marcamos la reserva como cancelada en el historial
    $sql_cancelar_res = "UPDATE reservas SET estado = 'cancelada'
                        WHERE estado = 'pendiente'
                        AND (created_at < :limite1 OR fecha_reserva < :limite2)";
    
    $stmt_cancelar = $pdo->prepare($sql_cancelar_res);
    $stmt_cancelar->execute([
        ':limite1' => $fecha_limite,
        ':limite2' => $fecha_limite
    ]);

    $pdo->commit();
    
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Error en limpieza de reservas: " . $e->getMessage());
}

// --- 2. CARGA DE DATOS DE LA FUNCIÓN ---

$sql_obra = "SELECT o.id, o.titulo, o.descripcion, o.imagen, o.duracion, f.fecha, f.hora,
             f.precio AS precio_funcion, o.precio AS precio_obra
             FROM funciones f
             JOIN obras o ON f.obra_id = o.id
             WHERE f.id = ?";

$stmt_obra = $pdo->prepare($sql_obra);
$stmt_obra->execute([$funcion_id]);
$info = $stmt_obra->fetch();

// Redirección si un listillo pone una ID inventada en la URL
if (!$info) {
    header("Location: index.php");
    exit;
}

// Lógica de precio híbrido: Si la función tiene precio propio, manda sobre el general
if (!empty($info['precio_funcion']) && $info['precio_funcion'] > 0) {
    $precio_final = $info['precio_funcion'];
} else {
    $precio_final = $info['precio_obra'];
}

// Carga de configuración global
$res_config = $pdo->query("SELECT clave, valor FROM configuracion")->fetchAll(PDO::FETCH_KEY_PAIR);

// Definición del límite de asientos con if-else clásico
if (isset($res_config['max_asientos_reserva'])) {
    $max_asientos = (int)$res_config['max_asientos_reserva'];
} else {
    $max_asientos = 4; // Por defecto
}

// --- 3. OPTIMIZACIÓN MAESTRA DE RENDIMIENTO ---
// Traemos TODOS los asientos de la función de golpe
$stmt_all = $pdo->prepare("SELECT id, codigo_asiento, estado, bloqueado_hasta FROM asientos WHERE funcion_id = ?");
$stmt_all->execute([$funcion_id]);

$asientos_bd = [];
while ($row = $stmt_all->fetch(PDO::FETCH_ASSOC)) {
    $asientos_bd[$row['codigo_asiento']] = $row; // Guardamos en memoria indexando por "F1A1"
}

include 'includes/header.php';
?>

  <div class="white-container">
    <div class="breadcrumbs">
        <a href="index.php" style="text-decoration:none; color:inherit;">INICIO</a>
        
        <span class="divider">▶</span>
        <a href="seleccion_hora.php?obra=<?php echo (int)$info["id"]; ?>" style="text-decoration:none; color:inherit;">
            <?php echo strtoupper(htmlspecialchars($info['titulo'])); ?>
        </a>
        
        <span class="divider">▶</span>
        <span class="current">RESERVA DE ENTRADAS</span>
    </div>

    <div class="info-obra-header">
        <img src="uploads/obras/<?php echo htmlspecialchars($info['imagen']); ?>" alt="Poster">
        <div class="info-obra-detalles">
            <h2><?php echo htmlspecialchars($info['titulo']); ?></h2>
            <div class="meta-pases">
                <span class="meta-item"><i class="far fa-calendar-alt"></i> <?php echo date('d/m/Y', strtotime($info['fecha'])); ?></span>
                <span class="meta-item"><i class="far fa-clock"></i> <?php echo date('H:i', strtotime($info['hora'])); ?>h</span>
                <span class="meta-item"><i class="fas fa-hourglass-half"></i> <?php echo (int)$info['duracion']; ?> min</span>
                <span class="meta-item"><i class="fas fa-tag"></i> <span id="precio-unitario"><?php echo number_format($precio_final, 2); ?></span>€</span>
            </div>
            <p style="color: #7f8c8d; font-size: 0.95rem; margin-top: 15px; line-height: 1.5;">
                <?php echo htmlspecialchars($info['descripcion']); ?>
            </p>
        </div>
    </div>

    <div class="booking-wrapper">
        <section class="mapa-teatro">

            <div class="escenario">ESCENARIO</div>
           
            <div class="asientos-container">
                <?php
                $config_filas = [1=>12, 2=>12, 3=>12, 4=>12, 5=>12, 6=>11, 7=>11, 8=>11, 9=>11, 10=>11, 11=>11, 12=>10, 13=>10, 14=>10, 15=>10, 16=>10, 17=>9];

                foreach ($config_filas as $num_fila => $total_asientos) {
                    echo "<div class='fila-wrapper'>";
                    echo "<div class='num-fila-label'>F$num_fila</div>";
                    echo "<div class='fila' data-fila='$num_fila'>";
                    
                    for ($asiento = 1; $asiento <= $total_asientos; $asiento++) {
                        $codigo = "F{$num_fila}A{$asiento}";
                        
                        // Lógica PMR pasada a if-else
                        if ($num_fila == 17 && ($asiento == 1 || $asiento == $total_asientos)) {
                            $clase_pmr = "minusvalido";
                        } else {
                            $clase_pmr = "";
                        }

                        // Buscamos en la memoria en lugar de machacar la base de datos
                        $clase_estado = "disponible";
                        $db_id = 0;

                        if (isset($asientos_bd[$codigo])) {
                            $datos = $asientos_bd[$codigo];
                            $db_id = (int)$datos['id'];

                            if ($datos['estado'] == 'comprado' || $datos['estado'] == 'reservado') {
                                $clase_estado = "reservado";
                            } elseif ($datos['estado'] == 'en_proceso' && strtotime($datos['bloqueado_hasta']) > time()) {
                                $clase_estado = "en_proceso";
                            }
                        }
                        
                        echo "<div class='asiento $clase_estado $clase_pmr' data-id='$db_id' data-codigo='$codigo' data-butaca='$asiento' title='Fila $num_fila, Asiento $asiento'>$asiento</div>";
                    }
                    echo "</div></div>";
                }
                ?>
            </div>

            <div class="leyenda-asientos" style="display: flex; justify-content: center; gap: 20px; margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 8px; border: 1px solid #eee;">
                <div style="display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: #444;">
                    <div style="width: 20px; height: 20px; background-color: #e74c3c; border-radius: 4px;"></div> Reservado
                </div>
                <div style="display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: #444;">
                    <div style="width: 20px; height: 20px; background-color: #2ecc71; border-radius: 4px;"></div> Libre
                </div>
                <div style="display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: #444;">
                    <div style="width: 20px; height: 20px; background-color: #f1c40f; border-radius: 4px;"></div> Seleccionado
                </div>
                <div style="display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: #444;">
                    <div style="width: 20px; height: 20px; background-color: #bdc3c7; border-radius: 4px;"></div> Bloqueado
                </div>
                <div style="display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: #444;">
                    <i class="fas fa-wheelchair" style="color: #3498db;"></i> PMR
                </div>
            </div>

        </section>

        <aside class="reserva-sidebar">
            <form action="confirmar.php" method="POST" class="reserva-form">
                <input type="hidden" name="funcion_id" value="<?php echo (int)$funcion_id; ?>">
                <input type="hidden" name="asientos_ids" id="asientos_input">
                
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <h3>Datos Personales</h3>
                <div class="form-group">
                    <label for="nombre">Nombre Completo</label>
                    <input type="text" id="nombre" name="nombre" placeholder="Nombre y apellidos" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="ejemplo@email.com" required>
                </div>
                <div class="form-group">
                    <label for="telefono">Teléfono</label>
                    <input type="tel" id="telefono" name="telefono" placeholder="600 000 000" required>
                </div>

                <div class="resumen-compra">
                    <div class="resumen-dinamico" id="caja-resumen" style="display:none;">
                        <p style="margin:0; font-size:0.9rem;">Asientos: <span id="lista-butacas"></span></p>
                        <span class="precio-total">Total: <span id="total-dinero">0</span>€</span>
                    </div>
                    <p style="margin-top:10px; font-size:0.9rem;">
                        Seleccionadas: <span id="contador-entradas">0</span> / <?php echo (int)$max_asientos; ?>
                    </p>
                </div>

                <button type="submit" class="btn-confirmar">CONFIRMAR RESERVA</button>
            </form>
        </aside>
    </div>
  </div>

 <?php include 'includes/footer.php'; ?>
 
<script>
    const LIMITE_RESERVA = <?php echo (int)$max_asientos; ?>;
</script>
<script src="assets/js/script.js"></script>
</body>
</html>
