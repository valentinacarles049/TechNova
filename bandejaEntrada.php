<?php 
ob_start();
session_start(); 
require_once("funcs/session_security.php");

// Redirigir ya que el módulo de bandeja de entrada está temporalmente desactivado
$currentTeam = $_SESSION["currentTeam"] ?? '';
$redirect_target = ($currentTeam === 'monitoreo' || $currentTeam === 'administrador') ? 'dashboard.php' : 'ticketManegement.php';
if (!headers_sent()) {
    header("Location: " . $redirect_target);
    exit();
} else {
    echo '<script>window.location.href=' . json_encode($redirect_target) . ';</script>';
    exit();
}

require_once("db.php");

$queryAbiertos = mysqli_query($conn, "SELECT COUNT(id) AS total FROM tickets WHERE estado = 'abierto'");
$totalAbiertos = mysqli_fetch_assoc($queryAbiertos)['total'];

$queryEnProgreso = mysqli_query($conn, "SELECT COUNT(id) AS total FROM tickets WHERE estado = 'en progreso'");
$totalEnProgreso = mysqli_fetch_assoc($queryEnProgreso)['total'];

$queryCerrados = mysqli_query($conn, "SELECT COUNT(id) AS total FROM tickets WHERE estado = 'cerrado'");
$totalCerrados = mysqli_fetch_assoc($queryCerrados)['total'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechNova - Bandeja de Entrada</title>
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <link rel="stylesheet" href="assets/index-V-K5O4c4.css">
</head>
<body class="bg-slate-950 text-white overflow-hidden">
  <div id="root">
    <div class="h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-emerald-950 overflow-hidden">
      <!-- Background -->
      <div class="absolute inset-0 opacity-5">
        <div
          class="absolute inset-0"
          style="
            background-image: radial-gradient(
              circle at 2px 2px,
              rgb(16, 185, 129) 1px,
              transparent 0px
            );
            background-size: 40px 40px;
          "
        ></div>
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
          <span
            class="flex w-full items-center justify-center rounded-md <?php echo $sidebarBadgeClass; ?> px-2 py-1 text-xs font-medium text-white"
          >
            <?php echo $sidebarBadgeText; ?>
          </span>
        </div>

        <!-- Nav -->
        

         <?php require_once("templates\sidebar.php") ?>

        <!-- Footer -->
        <div class="border-t border-slate-800 p-4">
          <button
            class="flex w-full items-center justify-center rounded-md py-2 text-slate-400 transition hover:bg-slate-800 hover:text-white transition-all"
          >
            ✕
          </button>
        </div>
      </aside>

      <!-- Contenido -->
      <div class="flex flex-1 flex-col overflow-hidden">

        <!-- Header -->
        <?php require_once('templates\header.php') ?>

        <!-- Main -->
        <main class="flex-1 overflow-auto p-8">

          <div class="mb-6">
            <h1 class="mb-2 text-2xl font-bold text-white">
              Bandeja de Entrada - Soporte
            </h1>

            <p class="text-slate-400">
              Reportes externos de jugadores y respuestas de otros equipos
            </p>
          </div>

          <!-- Cards -->
          <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">

            <div class="rounded-xl border border-slate-700 bg-slate-800/50 p-6">
              <div class="mb-2 text-sm text-slate-300">
                Nuevos
              </div>

              <div class="text-3xl font-bold text-white">
                <?php echo $totalAbiertos; ?>
              </div>
            </div>

            <div class="rounded-xl border border-slate-700 bg-slate-800/50 p-6">
              <div class="mb-2 text-sm text-slate-300">
                En Proceso
              </div>

              <div class="text-3xl font-bold text-white">
                <?php echo $totalEnProgreso; ?>
              </div>
            </div>

            <div class="rounded-xl border border-slate-700 bg-slate-800/50 p-6">
              <div class="mb-2 text-sm text-slate-300">
                Cerrados
              </div>

              <div class="text-3xl font-bold text-white">
                <?php echo $totalCerrados; ?>
              </div>
            </div>
          </div>

          <!-- Buscador -->
          <div class="mb-6 rounded-xl border border-slate-700 bg-slate-800/50 p-6">
            <input
              type="text"
              placeholder="Buscar en bandeja de entrada..."
              class="w-full rounded-md border border-slate-700 bg-slate-900 px-4 py-2 text-white outline-none transition focus:border-cyan-500"
            />
          </div>

        </main>
      </div>
    </div>

    <!-- Blur decorativo -->
    <div class="pointer-events-none absolute right-0 top-0 h-96 w-96 rounded-full bg-emerald-500/10 blur-3xl"></div>

    <div class="pointer-events-none absolute bottom-0 left-0 h-96 w-96 rounded-full bg-cyan-500/10 blur-3xl"></div>
  </div>
</div>

</body>
</html>