<?php
if (!isset($conn)) {
    require_once(dirname(__DIR__) . "/db.php");
}
require_once(dirname(__DIR__) . "/funcs/generalFuncs.php");
asegurarTablasEstructura($conn);

$currentScript = basename($_SERVER['PHP_SELF']);
$headerTitle = "Panel de Control";

if ($currentScript === 'bandejaEntrada.php') {
    $headerTitle = "Bandeja de Entrada";
} elseif ($currentScript === 'incidentes.php') {
    $headerTitle = "Tablero de Incidencia";
} elseif ($currentScript === 'ticketManegement.php') {
    $headerTitle = "Tickets";
} elseif ($currentScript === 'feedback.php') {
    $headerTitle = "Tablero de Calidad";
} elseif ($currentScript === 'games.php') {
    $headerTitle = "Juegos";
} elseif ($currentScript === 'drivers.php') {
    $headerTitle = "Drivers";
} elseif ($currentScript === 'profile.php') {
    $headerTitle = "Perfil";
}

$team = isset($_SESSION["currentTeam"]) ? $_SESSION["currentTeam"] : "soporte";
$badgeText = "Equipo de Soporte";
$badgeClass = "bg-cyan-600";

if ($team === 'desarrollo') {
    $badgeText = "Equipo de Desarrollo";
    $badgeClass = "bg-emerald-600";
} elseif ($team === 'monitoreo') {
    $badgeText = "Equipo de Monitoreo";
    $badgeClass = "bg-violet-600";
} elseif ($team === 'administrador') {
    $badgeText = "Administrador";
    $badgeClass = "bg-rose-600";
}

// 1. Control de cambio de semana para Monitoreo
$currentWeek = date('Y-W');
$resWeek = mysqli_query($conn, "SELECT valor FROM configuraciones WHERE clave = 'ultima_semana_notificada'");
$lastWeek = null;
if ($resWeek && $rowWeek = mysqli_fetch_assoc($resWeek)) {
    $lastWeek = $rowWeek['valor'];
}

if ($lastWeek !== $currentWeek) {
    crearNotificacion(
        $conn, 
        'monitoreo', 
        'Actualización Semanal de Gráficos', 
        'Ha comenzado una nueva semana. Por favor, revisen la actualización de los gráficos en el panel.', 
        'dashboard.php'
    );
    if ($lastWeek === null) {
        mysqli_query($conn, "INSERT INTO configuraciones (clave, valor) VALUES ('ultima_semana_notificada', '$currentWeek')");
    } else {
        mysqli_query($conn, "UPDATE configuraciones SET valor = '$currentWeek' WHERE clave = 'ultima_semana_notificada'");
    }
}

// 2. Obtener notificaciones del usuario de la sesión
$unreadCount = 0;
$notifications = [];
if (isset($_SESSION["currentID"])) {
    $userId = intval($_SESSION["currentID"]);
    
    $qCount = mysqli_query($conn, "SELECT COUNT(*) AS cant FROM notificaciones WHERE usuario_id = $userId AND leido = 0");
    if ($qCount) {
        $rowCount = mysqli_fetch_assoc($qCount);
        $unreadCount = intval($rowCount['cant']);
    }
    
    $qNotif = mysqli_query($conn, "SELECT * FROM notificaciones WHERE usuario_id = $userId ORDER BY leido ASC, fecha DESC LIMIT 6");
    if ($qNotif) {
        $notifications = mysqli_fetch_all($qNotif, MYSQLI_ASSOC);
    }
}
?>
<header class="border-b border-slate-800 bg-slate-900/90 px-8 py-4 backdrop-blur-sm">
          <div class="flex items-center justify-between">

            <div>
              <h2 class="text-xl font-semibold text-white">
                <?php echo $headerTitle; ?>
              </h2>

              <span
                class="mt-1 inline-flex rounded-md <?php echo $badgeClass; ?> px-2 py-0.5 text-xs text-white"
              >
                <?php echo $badgeText; ?>
              </span>
            </div>

            <div class="flex items-center gap-4">
            <!-- Contenedor relativo de notificaciones -->
            <div class="relative inline-block text-left" id="notifications-container">
              <button
                id="notifications-btn"
                class="relative rounded-md p-2 text-slate-300 transition hover:bg-slate-800 hover:text-white focus:outline-none"
              >
                🔔
                <span
                  id="notifications-badge"
                  class="absolute -right-1 -top-1 flex h-5 w-5 items-center justify-center rounded-md bg-red-600 text-[10px] font-bold text-white transition-all duration-300"
                  style="<?php echo $unreadCount > 0 ? '' : 'display: none;'; ?>"
                >
                  <?php echo $unreadCount; ?>
                </span>
              </button>

              <!-- Menú Desplegable (Dropdown) de Notificaciones -->
              <div
                id="notifications-dropdown"
                class="absolute right-0 mt-2 w-80 origin-top-right rounded-xl border border-slate-700 bg-slate-900 shadow-2xl ring-1 ring-black ring-opacity-5 focus:outline-none hidden z-50 transition-all duration-200 ease-in-out transform scale-95 opacity-0"
                style="border: 1px solid #334155 !important;"
              >
                <!-- Cabecera -->
                <div class="flex items-center justify-between border-b border-slate-800 px-4 py-3">
                  <span class="text-sm font-semibold text-white">Notificaciones</span>
                  <button
                    id="mark-all-read-btn"
                    class="text-xs font-medium text-cyan-400 hover:text-cyan-300 transition focus:outline-none"
                    style="color: #22d3ee !important;"
                  >
                    Marcar todas como leídas
                  </button>
                </div>
                
                <!-- Lista de notificaciones -->
                <div id="notifications-list" class="max-h-96 overflow-y-auto divide-y divide-slate-800/50">
                  <?php if (empty($notifications)): ?>
                    <div class="px-4 py-8 text-center text-xs text-slate-500 italic">
                      No tienes notificaciones
                    </div>
                  <?php else: ?>
                    <?php foreach ($notifications as $n): ?>
                      <div
                        class="notification-item flex flex-col px-4 py-3 hover:bg-slate-800/50 transition cursor-pointer <?php echo $n['leido'] == 0 ? 'bg-slate-800/30 border-l-2 border-cyan-500' : 'opacity-70'; ?>"
                        onclick="leerNotificacion(<?php echo $n['id']; ?>, '<?php echo htmlspecialchars($n['enlace']); ?>')"
                        data-id="<?php echo $n['id']; ?>"
                        data-leido="<?php echo $n['leido']; ?>"
                      >
                        <div class="flex items-center justify-between gap-2">
                          <span class="text-xs font-semibold <?php echo $n['leido'] == 0 ? 'text-white' : 'text-slate-300'; ?>">
                            <?php echo htmlspecialchars($n['titulo']); ?>
                          </span>
                          <span class="text-[9px] text-slate-500 whitespace-nowrap">
                            <?php echo date('d/m H:i', strtotime($n['fecha'])); ?>
                          </span>
                        </div>
                        <!-- Description in gray -->
                        <p class="mt-1 text-xs text-slate-400 font-normal leading-relaxed line-clamp-2" style="color: #94a3b8 !important;">
                          <?php echo htmlspecialchars($n['descripcion']); ?>
                        </p>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>

              <div class="flex items-center gap-3 border-l border-slate-700 pl-4">
                <div class="text-right">
                  <p class="text-sm text-white">
                    <?php echo htmlspecialchars(!empty($_SESSION["currentName"]) ? $_SESSION["currentName"] : ($_SESSION["currentUser"] ?? "Usuario")); ?>
                  </p>

                  <p class="text-xs text-slate-400">
                    <?php echo htmlspecialchars($_SESSION["currentEmail"] ?? ""); ?>
              
                  </p>
                </div>

                <div
                  class="flex h-10 w-10 items-center justify-center rounded-full bg-cyan-600 font-semibold text-white"
                >
                <?php 
                  $initialName = !empty($_SESSION["currentName"]) ? $_SESSION["currentName"] : ($_SESSION["currentUser"] ?? "U");
                  echo strtoupper(substr($initialName, 0, 1)); 
                ?>
                </div>


              </div>
            </div>
          </div>
        </header>

<script>
(function() {
    // 15 minutos en milisegundos (15 * 60 * 1000)
    const INACTIVITY_LIMIT = 15 * 60 * 1000;
    // Intervalo de latido: cada 3 minutos (180000 ms) para mantener activa la sesión si hay interacción
    const HEARTBEAT_INTERVAL = 3 * 60 * 1000;
    
    let inactivityTimer;
    let lastActivityTime = Date.now();
    
    // Determinar la ruta relativa según la profundidad
    const isSubfolder = window.location.pathname.includes('/funcs/');
    const logoutUrl = isSubfolder ? '../logout.php' : 'logout.php';
    const heartbeatUrl = isSubfolder ? '../heartbeat.php' : 'heartbeat.php';

    function resetInactivityTimer() {
        lastActivityTime = Date.now();
        clearTimeout(inactivityTimer);
        inactivityTimer = setTimeout(logoutUser, INACTIVITY_LIMIT);
    }

    function logoutUser() {
        window.location.href = logoutUrl;
    }

    function sendHeartbeat() {
        // Solo enviar latido si el usuario estuvo activo en el último intervalo
        const inactiveTime = Date.now() - lastActivityTime;
        if (inactiveTime < HEARTBEAT_INTERVAL) {
            fetch(heartbeatUrl)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'expired') {
                        logoutUser();
                    }
                })
                .catch(err => console.error("Error en heartbeat:", err));
        }
    }

    // Escuchar eventos comunes de interacción del usuario
    const activityEvents = ['mousemove', 'mousedown', 'keypress', 'scroll', 'touchstart', 'click'];
    activityEvents.forEach(eventName => {
        document.addEventListener(eventName, resetInactivityTimer, { passive: true });
    });

    // Inicializar el temporizador y el envío periódico del heartbeat
    resetInactivityTimer();
    setInterval(sendHeartbeat, HEARTBEAT_INTERVAL);
})();
</script>

<script>
(function() {
    const btn = document.getElementById('notifications-btn');
    const dropdown = document.getElementById('notifications-dropdown');
    
    if (!btn || !dropdown) return;
    
    // Toggle dropdown
    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        if (dropdown.classList.contains('hidden')) {
            dropdown.classList.remove('hidden');
            setTimeout(() => {
                dropdown.classList.remove('scale-95', 'opacity-0');
                dropdown.classList.add('scale-100', 'opacity-100');
            }, 10);
        } else {
            cerrarDropdown();
        }
    });
    
    function cerrarDropdown() {
        dropdown.classList.remove('scale-100', 'opacity-100');
        dropdown.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            dropdown.classList.add('hidden');
        }, 200);
    }
    
    // Cerrar clickeando afuera
    document.addEventListener('click', (e) => {
        if (!dropdown.contains(e.target) && !btn.contains(e.target)) {
            cerrarDropdown();
        }
    });
    
    // AJAX marcar todas como leídas
    const markAllBtn = document.getElementById('mark-all-read-btn');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isSubfolder = window.location.pathname.includes('/funcs/');
            const marcarTodasUrl = isSubfolder ? 'marcar_todas_leidas.php' : 'funcs/marcar_todas_leidas.php';
            
            fetch(marcarTodasUrl)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // Ocultar badge
                        const badge = document.getElementById('notifications-badge');
                        if (badge) {
                            badge.textContent = '0';
                            badge.style.display = 'none';
                        }
                        
                        // Modificar ítems en UI
                        const items = document.querySelectorAll('.notification-item');
                        items.forEach(item => {
                            item.classList.remove('bg-slate-800/30', 'border-l-2', 'border-cyan-500');
                            item.classList.add('opacity-70');
                            item.setAttribute('data-leido', '1');
                            
                            const title = item.querySelector('.font-semibold');
                            if (title) {
                                title.classList.remove('text-white');
                                title.classList.add('text-slate-300');
                            }
                        });
                    }
                })
                .catch(err => console.error("Error al marcar todas como leídas:", err));
        });
    }
})();

function leerNotificacion(id, enlace) {
    const item = document.querySelector(`.notification-item[data-id="${id}"]`);
    if (!item) return;
    
    const leido = parseInt(item.getAttribute('data-leido')) === 1;
    const isSubfolder = window.location.pathname.includes('/funcs/');
    const redirectUrl = isSubfolder ? '../' + enlace : enlace;
    
    if (!leido) {
        const marcarLeidaUrl = isSubfolder ? 'marcar_leida.php?id=' + id : 'funcs/marcar_leida.php?id=' + id;
        fetch(marcarLeidaUrl)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const badge = document.getElementById('notifications-badge');
                    if (badge) {
                        let count = parseInt(badge.textContent) - 1;
                        if (count <= 0) {
                            badge.textContent = '0';
                            badge.style.display = 'none';
                        } else {
                            badge.textContent = count;
                        }
                    }
                    window.location.href = redirectUrl;
                } else {
                    window.location.href = redirectUrl;
                }
            })
            .catch(() => {
                window.location.href = redirectUrl;
            });
    } else {
        window.location.href = redirectUrl;
    }
}
</script>

<style>
.field-error {
    border-color: #ef4444 !important;
    box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.25) !important;
}
.error-text {
    color: #fca5a5 !important;
    font-size: 0.75rem !important;
    margin-top: 0.35rem !important;
    display: flex !important;
    align-items: center !important;
    gap: 4px !important;
    font-weight: 500 !important;
}
</style>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const currentPage = window.location.pathname;

    // Clean up sessionStorage for other pages (modules)
    for (let i = sessionStorage.length - 1; i >= 0; i--) {
        const key = sessionStorage.key(i);
        if (key && key.startsWith('form_persist_') && !key.includes(currentPage)) {
            sessionStorage.removeItem(key);
        }
    }

    // Check if page loaded with an error toast
    const hasErrorToast = document.querySelector('.toast-error, .bg-red-500\\/10, [class*="toast-error"]') !== null || document.body.innerText.includes('❌ Error');

    // If there is no error toast, clear saved persistence data for this page so stale form data does not linger
    if (!hasErrorToast) {
        for (let i = sessionStorage.length - 1; i >= 0; i--) {
            const key = sessionStorage.key(i);
            if (key && key.startsWith(`form_persist_${currentPage}`)) {
                sessionStorage.removeItem(key);
            }
        }
    }

    // Identify eligible forms (ONLY actual creation/edition/registration forms; strictly exclude search, filter bars, and table action buttons)
    const forms = Array.from(document.querySelectorAll('form')).filter(form => {
        if (form.method.toLowerCase() === 'get') return false;

        // Exclude search forms
        if (form.querySelector('input[name*="buscar" i], input[name*="Buscar"], button[name*="buscar" i], button[name*="Buscar"], input[placeholder*="buscar" i]')) return false;

        // Exclude forms containing auto-submitting select/inputs (filter bars)
        if (form.querySelector('[onchange*="submit"]')) return false;

        // Exclude forms marked with no-persist
        if (form.hasAttribute('data-no-persist') || form.classList.contains('no-persist')) return false;

        // Exclude action buttons in tables (forms with 0 user-editable inputs)
        const editableInputs = Array.from(form.querySelectorAll('input, select, textarea')).filter(input => {
            if (input.type === 'password' || input.type === 'hidden' || input.type === 'submit' || input.type === 'button') return false;
            return true;
        });
        if (editableInputs.length === 0) return false;

        return true;
    });

    forms.forEach((form, formIndex) => {
        const formId = form.id || form.getAttribute('name') || `form_${formIndex}`;
        const persistKey = `form_persist_${currentPage}_${formId}`;
        const inputs = Array.from(form.querySelectorAll('input, select, textarea')).filter(input => {
            if (input.type === 'password' || input.type === 'hidden' || input.type === 'submit' || input.type === 'button') return false;
            return true;
        });

        // Restore values from sessionStorage ONLY if an error occurred (preserving user input)
        if (hasErrorToast) {
            const savedData = JSON.parse(sessionStorage.getItem(persistKey) || '{}');
            
            inputs.forEach(input => {
                if (savedData[input.name] !== undefined) {
                    if (input.type === 'checkbox') {
                        input.checked = !!savedData[input.name];
                    } else if (input.type === 'radio') {
                        if (input.value === savedData[input.name]) {
                            input.checked = true;
                        }
                    } else {
                        input.value = savedData[input.name];
                    }
                    
                    // Trigger change event to fire any page-specific behaviors (e.g. toggle incident fields)
                    // NEVER trigger if it has an inline submit call!
                    const onchangeAttr = (input.getAttribute('onchange') || '').toLowerCase();
                    if (!onchangeAttr.includes('submit')) {
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
            });
        }

        // Auto-save values to sessionStorage as the user types/selects
        inputs.forEach(input => {
            const saveHandler = () => {
                const currentData = JSON.parse(sessionStorage.getItem(persistKey) || '{}');
                if (input.type === 'checkbox') {
                    currentData[input.name] = input.checked;
                } else if (input.type === 'radio') {
                    if (input.checked) {
                        currentData[input.name] = input.value;
                    }
                } else {
                    currentData[input.name] = input.value;
                }
                sessionStorage.setItem(persistKey, JSON.stringify(currentData));
            };
            input.addEventListener('input', saveHandler);
            input.addEventListener('change', saveHandler);
        });

        // Form submission and validation
        form.addEventListener('submit', function(e) {
            // If the submit was triggered by a cancel button or modal launcher, clear storage and do not validate!
            const submitter = e.submitter;
            if (submitter && (
                submitter.name.toLowerCase().includes('cancel') || 
                submitter.name === 'FormTicket' || 
                submitter.name === 'FormFeedback' || 
                submitter.name === 'FormQA' || 
                submitter.name === 'FormCAPA' || 
                submitter.textContent.toLowerCase().includes('cancelar') ||
                submitter.textContent.toLowerCase().includes('cerrar') ||
                submitter.hasAttribute('formnovalidate')
            )) {
                sessionStorage.removeItem(persistKey);
                return; // Let it submit without validation
            }

            // Remove existing error highlights and messages
            form.querySelectorAll('.field-error').forEach(el => el.classList.remove('field-error'));
            form.querySelectorAll('.error-text').forEach(el => el.remove());

            let isValid = true;

            inputs.forEach(input => {
                // We only validate visible and enabled inputs
                if (input.disabled || input.offsetWidth === 0 || input.offsetHeight === 0) {
                    return;
                }

                let fieldValid = true;
                let errorMsg = '';

                // Check required
                if (input.hasAttribute('required')) {
                    if (input.type === 'checkbox' && !input.checked) {
                        fieldValid = false;
                        errorMsg = 'Debe aceptar este campo.';
                    } else if (input.value.trim() === '') {
                        fieldValid = false;
                        errorMsg = 'Este campo es obligatorio.';
                    }
                }

                // Check number min/max
                if (fieldValid && input.type === 'number') {
                    const val = parseFloat(input.value);
                    if (!isNaN(val)) {
                        const min = input.hasAttribute('min') ? parseFloat(input.getAttribute('min')) : NaN;
                        if (!isNaN(min) && val < min) {
                            fieldValid = false;
                            errorMsg = `El valor mínimo es ${min}.`;
                        }
                        const max = input.hasAttribute('max') ? parseFloat(input.getAttribute('max')) : NaN;
                        if (!isNaN(max) && val > max) {
                            fieldValid = false;
                            errorMsg = `El valor máximo es ${max}.`;
                        }
                    } else if (input.value.trim() !== '') {
                        fieldValid = false;
                        errorMsg = 'Debe ser un número válido.';
                    }
                }

                // Check email format
                if (fieldValid && input.type === 'email' && input.value.trim() !== '') {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(input.value.trim())) {
                        fieldValid = false;
                        errorMsg = 'Formato de correo electrónico no válido.';
                    }
                }

                if (!fieldValid) {
                    isValid = false;
                    input.classList.add('field-error');
                    
                    // Add help text
                    const errorEl = document.createElement('span');
                    errorEl.className = 'error-text';
                    errorEl.innerHTML = `⚠️ ${errorMsg}`;
                    
                    // Insert errorEl right after input or its closest custom styling container
                    input.parentNode.appendChild(errorEl);

                    // Add event listener to clear error when user corrects it
                    const clearError = () => {
                        input.classList.remove('field-error');
                        errorEl.remove();
                        input.removeEventListener('input', clearError);
                        input.removeEventListener('change', clearError);
                    };
                    input.addEventListener('input', clearError);
                    input.addEventListener('change', clearError);
                }
            });

            if (!isValid) {
                e.preventDefault();
                e.stopPropagation();
                
                // Show a toast error if not already visible
                if (!document.querySelector('.toast-error')) {
                    const toastContainer = document.getElementById('toast-container') || document.body;
                    const errorToast = document.createElement('div');
                    errorToast.className = 'toast-error';
                    errorToast.id = 'toast-message';
                    errorToast.innerHTML = '❌ Error: Por favor, corrija los campos resaltados en el formulario.';
                    errorToast.style.pointerEvents = 'auto';
                    errorToast.style.boxShadow = '0 20px 25px -5px rgba(0, 0, 0, 0.5), 0 8px 10px -6px rgba(0, 0, 0, 0.5)';
                    errorToast.style.backdropFilter = 'blur(8px)';
                    errorToast.style.position = 'fixed';
                    errorToast.style.top = '24px';
                    errorToast.style.right = '24px';
                    errorToast.style.zIndex = '9999';
                    errorToast.style.transition = 'all 0.5s cubic-bezier(0.16, 1, 0.3, 1)';
                    errorToast.style.transform = 'translateX(120%) scale(0.9)';
                    errorToast.style.opacity = '0';
                    
                    toastContainer.appendChild(errorToast);
                    setTimeout(() => {
                        errorToast.style.transform = 'translateX(0) scale(1)';
                        errorToast.style.opacity = '1';
                    }, 50);
                    
                    setTimeout(() => {
                        errorToast.style.transform = 'translateX(120%) scale(0.9)';
                        errorToast.style.opacity = '0';
                        setTimeout(() => errorToast.remove(), 600);
                    }, 4000);
                }
            }
        });
    });

    // Also listen to Cancel buttons directly to clear sessionStorage
    document.querySelectorAll('button[name*="Cancel"], button[name="FormTicket"], button[name="FormFeedback"], a[href$=".php"], a[href="incidentes.php"]').forEach(btn => {
        btn.addEventListener('click', () => {
            for (let i = sessionStorage.length - 1; i >= 0; i--) {
                const key = sessionStorage.key(i);
                if (key && key.startsWith('form_persist_') && key.includes(currentPage)) {
                    sessionStorage.removeItem(key);
                }
            }
        });
    });
});
</script>

