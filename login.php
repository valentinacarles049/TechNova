<?php
ob_start();
session_start();
require_once("db.php");

$login_error = "";

// Manejar cancelación del flujo de captcha (volver al Paso 1)
if (isset($_GET["cancel"]) || isset($_POST["cancel"])) {
    unset($_SESSION["pending_login"]);
    unset($_SESSION["pending_user"]);
    unset($_SESSION["pending_team"]);
    unset($_SESSION["captcha_num1"]);
    unset($_SESSION["captcha_num2"]);
    unset($_SESSION["captcha_expected"]);
    if (!headers_sent()) {
        header("Location: login.php");
        exit();
    } else {
        echo '<script>window.location.href="login.php";</script>';
        exit();
    }
}

// Inicializar captcha si estamos en el Paso 2 y no existe
if (isset($_SESSION["pending_login"]) && (!isset($_SESSION["captcha_num1"]) || !isset($_SESSION["captcha_num2"]))) {
    $_SESSION["captcha_num1"] = rand(1, 10);
    $_SESSION["captcha_num2"] = rand(1, 10);
    $_SESSION["captcha_expected"] = $_SESSION["captcha_num1"] + $_SESSION["captcha_num2"];
}

$selected_team = isset($_POST["team"]) ? trim($_POST["team"]) : (isset($_SESSION["pending_team"]) ? $_SESSION["pending_team"] : "soporte");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    // Paso 2: Validar captcha
    if (isset($_SESSION["pending_login"])) {
        $captcha_input = isset($_POST["captcha_input"]) ? intval($_POST["captcha_input"]) : -1;

        if ($captcha_input === $_SESSION["captcha_expected"]) {
            // Captcha correcto, iniciar sesión definitivamente
            $user = $_SESSION["pending_user"];
            $_SESSION["currentUser"] = $user["usuario"];
            $_SESSION["currentID"] = $user["id"];
            $_SESSION["currentEmail"] = $user["email"];
            $_SESSION["currentTeam"] = $user["rol_nombre"];
            $_SESSION["currentName"] = $user["nombre_completo"];
            $_SESSION["currentDepartment"] = $user["departamento"];

            // Inicializar parámetros de seguridad de sesión
            $_SESSION['last_activity'] = time();
            $pid_file = 'C:/xampp/apache/logs/httpd.pid';
            if (!file_exists($pid_file) && isset($_SERVER['DOCUMENT_ROOT'])) {
                $pid_file = dirname($_SERVER['DOCUMENT_ROOT']) . '/apache/logs/httpd.pid';
            }
            if (file_exists($pid_file) && is_readable($pid_file)) {
                $_SESSION['server_pid'] = trim(file_get_contents($pid_file));
                $_SESSION['server_mtime'] = filemtime($pid_file);
            }

            // Limpiar datos temporales
            unset($_SESSION["pending_login"]);
            unset($_SESSION["pending_user"]);
            unset($_SESSION["pending_team"]);
            unset($_SESSION["captcha_num1"]);
            unset($_SESSION["captcha_num2"]);
            unset($_SESSION["captcha_expected"]);

            // Redirigir según el rol del usuario (Bandeja de Entrada desactivada)
            $dest = ($_SESSION["currentTeam"] === 'monitoreo' || $_SESSION["currentTeam"] === 'administrador') ? 'dashboard.php' : 'ticketManegement.php';
            if (!headers_sent()) {
                header("Location: " . $dest);
                exit();
            } else {
                echo '<script>window.location.href=' . json_encode($dest) . ';</script>';
                exit();
            }
        } else {
            $login_error = "El captcha ingresado es incorrecto.";
            
            // Regenerar captcha después del intento fallido
            $_SESSION["captcha_num1"] = rand(1, 10);
            $_SESSION["captcha_num2"] = rand(1, 10);
            $_SESSION["captcha_expected"] = $_SESSION["captcha_num1"] + $_SESSION["captcha_num2"];
        }
    } 
    // Paso 1: Validar credenciales normales
    else {
        if (!empty($_POST["username"]) && !empty($_POST["password"])) {
            $usuario = mysqli_real_escape_string($conn, trim($_POST["username"]));
            $password = $_POST["password"];

            $query = "
            SELECT u.*, r.nombre AS rol_nombre 
            FROM usuarios u
            LEFT JOIN roles r ON u.IdRol = r.id
            WHERE u.email = '$usuario' OR u.usuario = '$usuario'";

            $result = mysqli_query($conn, $query);
            $user = mysqli_fetch_assoc($result);

            if ($user && (password_verify($password, $user["contraseña"]) || $user["contraseña"] == $password)) {
                // Validar que el rol seleccionado coincida con el del usuario
                if ($user["rol_nombre"] !== $selected_team) {
                    $login_error = "El rol seleccionado no corresponde a este usuario.";
                } else {
                    // Credenciales y rol correctos: pasar al Paso 2
                    $_SESSION["pending_login"] = true;
                    $_SESSION["pending_user"] = $user;
                    $_SESSION["pending_team"] = $selected_team;

                    // Generar primer captcha
                    $_SESSION["captcha_num1"] = rand(1, 10);
                    $_SESSION["captcha_num2"] = rand(1, 10);
                    $_SESSION["captcha_expected"] = $_SESSION["captcha_num1"] + $_SESSION["captcha_num2"];
                }
            } else {
                $login_error = "Usuario o contraseña incorrectos.";
            }
        }
    }
}

// Configuración de los roles para renderizar en el frontend
$roles_data = [
    "soporte" => [
        "name" => "Equipo de Soporte",
        "color" => "bg-cyan-600",
        "icon" => '
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 18v-6a9 9 0 0 1 18 0v6"></path>
                <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path>
            </svg>'
    ],
    "desarrollo" => [
        "name" => "Equipo de Desarrollo",
        "color" => "bg-emerald-600",
        "icon" => '
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="16 18 22 12 16 6"></polyline>
                <polyline points="8 6 2 12 8 18"></polyline>
            </svg>'
    ],
    "monitoreo" => [
        "name" => "Equipo de Monitoreo",
        "color" => "bg-violet-600",
        "icon" => '
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
            </svg>'
    ],
    "administrador" => [
        "name" => "Administrador",
        "color" => "bg-rose-600",
        "icon" => '
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
            </svg>'
    ]
];

$active_role = isset($roles_data[$selected_team]) ? $roles_data[$selected_team] : $roles_data["soporte"];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechNova - Iniciar Sesión</title>
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <link rel="stylesheet" href="assets/index-V-K5O4c4.css">
    <style>
        .bg-violet-600 {
            background-color: #7c3aed !important;
        }
        .bg-rose-600 {
            background-color: #e11d48 !important;
        }
    </style>
</head>
<body>

<div class="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-emerald-950 flex items-center justify-center p-4">

    <div class="absolute inset-0 opacity-5">
        <div 
            class="absolute inset-0"
            style="
                background-image: radial-gradient(circle at 2px 2px, rgb(16, 185, 129) 1px, transparent 0px);
                background-size: 40px 40px;
            ">
        </div>
    </div>

    <div class="relative w-full max-w-md">

        <div class="bg-slate-900/90 backdrop-blur-sm border border-emerald-900/30 rounded-2xl shadow-2xl p-8">

            <!-- Logo -->
            <div class="flex justify-center mb-8">
                <img src="assets/logo.png" alt="TECHNOVA Logo" class="h-16 w-auto object-contain">
            </div>

            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-white mb-2">Bienvenido</h1>
                <p class="text-slate-400">Sistema de Gestión TECHNOVA</p>
            </div>

            <?php if (!empty($login_error)) { ?>
                <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-md p-3 text-sm text-center mb-6">
                    <?php echo $login_error; ?>
                </div>
            <?php } ?>

            <!-- Form -->
            <form class="space-y-6" action="" method="POST">

                <?php if (isset($_SESSION["pending_login"])) { ?>
                    <!-- PASO 2: CAPTCHA -->
                    <div class="space-y-4">
                        <!-- Info de usuario verificado -->
                        <div class="bg-slate-850/60 border border-slate-800 rounded-lg p-3.5 flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-emerald-500/10 flex items-center justify-center text-emerald-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                            </div>
                            <div>
                                <div class="text-xs text-slate-500">Usuario verificado</div>
                                <div class="text-white font-medium"><?php echo htmlspecialchars($_SESSION["pending_user"]["usuario"]); ?></div>
                            </div>
                        </div>

                        <!-- Info de rol seleccionado -->
                        <div class="bg-slate-850/60 border border-slate-800 rounded-lg p-3.5 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg <?php echo $active_role['color']; ?> flex items-center justify-center">
                                    <?php echo $active_role['icon']; ?>
                                </div>
                                <div>
                                    <div class="text-xs text-slate-500">Rol seleccionado</div>
                                    <div class="text-white font-medium"><?php echo $active_role['name']; ?></div>
                                </div>
                            </div>
                            <span class="text-xs bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-2 py-0.5 rounded font-medium">Correcto</span>
                        </div>
                    </div>

                    <!-- Captcha de Seguridad -->
                    <div class="space-y-2">
                        <label class="text-slate-300 text-sm font-medium" for="captcha_input">
                            Verificación de Seguridad
                        </label>
                        <div class="flex gap-2">
                            <div class="flex-none bg-gradient-to-r from-slate-850 to-slate-900 border border-slate-700 text-emerald-400 font-bold rounded-md px-3 py-2 flex items-center justify-center tracking-wide select-none shadow-inner min-w-[130px]">
                                ¿<?php echo $_SESSION["captcha_num1"] . " + " . $_SESSION["captcha_num2"]; ?>?
                            </div>
                            <input
                                type="number"
                                id="captcha_input"
                                name="captcha_input"
                                placeholder="Respuesta"
                                required
                                autofocus
                                class="flex-1 bg-slate-800/50 border border-slate-700 text-white rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500/50"
                            >
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="space-y-3 pt-2">
                        <button
                            type="submit"
                            class="w-full bg-gradient-to-r from-emerald-600 to-cyan-600 hover:from-emerald-500 hover:to-cyan-500 text-white font-medium rounded-md py-3 shadow-lg shadow-emerald-950/20 transition duration-200">
                            Iniciar Sesión
                        </button>
                        <a href="login.php?cancel=1" class="block w-full text-center bg-slate-800/60 hover:bg-slate-800 border border-slate-700/60 text-slate-300 hover:text-white font-medium rounded-md py-2 transition duration-200 text-sm">
                            Volver
                        </a>
                    </div>

                <?php } else { ?>
                    <!-- PASO 1: CREDENCIALES -->

                    <!-- Dropdown de Equipo -->
                    <div class="space-y-2 relative" id="team-dropdown-container">
                        <label class="text-slate-300 text-sm font-medium">
                            Equipo
                        </label>
                        
                        <input type="hidden" name="team" id="selected-team" value="<?php echo $selected_team; ?>">
                        
                        <button type="button" id="dropdown-trigger" class="w-full bg-slate-800/50 border border-slate-700 hover:border-slate-600 text-white rounded-md px-3 py-2 flex items-center justify-between transition focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
                            <div class="flex items-center gap-3" id="selected-team-display">
                                <div class="w-8 h-8 rounded-lg <?php echo $active_role['color']; ?> flex items-center justify-center transition-colors">
                                    <?php echo $active_role['icon']; ?>
                                </div>
                                <span class="text-white font-medium"><?php echo $active_role['name']; ?></span>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>

                        <div id="dropdown-menu" class="hidden absolute left-0 right-0 mt-1 bg-slate-900 border border-slate-700/60 rounded-lg shadow-2xl z-50 overflow-hidden">
                            <div class="p-1 space-y-1">
                                <?php foreach ($roles_data as $key => $role) { 
                                    $is_selected = ($key === $selected_team);
                                    ?>
                                    <button type="button" data-value="<?php echo $key; ?>" class="dropdown-item w-full text-left px-3 py-2 rounded-lg flex items-center justify-between hover:bg-slate-800/80 transition-colors group">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-lg <?php echo $role['color']; ?> flex items-center justify-center">
                                                <?php echo $role['icon']; ?>
                                            </div>
                                            <span class="text-white font-medium group-hover:text-white transition-colors"><?php echo $role['name']; ?></span>
                                        </div>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="check-icon <?php echo $is_selected ? '' : 'hidden'; ?> w-5 h-5 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="20 6 9 17 4 12"></polyline>
                                        </svg>
                                    </button>
                                <?php } ?>
                            </div>
                        </div>
                    </div>

                    <!-- Usuario -->
                    <div class="space-y-2">
                        <label class="text-slate-300 text-sm font-medium" for="username">
                            Usuario
                        </label>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            placeholder="Ingrese su usuario"
                            required
                            class="w-full bg-slate-800/50 border border-slate-700 text-white rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500/50"
                            value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                        >
                    </div>

                    <!-- Contraseña -->
                    <div class="space-y-2">
                        <label class="text-slate-300 text-sm font-medium" for="password">
                            Contraseña
                        </label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Ingrese su contraseña"
                            required
                            class="w-full bg-slate-800/50 border border-slate-700 text-white rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500/50"
                        >
                    </div>

                    <!-- Botón -->
                    <button
                        type="submit"
                        class="w-full bg-gradient-to-r from-emerald-600 to-cyan-600 hover:from-emerald-500 hover:to-cyan-500 text-white font-medium rounded-md py-3 shadow-lg shadow-emerald-950/20 transition duration-200">
                        Iniciar Sesión
                    </button>
                <?php } ?>

            </form>



        </div>

        <!-- Blur decorativo -->
        <div class="absolute -top-40 -right-40 w-80 h-80 bg-emerald-500/20 rounded-full blur-3xl"></div>

        <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-cyan-500/20 rounded-full blur-3xl"></div>

    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Limpiar el estado del chatbot al cargar la pantalla de login (cierre de sesión)
    localStorage.removeItem('chatbot_open');

    const trigger = document.getElementById('dropdown-trigger');
    const menu = document.getElementById('dropdown-menu');
    const container = document.getElementById('team-dropdown-container');
    const selectedInput = document.getElementById('selected-team');
    const display = document.getElementById('selected-team-display');
    const items = document.querySelectorAll('.dropdown-item');

    // Evitar errores si estamos en el paso 2 y no existen los elementos
    if (trigger && menu && container) {
        // Toggle dropdown
        trigger.addEventListener('click', function (e) {
            e.stopPropagation();
            menu.classList.toggle('hidden');
        });

        // Close when clicking outside
        document.addEventListener('click', function (e) {
            if (!container.contains(e.target)) {
                menu.classList.add('hidden');
            }
        });

        // Handle selection
        items.forEach(item => {
            item.addEventListener('click', function () {
                const val = this.getAttribute('data-value');
                const innerDiv = this.querySelector('.flex.items-center.gap-3');

                // Update input
                if (selectedInput) selectedInput.value = val;

                // Update trigger display
                if (display) display.innerHTML = innerDiv.innerHTML;

                // Update checkboxes/checks
                items.forEach(i => {
                    const check = i.querySelector('.check-icon');
                    if (i === item) {
                        check.classList.remove('hidden');
                    } else {
                        check.classList.add('hidden');
                    }
                });

                // Close
                menu.classList.add('hidden');
            });
        });
    }
});
</script>
</body>
</html>