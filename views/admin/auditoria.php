<?php
require_once __DIR__ . '/../../config/App.php';
// HU-T.8 / RE-T.8.2–4: presentación de los datos ya acotados por el controlador.
$e = static fn ($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$etiquetasAccion = ['LOGIN' => 'Inicio de sesión', 'LOGIN_FAIL' => 'Acceso fallido', 'LOGOUT' => 'Cierre de sesión', 'INSERT' => 'Creó', 'UPDATE' => 'Editó', 'DELETE' => 'Eliminó', 'VIEW' => 'Consultó', 'OTHER' => 'Otro'];
$etiquetasModulo = ['usuarios' => 'Usuarios', 'usuario_clinica' => 'Equipo', 'clinicas' => 'Clínica', 'citas' => 'Citas', 'mascotas' => 'Pacientes', 'consultas' => 'Consultas', 'tipos_cita' => 'Tipos de cita', 'horarios_clinica' => 'Horarios de la clínica', 'horarios_veterinario' => 'Horarios del equipo', 'propuestas_horario' => 'Propuestas de horario', 'ausencias_veterinario' => 'Ausencias', 'cierres_clinica' => 'Cierres de la clínica', 'suscripciones' => 'Suscripción'];
$nombreModulo = static fn ($tabla): string => $etiquetasModulo[$tabla ?? ''] ?? ($tabla ? ucfirst(str_replace('_', ' ', $tabla)) : 'Sistema');
// El detalle es de lectura: no se publican claves o tokens dentro del marcado de la página.
$ocultarSecretos = static function ($valor) use (&$ocultarSecretos) {
    if (!is_array($valor)) return $valor;
    foreach ($valor as $clave => $dato) {
        $valor[$clave] = preg_match('/password|contrasen|token|secret|credencial|authorization|cookie|api.?key|llave|firma|hash/i', (string) $clave) ? '[Protegido]' : $ocultarSecretos($dato);
    }
    return $valor;
};
$leerCambios = static function ($valor) use ($ocultarSecretos): array {
    $datos = is_array($valor) ? $valor : json_decode((string) $valor, true);
    return is_array($datos) ? $ocultarSecretos($datos) : [];
};
$logs = $logs ?? [];
$acciones = $acciones ?? [];
$tablas = $tablas ?? [];
$stats_hoy = $stats_hoy ?? [];
$total = (int) ($total ?? 0);
$page = max(1, (int) ($page ?? 1));
$perPage = (int) ($perPage ?? 50);
$totalPages = max(1, (int) ceil($total / $perPage));
$baseFiltros = array_intersect_key($_GET, array_flip(['usuario', 'accion', 'tabla', 'fecha_desde', 'fecha_hasta']));
$paginaAuditoria = static fn (int $numero): string => 'index.php?' . http_build_query(array_merge($baseFiltros, ['action' => 'admin_auditoria', 'page' => $numero]));
$filtrosActivos = count(array_filter($baseFiltros, static fn ($valor): bool => (string) $valor !== ''));
?>
<link rel="stylesheet" href="css/auditoria.css?v=<?= $e(App::assetVersion()) ?>">
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.3.0/exceljs.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.1/jspdf.plugin.autotable.min.js" defer></script>
<section class="auditoria" data-auditoria data-total="<?= $total ?>" data-offset="<?= ($page - 1) * $perPage ?>" aria-label="Registro de auditoría">
    <form id="auditoriaFiltros" action="index.php" method="get" class="auditoria-herramientas">
        <input type="hidden" name="action" value="admin_auditoria">
        <div class="auditoria-periodos" role="group" aria-label="Periodo">
            <?php foreach (['hoy' => 'Hoy', '7' => '7 días', '30' => '30 días', 'rango' => 'Rango'] as $clave => $periodo): ?>
                <button type="button" data-periodo="<?= $e($clave) ?>" aria-pressed="<?= $clave === 'rango' ? 'true' : 'false' ?>"><?= $e($periodo) ?></button>
            <?php endforeach; ?>
        </div>
        <label class="auditoria-buscador"><i class="fas fa-search" aria-hidden="true"></i><input type="search" name="usuario" value="<?= $e($_GET['usuario'] ?? '') ?>" placeholder="Buscar persona" aria-label="Persona: nombre, documento o correo"></label>
        <div class="auditoria-selectores" data-filtros-escritorio>
            <div class="auditoria-selectores__campos" data-selectores>
                <label><span class="auditoria-sr">Acción</span><select name="accion" form="auditoriaFiltros" aria-label="Acción"><option value="">Todas las acciones</option><?php foreach ($acciones as $accion): ?><option value="<?= $e($accion) ?>" <?= ($_GET['accion'] ?? '') === $accion ? 'selected' : '' ?>><?= $e($etiquetasAccion[$accion] ?? 'Otra acción') ?></option><?php endforeach; ?></select></label>
                <label><span class="auditoria-sr">Módulo</span><select name="tabla" form="auditoriaFiltros" aria-label="Módulo"><option value="">Todos los módulos</option><?php foreach ($tablas as $tabla): ?><option value="<?= $e($tabla) ?>" <?= ($_GET['tabla'] ?? '') === $tabla ? 'selected' : '' ?>><?= $e($nombreModulo($tabla)) ?></option><?php endforeach; ?></select></label>
            </div>
        </div>
        <button type="button" class="auditoria-boton auditoria-filtros-abrir" data-abrir-dialogo="auditoriaFiltrosDialogo"><i class="fas fa-filter" aria-hidden="true"></i> Filtros<span class="auditoria-conteo" data-filtros-conteo <?= $filtrosActivos ? '' : 'hidden' ?>><?= $filtrosActivos ?></span></button>
        <div data-exportar-escritorio>
            <details class="auditoria-exportar" data-exportar><summary class="auditoria-boton"><i class="fas fa-download" aria-hidden="true"></i> Exportar<i class="fas fa-chevron-down" aria-hidden="true"></i></summary><div class="auditoria-exportar__opciones"><button type="button" data-ui-accion="exportar-auditoria-excel">Excel</button><button type="button" data-ui-accion="exportar-auditoria-pdf">PDF</button></div></details>
        </div>
        <button type="submit" class="auditoria-sr">Buscar</button>
    </form>
    <div class="auditoria-resumen" aria-label="Actividad de las últimas 24 horas">
        <span><strong><?= number_format(array_sum($stats_hoy), 0, ',', '.') ?> eventos</strong> <span class="auditoria-resumen__periodo">en las últimas 24 h</span><span class="auditoria-resumen__periodo--movil"> (24 h)</span></span>
        <button type="button" class="auditoria-resumen__chip" data-accion-rapida="DELETE"><?= (int) ($stats_hoy['DELETE'] ?? 0) ?> eliminaciones</button>
        <button type="button" class="auditoria-resumen__chip" data-accion-rapida="LOGIN_FAIL"><?= (int) ($stats_hoy['LOGIN_FAIL'] ?? 0) ?> accesos fallidos</button>
        <a href="index.php?action=admin_auditoria" class="auditoria-limpiar" <?= $filtrosActivos ? '' : 'hidden' ?>>Limpiar filtros</a>
    </div>
    <div class="auditoria-listado">
        <table class="auditoria-tabla" id="tablaAuditoria">
            <thead><tr><th>Cuándo</th><th>Quién</th><th>Acción</th><th>Qué</th><th><span class="auditoria-sr">Ver detalle</span></th></tr></thead>
            <tbody>
                <?php if (!$logs): ?>
                    <tr><td colspan="5" class="auditoria-vacia"><i class="fas fa-search" aria-hidden="true"></i><p>No hay eventos para estos filtros.</p><a href="index.php?action=admin_auditoria">Limpiar filtros</a></td></tr>
                <?php endif; ?>
                <?php foreach ($logs as $log):
                    $nombre = $log['usuario_nombre'] ?? 'Sistema';
                    $partes = array_values(array_filter(explode(' ', trim($nombre))));
                    $iniciales = mb_strtoupper(mb_substr($partes[0] ?? 'S', 0, 1) . mb_substr($partes[1] ?? '', 0, 1));
                    $fecha = strtotime($log['fecha_hora']);
                    $datosDetalle = ['fecha' => date('d/m/Y H:i', $fecha), 'persona' => $nombre, 'accion' => $log['accion'], 'etiqueta' => $etiquetasAccion[$log['accion']] ?? 'Otra acción', 'modulo' => $nombreModulo($log['tabla_afectada'] ?? null), 'tabla' => $log['tabla_afectada'] ?? '—', 'descripcion' => $log['descripcion'] ?? 'Sin descripción', 'ip' => $log['ip_address'] ?? '—', 'registro' => $log['registro_id'] ?? null, 'antes' => $leerCambios($log['datos_anteriores'] ?? null), 'despues' => $leerCambios($log['datos_nuevos'] ?? null)];
                ?>
                    <tr class="auditoria-fila" data-evento="<?= $e(json_encode($datosDetalle, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)) ?>" tabindex="0" aria-label="<?= $e('Ver evento de ' . $nombre . ', ' . $datosDetalle['fecha']) ?>">
                        <td class="auditoria-cuando"><span class="auditoria-fecha-dia"><?= date('d/m/Y', $fecha) ?></span><span class="auditoria-hora"><?= date('H:i', $fecha) ?></span></td>
                        <td class="auditoria-persona"><span class="auditoria-avatar" aria-hidden="true"><?= $e($iniciales) ?></span><span><?= $e($nombre) ?></span></td>
                        <td class="auditoria-accion"><span class="auditoria-insignia" data-accion="<?= $e($log['accion']) ?>"><?= $e($datosDetalle['etiqueta']) ?></span></td>
                        <td class="auditoria-que"><strong><?= $e($datosDetalle['modulo']) ?></strong><span> · <?= $e($datosDetalle['descripcion']) ?></span></td>
                        <td class="auditoria-ver"><button type="button" class="auditoria-ver__boton" aria-label="<?= $e('Ver detalle del evento de ' . $nombre) ?>"><i class="fas fa-chevron-right" aria-hidden="true"></i></button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <footer class="auditoria-pie">
        <span data-auditoria-contador role="status"><?= count($logs) ? (($page - 1) * $perPage + 1) . '–' . (($page - 1) * $perPage + count($logs)) : '0' ?> de <?= $total ?> eventos</span>
        <nav aria-label="Páginas de auditoría"><a class="auditoria-boton" href="<?= $e($paginaAuditoria(max(1, $page - 1))) ?>" data-auditoria-anterior aria-disabled="<?= $page <= 1 ? 'true' : 'false' ?>">Anterior</a><a class="auditoria-boton" href="<?= $e($paginaAuditoria(min($totalPages, $page + 1))) ?>" data-auditoria-siguiente aria-disabled="<?= $page >= $totalPages ? 'true' : 'false' ?>">Siguiente</a></nav>
    </footer>
</section>
<dialog class="zk-dialogo auditoria-dialogo-filtros" id="auditoriaFiltrosDialogo" aria-labelledby="auditoriaFiltrosTitulo">
    <div class="zk-dialogo__form">
        <header class="zk-dialogo__head"><h2 id="auditoriaFiltrosTitulo">Filtros</h2><button type="button" class="zk-dialogo__cerrar" data-cerrar-dialogo aria-label="Cerrar" autofocus><i class="fas fa-times" aria-hidden="true"></i></button></header>
        <div class="zk-dialogo__cuerpo auditoria-filtros-cuerpo">
            <div data-filtros-movil></div>
            <div class="auditoria-fechas"><label>Desde<input type="date" name="fecha_desde" form="auditoriaFiltros" value="<?= $e($_GET['fecha_desde'] ?? '') ?>"></label><label>Hasta<input type="date" name="fecha_hasta" form="auditoriaFiltros" value="<?= $e($_GET['fecha_hasta'] ?? '') ?>"></label></div>
            <p>Las fechas reemplazan el periodo (Rango).</p>
            <p class="auditoria-error" data-error-fechas role="status"></p>
            <a href="index.php?action=admin_auditoria">Limpiar filtros</a>
        </div>
        <footer class="zk-dialogo__pie"><div data-exportar-movil></div><button type="submit" form="auditoriaFiltros" class="auditoria-boton auditoria-boton--principal">Ver eventos</button></footer>
    </div>
</dialog>
<dialog class="zk-dialogo auditoria-detalle" id="auditoriaDetalle" aria-labelledby="auditoriaDetalleTitulo">
    <div class="zk-dialogo__form">
        <header class="zk-dialogo__head"><div><span class="auditoria-insignia" data-detalle-accion></span><h2 id="auditoriaDetalleTitulo" data-detalle-titulo>Detalle del evento</h2><p data-detalle-descripcion></p></div><button type="button" class="zk-dialogo__cerrar" data-cerrar-dialogo aria-label="Cerrar" autofocus><i class="fas fa-times" aria-hidden="true"></i></button></header>
        <div class="zk-dialogo__cuerpo"><dl class="auditoria-detalle__datos"><div><dt>Quién</dt><dd data-detalle-persona></dd></div><div><dt>Cuándo</dt><dd data-detalle-fecha></dd></div><div><dt>Módulo</dt><dd data-detalle-modulo></dd></div><div><dt>IP</dt><dd data-detalle-ip></dd></div></dl><section class="auditoria-cambios"><h3>Qué cambió</h3><table><thead><tr><th>Campo</th><th>Antes</th><th>Después</th></tr></thead><tbody data-detalle-cambios></tbody></table><p data-detalle-sin-cambios hidden>No se registraron cambios de campos para este evento.</p></section><p class="auditoria-solo-lectura">Solo lectura: la auditoría no se edita ni se borra.</p></div>
    </div>
</dialog>
<script src="js/auditoria.js?v=<?= $e(App::assetVersion()) ?>" defer></script>
