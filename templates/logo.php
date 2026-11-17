<?php
$sidebarBadgeClass = "bg-gradient-to-r from-cyan-600 to-blue-600";
$sidebarBadgeText = "Equipo de Soporte";
if (isset($_SESSION["currentTeam"])) {
    if ($_SESSION["currentTeam"] === "desarrollo") {
        $sidebarBadgeClass = "bg-gradient-to-r from-emerald-600 to-teal-600";
        $sidebarBadgeText = "Equipo de Desarrollo";
    } elseif ($_SESSION["currentTeam"] === "monitoreo") {
        $sidebarBadgeClass = "bg-gradient-to-r from-violet-600 to-indigo-600";
        $sidebarBadgeText = "Equipo de Monitoreo";
    } elseif ($_SESSION["currentTeam"] === "administrador") {
        $sidebarBadgeClass = "bg-gradient-to-r from-rose-600 to-orange-600";
        $sidebarBadgeText = "Administrador";
    }
}
?>
<style>
html {
    color-scheme: dark;
    scrollbar-width: thin;
    scrollbar-color: #334155 #0b1121;
}
::-webkit-scrollbar {
    width: 8px;
}
::-webkit-scrollbar-track {
    background-color: #0b1121;
}
::-webkit-scrollbar-thumb {
    background-color: #334155;
    border-radius: 4px;
}
::-webkit-scrollbar-thumb:hover {
    background-color: #475569;
}

.contenedor-acciones-top {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 16px;
  margin-bottom: 24px;
}

.btn-exportar {
  background-color: transparent !important;
  border: 1px solid #334155 !important;
  color: #e2e8f0 !important;
  font-weight: 500 !important;
  display: inline-flex !important;
  align-items: center !important;
  gap: 8px !important;
  border-radius: 6px !important;
  padding: 8px 16px !important;
  cursor: pointer !important;
  transition: all 0.2s ease-in-out !important;
  text-decoration: none !important;
}
.btn-exportar:hover {
  background-color: rgba(51, 65, 85, 0.3) !important;
  border-color: #475569 !important;
}

.contenedor-acciones-tabla {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 12px;
}

.btn-accion-icono {
  background: transparent !important;
  border: none !important;
  padding: 4px !important;
  cursor: pointer !important;
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  transition: transform 0.2s ease, opacity 0.2s ease !important;
  font-size: 1.1rem !important;
  text-decoration: none !important;
}
.btn-accion-icono:hover {
  transform: scale(1.15) !important;
  opacity: 0.8 !important;
}

.icono-ver {
  color: #06b6d4 !important;
}

.icono-eliminar {
  color: #f87171 !important;
}

/* Soporte */
.bg-cyan-600 {
    background-color: #0891b2 !important;
}
.from-cyan-600.to-blue-600,
[class*="from-cyan-600"][class*="to-blue-600"] {
    background: linear-gradient(to right, #0891b2, #2563eb) !important;
}

/* Desarrollo */
.bg-emerald-600 {
    background-color: #059669 !important;
}
.from-emerald-600.to-teal-600,
[class*="from-emerald-600"][class*="to-teal-600"] {
    background: linear-gradient(to right, #059669, #0d9488) !important;
}

/* Monitoreo */
.bg-violet-600 {
    background-color: #7c3aed !important;
}
.from-violet-600.to-indigo-600,
[class*="from-violet-600"][class*="to-indigo-600"] {
    background: linear-gradient(to right, #7c3aed, #4f46e5) !important;
}

/* Administrador */
.bg-rose-600 {
    background-color: #e11d48 !important;
}
.from-rose-600.to-orange-600,
[class*="from-rose-600"][class*="to-orange-600"] {
    background: linear-gradient(to right, #e11d48, #ea580c) !important;
}

/* Override Logo Height to keep it large */
.h-16 {
    height: auto !important;
}

/* Unified registrar button styles */
.btn-registrar {
  background: linear-gradient(to right, #0bb17b, #0095b5) !important;
  border: none !important;
  border-radius: 8px !important;
  color: #ffffff !important;
  font-weight: 500 !important;
  text-align: center !important;
  padding: 10px 20px !important;
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  gap: 8px !important;
  transition: all 0.2s ease-in-out !important;
  cursor: pointer !important;
  text-decoration: none !important;
}
.btn-registrar:hover {
  filter: brightness(1.1) !important;
  transform: translateY(-1px) !important;
}
</style>
<div class="border-b border-slate-800 p-6">
  <div class="flex items-center justify-center">
    <!-- Image Logo -->
    <img src="assets/logo.png" alt="TECHNOVA Logo" class="h-16 w-auto object-contain">
