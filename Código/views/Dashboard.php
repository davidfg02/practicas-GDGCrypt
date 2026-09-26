<?php

if (!isset($datosVista) || !is_array($datosVista)) {
    die("Error: esta vista no se puede abrir directamente. Entra desde public/hyperAdmin.php o public/admin.php");
}

extract($datosVista);

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard de Logs</title>
    <link rel="stylesheet" href="styles.css">
</head>

<body>

    <div class="dashboard">

        <header class="dashboard-header">
            <div>
                <h1>Dashboard de Logs - <?= h($modo) ?></h1>
                <p>Análisis de logs y eventos TicketBai</p>
            </div>
        </header>

        <section class="filters-card">
            <form method="GET" class="filters-form">

                <div class="filter-group">
                    <label for="periodo">Periodo</label>
                    <select name="periodo" id="periodo">
                        <option value="ultimo_mes" <?= $periodo === "ultimo_mes" ? "selected" : "" ?>>Último mes</option>
                        <option value="trimestre" <?= $periodo === "trimestre" ? "selected" : "" ?>>Último trimestre</option>
                        <option value="semestre" <?= $periodo === "semestre" ? "selected" : "" ?>>Último semestre</option>
                        <option value="ultimo_anio" <?= $periodo === "ultimo_anio" ? "selected" : "" ?>>Último año</option>
                        <option value="personalizado" <?= $periodo === "personalizado" ? "selected" : "" ?>>Personalizado</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="tipo_log">Tipo de log</label>

                    <select name="tipo_log" id="tipo_log">
                        <option value="">Todos</option>

                        <?php foreach ($tiposLogs as $tipo): ?>
                            <option
                                value="<?= h($tipo["pktipologs"]) ?>"
                                <?= ($filtroTipo == $tipo["pktipologs"]) ? "selected" : "" ?>>
                                <?= h($tipo["tipo"]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="modulo">Palabras clave</label>
                    <input
                        type="text"
                        name="modulo"
                        id="modulo"
                        placeholder="Ej: portal link WEBSIGNADM"
                        value="<?= h($filtroModulo) ?>">
                </div>

                <div class="filter-group">
                    <label for="fecha_desde">Desde</label>
                    <input
                        type="date"
                        name="fecha_desde"
                        id="fecha_desde"
                        value="<?= h($filtroFechaDesde) ?>">
                </div>

                <div class="filter-group">
                    <label for="fecha_hasta">Hasta</label>
                    <input
                        type="date"
                        name="fecha_hasta"
                        id="fecha_hasta"
                        value="<?= h($filtroFechaHasta) ?>">
                </div>

                <div class="filter-group">
                    <label for="agrupacion_ticket">Escala TicketBai</label>
                    <select name="agrupacion_ticket" id="agrupacion_ticket">
                        <option value="dia" <?= $agrupacionTicket === "dia" ? "selected" : "" ?>>Día</option>
                        <option value="hora" <?= $agrupacionTicket === "hora" ? "selected" : "" ?>>Hora</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="tipo_operacion">Operación TicketBai</label>
                    <select name="tipo_operacion" id="tipo_operacion">
                        <option value="todas" <?= $tipoOperacion === "todas" ? "selected" : "" ?>>Todas</option>
                        <option value="firma" <?= $tipoOperacion === "firma" ? "selected" : "" ?>>Firmas</option>
                        <option value="crc" <?= $tipoOperacion === "crc" ? "selected" : "" ?>>CRC</option>
                    </select>
                </div>

                <?php if ($modo === "hyperAdmin"): ?>
                    <div class="filter-group">
                        <label for="usuario">Usuario</label>

                        <select name="usuario" id="usuario">
                            <option value="">Todos</option>

                            <?php foreach ($usuarios as $usuario): ?>
                                <option
                                    value="<?= h($usuario["id"]) ?>"
                                    <?= $filtroUsuario == $usuario["id"] ? "selected" : "" ?>>
                                    <?= h($usuario["nombre"]) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="filter-group">
                    <label for="ticket_string2">String2 / Usuario</label>
                    <input
                        type="text"
                        name="ticket_string2"
                        id="ticket_string2"
                        placeholder="ID de usuario"
                        value="<?= h($filtroTicketString2) ?>"
                        <?= $modo === "admin" ? "readonly" : "" ?>>
                </div>

                <button type="submit">Aplicar filtro</button>
                <a href="<?= strtok($_SERVER["REQUEST_URI"], '?') ?>" class="reset-link">Limpiar</a>

            </form>
        </section>

        <?php if (!$hayFiltros): ?>
            <section class="empty-panel">
                <h2>Dashboard en espera</h2>

                <?php if (!empty($mensajeError)): ?>
                    <p><?= h($mensajeError) ?></p>
                <?php else: ?>
                    <p>Selecciona un rango de fechas para consultar la base de datos y pintar los gráficos.</p>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($hayFiltros): ?>
            <section class="kpi-strip">
                <article class="kpi-card">
                    <span>Total logs</span>
                    <strong><?= number_format($totalLogs, 0, ',', '.') ?></strong>
                </article>

                <article class="kpi-card">
                    <span>Tipos visibles</span>
                    <strong><?= $numTipos ?></strong>
                </article>

                <article class="kpi-card">
                    <span>Top orígenes</span>
                    <strong><?= $numOrigenes ?></strong>
                </article>

                <article class="kpi-card">
                    <span>Top operadores</span>
                    <strong><?= $numOperadores ?></strong>
                </article>
            </section>
        <?php endif; ?>

        <section class="charts-grid">

            <article class="chart-card chart-card-wide">
                <div class="section-header">
                    <h2>Timeline TicketBai</h2>
                    <p>
                        Eventos TicketBai dentro del rango seleccionado.
                        Total detectado: <?= number_format($totalTicketBai, 0, ',', '.') ?>
                    </p>
                </div>

                <?php if (!$hayFiltros || count($datosTicketTimeline) === 0): ?>
                    <div class="no-data">Sin datos TicketBai, aplica un filtro válido</div>
                <?php else: ?>
                    <?php pintarGraficoTemporalSvg($datosTicketTimeline, "Escala temporal"); ?>
                <?php endif; ?>
            </article>

            <article class="chart-card">
                <div class="section-header">
                    <h2>Tipos de logs</h2>
                    <p>Distribución por nivel de log, sin vacíos ni otros</p>
                </div>

                <?php if (!$hayFiltros || count($datosTipo) === 0): ?>
                    <div class="no-data">Sin datos, aplica un filtro válido</div>
                <?php else: ?>
                    <?php pintarGraficoBarrasVertical($datosTipo, $maxTipo); ?>
                <?php endif; ?>
            </article>

            <article class="chart-card">
                <div class="section-header">
                    <h2>Origen</h2>
                    <p>Distribución de logs según origen o IP</p>

                    <form method="GET" class="chart-filter-form">
                        <input type="hidden" name="periodo" value="<?= h($periodo) ?>">
                        <input type="hidden" name="tipo_log" value="<?= h($filtroTipo) ?>">
                        <input type="hidden" name="modulo" value="<?= h($filtroModulo) ?>">
                        <input type="hidden" name="fecha_desde" value="<?= h($filtroFechaDesde) ?>">
                        <input type="hidden" name="fecha_hasta" value="<?= h($filtroFechaHasta) ?>">
                        <input type="hidden" name="agrupacion_ticket" value="<?= h($agrupacionTicket) ?>">
                        <input type="hidden" name="tipo_operacion" value="<?= h($tipoOperacion) ?>">
                        <input type="hidden" name="usuario" value="<?= h($filtroUsuario) ?>">
                        <input type="hidden" name="ticket_string2" value="<?= h($filtroTicketString2) ?>">

                        <label for="grafico_origen">Ver:</label>

                        <select name="grafico_origen" id="grafico_origen">
                            <option value="top_origen" <?= $filtroGraficoOrigen === "top_origen" ? "selected" : "" ?>>
                                Top 10
                            </option>

                            <option value="todos_origenes" <?= $filtroGraficoOrigen === "todos_origenes" ? "selected" : "" ?>>
                                Todos
                            </option>
                        </select>

                        <button type="submit">Actualizar</button>
                    </form>
                </div>

                <?php if (!$hayFiltros || count($datosOrigen) === 0): ?>
                    <div class="no-data">Sin datos, aplica un filtro válido</div>
                <?php else: ?>

                    <div class="pie-layout">
                        <div
                            class="pie-chart"
                            style="background: <?= h($pieOrigenGradient) ?>;">
                        </div>

                        <div class="pie-legend">
                            <?php foreach ($datosOrigen as $index => $dato): ?>
                                <div class="legend-item">
                                    <span
                                        class="legend-color"
                                        style="background-color: <?= h(colorGrafico($index)) ?>;">
                                    </span>

                                    <span class="legend-label"><?= h($dato["label"]) ?></span>
                                    <strong><?= number_format((int)$dato["total"], 0, ',', '.') ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                <?php endif; ?>
            </article>

            <article class="chart-card">
                <div class="section-header">
                    <h2>Operadores Top 10</h2>
                    <p>Usuarios con más actividad</p>
                </div>

                <?php if (!$hayFiltros || count($datosOperador) === 0): ?>
                    <div class="no-data">Sin datos, aplica un filtro válido</div>
                <?php else: ?>
                    <?php pintarGraficoBarrasVertical($datosOperador, $maxOperador); ?>
                <?php endif; ?>
            </article>

            <article class="chart-card">
                <div class="section-header">
                    <h2>Eventos clasificados</h2>
                    <p>Categorías detectadas según el texto del evento</p>
                </div>

                <?php if (!$hayFiltros || count($datosEvento) === 0): ?>
                    <div class="no-data">Sin eventos clasificables</div>
                <?php else: ?>
                    <?php pintarGraficoBarrasVertical($datosEvento, $maxEvento); ?>
                <?php endif; ?>
            </article>

            <article class="chart-card">
                <div class="section-header">
                    <h2>Firma</h2>
                    <p>Distribución entre logs con firma y sin firma</p>
                </div>

                <?php if (!$hayFiltros || count($datosFirma) === 0): ?>
                    <div class="no-data">Sin datos, aplica un filtro válido</div>
                <?php else: ?>

                    <div class="pie-layout">
                        <div
                            class="pie-chart"
                            style="background: <?= h($pieFirmaGradient) ?>;">
                        </div>

                        <div class="pie-legend">
                            <?php foreach ($datosFirma as $index => $dato): ?>
                                <div class="legend-item">
                                    <span
                                        class="legend-color"
                                        style="background-color: <?= h(colorGrafico($index)) ?>;">
                                    </span>

                                    <span class="legend-label"><?= h($dato["label"]) ?></span>
                                    <strong><?= number_format((int)$dato["total"], 0, ',', '.') ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                <?php endif; ?>
            </article>

            <article class="chart-card">
                <div class="section-header">
                    <h2>Tipo de acceso</h2>
                    <p>Logs agrupados por PORTAL, LINK o PORTAL&LINK</p>
                </div>

                <?php if (!$hayFiltros || count($datosAcceso) === 0): ?>
                    <div class="no-data">No se detectó PORTAL, LINK o PORTAL&LINK</div>
                <?php else: ?>
                    <?php pintarGraficoBarrasVertical($datosAcceso, $maxAcceso); ?>
                <?php endif; ?>
            </article>

        </section>

    </div>

</body>

</html>