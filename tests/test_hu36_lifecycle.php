<?php
/**
 * Test Suite Automatizado para HU-36:
 * Reingeniería del Ciclo de Vida y Transiciones de Estados del Ticket
 */

require_once(__DIR__ . "/../db.php");
require_once(__DIR__ . "/../funcs/ticketLifecycle.php");

$totalAsserts = 0;
$passedAsserts = 0;
$failedAsserts = 0;

function assertTrue($condition, $message) {
    global $totalAsserts, $passedAsserts, $failedAsserts;
    $totalAsserts++;
    if ($condition) {
        $passedAsserts++;
        echo "  [PASS] $message\n";
    } else {
        $failedAsserts++;
        echo "  [FAIL] $message\n";
    }
}

function assertThrows($callable, $expectedExceptionClass, $message) {
    global $totalAsserts, $passedAsserts, $failedAsserts;
    $totalAsserts++;
    try {
        $callable();
        $failedAsserts++;
        echo "  [FAIL] $message (Expected exception $expectedExceptionClass was NOT thrown)\n";
    } catch (Exception $e) {
        if ($e instanceof $expectedExceptionClass) {
            $passedAsserts++;
            echo "  [PASS] $message (Caught " . get_class($e) . ": " . $e->getMessage() . ")\n";
        } else {
            $failedAsserts++;
            echo "  [FAIL] $message (Caught unexpected exception " . get_class($e) . ": " . $e->getMessage() . ")\n";
        }
    }
}

echo "======================================================================\n";
echo "EJECUTANDO SUITE DE PRUEBAS PARA HU-36 (CICLO DE VIDA DE TICKETS)\n";
echo "======================================================================\n\n";

// --------------------------------------------------------------------
// TEST 1: Criterio 1 - 7 Estados Oficiales Únicamente
// --------------------------------------------------------------------
echo "TEST 1: Criterio 1 - Verificación de los 7 Estados Oficiales Únicos\n";
$estadosOficiales = TicketEstado::todos();
assertTrue(count($estadosOficiales) === 7, "El sistema contempla exactamente 7 estados oficiales.");
assertTrue(in_array('Nuevo', $estadosOficiales, true), "Estado 'Nuevo' está presente.");
assertTrue(in_array('Asignado', $estadosOficiales, true), "Estado 'Asignado' está presente.");
assertTrue(in_array('En Progreso', $estadosOficiales, true), "Estado 'En Progreso' está presente.");
assertTrue(in_array('En Espera', $estadosOficiales, true), "Estado 'En Espera' está presente.");
assertTrue(in_array('Resuelto', $estadosOficiales, true), "Estado 'Resuelto' está presente.");
assertTrue(in_array('Cerrado', $estadosOficiales, true), "Estado 'Cerrado' está presente.");
assertTrue(in_array('Cancelado', $estadosOficiales, true), "Estado 'Cancelado' está presente.");

assertTrue(TicketEstado::esValido('Nuevo'), "'Nuevo' es un estado válido.");
assertTrue(TicketEstado::esValido('En Progreso'), "'En Progreso' es un estado válido.");
assertTrue(!TicketEstado::esValido('Pendiente'), "'Pendiente' NO es un estado oficial.");
assertTrue(!TicketEstado::esValido('Rechazado'), "'Rechazado' NO es un estado oficial.");

assertThrows(function() {
    TicketStateMachine::validarTransicion('Nuevo', 'EstadoInvalido');
}, TransicionTicketException::class, "Rechaza transición hacia estado no oficial.");

echo "\n";

// --------------------------------------------------------------------
// TEST 2: Criterio 2 - Desde 'Nuevo', solo asignarTicket o cancelarTicket
// --------------------------------------------------------------------
echo "TEST 2: Criterio 2 - Transiciones permitidas y bloqueadas desde 'Nuevo'\n";

// Crear un ticket de prueba en estado 'Nuevo'
mysqli_query($conn, "INSERT INTO tickets (titulo, descripcion, categoria, prioridad, estado, usuario) 
                     VALUES ('Test Ticket HU36', 'Prueba automatizada de ciclo de vida', 'General', 'media', 'Nuevo', 'tester')");
$testTicketId = mysqli_insert_id($conn);
$ticket = new Ticket($testTicketId, $conn);

assertTrue($ticket->estado === TicketEstado::NUEVO, "Ticket de prueba creado en estado 'Nuevo'.");

// Bloquear transición ilegal desde Nuevo (ej. a En Progreso)
assertThrows(function() use ($ticket) {
    $ticket->iniciarAtencion();
}, TransicionTicketException::class, "Bloquea iniciarAtencion directamente desde 'Nuevo'.");

// Bloquear transición ilegal a Resuelto
assertThrows(function() use ($ticket) {
    $ticket->resolverTicket("Solución anticipada");
}, TransicionTicketException::class, "Bloquea resolverTicket directamente desde 'Nuevo'.");

// Bloquear transición ilegal a Cerrado
assertThrows(function() use ($ticket) {
    $ticket->cerrarTicket();
}, TransicionTicketException::class, "Bloquea cerrarTicket directamente desde 'Nuevo'.");

// Ejecutar acción permitida 1: asignarTicket(idAgente)
$ticket->asignarTicket('agente_qa');
assertTrue($ticket->estado === TicketEstado::ASIGNADO, "asignarTicket transiciona de 'Nuevo' a 'Asignado'.");
assertTrue($ticket->asignado === 'agente_qa', "Agente asignado queda registrado correctamente.");

// Probar acción permitida 2: cancelarTicket desde Nuevo
mysqli_query($conn, "INSERT INTO tickets (titulo, descripcion, categoria, prioridad, estado, usuario) 
                     VALUES ('Test Cancel Ticket', 'Para probar cancelación', 'General', 'baja', 'Nuevo', 'tester')");
$cancelId = mysqli_insert_id($conn);
$cancelTicket = new Ticket($cancelId, $conn);
$cancelTicket->cancelarTicket("Ticket duplicado por error");
assertTrue($cancelTicket->estado === TicketEstado::CANCELADO, "cancelarTicket transiciona de 'Nuevo' a 'Cancelado'.");

// Ticket Cancelado es terminal: no admite cambios
assertThrows(function() use ($cancelTicket) {
    $cancelTicket->asignarTicket('soporte');
}, TransicionTicketException::class, "Un ticket Cancelado no admite transiciones.");

echo "\n";

// --------------------------------------------------------------------
// TEST 3: Criterio 3 - iniciarAtencion() transiciona de 'Asignado' a 'En Progreso'
// --------------------------------------------------------------------
echo "TEST 3: Criterio 3 - iniciarAtencion() de 'Asignado' a 'En Progreso'\n";

assertTrue($ticket->estado === TicketEstado::ASIGNADO, "Ticket está en estado 'Asignado'.");

// Intentar cerrar directamente desde Asignado (debe fallar)
assertThrows(function() use ($ticket) {
    $ticket->cerrarTicket();
}, TransicionTicketException::class, "Bloquea cerrarTicket desde 'Asignado'.");

// Ejecutar iniciarAtencion()
$ticket->iniciarAtencion();
assertTrue($ticket->estado === TicketEstado::EN_PROGRESO, "iniciarAtencion() transiciona exitosamente a 'En Progreso'.");

echo "\n";

// --------------------------------------------------------------------
// TEST 4: Criterio 4 - solicitarDatosUsuario() y reactivación automática por respuesta
// --------------------------------------------------------------------
echo "TEST 4: Criterio 4 - solicitarDatosUsuario() y reactivación automática\n";

assertTrue($ticket->estado === TicketEstado::EN_PROGRESO, "Ticket está en estado 'En Progreso'.");

// Ejecutar solicitarDatosUsuario()
$ticket->solicitarDatosUsuario("Por favor adjunta captura del mensaje de error.");
assertTrue($ticket->estado === TicketEstado::EN_ESPERA, "solicitarDatosUsuario() transiciona a 'En Espera'.");

// Intentar resolver desde En Espera (debe fallar, debe reactivarse primero)
assertThrows(function() use ($ticket) {
    $ticket->resolverTicket("Solución técnica");
}, TransicionTicketException::class, "Bloquea resolverTicket mientras está 'En Espera'.");

// Recepción de respuesta del usuario reactiva automáticamente a 'En Progreso'
$ticket->recibirRespuestaUsuario("Adjunto captura del error 404.");
assertTrue($ticket->estado === TicketEstado::EN_PROGRESO, "Recepción de respuesta reactiva automáticamente a 'En Progreso'.");

echo "\n";

// --------------------------------------------------------------------
// TEST 5: Criterio 5 - Obligatoriedad de respuesta_resolucion para pasar a 'Resuelto'
// --------------------------------------------------------------------
echo "TEST 5: Criterio 5 - Obligatoriedad de respuesta_resolucion para 'Resuelto'\n";

assertTrue($ticket->estado === TicketEstado::EN_PROGRESO, "Ticket está en 'En Progreso'.");

// Intentar pasar a Resuelto con respuesta vacía (debe fallar)
assertThrows(function() use ($ticket) {
    $ticket->resolverTicket("");
}, TransicionTicketException::class, "Rechaza resolverTicket con respuesta_resolucion vacía.");

assertThrows(function() use ($ticket) {
    $ticket->resolverTicket("    ");
}, TransicionTicketException::class, "Rechaza resolverTicket con respuesta_resolucion de puros espacios.");

// Ejecutar con respuesta de resolución técnica válida
$dictamen = "Se ajustaron los parámetros de timeout en el archivo de configuración y se comprobó la conectividad.";
$ticket->resolverTicket($dictamen);
assertTrue($ticket->estado === TicketEstado::RESUELTO, "Transiciona exitosamente a 'Resuelto'.");
assertTrue($ticket->respuesta_resolucion === $dictamen, "respuesta_resolucion guardada en base de datos.");

echo "\n";

// --------------------------------------------------------------------
// TEST 6: Criterio 6 - Transiciones desde 'Resuelto': Cerrar o Reabrir por Inconformidad
// --------------------------------------------------------------------
echo "TEST 6: Criterio 6 - Solo Cerrado o Reabrir por Inconformidad desde 'Resuelto'\n";

assertTrue($ticket->estado === TicketEstado::RESUELTO, "Ticket está en 'Resuelto'.");

// Bloquear volver a Nuevo
assertThrows(function() use ($ticket) {
    TicketStateMachine::validarTransicion(TicketEstado::RESUELTO, TicketEstado::NUEVO);
}, TransicionTicketException::class, "Bloquea transicionar de 'Resuelto' a 'Nuevo'.");

// Bloquear volver a En Espera
assertThrows(function() use ($ticket) {
    $ticket->solicitarDatosUsuario("Datos");
}, TransicionTicketException::class, "Bloquea solicitarDatosUsuario desde 'Resuelto'.");

// Probar reabrirPorInconformidad() -> Retorna a En Progreso
$ticket->reabrirPorInconformidad("El cliente indica que el error sigue ocurriendo en modo pantalla completa.");
assertTrue($ticket->estado === TicketEstado::EN_PROGRESO, "reabrirPorInconformidad() retorna el ticket a 'En Progreso'.");

// Volver a resolverlo
$ticket->resolverTicket("Parche adicional aplicado para pantalla completa.");
assertTrue($ticket->estado === TicketEstado::RESUELTO, "Ticket vuelve a 'Resuelto'.");

// Probar cerrarTicket() -> Pasa a Cerrado (confirmado/archivado)
$ticket->cerrarTicket();
assertTrue($ticket->estado === TicketEstado::CERRADO, "cerrarTicket() transiciona de 'Resuelto' a 'Cerrado'.");
assertTrue(!empty($ticket->fecha_cierre), "fecha_cierre fue registrada al cerrar el ticket.");

// Verificar que Cerrado es terminal
assertThrows(function() use ($ticket) {
    $ticket->iniciarAtencion();
}, TransicionTicketException::class, "Un ticket Cerrado no puede reabrirse directamente ni cambiar estado.");

// --------------------------------------------------------------------
// TEST 7: Funciones Procedurales Globales
// --------------------------------------------------------------------
echo "\nTEST 7: Verificación de Funciones Procedurales Globales\n";
mysqli_query($conn, "INSERT INTO tickets (titulo, descripcion, categoria, prioridad, estado, usuario) 
                     VALUES ('Procedural Test', 'Test de funciones globales', 'Drivers', 'alta', 'Nuevo', 'testuser')");
$procId = mysqli_insert_id($conn);

assertTrue(asignarTicket($procId, 'support_agent_2'), "Función procedural asignarTicket(\$id, \$agente) funciona.");
assertTrue(iniciarAtencion($procId), "Función procedural iniciarAtencion(\$id) funciona.");
assertTrue(solicitarDatosUsuario($procId, "Enviar logs"), "Función procedural solicitarDatosUsuario(\$id) funciona.");
assertTrue(recibirRespuestaUsuario($procId, "Logs adjuntos"), "Función procedural recibirRespuestaUsuario(\$id) funciona.");
assertTrue(resolverTicket($procId, "Actualización de driver aplicada exitosamente"), "Función procedural resolverTicket(\$id, \$res) funciona.");
assertTrue(reabrirPorInconformidad($procId, "No funcionó"), "Función procedural reabrirPorInconformidad(\$id) funciona.");
assertTrue(resolverTicket($procId, "Segundo parche aplicado"), "Volver a resolver con función procedural.");
assertTrue(cerrarTicket($procId), "Función procedural cerrarTicket(\$id) funciona.");

// Limpieza de tickets temporales creados en tests
mysqli_query($conn, "DELETE FROM historial_tickets WHERE ticket_id IN ($testTicketId, $cancelId, $procId)");
mysqli_query($conn, "DELETE FROM feedbacks WHERE ticket_id IN ($testTicketId, $cancelId, $procId)");
mysqli_query($conn, "DELETE FROM notificaciones WHERE enlace LIKE '%$testTicketId%' OR enlace LIKE '%$procId%'");
mysqli_query($conn, "DELETE FROM tickets WHERE id IN ($testTicketId, $cancelId, $procId)");

echo "\n======================================================================\n";
echo "RESULTADOS DE LA SUITE:\n";
echo "Total de Aserciones: $totalAsserts\n";
echo "Aserciones Exitosas: $passedAsserts\n";
echo "Aserciones Fallidas: $failedAsserts\n";
echo "======================================================================\n";

if ($failedAsserts === 0) {
    echo "¡TODAS LAS PRUEBAS DE HU-36 PASARON EXITOSAMENTE (100% OK)!\n";
    exit(0);
} else {
    echo "HAY PRUEBAS FALLIDAS EN HU-36.\n";
    exit(1);
}
