<?php
/**
 * Funciones para cargar y acceder a variables de entorno desde un archivo .env
 * TechNova - HU-40: Conexión segura con el asistente virtual
 */

if (!function_exists('cargarEnv')) {
    /**
     * Carga las variables definidas en un archivo .env en $_ENV, $_SERVER y putenv()
     * 
     * @param string|null $rutaDirectorio Ruta del directorio donde reside el archivo .env (por defecto la raíz del proyecto)
     * @return bool True si se cargó el archivo, False si no existe
     */
    function cargarEnv(?string $rutaDirectorio = null): bool {
        if ($rutaDirectorio === null) {
            $rutaDirectorio = dirname(__DIR__);
        }

        $archivoEnv = rtrim($rutaDirectorio, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env';

        if (!file_exists($archivoEnv) || !is_readable($archivoEnv)) {
            return false;
        }

        $lineas = file($archivoEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lineas === false) {
            return false;
        }

        foreach ($lineas as $linea) {
            $linea = trim($linea);

            // Ignorar líneas vacías o comentarios
            if ($linea === '' || strpos($linea, '#') === 0) {
                continue;
            }

            // Separar por el primer signo igual
            if (strpos($linea, '=') !== false) {
                list($nombre, $valor) = explode('=', $linea, 2);
                $nombre = trim($nombre);
                $valor = trim($valor);

                // Quitar comillas simples o dobles que envuelvan el valor
                if (
                    (strlen($valor) >= 2) && 
                    (($valor[0] === '"' && substr($valor, -1) === '"') ||
                     ($valor[0] === "'" && substr($valor, -1) === "'"))
                ) {
                    $valor = substr($valor, 1, -1);
                }

                // Asignar en los diferentes ámbitos si no existen ya
                if (!array_key_exists($nombre, $_ENV)) {
                    $_ENV[$nombre] = $valor;
                }
                if (!array_key_exists($nombre, $_SERVER)) {
                    $_SERVER[$nombre] = $valor;
                }
                putenv("{$nombre}={$valor}");
            }
        }

        return true;
    }
}

if (!function_exists('env')) {
    /**
     * Obtiene el valor de una variable de entorno con soporte para valor por defecto
     * 
     * @param string $clave Nombre de la variable
     * @param mixed $default Valor de contingencia si no está definida
     * @return mixed
     */
    function env(string $clave, $default = null) {
        $valor = getenv($clave);
        if ($valor !== false) {
            return $valor;
        }

        if (isset($_ENV[$clave])) {
            return $_ENV[$clave];
        }

        if (isset($_SERVER[$clave])) {
            return $_SERVER[$clave];
        }

        return $default;
    }
}
