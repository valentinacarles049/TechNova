<?php
/**
 * Test de renderizado de la UI de ticketManegement.php con sesión de Soporte
 */
session_start();
$_SESSION["currentUser"] = "support";
$_SESSION["currentTeam"] = "soporte";
$_SESSION["userRole"] = "2";

// Simular entorno web
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PHP_SELF'] = '/ticketManegement.php';
$_SERVER['DOCUMENT_ROOT'] = 'c:/wamp64/www';

ob_start();
include(__DIR__ . "/../ticketManegement.php");
$html = ob_get_clean();

echo "Longitud HTML generado: " . strlen($html) . " bytes\n";

$checks = [
    "Título Limpio Gestión de Tickets" => strpos($html, "Gestión de Tickets") !== false,
    "Sin Etiqueta HU-36 Oficial" => strpos($html, "HU-36 Oficial") === false,
    "Badge Nuevo" => strpos($html, "badge-nuevo") !== false,
    "Badge Asignado" => strpos($html, "badge-asignado") !== false,
    "Badge En Progreso" => strpos($html, "badge-en-progreso") !== false,
    "Badge En Espera" => strpos($html, "badge-en-espera") !== false,
    "Badge Resuelto" => strpos($html, "badge-resuelto") !== false,
    "Badge Cerrado" => strpos($html, "badge-cerrado") !== false,
    "Badge Cancelado" => strpos($html, "badge-cancelado") !== false,
    "Métrica Total de Casos" => strpos($html, "Total de Casos") !== false,
    "Métrica En Cola" => strpos($html, "En Cola") !== false,
    "Métrica En Atención Activa" => strpos($html, "En Atención Activa") !== false,
    "Métrica Resolución" => strpos($html, "Resolución") !== false,
    "Sin Barra Duplicada Filtrar por Estado" => strpos($html, "Filtrar por Estado:") === false,
    "Filtro select estadoBuscar" => strpos($html, 'name="estadoBuscar"') !== false,
    "Opción filtro Resuelto" => strpos($html, '<option value="Resuelto"') !== false,
    "Opción filtro En Espera" => strpos($html, '<option value="En Espera"') !== false,
    "Opción filtro Cancelado" => strpos($html, '<option value="Cancelado"') !== false,
];

$allPassed = true;
foreach ($checks as $name => $passed) {
    if ($passed) {
        echo "  [PASS] $name\n";
    } else {
        echo "  [FAIL] $name\n";
        $allPassed = false;
    }
}

if ($allPassed) {
    echo "\n¡TODAS LAS COMPROBACIONES DE RENDERIZADO VISUAL PASARON AL 100%!\n";
    exit(0);
} else {
    echo "\nFALLARON ALGUNAS COMPROBACIONES DE RENDERIZADO VISUAL.\n";
    exit(1);
}
