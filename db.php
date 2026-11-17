<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');

$conn = mysqli_connect(
    "localhost",
    "root",
    "root",
    "technova"
);

if (!$conn) {
    die("Error de conexión: " . mysqli_connect_error());
}

mysqli_query($conn, "SET time_zone = '-03:00'");
?>
