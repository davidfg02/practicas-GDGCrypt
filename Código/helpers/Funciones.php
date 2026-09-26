<?php

function h($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

function crearWhere(array $condiciones): string
{
    return count($condiciones) === 0 ? "" : "WHERE " . implode(" AND ", $condiciones);
}

function crearFiltroPalabrasClave(mysqli $con, string $texto): string
{
    $texto = trim($texto);

    if ($texto === "") {
        return "";
    }

    $palabras = preg_split('/[\s,;]+/', $texto);
    $condiciones = [];

    foreach ($palabras as $palabra) {
        $palabra = trim($palabra);

        if ($palabra === "") {
            continue;
        }

        $palabra = $con->real_escape_string($palabra);

        $condiciones[] = "
            (
                logs.modulo LIKE '%$palabra%' OR
                logs.evento LIKE '%$palabra%' OR
                logs.origen LIKE '%$palabra%'
            )
        ";
    }

    if (count($condiciones) === 0) {
        return "";
    }

    return "(" . implode(" OR ", $condiciones) . ")";
}

function obtenerMaximo(array $datos): int
{
    $max = 0;

    foreach ($datos as $dato) {
        $max = max($max, (int)$dato["total"]);
    }

    return $max;
}

function calcularPorcentaje(int $valor, int $maximo): float
{
    return $maximo <= 0 ? 0 : ($valor / $maximo) * 100;
}

function colorGrafico(int $index): string
{
    $colores = [
        "#5cb0b8",
        "#357f86",
        "#f0bf5a",
        "#e07b54",
        "#8dc98c",
        "#9b7fd4",
        "#e06891",
        "#5b8fd4",
        "#7aa6a2",
        "#34495e"
    ];

    return $colores[$index % count($colores)];
}

function crearConicGradient(array $datos): string
{
    $total = 0;

    foreach ($datos as $dato) {
        $total += (int)$dato["total"];
    }

    if ($total <= 0) {
        return "conic-gradient(#dceff1 0deg 360deg)";
    }

    $inicio = 0;
    $partes = [];

    foreach ($datos as $index => $dato) {
        $valor = (int)$dato["total"];

        if ($valor <= 0) {
            continue;
        }

        $grados = ($valor / $total) * 360;
        $fin = $inicio + $grados;
        $color = colorGrafico($index);

        $partes[] = "$color {$inicio}deg {$fin}deg";
        $inicio = $fin;
    }

    return "conic-gradient(" . implode(", ", $partes) . ")";
}

function pintarGraficoBarrasVertical(array $datos, int $maximo): void
{
?>
    <div class="vertical-chart">
        <div class="chart-grid-lines">
            <div></div>
            <div></div>
            <div></div>
            <div></div>
            <div></div>
        </div>

        <div class="vertical-bars">
            <?php foreach ($datos as $dato): ?>
                <?php
                $valor = (int)$dato["total"];
                $altura = calcularPorcentaje($valor, $maximo);
                ?>

                <div class="vertical-bar-item">
                    <div class="bar-number">
                        <?= number_format($valor, 0, ',', '.') ?>
                    </div>

                    <div
                        class="vertical-bar"
                        style="height: <?= $altura ?>%;">
                    </div>

                    <div class="bar-label">
                        <?= h($dato["label"]) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php
}

function pintarGraficoTemporalSvg(array $datos, string $tituloEjeX = "Escala temporal"): void
{
    if (count($datos) === 0) {
        echo '<div class="no-data">Sin datos TicketBai para los filtros aplicados</div>';
        return;
    }

    $width = 1200;
    $height = 420;

    $paddingLeft = 75;
    $paddingRight = 35;
    $paddingTop = 35;
    $paddingBottom = 95;

    $chartWidth = $width - $paddingLeft - $paddingRight;
    $chartHeight = $height - $paddingTop - $paddingBottom;

    $maximo = obtenerMaximo($datos);
    $totalPuntos = count($datos);

    $puntos = [];

    foreach ($datos as $index => $dato) {
        $x = $totalPuntos === 1
            ? $paddingLeft + ($chartWidth / 2)
            : $paddingLeft + (($chartWidth / ($totalPuntos - 1)) * $index);

        $valor = (int)$dato["total"];
        $y = $paddingTop + $chartHeight - (($valor / max($maximo, 1)) * $chartHeight);

        $puntos[] = [
            "x" => $x,
            "y" => $y,
            "label" => $dato["label"],
            "total" => $valor
        ];
    }

    $polyline = implode(" ", array_map(function ($p) {
        return $p["x"] . "," . $p["y"];
    }, $puntos));

    $saltoEtiquetas = max(1, ceil($totalPuntos / 14));

?>
    <div class="timeline-wrapper">
        <svg viewBox="0 0 <?= $width ?> <?= $height ?>" class="timeline-svg">

            <?php for ($i = 0; $i <= 4; $i++): ?>
                <?php
                $y = $paddingTop + (($chartHeight / 4) * $i);
                $valorLinea = round($maximo - (($maximo / 4) * $i));
                ?>

                <line
                    x1="<?= $paddingLeft ?>"
                    y1="<?= $y ?>"
                    x2="<?= $width - $paddingRight ?>"
                    y2="<?= $y ?>"
                    class="timeline-grid-horizontal"
                />

                <text
                    x="<?= $paddingLeft - 28 ?>"
                    y="<?= $y + 6 ?>"
                    text-anchor="end"
                    class="timeline-y-label">
                    <?= number_format($valorLinea, 0, ',', '.') ?>
                </text>
            <?php endfor; ?>

            <?php foreach ($puntos as $index => $p): ?>
                <?php if ($index % $saltoEtiquetas === 0): ?>
                    <line
                        x1="<?= $p["x"] ?>"
                        y1="<?= $paddingTop ?>"
                        x2="<?= $p["x"] ?>"
                        y2="<?= $paddingTop + $chartHeight ?>"
                        class="timeline-grid-vertical"
                    />
                <?php endif; ?>
            <?php endforeach; ?>

            <line
                x1="<?= $paddingLeft ?>"
                y1="<?= $paddingTop ?>"
                x2="<?= $paddingLeft ?>"
                y2="<?= $paddingTop + $chartHeight ?>"
                class="timeline-axis"
            />

            <line
                x1="<?= $paddingLeft ?>"
                y1="<?= $paddingTop + $chartHeight ?>"
                x2="<?= $width - $paddingRight ?>"
                y2="<?= $paddingTop + $chartHeight ?>"
                class="timeline-axis"
            />

            <polyline
                points="<?= h($polyline) ?>"
                class="timeline-line"
            />

            <?php foreach ($puntos as $index => $p): ?>
                <circle
                    cx="<?= $p["x"] ?>"
                    cy="<?= $p["y"] ?>"
                    r="5"
                    class="timeline-point">
                    <title><?= h($p["label"]) ?>: <?= number_format($p["total"], 0, ',', '.') ?> eventos</title>
                </circle>

                <?php if ($index % $saltoEtiquetas === 0): ?>
                    <text
                        x="<?= $p["x"] ?>"
                        y="<?= $paddingTop + $chartHeight + 48 ?>"
                        text-anchor="end"
                        transform="rotate(-45 <?= $p["x"] ?> <?= $paddingTop + $chartHeight + 48 ?>)"
                        class="timeline-x-label">
                        <?= h(formatearEtiquetaFecha($p["label"])) ?>
                    </text>
                <?php endif; ?>
            <?php endforeach; ?>

            <text
                x="<?= $width / 2 ?>"
                y="<?= $height - 15 ?>"
                text-anchor="middle"
                class="timeline-title">
                <?= h($tituloEjeX) ?>
            </text>
        </svg>
    </div>
<?php
}

function formatearEtiquetaFecha(string $fecha): string
{
    $timestamp = strtotime($fecha);

    if (!$timestamp) {
        return $fecha;
    }

    $meses = [
        "Jan" => "Ene",
        "Feb" => "Feb",
        "Mar" => "Mar",
        "Apr" => "Abr",
        "May" => "May",
        "Jun" => "Jun",
        "Jul" => "Jul",
        "Aug" => "Ago",
        "Sep" => "Sep",
        "Oct" => "Oct",
        "Nov" => "Nov",
        "Dec" => "Dic"
    ];

    $texto = date("d M", $timestamp);

    return str_replace(array_keys($meses), array_values($meses), $texto);
}