<?php
ob_start();
session_start();
require_once("funcs/session_security.php");
require_once("db.php");
require_once("funcs/generalFuncs.php");

if (!function_exists('redirigirPerfil')) {
    function redirigirPerfil($url = "profile.php") {
        if (!headers_sent()) {
            header("Location: " . $url);
            exit;
        } else {
            echo '<script>window.location.href=' . json_encode($url) . ';</script>';
            echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url) . '"></noscript>';
            exit;
        }
    }
}

$currentUserId = intval($_SESSION["currentID"] ?? 0);
if ($currentUserId <= 0) {
    redirigirPerfil("login.php");
}

// 2. Obtener información actualizada del usuario desde la base de datos
$userQuery = mysqli_query($conn, "
    SELECT u.*, r.nombre AS rol_nombre 
    FROM usuarios u 
    LEFT JOIN roles r ON u.IdRol = r.id 
    WHERE u.id = $currentUserId
");
$user = mysqli_fetch_assoc($userQuery);

if (!$user) {
    die("Error: Usuario no encontrado.");
}

// Sincronizar sesión por si hubo cambios previos
$_SESSION["currentUser"] = $user["usuario"];
$_SESSION["currentEmail"] = $user["email"];
$_SESSION["currentName"] = $user["nombre_completo"];
$_SESSION["currentDepartment"] = $user["departamento"];
$_SESSION["currentTeam"] = $user["rol_nombre"];

// 3. Procesar formulario: Guardar Cambios (Información Personal)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["guardar_cambios"])) {
    $nombre_completo = trim($_POST["nombre_completo"]);
    $email = trim($_POST["email"]);

    if (!empty($nombre_completo) && !empty($email)) {
        $nombre_completo_esc = mysqli_real_escape_string($conn, $nombre_completo);
        $email_esc = mysqli_real_escape_string($conn, $email);

        $updateQuery = "UPDATE usuarios SET nombre_completo = '$nombre_completo_esc', email = '$email_esc' WHERE id = $currentUserId";
        if (mysqli_query($conn, $updateQuery)) {
            $_SESSION["currentName"] = $nombre_completo;
            $_SESSION["currentEmail"] = $email;
            $_SESSION["msg"] = '<div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 rounded-md p-3.5 text-sm text-center mb-6">✅ Información personal actualizada correctamente.</div>';
        } else {
            $_SESSION["msg"] = '<div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-md p-3.5 text-sm text-center mb-6">❌ Error al actualizar la información personal.</div>';
        }
    } else {
        $_SESSION["msg"] = '<div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-md p-3.5 text-sm text-center mb-6">❌ El nombre completo y el email no pueden estar vacíos.</div>';
    }
    redirigirPerfil("profile.php");
}

// 4. Procesar formulario: Cambiar Contraseña (Seguridad)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["cambiar_contrasena"])) {
    $contrasena_actual = $_POST["contrasena_actual"];
    $nueva_contrasena = $_POST["nueva_contrasena"];
    $confirmar_contrasena = $_POST["confirmar_contrasena"];

    if (!empty($contrasena_actual) && !empty($nueva_contrasena) && !empty($confirmar_contrasena)) {
        $is_valid_current = password_verify($contrasena_actual, $user["contraseña"]) || ($contrasena_actual === $user["contraseña"]);
        if (!$is_valid_current) {
            $_SESSION["msg"] = '<div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-md p-3.5 text-sm text-center mb-6">❌ La contraseña actual es incorrecta.</div>';
        } elseif ($nueva_contrasena !== $confirmar_contrasena) {
            $_SESSION["msg"] = '<div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-md p-3.5 text-sm text-center mb-6">❌ La nueva contraseña y su confirmación no coinciden.</div>';
        } else {
            $nueva_contrasena_hash = password_hash($nueva_contrasena, PASSWORD_DEFAULT);
            $nueva_contrasena_esc = mysqli_real_escape_string($conn, $nueva_contrasena_hash);
            $updatePassQuery = "UPDATE usuarios SET contraseña = '$nueva_contrasena_esc' WHERE id = $currentUserId";
            if (mysqli_query($conn, $updatePassQuery)) {
                $_SESSION["msg"] = '<div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 rounded-md p-3.5 text-sm text-center mb-6">✅ Contraseña cambiada con éxito.</div>';
            } else {
                $_SESSION["msg"] = '<div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-md p-3.5 text-sm text-center mb-6">❌ Error al actualizar la contraseña.</div>';
            }
        }
    } else {
        $_SESSION["msg"] = '<div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-md p-3.5 text-sm text-center mb-6">❌ Todos los campos de contraseña son obligatorios.</div>';
    }
    redirigirPerfil("profile.php");
}

// 5. Calcular Estadísticas de Actividad (Totales reales respetando los accesos y alcances de cada rol)
$team = $user["rol_nombre"];
$username = $user["usuario"];

$incCount = 0;
$tktCount = 0;
$calidadCount = 0;

if ($team === 'administrador') {
    // Administrador: Ve sus estadísticas correspondientes (propias de su usuario y rol)
    $queryInc = mysqli_query($conn, "
        SELECT COUNT(DISTINCT inc.id) as total 
        FROM incidencias inc 
        JOIN tickets t ON inc.ticket_id = t.id 
        LEFT JOIN historial_tickets h ON t.id = h.ticket_id
        WHERE t.estado != 'inactivo'
          AND (t.asignado = '$team' OR t.asignado = '$username' OR t.usuario = '$username' OR (h.usuario = '$username' AND h.tipo_cambio LIKE '%incidencia%'))
    ");
    $rowInc = mysqli_fetch_assoc($queryInc);
    $incCount = ($rowInc['total'] ?? 0);

    $queryTkt = mysqli_query($conn, "
        SELECT COUNT(DISTINCT t.id) as total 
        FROM tickets t
        LEFT JOIN historial_tickets h ON t.id = h.ticket_id
        WHERE t.estado = 'cerrado'
          AND (t.asignado = '$team' OR t.asignado = '$username' OR t.usuario = '$username' OR (h.usuario = '$username' AND h.tipo_cambio LIKE '%Estado: \'Cerrado\'%'))
    ");
    $rowTkt = mysqli_fetch_assoc($queryTkt);
    $tktCount = ($rowTkt['total'] ?? 0);

    $queryFb = mysqli_query($conn, "
        SELECT COUNT(DISTINCT f.id) as total 
        FROM feedbacks f
        LEFT JOIN tickets t ON f.ticket_id = t.id
        LEFT JOIN historial_tickets h ON t.id = h.ticket_id
        WHERE f.estado != 'Nuevo' AND f.estado != 'inactivo'
          AND (t.asignado = '$team' OR t.asignado = '$username' OR t.usuario = '$username' OR f.usuario = '$username' OR (h.usuario = '$username' AND h.tipo_cambio LIKE '%Estado: \'Cerrado\'%'))
    ");
    $rowFb = mysqli_fetch_assoc($queryFb);
    $calidadCount = ($rowFb['total'] ?? 0);

} elseif ($team === 'desarrollo') {
    // Desarrollo: Solo ve incidencias correspondientes a su rol o usuario (tickets y calidad quedan en 0)
    $queryInc = mysqli_query($conn, "
        SELECT COUNT(DISTINCT inc.id) as total 
        FROM incidencias inc 
        JOIN tickets t ON inc.ticket_id = t.id 
        LEFT JOIN historial_tickets h ON t.id = h.ticket_id
        WHERE t.estado != 'inactivo'
          AND (t.asignado = '$team' OR t.asignado = '$username' OR t.usuario = '$username' OR (h.usuario = '$username' AND h.tipo_cambio LIKE '%incidencia%'))
    ");
    $rowInc = mysqli_fetch_assoc($queryInc);
    $incCount = ($rowInc['total'] ?? 0);

} elseif ($team === 'soporte') {
    // Soporte: Ve tickets resueltos y evaluaciones de calidad asociadas (incidencias queda en 0)
    $queryTkt = mysqli_query($conn, "
        SELECT COUNT(DISTINCT t.id) as total 
        FROM tickets t
        LEFT JOIN historial_tickets h ON t.id = h.ticket_id
        WHERE t.estado = 'cerrado'
          AND (t.asignado = '$team' OR t.asignado = '$username' OR t.usuario = '$username' OR (h.usuario = '$username' AND h.tipo_cambio LIKE '%Estado: \'Cerrado\'%'))
    ");
    $rowTkt = mysqli_fetch_assoc($queryTkt);
    $tktCount = ($rowTkt['total'] ?? 0);

    $queryFb = mysqli_query($conn, "
        SELECT COUNT(DISTINCT f.id) as total 
        FROM feedbacks f
        LEFT JOIN tickets t ON f.ticket_id = t.id
        LEFT JOIN historial_tickets h ON t.id = h.ticket_id
        WHERE f.estado != 'Nuevo' AND f.estado != 'inactivo'
          AND (t.asignado = '$team' OR t.asignado = '$username' OR t.usuario = '$username' OR f.usuario = '$username' OR (h.usuario = '$username' AND h.tipo_cambio LIKE '%Estado: \'Cerrado\'%'))
    ");
    $rowFb = mysqli_fetch_assoc($queryFb);
    $calidadCount = ($rowFb['total'] ?? 0);
} else {
    // Monitoreo: Queda en 0 para todos los contadores
    $incCount = 0;
    $tktCount = 0;
    $calidadCount = 0;
}

// 6. Configurar iniciales del avatar (1 sola letra para concordar con el perfil superior)
$nombreCompleto = !empty($user["nombre_completo"]) ? $user["nombre_completo"] : $user["usuario"];
$initials = strtoupper(substr(trim($nombreCompleto), 0, 1));

// Mapear Rol a formato legible (Capitalizado directamente desde la DB)
$roleDisplay = ucfirst($user["rol_nombre"]);
$themeColorClass = "bg-cyan-600 text-white";
$avatarColorClass = "bg-cyan-600 text-white font-semibold";
if ($user["rol_nombre"] === 'administrador') {
    $themeColorClass = "bg-rose-600 text-white";
    $avatarColorClass = "bg-teal-600 text-white font-semibold"; // Verde/azul sólido como en la captura
} elseif ($user["rol_nombre"] === 'desarrollo') {
    $themeColorClass = "bg-emerald-600 text-white";
    $avatarColorClass = "bg-emerald-600 text-white font-semibold";
} elseif ($user["rol_nombre"] === 'monitoreo') {
    $themeColorClass = "bg-violet-600 text-white";
    $avatarColorClass = "bg-violet-600 text-white font-semibold";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechNova - Perfil de Usuario</title>
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <link rel="stylesheet" href="assets/index-V-K5O4c4.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            color: #e2e8f0;
        }
        .card-bg {
            background-color: #111827; /* Slate 900 */
            border-color: #1f2937; /* Slate 800 */
        }
    </style>
</head>
<body class="bg-slate-950 text-white overflow-hidden">
    <div id="root">
        <div class="h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-emerald-950 overflow-hidden">
            <!-- Background grids -->
            <div class="absolute inset-0 opacity-5 pointer-events-none">
                <div class="absolute inset-0" style="
                    background-image: radial-gradient(circle at 2px 2px, rgb(16, 185, 129) 1px, transparent 0px);
                    background-size: 40px 40px;
                "></div>
            </div>

            <div class="relative h-full flex">
                <!-- Sidebar -->
                <aside class="w-64 bg-slate-900/90 backdrop-blur-sm border-r border-emerald-900/30 transition-all duration-300 flex flex-col">
                    <?php require_once("templates/logo.php"); ?>
                    </div></div> <!-- Cierra los dos divs abiertos en templates/logo.php -->

                    <!-- Badge -->
                    <div class="border-b border-slate-700 bg-slate-800/50 px-4 py-3">
                        <span class="flex w-full items-center justify-center rounded-md <?php echo $sidebarBadgeClass; ?> px-2 py-1 text-xs font-medium text-white">
                            <?php echo $sidebarBadgeText; ?>
                        </span>
                    </div>

                    <!-- Navigation -->
                    <?php require_once("templates/sidebar.php"); ?>

                    <!-- Footer -->
                    <div class="p-4 border-t border-slate-800">
                        <button class="w-full flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-800 rounded-md py-2 transition-all">
                            ✕
                        </button>
                    </div>
                </aside>

                <!-- Main Content -->
                <div class="flex-1 flex flex-col overflow-hidden">
                    <!-- Header -->
                    <?php require_once("templates/header.php"); ?>

                    <!-- Main scrollable panel -->
                    <main class="flex-1 overflow-y-auto p-6 md:p-8">
                        <div class="max-w-4xl mx-auto space-y-6">
                            


                            <!-- Toast / Mensajes de Éxito o Error -->
                            <?php if (isset($_SESSION["msg"])): ?>
                                <div class="mt-2">
                                    <?php 
                                        echo $_SESSION["msg"]; 
                                        unset($_SESSION["msg"]); 
                                    ?>
                                </div>
                            <?php endif; ?>

                            <!-- CARD 1: Información Personal -->
                            <div class="card-bg rounded-xl border p-6 shadow-xl">
                                <h3 class="text-lg font-medium text-white mb-6">Información Personal</h3>

                                <!-- Avatar y Cabecera de Usuario -->
                                <div class="flex items-center gap-4 mb-8">
                                    <div class="flex items-center justify-center text-2xl font-bold <?php echo $avatarColorClass; ?>" style="width: 56px; height: 56px; min-width: 56px; min-height: 56px; border-radius: 9999px; flex-shrink: 0; aspect-ratio: 1/1;">
                                        <?php echo $initials; ?>
                                    </div>
                                    <div>
                                        <h4 class="text-xl font-bold text-white"><?php echo htmlspecialchars($nombreCompleto); ?></h4>
                                        <p class="text-sm text-slate-400"><?php echo htmlspecialchars($roleDisplay); ?></p>
                                    </div>
                                </div>

                                <!-- Formulario de Información Personal (Solo Lectura) -->
                                <form onsubmit="return false;" class="space-y-6">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <!-- Nombre Completo -->
                                        <div class="space-y-2">
                                            <label class="block text-sm font-medium text-slate-300">
                                                Nombre Completo
                                            </label>
                                            <div class="relative rounded-md shadow-sm opacity-60">
                                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                    <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                    </svg>
                                                </div>
                                                <input 
                                                    type="text" 
                                                    value="<?php echo htmlspecialchars($user["nombre_completo"] ?? ""); ?>" 
                                                    disabled 
                                                    class="block w-full pl-10 pr-3 py-2.5 bg-slate-950 border border-slate-800 rounded-lg text-slate-400 text-sm cursor-not-allowed"
                                                >
                                            </div>
                                        </div>

                                        <!-- Email -->
                                        <div class="space-y-2">
                                            <label class="block text-sm font-medium text-slate-300">
                                                Email
                                            </label>
                                            <div class="relative rounded-md shadow-sm opacity-60">
                                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                    <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 00-2 2z"></path>
                                                    </svg>
                                                </div>
                                                <input 
                                                    type="email" 
                                                    value="<?php echo htmlspecialchars($user["email"]); ?>" 
                                                    disabled 
                                                    class="block w-full pl-10 pr-3 py-2.5 bg-slate-950 border border-slate-800 rounded-lg text-slate-400 text-sm cursor-not-allowed"
                                                >
                                            </div>
                                        </div>

                                        <!-- Rol -->
                                        <div class="space-y-2">
                                            <label class="block text-sm font-medium text-slate-300">
                                                Rol
                                            </label>
                                            <div class="relative rounded-md shadow-sm opacity-60">
                                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                    <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                                    </svg>
                                                </div>
                                                <input 
                                                    type="text" 
                                                    value="<?php echo htmlspecialchars($roleDisplay); ?>" 
                                                    disabled 
                                                    class="block w-full pl-10 pr-3 py-2.5 bg-slate-950 border border-slate-800 rounded-lg text-slate-400 text-sm cursor-not-allowed"
                                                >
                                            </div>
                                        </div>

                                        <!-- Departamento -->
                                        <div class="space-y-2">
                                            <label class="block text-sm font-medium text-slate-300">
                                                Departamento
                                            </label>
                                            <div class="relative rounded-md shadow-sm opacity-60">
                                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                    <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                                    </svg>
                                                </div>
                                                <input 
                                                    type="text" 
                                                    value="<?php echo htmlspecialchars($user["departamento"] ?? "N/A"); ?>" 
                                                    disabled 
                                                    class="block w-full pl-10 pr-3 py-2.5 bg-slate-950 border border-slate-800 rounded-lg text-slate-400 text-sm cursor-not-allowed"
                                                >
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <!-- CARD 2: Seguridad -->
                            <div class="card-bg rounded-xl border p-6 shadow-xl">
                                <h3 class="text-lg font-medium text-white mb-6">Seguridad</h3>

                                <!-- Formulario de Cambio de Contraseña -->
                                <form action="profile.php" method="POST" class="space-y-6">
                                    <!-- Contraseña Actual -->
                                    <div class="space-y-2">
                                        <label class="block text-sm font-medium text-slate-300">
                                            Contraseña Actual
                                        </label>
                                        <div class="relative rounded-md shadow-sm">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                                </svg>
                                            </div>
                                            <input 
                                                type="password" 
                                                name="contrasena_actual" 
                                                placeholder="Ingrese su contraseña actual" 
                                                required 
                                                class="block w-full pl-10 pr-3 py-2.5 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50"
                                            >
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <!-- Nueva Contraseña -->
                                        <div class="space-y-2">
                                            <label class="block text-sm font-medium text-slate-300">
                                                Nueva Contraseña
                                            </label>
                                            <div class="relative rounded-md shadow-sm">
                                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                    <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                                    </svg>
                                                </div>
                                                <input 
                                                    type="password" 
                                                    name="nueva_contrasena" 
                                                    placeholder="Nueva contraseña" 
                                                    required 
                                                    class="block w-full pl-10 pr-3 py-2.5 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50"
                                                >
                                            </div>
                                        </div>

                                        <!-- Confirmar Contraseña -->
                                        <div class="space-y-2">
                                            <label class="block text-sm font-medium text-slate-300">
                                                Confirmar Contraseña
                                            </label>
                                            <div class="relative rounded-md shadow-sm">
                                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                    <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                                    </svg>
                                                </div>
                                                <input 
                                                    type="password" 
                                                    name="confirmar_contrasena" 
                                                    placeholder="Confirme su contraseña" 
                                                    required 
                                                    class="block w-full pl-10 pr-3 py-2.5 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50"
                                                >
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Botón Cambiar Contraseña -->
                                    <div class="pt-2">
                                        <button 
                                            type="submit" 
                                            name="cambiar_contrasena" 
                                            class="border border-emerald-500/50 text-emerald-400 hover:bg-emerald-500/10 hover:border-emerald-500 font-medium rounded-lg py-2.5 px-4 transition-all text-sm flex items-center gap-2"
                                        >
                                            <svg class="w-4 h-4 flex-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                                <path d="M7 11V7a5 5 0 0110 0v4"></path>
                                            </svg>
                                            <span>Cambiar Contraseña</span>
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- CARD 3: Tu Actividad -->
                            <div class="card-bg rounded-xl border p-6 shadow-xl">
                                <h3 class="text-lg font-medium text-white mb-6">Tu Actividad</h3>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                                    <!-- Incidencias Gestionadas -->
                                    <div class="rounded-xl border border-slate-800 bg-slate-950/40 p-6 flex flex-col justify-between">
                                        <span class="text-sm text-slate-400">Incidencias Gestionadas</span>
                                        <span class="text-4xl font-semibold text-white mt-4"><?php echo $incCount; ?></span>
                                    </div>

                                    <!-- Tickets Resueltos -->
                                    <div class="rounded-xl border border-slate-800 bg-slate-950/40 p-6 flex flex-col justify-between">
                                        <span class="text-sm text-slate-400">Tickets Resueltos</span>
                                        <span class="text-4xl font-semibold text-white mt-4"><?php echo $tktCount; ?></span>
                                    </div>

                                    <!-- Calidad Procesada -->
                                    <div class="rounded-xl border border-slate-800 bg-slate-950/40 p-6 flex flex-col justify-between">
                                        <span class="text-sm text-slate-400">Controles de Calidad Procesados</span>
                                        <span class="text-4xl font-semibold text-white mt-4"><?php echo $calidadCount; ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- CARD 4: Cerrar Sesión -->
                            <div class="card-bg rounded-xl border p-6 shadow-xl flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                <div>
                                    <h3 class="text-lg font-medium text-white">Cerrar Sesión</h3>
                                    <p class="text-sm text-slate-400 mt-1">Salir del sistema de gestión</p>
                                </div>
                                <div>
                                    <a 
                                        href="logout.php" 
                                        class="bg-red-600 hover:bg-red-500 text-white font-medium rounded-lg py-2.5 px-4 transition duration-200 text-sm flex items-center gap-2 shadow-lg shadow-red-950/20"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                            <path fill-rule="evenodd" d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0v2z"/>
                                            <path fill-rule="evenodd" d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z"/>
                                        </svg>
                                        <span>Cerrar Sesión</span>
                                    </a>
                                </div>
                            </div>

                        </div>
                    </main>
                </div>
            </div>

            <!-- Background Glow -->
            <div class="absolute top-0 right-0 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="absolute bottom-0 left-0 w-96 h-96 bg-cyan-500/10 rounded-full blur-3xl pointer-events-none"></div>
        </div>
    </div>
</body>
</html>
