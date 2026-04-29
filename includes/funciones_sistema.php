<?php
// Este script contiene la lógica para "poblar" el teatro
function prepararMapaAsientos($funcion_id, $pdo) {
    // Configuración de filas real
    $config_filas = [
        1=>12, 2=>12, 3=>12, 4=>12, 5=>12,
        6=>11, 7=>11, 8=>11, 9=>11, 10=>11, 11=>11,
        12=>10, 13=>10, 14=>10, 15=>10, 16=>10,
        17=>9
    ];

    $sql = "INSERT INTO asientos (funcion_id, codigo_asiento, tipo, estado) VALUES (?, ?, ?, 'libre')";
    $stmt = $pdo->prepare($sql);

    foreach ($config_filas as $num_fila => $total_asientos) {
        for ($asiento = 1; $asiento <= $total_asientos; $asiento++) {
            $codigo = "F{$num_fila}A{$asiento}";
            // Regla de PMR: Fila 17, extremos
            $tipo = ($num_fila == 17 && ($asiento == 1 || $asiento == $total_asientos)) ? 'pmr' : 'normal';
            
            $stmt->execute([$funcion_id, $codigo, $tipo]);
        }
    }
}
?>