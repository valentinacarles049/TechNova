<?php
/**
 * Test Suite Automatizado para HU-39:
 * Reporte de SLA Warning y Redistribución Rápida de Tickets
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . "/../db.php");
require_once(__DIR__ . "/../funcs/ticketLifecycle.php");
require_once(__DIR__ . "/../funcs/ticketFunc.php");

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
echo "EJECUTANDO SUITE DE PRUEBAS PARA HU-39 (SLA WARNING Y REDISTRIBUCIÓN)\n";
echo "======================================================================\n\n";

// --------------------------------------------------------------------
// TEST 1: Seguridad y Control de Acceso (RBAC)
// --------------------------------------------------------------------
echo "TEST 1: Seguridad y Control de Acceso (RBAC)\n";

// 1.1 Sin sesión
unset($_SESSION["currentUser"]);
unset($_SESSION["currentTeam"]);
assertTrue(verificarPermisoMonitoreoOSla() === false, "Sin sesión activa: permiso denegado.");

// 1.2 Rol Soporte
$_SESSION["currentUser"] = "agente_soporte";
$_SESSION["currentTeam"] = "soporte";
assertTrue(verificarPermisoMonitoreoOSla() === false, "Rol 'soporte': permiso denegado para módulo SLA.");

// 1.3 Rol Desarrollo
$_SESSION["currentUser"] = "dev_junior";
$_SESSION["currentTeam"] = "desarrollo";
assertTrue(verificarPermisoMonitoreoOSla() === false, "Rol 'desarrollo': permiso denegado para módulo SLA.");

// 1.4 Rol Monitoreo
$_SESSION["currentUser"] = "valeen";
$_SESSION["currentTeam"] = "monitoreo";
assertTrue(verificarPermisoMonitoreoOSla() === true, "Rol 'monitoreo': acceso concedido exitosamente.");

// 1.5 Rol Administrador
$_SESSION["currentUser"] = "admin";
$_SESSION["currentTeam"] = "administrador";
assertTrue(verificarPermisoMonitoreoOSla() === true, "Rol 'administrador': acceso concedido exitosamente.");

// 1.6 Rol Responsable SLA
$_SESSION["currentUser"] = "sla_lead";
$_SESSION["currentTeam"] = "responsable sla";
assertTrue(verificarPermisoMonitoreoOSla() === true, "Rol 'responsable sla': acceso concedido exitosamente.");

// --------------------------------------------------------------------
// TEST 2: Lógica de Negocio y Cálculo de SLA por Prioridad
// --------------------------------------------------------------------
echo "\nTEST 2: Lógica de Negocio y Cálculo de SLA por Prioridad\n";

assertTrue(obtenerHorasSlaPorPrioridad('critica') === 4, "SLA Prioridad Crítica es 4 horas.");
assertTrue(obtenerHorasSlaPorPrioridad('alta') === 24, "SLA Prioridad Alta es 24 horas.");
assertTrue(obtenerHorasSlaPorPrioridad('media') === 48, "SLA Prioridad Media es 48 horas.");
assertTrue(obtenerHorasSlaPorPrioridad('baja') === 96, "SLA Prioridad Baja es 96 horas.");

// Ticket En Tiempo (ej. creado hace 1 hora con prioridad baja de 96h)
$tktEnTiempo = [
    'prioridad' => 'baja',
    'estado' => 'En Progreso',
    'fecha_apertura' => date('Y-m-d H:i:s', time() - 3600),
    'fecha_cierre' => null
];
$slaOk = calcularSlaTicket($tktEnTiempo);
assertTrue($slaOk['estado_sla'] === 'en_tiempo', "Ticket con poco tiempo transcurrido está 'en_tiempo'.");
assertTrue($slaOk['badge_class'] === 'badge-sla-ok', "Badge de En Tiempo asignado correctamente.");

// Ticket en SLA Warning (ej. creado hace 3 horas con prioridad crítica de 4h -> 75% consumido)
$tktWarning = [
    'prioridad' => 'critica',
    'estado' => 'En Progreso',
    'fecha_apertura' => date('Y-m-d H:i:s', time() - (3 * 3600)),
    'fecha_cierre' => null
];
$slaWarn = calcularSlaTicket($tktWarning);
assertTrue($slaWarn['estado_sla'] === 'warning', "Ticket al 75% del SLA crítica califica como 'warning'.");
assertTrue($slaWarn['badge_class'] === 'badge-sla-warning', "Badge 'badge-sla-warning' asignado.");
assertTrue(strpos($slaWarn['etiqueta'], 'SLA Warning') !== false, "Etiqueta visible contiene 'SLA Warning'.");

// Ticket con SLA Incumplido / Vencido (ej. creado hace 6 horas con prioridad crítica de 4h -> 150% consumido)
$tktVencido = [
    'prioridad' => 'critica',
    'estado' => 'Asignado',
    'fecha_apertura' => date('Y-m-d H:i:s', time() - (6 * 3600)),
    'fecha_cierre' => null
];
$slaVenc = calcularSlaTicket($tktVencido);
assertTrue($slaVenc['estado_sla'] === 'vencido', "Ticket con tiempo excedido califica como 'vencido'.");
assertTrue($slaVenc['badge_class'] === 'badge-sla-vencido', "Badge 'badge-sla-vencido' asignado.");
assertTrue(strpos($slaVenc['etiqueta'], 'SLA Incumplido') !== false, "Etiqueta visible contiene 'SLA Incumplido'.");

// Resumen cuantitativo de métricas
$metricas = obtenerMetricasSla($conn);
assertTrue(isset($metricas['vencidos']), "Métricas contienen contador 'vencidos'.");
assertTrue(isset($metricas['warning']), "Métricas contienen contador 'warning'.");
assertTrue(isset($metricas['en_tiempo']), "Métricas contienen contador 'en_tiempo'.");
assertTrue(isset($metricas['total']), "Métricas contienen contador 'total'.");

// --------------------------------------------------------------------
// TEST 3: Mutación reasignarAgente(idAgente) y Continuidad Operativa
// --------------------------------------------------------------------
echo "\nTEST 3: Mutación reasignarAgente(idAgente) y Continuidad Operativa\n";

// Crear ticket para prueba de reasignación desde Nuevo
$insQuery = "INSERT INTO tickets (titulo, descripcion, categoria, prioridad, estado, usuario, asignado, fecha_apertura)
             VALUES ('TKT HU39 Prueba Reasignar', 'Verificación de redistribución rápida SLA', 'drivers', 'critica', 'Nuevo', 'gamer_pro', NULL, NOW())";
mysqli_query($conn, $insQuery);
$testId = mysqli_insert_id($conn);
assertTrue($testId > 0, "Ticket de prueba HU-39 creado con ID: #$testId.");

// 3.1 Reasignación desde Nuevo transiciona formalmente a Asignado
$ticketObj = new Ticket($testId, $conn);
$resultadoAsig = $ticketObj->reasignarAgente('support', 'Reasignación por proximidad de vencimiento SLA');
assertTrue($resultadoAsig === true, "reasignarAgente ejecutado con éxito.");
assertTrue($ticketObj->estado === TicketEstado::ASIGNADO, "Ticket pasa a estado 'Asignado'.");
assertTrue($ticketObj->asignado === 'support', "Agente asignado es 'support'.");

// 3.2 Reasignación en ticket en curso (En Progreso) mantiene estado y redistribuye
$ticketObj->iniciarAtencion();
assertTrue($ticketObj->estado === TicketEstado::EN_PROGRESO, "Ticket transiciona a 'En Progreso'.");

// Redistribución rápida a otro agente
$ticketObj->reasignarAgente('valen', 'Redistribución por carga operativa');
assertTrue($ticketObj->estado === TicketEstado::EN_PROGRESO, "Ticket permanece en 'En Progreso' tras redistribución.");
assertTrue($ticketObj->asignado === 'valen', "Nuevo agente asignado es 'valen'.");

// 3.3 Auditoría en historial_tickets
$qHist = mysqli_query($conn, "SELECT tipo_cambio FROM historial_tickets WHERE ticket_id = $testId ORDER BY id DESC LIMIT 1");
$rHist = mysqli_fetch_assoc($qHist);
assertTrue(strpos($rHist['tipo_cambio'], 'HU-39 Redistribución SLA') !== false, "Historial de ticket registra traza de auditoría HU-39.");

// 3.4 Función procedural reasignarAgente()
$resProc = reasignarAgente($conn, $testId, 'support', 'Reasignación procedural');
assertTrue($resProc === true, "Función procedural reasignarAgente funciona correctamente.");

// 3.5 Rechazo de agente vacío
assertThrows(function() use ($ticketObj) {
    $ticketObj->reasignarAgente('');
}, TransicionTicketException::class, "Rechaza reasignarAgente con agente vacío.");

// 3.6 Rechazo de reasignación en tickets cerrados
$ticketObj->resolverTicket("Solución técnica aplicada correctamente para cierre.");
$ticketObj->cerrarTicket();
assertTrue($ticketObj->estado === TicketEstado::CERRADO, "Ticket cerrado.");

assertThrows(function() use ($ticketObj) {
    $ticketObj->reasignarAgente('support');
}, TransicionTicketException::class, "Rechaza reasignarAgente en ticket cerrado.");

// --------------------------------------------------------------------
// TEST 4: Verificación de UI y Componentes según RBAC
// --------------------------------------------------------------------
echo "\nTEST 4: Verificación de UI y Componentes según RBAC\n";

// 4.1 Renderizado con rol MONITOREO
$helperRender = __DIR__ . "/helper_render_role.php";
$htmlMonitoreo = shell_exec("C:\\xampp\\php\\php.exe \"$helperRender\" valeen monitoreo 4");

assertTrue(strpos($htmlMonitoreo, 'id="sla-summary-dashboard"') !== false, "Monitoreo: Dashboard de Resumen Cuantitativo de SLA visible.");
assertTrue(strpos($htmlMonitoreo, 'cnt-sla-vencidos') !== false, "Monitoreo: Contador de SLA Vencidos presente.");
assertTrue(strpos($htmlMonitoreo, 'cnt-sla-warning') !== false, "Monitoreo: Contador de SLA Warning presente.");
assertTrue(strpos($htmlMonitoreo, 'cnt-sla-tiempo') !== false, "Monitoreo: Contador de En Tiempo presente.");
assertTrue(strpos($htmlMonitoreo, 'name="slaBuscar"') !== false, "Monitoreo: Selector de filtro rápido de SLA presente.");
assertTrue(strpos($htmlMonitoreo, '<option value="warning"') !== false, "Monitoreo: Opción filtro SLA Warning presente.");
assertTrue(strpos($htmlMonitoreo, '<option value="vencido"') !== false, "Monitoreo: Opción filtro SLA Vencidos presente.");
assertTrue(strpos($htmlMonitoreo, '>SLA</th>') !== false, "Monitoreo: Columna de SLA presente en la tabla.");
assertTrue(strpos($htmlMonitoreo, 'invocarReasignarAgente') !== false, "Monitoreo: Función JS de reasignación rápida presente.");

// 4.2 Renderizado con rol SOPORTE
$htmlSoporte = shell_exec("C:\\xampp\\php\\php.exe \"$helperRender\" support soporte 2");

assertTrue(strpos($htmlSoporte, 'id="sla-summary-dashboard"') === false, "Soporte: Dashboard de Resumen SLA OCULTO.");
assertTrue(strpos($htmlSoporte, 'name="slaBuscar"') === false, "Soporte: Filtro rápido de SLA OCULTO.");
assertTrue(strpos($htmlSoporte, '>SLA</th>') === false, "Soporte: Columna de SLA en tabla OCULTA.");
assertTrue(strpos($htmlSoporte, 'btn-reasignar-inline') === false, "Soporte: Botón de reasignación inline en grilla OCULTO.");

// --------------------------------------------------------------------
// TEST 5: Protección de API api_sla.php
// --------------------------------------------------------------------
echo "\nTEST 5: Protección de API api_sla.php\n";

$helperApi = __DIR__ . "/helper_api_call.php";

// 5.1 Sin sesión -> 401
$outNoAuth = shell_exec("C:\\xampp\\php\\php.exe \"$helperApi\" __NO_AUTH__ none counters");
$resNoAuth = json_decode($outNoAuth, true);
assertTrue(isset($resNoAuth['success']) && $resNoAuth['success'] === false, "API sin autenticación responde success=false.");
assertTrue(strpos($resNoAuth['error'] ?? '', 'No autenticado') !== false, "Mensaje de error indica requerimiento de autenticación.");

// 5.2 Con rol Soporte -> 403
$outSoporte = shell_exec("C:\\xampp\\php\\php.exe \"$helperApi\" support soporte counters");
$resSoporte = json_decode($outSoporte, true);
assertTrue(isset($resSoporte['success']) && $resSoporte['success'] === false, "API con rol Soporte responde success=false.");
assertTrue(strpos($resSoporte['error'] ?? '', 'Acceso denegado') !== false, "Mensaje de error indica restricción por rol RBAC.");

// 5.3 Con rol Monitoreo -> 200 y contadores
$outMonitoreo = shell_exec("C:\\xampp\\php\\php.exe \"$helperApi\" valeen monitoreo counters");
$resMonitoreo = json_decode($outMonitoreo, true);
assertTrue(isset($resMonitoreo['success']) && $resMonitoreo['success'] === true, "API con rol Monitoreo responde success=true.");
assertTrue(isset($resMonitoreo['data']['vencidos']), "API devuelve contadores cuantitativos en tiempo real.");

// 5.4 Mutación reasignarAgente vía API con rol Monitoreo
$qApiTkt = mysqli_query($conn, "INSERT INTO tickets (titulo, descripcion, categoria, prioridad, estado, usuario, asignado, fecha_apertura) VALUES ('TKT API Test', 'Prueba mutacion API', 'drivers', 'alta', 'Asignado', 'gamer_pro', 'valen', NOW())");
$apiTktId = mysqli_insert_id($conn);

$payloadArray = [
    'ticket_id' => $apiTktId,
    'idAgente' => 'support',
    'motivo' => 'Redistribución preventiva vía API'
];
$base64Payload = base64_encode(json_encode($payloadArray));

$outMutacion = shell_exec("C:\\xampp\\php\\php.exe \"$helperApi\" valeen monitoreo reasignar \"$base64Payload\"");
$resMutacion = json_decode($outMutacion, true);

assertTrue(isset($resMutacion['success']) && $resMutacion['success'] === true, "Mutación exitosa devuelta en JSON.");
assertTrue(($resMutacion['nuevo_agente'] ?? '') === 'support', "Agente reasignado correctamente vía API.");

// Limpieza de datos de prueba
mysqli_query($conn, "DELETE FROM historial_tickets WHERE ticket_id IN ($testId, $apiTktId)");
mysqli_query($conn, "DELETE FROM tickets WHERE id IN ($testId, $apiTktId)");

echo "\n======================================================================\n";
echo "RESULTADOS DE LA SUITE HU-39:\n";
echo "Total de Aserciones: $totalAsserts\n";
echo "Aserciones Exitosas: $passedAsserts\n";
echo "Aserciones Fallidas: $failedAsserts\n";
echo "======================================================================\n";

if ($failedAsserts === 0) {
    echo "¡TODAS LAS PRUEBAS DE HU-39 PASARON EXITOSAMENTE (100% OK)!\n";
    exit(0);
} else {
    echo "FALLARON $failedAsserts PRUEBAS.\n";
    exit(1);
}
