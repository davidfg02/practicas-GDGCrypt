<?php

$host = "localhost";
$usuario = "TU_USUARIO";
$password = "TU_PASSWORD";
$bd = "gdgcryptbbdd";

$con = new mysqli($host, $usuario, $password, $bd);

if($con->connect_error) {
    die("Error de conexion: " . $con->connect_error);
}

$con->set_charset("utf8");

