<?php
$currentPage = basename($_SERVER['PHP_SELF']);

function getMenuClass($isActive) {
    if ($isActive) {
        return "active-menu-hover flex w-full items-center gap-3 rounded-md border border-emerald-800/50 bg-gradient-to-r from-emerald-600/20 to-cyan-600/20 px-4 py-2 text-sm text-emerald-400 transition hover:opacity-90";
    }
    return "flex w-full items-center gap-3 rounded-md px-4 py-2 text-sm text-slate-300 transition hover:bg-slate-800 hover:text-white";
}
?>
<style>
.active-menu-hover:hover {
    opacity: 0.9 !important;
}
</style>

<nav class="flex-1 space-y-2 overflow-y-auto p-4">

  <?php if (false): ?>
  <!-- Bandeja de Entrada -->
  <a href="bandejaEntrada.php" class="<?php echo getMenuClass($currentPage === 'bandejaEntrada.php'); ?>">
    <svg class="h-5 w-5 flex-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0a2 2 0 01-2 2H6a2 2 0 01-2-2m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
    </svg>
    <span>Bandeja de Entrada</span>
  </a>
  <?php endif; ?>

<?php if (isset($_SESSION["currentTeam"]) && ($_SESSION["currentTeam"] === "monitoreo" || $_SESSION["currentTeam"] === "administrador")) { ?>
  <!-- Dashboard -->
  <a href="dashboard.php" class="<?php echo getMenuClass($currentPage === 'dashboard.php'); ?>">
    <svg class="h-5 w-5 flex-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
    </svg>
    <span>Dashboard</span>
  </a>
<?php } ?>

<?php if (isset($_SESSION["currentTeam"]) && in_array($_SESSION["currentTeam"], ["desarrollo", "administrador", "monitoreo"])) { ?>
  <!-- Tablero de Incidencia -->
  <a href="incidentes.php" class="<?php echo getMenuClass($currentPage === 'incidentes.php'); ?>">
    <svg class="h-5 w-5 flex-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
    </svg>
    <span>Tablero de Incidencia</span>
  </a>
<?php } ?>
  
  <!-- Tickets -->
  <a href="ticketManegement.php" class="<?php echo getMenuClass($currentPage === 'ticketManegement.php'); ?>">
    <svg class="h-5 w-5 flex-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
    </svg>
    <span>Tickets</span>
  </a>

<?php if (isset($_SESSION["currentTeam"]) && $_SESSION["currentTeam"] !== "desarrollo") { ?>
  <!-- Tablero de Calidad -->
  <a href="feedback.php" class="<?php echo getMenuClass($currentPage === 'feedback.php'); ?>">
    <svg class="h-5 w-5 flex-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
    </svg>
    <span>Tablero de Calidad</span>
  </a>
<?php } ?>

  <!-- Juegos -->
  <a href="games.php" class="<?php echo getMenuClass($currentPage === 'games.php'); ?>">
    <svg class="h-5 w-5 flex-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
      <path stroke-linecap="round" stroke-linejoin="round" d="M18.5 7h-13C4.12 7 3 8.12 3 9.5v5c0 1.38 1.12 2.5 2.5 2.5h13c1.38 0 2.5-1.12 2.5-2.5v-5C21 8.12 19.88 7 18.5 7z"></path>
      <path stroke-linecap="round" stroke-linejoin="round" d="M9 12H7m1 1v-2m7.5 1.5a.5.5 0 110-1 .5.5 0 010 1zm2 1.5a.5.5 0 110-1 .5.5 0 010 1z"></path>
    </svg>
    <span>Juegos</span>
  </a>

  <!-- Drivers -->
  <a href="drivers.php" class="<?php echo getMenuClass($currentPage === 'drivers.php'); ?>">
    <svg class="h-5 w-5 flex-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
    </svg>
    <span>Drivers</span>
  </a>

  <!-- Perfil -->
  <a href="profile.php" class="<?php echo getMenuClass($currentPage === 'profile.php'); ?>">
    <svg class="h-5 w-5 flex-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
    </svg>
    <span>Perfil</span>
  </a>

</nav>
<?php include_once __DIR__ . '/chatbot.php'; ?>