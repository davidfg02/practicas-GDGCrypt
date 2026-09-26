<?php

require_once __DIR__ . "/../config/conexion.php";
require_once __DIR__ . "/../controllers/DashboardController.php";

$string2Admin = "DEMO_USER";

$controller = new DashboardController($con, "admin", $string2Admin);
$controller->mostrarDashboard();

$con->close();