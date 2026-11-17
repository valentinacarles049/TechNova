<?php
/**
 * Test Suite de Clasificación Automática de Incidencias Técnicas y Selectores Opcionales
 */
session_start();
$_SESSION["currentUser"] = "support";
$_SESSION["currentTeam"] = "soporte";
$_SESSION["userRole"] = "2";

function redirigirTicket($url = "ticketManegement.php") {
    // Evitar exit() durante ejecución de pruebas
}

require_once(__DIR__ . "/../db.php");
require_once(__DIR__ . "/../funcs/ticketFunc.php");

echo "======================================================================\n";
echo "PRUEBAS: SELECTORES DIRECTOS/OPCIONALES Y CLASIFICACIÓN DE INCIDENCIAS\n";
echo "======================================================================\n\n";

$totalAsserts = 0;
$passedAsserts = 0;

function assertTest(bool $condition, string $message) {
    global $totalAsserts, $passedAsserts;
    $totalAsserts++;
    if ($condition) {
        $passedAsserts++;
        echo "  [PASS] $message\n";
    } else {
        echo "  [FAIL] $message\n";
    }
}

// --------------------------------------------------------------------
// TEST 1: Criterio 1 y 2 - Verificación del Formulario Modal (UI)
// --------------------------------------------------------------------
echo "TEST 1: Criterio 1 y 2 - Ausencia de checkbox y presencia de selectores opcionales\n";

ob_start();
createTicket(null); // Renderizar modal de creación
$modalHtml = ob_get_clean();

// Criterio 1: Eliminar checkbox manual es_incidencia
assertTest(strpos($modalHtml, 'id="es_incidencia"') === false, "Checkbox con id='es_incidencia' NO existe en el formulario.");
assertTest(strpos($modalHtml, 'name="es_incidencia"') === false, "Input con name='es_incidencia' NO existe en el formulario.");
assertTest(strpos($modalHtml, 'toggleTicketIncidenciaFields') === false, "Función JS toggleTicketIncidenciaFields NO existe.");

// Criterio 2: Selectores de Juego y Driver permanentemente habilitados y opcionales
assertTest(strpos($modalHtml, 'name="juego_id"') !== false, "Selector de juego (juego_id) está presente.");
assertTest(strpos($modalHtml, 'name="driver_id"') !== false, "Selector de driver (driver_id) está presente.");
assertTest(strpos($modalHtml, '<option value="">Selecciona un juego (Opcional)</option>') !== false, "Selector de juego indica explícitamente opción vacía.");
assertTest(strpos($modalHtml, '<option value="">Selecciona un driver (Opcional)</option>') !== false, "Selector de driver indica explícitamente opción vacía.");

// Verificar que en soporte/admin no tengan disabled ni required
preg_match('/<select[^>]*name="juego_id"[^>]*>/', $modalHtml, $matchJuego);
$juegoTag = $matchJuego[0] ?? '';
assertTest(strpos($juegoTag, 'disabled') === false, "Selector de juego NO tiene atributo disabled.");
assertTest(strpos($juegoTag, 'required') === false, "Selector de juego NO tiene atributo required (es opcional).");

preg_match('/<select[^>]*name="driver_id"[^>]*>/', $modalHtml, $matchDriver);
$driverTag = $matchDriver[0] ?? '';
assertTest(strpos($driverTag, 'disabled') === false, "Selector de driver NO tiene atributo disabled.");
assertTest(strpos($driverTag, 'required') === false, "Selector de driver NO tiene atributo required (es opcional).");

echo "\n";

// --------------------------------------------------------------------
// TEST 2: Función helper esCategoriaTecnica
// --------------------------------------------------------------------
echo "TEST 2: Validación de esCategoriaTecnica\n";
assertTest(esCategoriaTecnica("Bug en texturas") === true, "Detecta 'Bug en texturas' como técnico.");
assertTest(esCategoriaTecnica("Falla técnica de red") === true, "Detecta 'Falla técnica de red' como técnico.");
assertTest(esCategoriaTecnica("Error 0x8000") === true, "Detecta 'Error 0x8000' como técnico.");
assertTest(esCategoriaTecnica("Crash en mapa") === true, "Detecta 'Crash en mapa' como técnico.");
assertTest(esCategoriaTecnica("Drivers") === true, "Detecta 'Drivers' como técnico.");
assertTest(esCategoriaTecnica("Gráficos") === true, "Detecta 'Gráficos' como técnico.");
assertTest(esCategoriaTecnica("Cuentas") === false, "Detecta 'Cuentas' como NO técnico.");
assertTest(esCategoriaTecnica("Facturación y Pagos") === false, "Detecta 'Facturación y Pagos' como NO técnico.");
assertTest(esCategoriaTecnica("Consulta General") === false, "Detecta 'Consulta General' como NO técnico.");

echo "\n";

// --------------------------------------------------------------------
// TEST 3: Criterio 3 y 4 - Persistencia y Clasificación en Base de Datos
// --------------------------------------------------------------------
echo "TEST 3: Criterio 3 y 4 - Pruebas de Registro y Persistencia en Base de Datos\n";

// Obtener un juego y driver válidos para las pruebas
$qJuego = mysqli_query($conn, "SELECT id FROM juegos LIMIT 1");
$juegoRow = mysqli_fetch_assoc($qJuego);
$testJuegoId = $juegoRow ? intval($juegoRow['id']) : 1;

$qDriver = mysqli_query($conn, "SELECT id FROM drivers LIMIT 1");
$driverRow = mysqli_fetch_assoc($qDriver);
$testDriverId = $driverRow ? intval($driverRow['id']) : 1;

// Caso A: Solo Juego seleccionado, categoría no técnica -> es_incidencia = 1
$_POST = [
    "crear_ticket" => "1",
    "titulo" => "Test Case A - Solo Juego",
    "descripcion" => "Ticket con juego asignado pero categoría administrativa",
    "categoria" => "Cuentas",
    "prioridad" => "media",
    "usuario" => "gamer_pro",
    "juego_id" => strval($testJuegoId),
    "driver_id" => ""
];
ob_start();
include(__DIR__ . "/../funcs/ticketFunc.php");
ob_end_clean();

$qA = mysqli_query($conn, "SELECT * FROM tickets WHERE titulo = 'Test Case A - Solo Juego' ORDER BY id DESC LIMIT 1");
$ticketA = mysqli_fetch_assoc($qA);
assertTest($ticketA !== null, "Ticket A fue creado en la base de datos.");
assertTest(intval($ticketA['es_incidencia']) === 1, "Ticket A con solo juego se clasifica como es_incidencia = 1.");
assertTest(intval($ticketA['juego_id']) === $testJuegoId, "Ticket A guardó el juego_id correctamente.");
assertTest($ticketA['driver_id'] === null, "Ticket A guardó driver_id como NULL.");

// Caso B: Solo Driver seleccionado, categoría no técnica -> es_incidencia = 1
$_POST = [
    "crear_ticket" => "1",
    "titulo" => "Test Case B - Solo Driver",
    "descripcion" => "Ticket con driver asignado pero categoría administrativa",
    "categoria" => "Consulta General",
    "prioridad" => "baja",
    "usuario" => "gamer_pro",
    "juego_id" => "",
    "driver_id" => strval($testDriverId)
];
ob_start();
include(__DIR__ . "/../funcs/ticketFunc.php");
ob_end_clean();

$qB = mysqli_query($conn, "SELECT * FROM tickets WHERE titulo = 'Test Case B - Solo Driver' ORDER BY id DESC LIMIT 1");
$ticketB = mysqli_fetch_assoc($qB);
assertTest($ticketB !== null, "Ticket B fue creado en la base de datos.");
assertTest(intval($ticketB['es_incidencia']) === 1, "Ticket B con solo driver se clasifica como es_incidencia = 1.");
assertTest($ticketB['juego_id'] === null, "Ticket B guardó juego_id como NULL.");
assertTest(intval($ticketB['driver_id']) === $testDriverId, "Ticket B guardó driver_id correctamente.");

// Caso C: Sin Juego ni Driver, pero categoría técnica (Bug) -> es_incidencia = 1
$_POST = [
    "crear_ticket" => "1",
    "titulo" => "Test Case C - Categoría Técnica Bug",
    "descripcion" => "Ticket sin juego ni driver pero con categoría de bug técnico",
    "categoria" => "Bug en cliente",
    "prioridad" => "alta",
    "usuario" => "gamer_pro",
    "juego_id" => "",
    "driver_id" => ""
];
ob_start();
include(__DIR__ . "/../funcs/ticketFunc.php");
ob_end_clean();

$qC = mysqli_query($conn, "SELECT * FROM tickets WHERE titulo = 'Test Case C - Categoría Técnica Bug' ORDER BY id DESC LIMIT 1");
$ticketC = mysqli_fetch_assoc($qC);
assertTest($ticketC !== null, "Ticket C fue creado en la base de datos.");
assertTest(intval($ticketC['es_incidencia']) === 1, "Ticket C con categoría técnica se clasifica como es_incidencia = 1.");
assertTest($ticketC['juego_id'] === null, "Ticket C guardó juego_id como NULL.");
assertTest($ticketC['driver_id'] === null, "Ticket C guardó driver_id como NULL.");

// Caso D: Criterio 4 - Sin Juego, Sin Driver y Categoría No Técnica -> es_incidencia = 0
$_POST = [
    "crear_ticket" => "1",
    "titulo" => "Test Case D - Consulta No Técnica",
    "descripcion" => "Consulta de facturación sin elementos técnicos",
    "categoria" => "Facturación",
    "prioridad" => "baja",
    "usuario" => "gamer_pro",
    "juego_id" => "",
    "driver_id" => ""
];
ob_start();
include(__DIR__ . "/../funcs/ticketFunc.php");
ob_end_clean();

$qD = mysqli_query($conn, "SELECT * FROM tickets WHERE titulo = 'Test Case D - Consulta No Técnica' ORDER BY id DESC LIMIT 1");
$ticketD = mysqli_fetch_assoc($qD);
assertTest($ticketD !== null, "Ticket D fue creado en la base de datos.");
assertTest(intval($ticketD['es_incidencia']) === 0, "Ticket D sin elementos técnicos persiste como es_incidencia = 0.");
assertTest($ticketD['juego_id'] === null, "Ticket D guardó juego_id como NULL.");
assertTest($ticketD['driver_id'] === null, "Ticket D guardó driver_id como NULL.");

// Caso E: Edición - Actualizar ticket D para asignarle un Driver -> Debe pasar a es_incidencia = 1
$_POST = [
    "editar_ticket" => strval($ticketD['id']),
    "titulo" => "Test Case D - Ahora con Driver",
    "descripcion" => $ticketD['descripcion'],
    "categoria" => $ticketD['categoria'],
    "prioridad" => $ticketD['prioridad'],
    "usuario" => $ticketD['usuario'],
    "asignado" => "",
    "estado" => "Nuevo",
    "juego_id" => "",
    "driver_id" => strval($testDriverId)
];
ob_start();
include(__DIR__ . "/../funcs/ticketFunc.php");
ob_end_clean();

$qE = mysqli_query($conn, "SELECT * FROM tickets WHERE id = " . $ticketD['id']);
$ticketE = mysqli_fetch_assoc($qE);
assertTest(intval($ticketE['es_incidencia']) === 1, "Al editar ticket agregando Driver, pasa automáticamente a es_incidencia = 1.");
assertTest(intval($ticketE['driver_id']) === $testDriverId, "Ticket editado guardó el driver_id.");

// Limpieza de datos de prueba
mysqli_query($conn, "DELETE FROM tickets WHERE titulo LIKE 'Test Case %'");

echo "\n======================================================================\n";
echo "RESULTADOS DE LA SUITE:\n";
echo "Total de Aserciones: $totalAsserts\n";
echo "Aserciones Exitosas: $passedAsserts\n";
echo "======================================================================\n";

if ($totalAsserts === $passedAsserts) {
    echo "¡TODAS LAS PRUEBAS PASARON AL 100% (4 CRITERIOS VALIDADOS)!\n";
    exit(0);
} else {
    echo "HUBO FALLAS EN LAS PRUEBAS.\n";
    exit(1);
}
