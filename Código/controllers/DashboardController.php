<?php

require_once __DIR__ . "/../models/LogModel.php";
require_once __DIR__ . "/../helpers/funciones.php";

class DashboardController
{
    private mysqli $con;
    private LogModel $logModel;
    private string $modo;
    private ?string $string2Admin;

    public function __construct(mysqli $con, string $modo = "hyperAdmin", ?string $string2Admin = null)
    {
        $this->con = $con;
        $this->logModel = new LogModel($con);
        $this->modo = $modo;
        $this->string2Admin = $string2Admin;
    }

    public function mostrarDashboard(): void
    {
        $periodo = $_GET["periodo"] ?? "ultimo_mes";

        $filtroTipo       = $_GET["tipo_log"] ?? "";
        $filtroModulo     = $_GET["modulo"] ?? "";
        $filtroFechaDesde = $_GET["fecha_desde"] ?? "";
        $filtroFechaHasta = $_GET["fecha_hasta"] ?? "";

        $filtroUsuario       = $_GET["usuario"] ?? "";
        $filtroTicketString2 = $_GET["ticket_string2"] ?? "";
        $filtroUsuarioCn     = "";

        $tipoOperacion = $_GET["tipo_operacion"] ?? "todas";
        $agrupacionTicket = $_GET["agrupacion_ticket"] ?? "dia";
        $filtroGraficoOrigen = $_GET["grafico_origen"] ?? "top_origen";

        if (!in_array($agrupacionTicket, ["dia", "hora"], true)) {
            $agrupacionTicket = "dia";
        }

        if (!in_array($tipoOperacion, ["todas", "firma", "crc"], true)) {
            $tipoOperacion = "todas";
        }

        if (!in_array($filtroGraficoOrigen, ["top_origen", "todos_origenes"], true)) {
            $filtroGraficoOrigen = "top_origen";
        }

        $hoy = new DateTime();

        if ($periodo !== "personalizado") {
            $filtroFechaHasta = $hoy->format("Y-m-d");

            if ($periodo === "ultimo_mes") {
                $filtroFechaDesde = (clone $hoy)->modify("-1 month")->format("Y-m-d");
            } elseif ($periodo === "trimestre") {
                $filtroFechaDesde = (clone $hoy)->modify("-3 months")->format("Y-m-d");
            } elseif ($periodo === "semestre") {
                $filtroFechaDesde = (clone $hoy)->modify("-6 months")->format("Y-m-d");
            } elseif ($periodo === "ultimo_anio") {
                $filtroFechaDesde = (clone $hoy)->modify("-1 year")->format("Y-m-d");
            } else {
                $periodo = "ultimo_mes";
                $filtroFechaDesde = (clone $hoy)->modify("-1 month")->format("Y-m-d");
            }
        }

        $hayFiltros = ($filtroFechaDesde !== "" && $filtroFechaHasta !== "");
        $mensajeError = "";

        if ($filtroFechaDesde === "" || $filtroFechaHasta === "") {
            $mensajeError = "Debes seleccionar una fecha desde y una fecha hasta para cargar el dashboard.";
        }

        if ($filtroFechaDesde !== "" && $filtroFechaHasta !== "") {
            if (strtotime($filtroFechaDesde) > strtotime($filtroFechaHasta)) {
                $hayFiltros = false;
                $mensajeError = "La fecha desde no puede ser mayor que la fecha hasta.";
            }
        }

        $tiposLogs = $this->logModel->obtenerTiposLogs();
        $usuarios = $this->logModel->obtenerUsuarios();

        if ($this->modo === "admin" && $this->string2Admin !== null) {
            $filtroTicketString2 = $this->string2Admin;
            $filtroUsuario = "";
        } elseif ($filtroUsuario !== "") {
            foreach ($usuarios as $usuario) {
                if ((string)$usuario["id"] === (string)$filtroUsuario) {
                    $filtroTicketString2 = $usuario["string2"];
                    $filtroUsuarioCn = $usuario["cn"] ?? "";
                    break;
                }
            }
        }

        $datosTipo = [];
        $datosOrigen = [];
        $datosOperador = [];
        $datosEvento = [];
        $datosFirma = [];
        $datosAcceso = [];
        $datosTicketTimeline = [];

        $totalLogs = 0;
        $numOrigenes = 0;
        $numOperadores = 0;
        $numTipos = 0;
        $totalTicketBai = 0;

        if ($hayFiltros) {
            $condicionesBase = [];

            if ($filtroTipo !== "") {
                $condicionesBase[] = "logs.tipo_log = " . intval($filtroTipo);
            }

            if (trim($filtroModulo) !== "") {
                $condicionPalabrasClave = crearFiltroPalabrasClave($this->con, $filtroModulo);

                if ($condicionPalabrasClave !== "") {
                    $condicionesBase[] = $condicionPalabrasClave;
                }
            }

            if (trim($filtroUsuarioCn) !== "") {
                $cn = $this->con->real_escape_string(trim($filtroUsuarioCn));

                $condicionesBase[] = "
                    (
                        logs.operador LIKE '%CN=$cn,%'
                        OR logs.operador LIKE '%CN= $cn,%'
                        OR logs.evento LIKE '%CN=$cn,%'
                        OR logs.evento LIKE '%CN= $cn,%'
                        OR logs.cert2log LIKE '%CN=$cn,%'
                        OR logs.cert2log LIKE '%CN= $cn,%'
                    )
                ";
            }

            $fechaDesde = $this->con->real_escape_string($filtroFechaDesde);
            $fechaHasta = $this->con->real_escape_string($filtroFechaHasta);

            $condicionesBase[] = "logs.`date` >= '$fechaDesde 00:00:00'";
            $condicionesBase[] = "logs.`date` <= '$fechaHasta 23:59:59'";

            $whereBase = crearWhere($condicionesBase);

            $this->logModel->crearTablaTemporalLogs($whereBase);

            $totalLogs = $this->logModel->obtenerTotalLogs();

            $condicionesTicket = [];

            if ($tipoOperacion === "firma") {
                $condicionesTicket[] = "logs.evento LIKE 'TicketBai with%'";
            } elseif ($tipoOperacion === "crc") {
                $condicionesTicket[] = "logs.evento LIKE 'TicketBai CRC%'";
            } else {
                $condicionesTicket[] = "
                    (
                        logs.evento LIKE 'TicketBai with%'
                        OR logs.evento LIKE 'TicketBai CRC%'
                    )
                ";
            }

            if (trim($filtroTicketString2) !== "") {
                $string2 = $this->con->real_escape_string(trim($filtroTicketString2));
                $condicionesTicket[] = "logs.evento LIKE '%$string2%'";
            }

            $whereTicket = crearWhere($condicionesTicket);

            $datosTicketTimeline = $this->logModel->obtenerTimelineTicketBai($whereTicket, $agrupacionTicket);
            $totalTicketBai = array_sum(array_column($datosTicketTimeline, "total"));

            $whereTipo = crearWhere([
                "tipologs.tipo IS NOT NULL",
                "TRIM(tipologs.tipo) <> ''",
                "LOWER(TRIM(tipologs.tipo)) NOT IN ('otros', 'otro', 'other')"
            ]);

            $datosTipo = $this->logModel->obtenerDatosTipo($whereTipo);
            $numTipos = count($datosTipo);

            $whereOrigen = crearWhere([
                "logs.origen IS NOT NULL",
                "TRIM(logs.origen) <> ''"
            ]);

            $datosOrigen = $this->logModel->obtenerDatosOrigen($whereOrigen, $filtroGraficoOrigen);
            $numOrigenes = count($datosOrigen);

            $whereOperador = crearWhere([
                "logs.operador IS NOT NULL",
                "TRIM(logs.operador) <> ''"
            ]);

            $datosOperador = $this->logModel->obtenerDatosOperador($whereOperador);
            $numOperadores = count($datosOperador);

            $datosEvento = $this->logModel->obtenerDatosEvento();
            $datosFirma = $this->logModel->obtenerDatosFirma();
            $datosAcceso = $this->logModel->obtenerDatosAcceso();
        }

        $datosVista = [
            "modo" => $this->modo,
            "periodo" => $periodo,

            "filtroTipo" => $filtroTipo,
            "filtroModulo" => $filtroModulo,
            "filtroFechaDesde" => $filtroFechaDesde,
            "filtroFechaHasta" => $filtroFechaHasta,

            "filtroUsuario" => $filtroUsuario,
            "usuarios" => $usuarios,
            "filtroTicketString2" => $filtroTicketString2,
            "tipoOperacion" => $tipoOperacion,
            "agrupacionTicket" => $agrupacionTicket,
            "filtroGraficoOrigen" => $filtroGraficoOrigen,

            "hayFiltros" => $hayFiltros,
            "mensajeError" => $mensajeError,
            "tiposLogs" => $tiposLogs,

            "datosTipo" => $datosTipo,
            "datosOrigen" => $datosOrigen,
            "datosOperador" => $datosOperador,
            "datosEvento" => $datosEvento,
            "datosFirma" => $datosFirma,
            "datosAcceso" => $datosAcceso,
            "datosTicketTimeline" => $datosTicketTimeline,

            "totalLogs" => $totalLogs,
            "numOrigenes" => $numOrigenes,
            "numOperadores" => $numOperadores,
            "numTipos" => $numTipos,
            "totalTicketBai" => $totalTicketBai,

            "maxTipo" => obtenerMaximo($datosTipo),
            "maxOperador" => obtenerMaximo($datosOperador),
            "maxEvento" => obtenerMaximo($datosEvento),
            "maxAcceso" => obtenerMaximo($datosAcceso),

            "pieOrigenGradient" => crearConicGradient($datosOrigen),
            "pieFirmaGradient" => crearConicGradient($datosFirma)
        ];

        require_once __DIR__ . "/../views/Dashboard.php";
    }
}