<?php 
ob_start();
session_start();
require_once("funcs/session_security.php");
require_once("db.php");
require_once("funcs/gameFunc.php");

$canManage = (isset($_SESSION["currentTeam"]) && ($_SESSION["currentTeam"] === "soporte" || $_SESSION["currentTeam"] === "administrador"));

if (!empty($_POST["eliminar"])){
    eliminarJuego($conn);
};

// Paginación
$registros_por_pagina = 10;
$pagina_actual = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

$total_juegos = contarJuegos($conn);
$total_paginas = ceil($total_juegos / $registros_por_pagina);

$juegos = obtenerJuegos($conn, $registros_por_pagina, $offset);     

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechNova - Juegos</title>
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <link rel="stylesheet" href="assets/index-V-K5O4c4.css">
</head>
<body>
  <div id="root">
    <div class="h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-emerald-950 overflow-hidden">
      <!-- Background -->
      <div class="absolute inset-0 opacity-5">
        <div class="absolute inset-0" style="background-image: radial-gradient(circle at 2px 2px, rgb(16, 185, 129) 1px, transparent 0px); background-size: 40px 40px;"></div>
      </div>

      <div class="relative h-full flex">
        <!-- Sidebar -->
        <aside class="w-64 bg-slate-900/90 backdrop-blur-sm border-r border-emerald-900/30 transition-all duration-300 flex flex-col">
          <!-- Logo -->
          <?php require_once("templates\logo.php") ?>
          </div>
        </div>

          <!-- Badge -->
        <div class="border-b border-slate-700 bg-slate-800/50 px-4 py-3">
          <span class="flex w-full items-center justify-center rounded-md <?php echo $sidebarBadgeClass ?? 'bg-slate-700'; ?> px-2 py-1 text-xs font-medium text-white">
            <?php echo $sidebarBadgeText ?? $_SESSION["currentTeam"]; ?>
          </span>
        </div>

          <!-- Navigation -->
          <?php require_once("templates\sidebar.php") ?>

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
          <?php require_once("templates\header.php") ?>

          <!-- Main -->
          <main class="flex-1 overflow-auto p-8">
            <div class="space-y-6">
              <!-- Title -->
              <div class="flex items-center justify-between">
                <div>
                  <h1 class="text-white text-2xl font-semibold">
                    Catálogo de Juegos
                  </h1>
                  <p class="text-slate-400">
                    Consulta y administración de juegos disponibles
                  </p>
                </div>

                <?php if($canManage): ?>
                <div class="contenedor-acciones-top">
                  <form method="POST" action="games.php<?php echo !empty($_GET['page']) ? '?page=' . $_GET['page'] : ''; ?><?php echo !empty($_GET['buscar']) ? '&buscar=' . urlencode($_GET['buscar']) : ''; ?>">
                      <button
                          type="submit"
                          name="FormJuego"
                          class="btn-registrar"
                          value="1"
                      >
                          + Registrar Juego
                      </button>
                      <?php 
                      if (!empty($_POST["FormJuego"]) || (isset($_SESSION["persisted_form_type"]) && $_SESSION["persisted_form_type"] === "crear_juego")) { 
                          $values = isset($_SESSION["persisted_form"]) ? $_SESSION["persisted_form"] : null;
                          createJuego($values); 
                          unset($_SESSION["persisted_form"]);
                          unset($_SESSION["persisted_form_type"]);
                      } 
                      ?>
                  </form>
                </div>
                <?php endif; ?>
              </div>
            </div>

            <form method="get" action="games.php">
              <div class="w-full p-6 border border-slate-700 rounded-lg bg-slate-900/40 mt-6 mb-6">
                <!-- FILTROS -->
                <div class="flex flex-col md:flex-row gap-4 items-stretch md:items-center">
                    <!-- SEARCH -->
                    <div class="flex-1 relative">
                        <input
                            type="text"
                            name="buscar"
                            value="<?php echo htmlspecialchars($_GET["buscar"] ?? ""); ?>"
                            placeholder="Buscar juegos por nombre o motor gráfico..."
                            class="w-full pl-10 h-9 rounded-md border border-slate-700 bg-slate-900 text-white px-3 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                    </div>
                </div>
                
                <div class="flex justify-between items-center mt-4">
                    <div class="flex gap-2">
                        <button
                            type="submit"
                            class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-800 hover:text-white text-sm"
                        >
                            Buscar
                        </button>

                        <?php if (!empty($_GET["buscar"])): ?>
                        <a href="games.php" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-800 hover:text-white text-sm flex items-center">
                            Limpiar
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
              </div>
            </form>

            <div class="w-full mt-6 overflow-x-auto rounded-lg border border-slate-700">
                <table class="w-full min-w-[800px] text-sm">
                    <thead>
                        <tr class="border-b border-slate-700 text-slate-300 bg-slate-900/60">
                            <th class="text-left p-3 whitespace-nowrap">ID</th>
                            <th class="text-left p-3 whitespace-nowrap">Nombre del Juego</th>
                            <th class="text-left p-3 whitespace-nowrap">Motor Gráfico</th>
                            <th class="text-center p-3 whitespace-nowrap">Estado</th>
                            <th class="text-center p-3 whitespace-nowrap">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="text-white">
                        <?php
                        if (isset($_POST["editar"])) {
                            $juegoEditar = array_filter($juegos, function($j) { return $j['id'] == $_POST['editar']; });
                            $juegoEditar = !empty($juegoEditar) ? array_values($juegoEditar)[0] : null;
                            if ($juegoEditar) {
                                createJuego($juegoEditar);
                            }
                        } else if (isset($_SESSION["persisted_form_type"]) && $_SESSION["persisted_form_type"] === "editar_juego") {
                            createJuego($_SESSION["persisted_form"]);
                            unset($_SESSION["persisted_form"]);
                            unset($_SESSION["persisted_form_type"]);
                        }
                        
                        if (!empty($juegos)) {
                            foreach ($juegos as $index => $juego) {
                                $estadoClase = ($juego["estado"] == 'activo') ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400';
                                echo '
                                <tr class="border-b border-slate-700 hover:bg-slate-800/50">
                                    <td class="p-2 text-emerald-400">
                                        JGO-' . str_pad($juego["id"], 3, "0", STR_PAD_LEFT) . '
                                    </td>
                                    <td class="p-2 font-medium">
                                        ' . htmlspecialchars($juego["nombre"]) . '
                                    </td>
                                    <td class="p-2 text-cyan-300">
                                        ' . htmlspecialchars($juego["motor_grafico"]) . '
                                    </td>
                                    <td class="p-2 text-center">
                                        <span class="px-2 py-1 rounded-full text-xs ' . $estadoClase . '">
                                            ' . ucfirst(htmlspecialchars($juego["estado"])) . '
                                        </span>
                                    </td>
                                    <td class="p-2 text-center">
                                        <div class="contenedor-acciones-tabla">
                                            <form method="POST" action="games.php' . (!empty($_GET['page']) ? '?page=' . $_GET['page'] : '') . (!empty($_GET['buscar']) ? '&buscar=' . urlencode($_GET['buscar']) : '') . '" class="inline-block m-0 p-0">
                                                <button class="btn-accion-icono icono-ver"
                                                name="editar"
                                                type="submit"
                                                title="Ver Detalles / Editar"
                                                value="' . $juego["id"] . '">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/></svg>
                                                </button>
                                            </form>
                                            ' . ($canManage ? '
                                            <form method="POST" action="games.php' . (!empty($_GET['page']) ? '?page=' . $_GET['page'] : '') . (!empty($_GET['buscar']) ? '&buscar=' . urlencode($_GET['buscar']) : '') . '" class="inline-block m-0 p-0">
                                                <button
                                                    type="submit"
                                                    name="eliminar"
                                                    value="' . $juego["id"] . '"
                                                    title="Eliminar"
                                                    class="btn-accion-icono icono-eliminar"
                                                    onclick="return confirm(\'¿Estás seguro que deseas eliminar este juego?\');"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-trash" viewBox="0 0 16 16"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg>
                                                </button>
                                            </form>' : '') . '
                                        </div>
                                    </td>
                                </tr>';
                            }
                        } else {
                            echo '<tr><td colspan="5" class="p-4 text-center text-slate-400">No se encontraron juegos.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- Controles de Paginación -->
            <?php if ($total_paginas > 1): ?>
            <div class="flex items-center justify-between border-t border-slate-700 bg-slate-900/40 px-4 py-3 sm:px-6 mt-4 rounded-lg">
                <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-slate-400">
                            Mostrando <span class="font-medium text-white"><?php echo $offset + 1; ?></span> a 
                            <span class="font-medium text-white"><?php echo min($offset + $registros_por_pagina, $total_juegos); ?></span> 
                            de <span class="font-medium text-white"><?php echo $total_juegos; ?></span> resultados
                        </p>
                    </div>
                    <div>
                        <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                            <?php 
                            $query_params = [];
                            if(!empty($_GET['buscar'])) $query_params['buscar'] = $_GET['buscar'];
                            
                            // Botón Anterior
                            if ($pagina_actual > 1) {
                                $prev_params = $query_params;
                                $prev_params['page'] = $pagina_actual - 1;
                                $prev_url = 'games.php?' . http_build_query($prev_params);
                                echo '<a href="' . $prev_url . '" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-700 hover:bg-slate-800 focus:z-20 focus:outline-offset-0">
                                    <span class="sr-only">Anterior</span>
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" /></svg>
                                </a>';
                            }

                            // Números de página
                            for ($i = 1; $i <= $total_paginas; $i++) {
                                $page_params = $query_params;
                                $page_params['page'] = $i;
                                $page_url = 'games.php?' . http_build_query($page_params);
                                if ($i == $pagina_actual) {
                                    echo '<a href="' . $page_url . '" aria-current="page" class="relative z-10 inline-flex items-center bg-emerald-600 px-4 py-2 text-sm font-semibold text-white focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600">' . $i . '</a>';
                                } else {
                                    echo '<a href="' . $page_url . '" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-white ring-1 ring-inset ring-slate-700 hover:bg-slate-800 focus:z-20 focus:outline-offset-0">' . $i . '</a>';
                                }
                            }

                            // Botón Siguiente
                            if ($pagina_actual < $total_paginas) {
                                $next_params = $query_params;
                                $next_params['page'] = $pagina_actual + 1;
                                $next_url = 'games.php?' . http_build_query($next_params);
                                echo '<a href="' . $next_url . '" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-700 hover:bg-slate-800 focus:z-20 focus:outline-offset-0">
                                    <span class="sr-only">Siguiente</span>
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" /></svg>
                                </a>';
                            }
                            ?>
                        </nav>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
          </main>
        </div>
      </div>

      <!-- Background Glow -->
      <div class="absolute top-0 right-0 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
      <div class="absolute bottom-0 left-0 w-96 h-96 bg-cyan-500/10 rounded-full blur-3xl pointer-events-none"></div>
    </div>
  </div>

  <!-- Toast Container para Alertas Elegantes -->
  <style>
    #toast-container {
        position: fixed;
        top: 24px;
        right: 24px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 12px;
        max-width: 450px;
        pointer-events: none;
    }
    #toast-message {
        pointer-events: auto;
        transform: translateX(120%) scale(0.9);
        opacity: 0;
        transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5), 0 8px 10px -6px rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(8px);
    }
    #toast-message.toast-visible {
        transform: translateX(0) scale(1);
        opacity: 1;
    }
    .toast-success {
        background-color: rgba(6, 78, 59, 0.95) !important;
        border: 1px solid #10b981 !important;
        color: #a7f3d0 !important;
        border-radius: 12px;
        padding: 16px 20px;
        font-family: system-ui, -apple-system, sans-serif;
        font-weight: 500;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .toast-error {
        background-color: rgba(127, 29, 29, 0.95) !important;
        border: 1px solid #ef4444 !important;
        color: #fca5a5 !important;
        border-radius: 12px;
        padding: 16px 20px;
        font-family: system-ui, -apple-system, sans-serif;
        font-weight: 500;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
  </style>
  <div id="toast-container">
      <?php if (isset($_SESSION["msg"])): ?>
          <div id="toast-message">
              <?php echo $_SESSION["msg"]; ?>
          </div>
          <?php unset($_SESSION["msg"]); ?>
      <?php endif; ?>
  </div>

  <script>
  document.addEventListener("DOMContentLoaded", function() {
      const toast = document.getElementById("toast-message");
      if (toast) {
          setTimeout(() => {
              toast.classList.add("toast-visible");
          }, 150);
          setTimeout(() => {
              toast.classList.remove("toast-visible");
              setTimeout(() => {
                  toast.remove();
              }, 600);
          }, 4500);
      }
  });
  </script>
</body>
</html>
