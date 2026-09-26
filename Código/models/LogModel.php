<?php

class LogModel
{
    private mysqli $con;
    private string $tablaTemporal = "tmp_logs_dashboard";

    public function __construct(mysqli $con)
    {
        $this->con = $con;
    }

    public function obtenerTiposLogs(): array
    {
        $sql = "SELECT pktipologs, tipo, type FROM tipologs ORDER BY pktipologs";
        return $this->ejecutarConsultaDatos($sql, "Error al cargar tipos de logs");
    }

    public function obtenerUsuarios(): array
    {
        $sql = "
            SELECT id, nombre, string2, cn
            FROM usuarios
            ORDER BY CAST(REPLACE(nombre, 'USER', '') AS UNSIGNED) ASC
        ";

        return $this->ejecutarConsultaDatos($sql, "Error al cargar usuarios");
    }

    public function crearTablaTemporalLogs(string $whereBase): void
    {
        $this->con->query("DROP TEMPORARY TABLE IF EXISTS {$this->tablaTemporal}");

        $sql = "
            CREATE TEMPORARY TABLE {$this->tablaTemporal} AS
            SELECT 
                pk_log,
                tipo_log,
                origen,
                operador,
                evento,
                cert2log,
                modulo,
                `date`
            FROM logs
            $whereBase
        ";

        if (!$this->con->query($sql)) {
            die("Error creando tabla temporal: " . $this->con->error);
        }
    }

    public function obtenerTotalLogs(): int
    {
        $sql = "SELECT COUNT(*) AS total FROM {$this->tablaTemporal}";
        $resultado = $this->con->query($sql);

        if (!$resultado) {
            die("Error calculando total de logs: " . $this->con->error);
        }

        $fila = $resultado->fetch_assoc();
        return (int)$fila["total"];
    }

    public function obtenerTimelineTicketBai(string $whereTicket, string $agrupacion): array
    {
        $formatoFechaSql = "DATE(logs.`date`)";

        if ($agrupacion === "hora") {
            $formatoFechaSql = "DATE_FORMAT(logs.`date`, '%Y-%m-%d %H:00')";
        }

        $sql = "
            SELECT 
                $formatoFechaSql AS label,
                COUNT(*) AS total
            FROM {$this->tablaTemporal} logs
            $whereTicket
            GROUP BY label
            ORDER BY label ASC
        ";

        return $this->ejecutarConsultaDatos($sql, "Error cargando timeline TicketBai");
    }

    public function obtenerDatosTipo(string $whereTipo): array
    {
        $sql = "
            SELECT 
                tipologs.tipo AS label,
                COUNT(logs.pk_log) AS total
            FROM {$this->tablaTemporal} logs
            INNER JOIN tipologs 
                ON logs.tipo_log = tipologs.pktipologs
            $whereTipo
            GROUP BY tipologs.pktipologs, tipologs.tipo
            HAVING total > 0
            ORDER BY total DESC
        ";

        return $this->ejecutarConsultaDatos($sql, "Error gráfico tipos");
    }

    public function obtenerDatosOrigen(string $whereOrigen, string $modo = "top_origen"): array
    {
        $limite = "LIMIT 10";

        if ($modo === "todos_origenes") {
            $limite = "";
        }

        $sql = "
        SELECT 
            logs.origen AS label,
            COUNT(logs.pk_log) AS total
        FROM {$this->tablaTemporal} logs
        $whereOrigen
        GROUP BY logs.origen
        ORDER BY total DESC
        $limite
    ";

        return $this->ejecutarConsultaDatos($sql, "Error gráfico orígenes");
    }

    public function obtenerDatosOperador(string $whereOperador): array
    {
        $sql = "
            SELECT 
                logs.operador AS label,
                COUNT(logs.pk_log) AS total
            FROM {$this->tablaTemporal} logs
            $whereOperador
            GROUP BY logs.operador
            ORDER BY total DESC
            LIMIT 10
        ";

        $datosOriginales = $this->ejecutarConsultaDatos($sql, "Error gráfico operadores");

        $operadoresAgrupados = [];

        foreach ($datosOriginales as $fila) {
            $nombreLimpio = $this->limpiarNombreOperador($fila["label"]);
            $total = (int)$fila["total"];

            if (!isset($operadoresAgrupados[$nombreLimpio])) {
                $operadoresAgrupados[$nombreLimpio] = 0;
            }

            $operadoresAgrupados[$nombreLimpio] += $total;
        }

        arsort($operadoresAgrupados);

        $datosFinales = [];

        foreach (array_slice($operadoresAgrupados, 0, 10, true) as $label => $total) {
            $datosFinales[] = [
                "label" => $label,
                "total" => $total
            ];
        }

        return $datosFinales;
    }

    private function limpiarNombreOperador(string $operador): string
    {
        $posicionCN = strpos($operador, "CN=");

        if ($posicionCN === false) {
            return trim($operador);
        }

        $desdeCN = substr($operador, $posicionCN + 3);
        $partes = explode(",", $desdeCN);

        return trim($partes[0]);
    }

    public function obtenerDatosEvento(): array
    {
        $sql = "
            SELECT 
                CASE
                    WHEN logs.evento LIKE 'Log in%' THEN 'Login'
                    WHEN logs.evento LIKE 'New Entity%' THEN 'Nueva entidad'
                    WHEN logs.evento LIKE '%sign%' THEN 'Firma'
                    WHEN logs.evento LIKE '%firma%' THEN 'Firma'
                    WHEN logs.evento LIKE '%pdf%' THEN 'PDF'
                    WHEN logs.evento LIKE '%error%' THEN 'Error'
                    WHEN logs.evento LIKE '%delete%' THEN 'Eliminación'
                    WHEN logs.evento LIKE '%update%' THEN 'Actualización'
                    WHEN logs.evento LIKE '%create%' THEN 'Creación'
                    ELSE NULL
                END AS label,
                COUNT(*) AS total
            FROM {$this->tablaTemporal} logs
            GROUP BY label
            HAVING label IS NOT NULL
            ORDER BY total DESC
        ";

        return $this->ejecutarConsultaDatos($sql, "Error gráfico eventos");
    }

    public function obtenerDatosFirma(): array
    {
        $sql = "
            SELECT 
                CASE
                    WHEN logs.cert2log IS NOT NULL AND TRIM(logs.cert2log) <> '' THEN 'CON FIRMA'
                    ELSE 'SIN FIRMA'
                END AS label,
                COUNT(logs.pk_log) AS total
            FROM {$this->tablaTemporal} logs
            GROUP BY label
            ORDER BY FIELD(label, 'CON FIRMA', 'SIN FIRMA')
        ";

        return $this->ejecutarConsultaDatos($sql, "Error gráfico firma");
    }

    public function obtenerDatosAcceso(): array
    {
        $sql = "
            SELECT 
                CASE
                    WHEN logs.origen LIKE '%portal%' AND logs.origen LIKE '%link%' THEN 'PORTAL&LINK'
                    WHEN logs.modulo LIKE '%portal%' AND logs.modulo LIKE '%link%' THEN 'PORTAL&LINK'
                    WHEN logs.evento LIKE '%portal%' AND logs.evento LIKE '%link%' THEN 'PORTAL&LINK'

                    WHEN logs.origen LIKE '%portal%' OR logs.modulo LIKE '%portal%' OR logs.evento LIKE '%portal%' THEN 'PORTAL'

                    WHEN logs.origen LIKE '%link%' OR logs.modulo LIKE '%link%' OR logs.evento LIKE '%link%' THEN 'LINK'

                    ELSE NULL
                END AS label,
                COUNT(*) AS total
            FROM {$this->tablaTemporal} logs
            GROUP BY label
            HAVING label IS NOT NULL
            ORDER BY FIELD(label, 'PORTAL', 'LINK', 'PORTAL&LINK')
        ";

        return $this->ejecutarConsultaDatos($sql, "Error gráfico tipo de acceso");
    }

    private function ejecutarConsultaDatos(string $sql, string $mensajeError): array
    {
        $resultado = $this->con->query($sql);

        if (!$resultado) {
            die($mensajeError . ": " . $this->con->error);
        }

        $datos = [];

        while ($fila = $resultado->fetch_assoc()) {
            $datos[] = $fila;
        }

        return $datos;
    }
}
