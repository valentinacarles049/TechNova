<?php
require_once("./db.php");

function redirigirDriver($url = "drivers.php") {
    if (!headers_sent()) {
        header("Location: " . $url);
        exit;
    }
    echo '<script>window.location.href = ' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url) . '"></noscript>';
    exit;
}

function normalizarTextoDriver($texto) {
    $texto = trim($texto);
    $texto = preg_replace('/\s+/', ' ', $texto);
    $texto = strtolower($texto);
    return $texto;
}

if (!empty($_POST["crear_driver"])) {
    $nombre = mysqli_real_escape_string($conn, trim($_POST["nombre"] ?? ""));
    $version = mysqli_real_escape_string($conn, trim($_POST["version"] ?? ""));
    $fecha = mysqli_real_escape_string($conn, trim($_POST["fecha"] ?? ""));
    $tamanio = !empty($_POST["tamanio"]) ? intval($_POST["tamanio"]) : 0;
    $categoriaDriver = mysqli_real_escape_string($conn, trim($_POST["categoriaDriver"] ?? ""));

    if (!empty($nombre) && !empty($version) && !empty($fecha) && $tamanio > 0 && !empty($categoriaDriver)) {
        
        $query = "
            INSERT INTO drivers
            (nombre, version, fecha, tamanio, categoriaDriver, estado)
            VALUES
            (
                '$nombre',
                '$version',
                '$fecha',
                $tamanio,
                '$categoriaDriver',
                'activo'
            )
        ";

        if(mysqli_query($conn, $query)){
            $_SESSION["msg"] = '<div class="toast-success">✅ ¡Driver creado exitosamente!</div>';
            unset($_SESSION["persisted_form"]);
            unset($_SESSION["persisted_form_type"]);
        } else {
            $_SESSION["msg"] = '<div class="toast-error">❌ Error: ' . htmlspecialchars(mysqli_error($conn)) . '</div>';
            $_SESSION["persisted_form"] = $_POST;
            $_SESSION["persisted_form_type"] = "crear_driver";
        }
        redirigirDriver("drivers.php");
    }
    $_SESSION["msg"] = '<div class="toast-error">❌ Error: No se pudo crear el driver - Todos los campos son obligatorios.</div>';
    $_SESSION["persisted_form"] = $_POST;
    $_SESSION["persisted_form_type"] = "crear_driver";
    redirigirDriver("drivers.php");
}

if (!empty($_POST["editar_driver"])) {
    $id = intval($_POST["editar_driver"]);
    $nombre = mysqli_real_escape_string($conn, trim($_POST["nombre"] ?? ""));
    $version = mysqli_real_escape_string($conn, trim($_POST["version"] ?? ""));
    $fecha = mysqli_real_escape_string($conn, trim($_POST["fecha"] ?? ""));
    $tamanio = !empty($_POST["tamanio"]) ? intval($_POST["tamanio"]) : 0;
    $categoriaDriver = mysqli_real_escape_string($conn, trim($_POST["categoriaDriver"] ?? ""));

    if (!empty($nombre) && !empty($version) && !empty($fecha) && $tamanio > 0 && !empty($categoriaDriver)) {

        $query = "
            UPDATE drivers SET
                nombre = '$nombre',
                version = '$version',
                fecha = '$fecha',
                tamanio = $tamanio,
                categoriaDriver = '$categoriaDriver'
            WHERE id = $id
        ";
        
        if(mysqli_query($conn, $query)){
            $_SESSION["msg"] = '<div class="toast-success">✅ Driver editado exitosamente.</div>';
            unset($_SESSION["persisted_form"]);
            unset($_SESSION["persisted_form_type"]);
        } else {
            $_SESSION["msg"] = '<div class="toast-error">❌ Error: ' . htmlspecialchars(mysqli_error($conn)) . '</div>';
            $_SESSION["persisted_form"] = $_POST;
            $_SESSION["persisted_form"]["id"] = $id;
            $_SESSION["persisted_form_type"] = "editar_driver";
        }
    } else {
        $_SESSION["msg"] = '<div class="toast-error">❌ Error: No se pudo editar el driver - Todos los campos son obligatorios.</div>';
        $_SESSION["persisted_form"] = $_POST;
        $_SESSION["persisted_form"]["id"] = $id;
        $_SESSION["persisted_form_type"] = "editar_driver";
    }

    redirigirDriver("drivers.php");
}

function obtenerDrivers($conn){
    $sql = "SELECT * FROM drivers WHERE estado != 'inactivo'";

    if (!empty($_POST["inputBuscar"])) {
        $buscar = mysqli_real_escape_string($conn, trim($_POST["inputBuscar"]));
        $sql .= "
            AND (
                nombre LIKE '%$buscar%'
                OR version LIKE '%$buscar%'
                OR categoriaDriver LIKE '%$buscar%'
            )
        ";
    }

    $sql .= " ORDER BY id DESC";

    $query = mysqli_query($conn, $sql);
    
    if(!$query) return [];

    return mysqli_fetch_all($query, MYSQLI_ASSOC);
}

function eliminarDriver($conn){
    $idEliminar = intval($_POST["eliminar"]);

    // Soft delete (baja lógica)
    $queryDelete = "
        UPDATE drivers 
        SET estado = 'inactivo'
        WHERE id = $idEliminar
    ";

    if(mysqli_query($conn, $queryDelete)){
        $_SESSION["msg"] = '<div class="toast-success">✅ Driver eliminado exitosamente.</div>';
    } else {
        $_SESSION["msg"] = '<div class="toast-error">❌ Error al eliminar el driver.</div>';
    }

    redirigirDriver("drivers.php");
}

//Create or edit modal
function createDriver($values = null){
  global $conn;
  require_once("db.php");

  // Only soporte can create or edit
  $canEdit = (isset($_SESSION["currentTeam"]) && ($_SESSION["currentTeam"] === "soporte" || $_SESSION["currentTeam"] === "administrador"));
  $disabled_attr = (!$canEdit) ? 'disabled style="background-color: #1e293b !important; color: #94a3b8 !important; border: 1px solid #334155 !important; opacity: 0.6; cursor: not-allowed;"' : 'style="background-color: #1e293b !important; color: #ffffff !important; border: 1px solid #475569 !important;"';

  $modal_title = "Editar Driver";
  if (is_null($values)) {
      $modal_title = "Crear Nuevo Driver";
  } else if (!$canEdit) {
      $modal_title = "Detalles del Driver";
  }

  $cancel_btn_text = (!$canEdit) ? "Cerrar" : "Cancelar";

  $button = "";

  if ($canEdit) {
      if (is_null($values)) {
          $button = '<button
              type="submit"
              class="btn-registrar"
              name="crear_driver"
              value="1"
          >
              Crear Driver
          </button>';
      } else { 
          $button = '<button
              type="submit"
              class="btn-registrar"
              name="editar_driver"
              value="'.$values["id"].'">
              Guardar Cambios
          </button>'; 
      }
  }

  echo '
  <div role="dialog" id="create-driver-modal" aria-modal="true" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
    <div class="relative w-full max-w-2xl rounded-2xl border border-slate-600 bg-[#0f172a] p-6 shadow-2xl" style="background-color: #0f172a !important; border: 1px solid #475569 !important;">

      <!-- Header -->
      <div class="mb-6 flex items-start justify-between">
        <div>
          <h2 class="text-2xl font-semibold text-white" style="color: #ffffff !important;">
          ' . $modal_title . '
          </h2>
          <p class="mt-1 text-sm text-slate-400" style="color: #94a3b8 !important;">
            ' . ($canEdit ? 'Completa la información del driver.' : 'Visualizando información del driver.') . '
          </p>
        </div>
      </div>

      <!-- Form -->
      <form class="space-y-5" method="POST">

        <!-- Nombre -->
        <div>
          <label for="nombre" class="mb-2 block text-sm font-medium text-white" style="color: #ffffff !important;">
            Nombre del Fabricante / Driver
          </label>
          <input
            id="nombre"
            name="nombre"
            type="text"
            placeholder="Ej: NVIDIA GeForce Game Ready"
            value="' . htmlspecialchars($values["nombre"] ?? "") . '"
            class="w-full rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-3 text-sm text-white placeholder:text-slate-400 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
            ' . $disabled_attr . '
            required
          />
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <!-- Versión -->
            <div>
            <label for="version" class="mb-2 block text-sm font-medium text-white" style="color: #ffffff !important;">
                Versión
            </label>
            <input
                id="version"
                name="version"
                type="text"
                placeholder="Ej: 545.84"
                value="' . htmlspecialchars($values["version"] ?? "") . '"
                class="w-full rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-3 text-sm text-white placeholder:text-slate-400 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
                ' . $disabled_attr . '
                required
            />
            </div>

            <!-- Categoría -->
            <div>
            <label for="categoriaDriver" class="mb-2 block text-sm font-medium text-white" style="color: #ffffff !important;">
                Categoría / Tipo
            </label>
            <input
                id="categoriaDriver"
                name="categoriaDriver"
                type="text"
                placeholder="Ej: Tarjeta Gráfica"
                value="' . htmlspecialchars($values["categoriaDriver"] ?? "") . '"
                class="w-full rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-3 text-sm text-white placeholder:text-slate-400 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
                ' . $disabled_attr . '
                required
            />
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <!-- Fecha -->
            <div>
            <label for="fecha" class="mb-2 block text-sm font-medium text-white" style="color: #ffffff !important;">
                Fecha de Lanzamiento
            </label>
            <input
                id="fecha"
                name="fecha"
                type="date"
                value="' . htmlspecialchars($values["fecha"] ?? "") . '"
                class="w-full rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-3 text-sm text-white placeholder:text-slate-400 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
                ' . $disabled_attr . '
                required
            />
            </div>

            <!-- Tamaño -->
            <div>
            <label for="tamanio" class="mb-2 block text-sm font-medium text-white" style="color: #ffffff !important;">
                Tamaño (MB)
            </label>
            <input
                id="tamanio"
                name="tamanio"
                type="number"
                min="1"
                placeholder="Ej: 720"
                value="' . htmlspecialchars($values["tamanio"] ?? "") . '"
                class="w-full rounded-xl border border-slate-600 bg-[#1e293b] px-4 py-3 text-sm text-white placeholder:text-slate-400 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
                ' . $disabled_attr . '
                required
            />
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4">
          <button
            type="submit"
            class="rounded-xl border border-slate-600 px-5 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800"
            name="FormDriverCancel"
            value="1"
            style="color: #cbd5e1 !important; border-color: #475569 !important; background-color: #0f172a !important;"
            formnovalidate
          >
            ' . $cancel_btn_text . '
          </button>
          ' . $button . '
        </div>

      </form>
    </div>
  </div>'; 
}
?>
