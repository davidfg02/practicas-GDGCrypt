<?php

require_once __DIR__ . "/../config/conexion.php";
require_once __DIR__ . "/../controllers/DashboardController.php";

$controller = new DashboardController($con, "hyperAdmin");
$controller->mostrarDashboard();

$con->close();