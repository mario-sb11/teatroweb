/**
 * ARCHIVO: assets/js/script.js
 * DESCRIPCIÓN: Lógica interactiva para la selección de butacas y bloqueo temporal.
 */

// Listado local para controlar los IDs de los asientos que el usuario va seleccionando
let seleccionados = [];
// Variable de control para evitar peticiones simultáneas (Evita que la página se ralle)
let procesando = false;

// Captura de elementos de la interfaz para actualizar el formulario y el contador visual
const inputAsientos = document.getElementById('asientos_input');
const contador = document.getElementById('contador-entradas');

// Función principal para gestionar la selección de butacas y el bloqueo en tiempo real
async function toggleAsiento(asiento) {
    // Si ya estamos enviando una petición, ignoramos los clics extra
    if (procesando) return;

    const dbId = asiento.dataset.id;

    // Se cancela la acción si el asiento está ocupado o bloqueado por otro usuario
    if (asiento.classList.contains('reservado') || 
       (asiento.classList.contains('en_proceso') && !asiento.classList.contains('seleccionado'))) {
        return; 
    }

    const esSeleccionado = asiento.classList.contains('seleccionado');
    const accion = esSeleccionado ? 'liberar' : 'bloquear';
    const maxPermitido = (typeof LIMITE_RESERVA !== 'undefined') ? LIMITE_RESERVA : 4;

    if (accion === 'bloquear' && seleccionados.length >= maxPermitido) {
        alert("Máximo " + maxPermitido + " asientos por reserva.");
        return;
    }

    // ACTIVAMOS EL BLOQUEO: A partir de aquí, ningún clic funcionará hasta terminar
    procesando = true;
    asiento.style.opacity = "0.4"; // Feedback visual de que "está cargando"

    try {
        // Sincronización con el servidor
        const res = await fetch('includes/bloquear_temporal.php', {
            method: 'POST',
            body: JSON.stringify({ id: dbId, accion: accion }),
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await res.json();

        if (data.success || accion === 'liberar') {
            asiento.classList.toggle('seleccionado');
            
            if (accion === 'bloquear') {
                seleccionados.push(dbId);
            } else {
                seleccionados = seleccionados.filter(id => id !== dbId);
            }
            
            // Actualizamos el dinero y el resumen
            actualizarResumenDinamico();

        } else {
            // Si el servidor dice que no, avisamos y refrescamos el mapa
            alert("Asiento no disponible");
            location.reload();
        }
    } catch (error) {
        console.error("Error de conexión:", error);
    } finally {
        // LIBERAMOS EL BLOQUEO: Ya se puede volver a clicar
        procesando = false;
        asiento.style.opacity = "1";
    }

    // Reflejamos los cambios en el contador
    contador.innerText = seleccionados.length;
    inputAsientos.value = seleccionados.join(',');
}

/**
 * Función para refrescar el resumen de compra y calcular el total de dinero.
 */
function actualizarResumenDinamico() {
    const cajaResumen = document.getElementById('caja-resumen');
    const listaButacas = document.getElementById('lista-butacas');
    const totalDinero = document.getElementById('total-dinero');
    const precioUnitario = parseFloat(document.getElementById('precio-unitario').innerText);
    
    const asientosMarcados = document.querySelectorAll('.asiento.seleccionado');

    if (asientosMarcados.length > 0) {
        cajaResumen.style.display = 'block';
        let textos = [];
        asientosMarcados.forEach(as => {
            let fila = as.parentElement.getAttribute('data-fila');
            let butaca = as.getAttribute('data-butaca');
            textos.push(`F${fila}-A${butaca}`);
        });
        listaButacas.innerText = textos.join(', ');
        totalDinero.innerText = (asientosMarcados.length * precioUnitario).toFixed(2);
    } else {
        cajaResumen.style.display = 'none';
    }
}

// Inicialización
document.querySelectorAll('.asiento.disponible').forEach(asiento => {
    asiento.addEventListener('click', () => toggleAsiento(asiento));
});