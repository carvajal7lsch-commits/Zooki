<?php
/** HU-0.5/HU-7.4: destino interno único; origen, página y referencia no eligen una URL externa. */
final class EnlaceAjustes
{
    public static function suscripcion(array $entrada = []): string
    {
        $parametros = ['action' => 'admin_configuracion', 'seccion' => 'suscripcion'];
        if (($entrada['desde'] ?? null) === 'inicio') {
            $parametros['desde'] = 'inicio';
        }
        $pagina = filter_var($entrada['pagina'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);
        if ($pagina !== false) {
            $parametros['pagina'] = $pagina;
        }
        if (($entrada['vista_suscripcion'] ?? null) === 'historial' || isset($parametros['pagina'])) {
            $parametros['vista_suscripcion'] = 'historial';
        }
        if (is_string($entrada['referencia'] ?? null) && preg_match('/^zk_[a-f0-9]{48}$/D', $entrada['referencia'])) {
            $parametros['referencia'] = $entrada['referencia'];
        }
        return 'index.php?' . http_build_query($parametros) . '#suscripcion';
    }
}
