<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../../helpers/EnlaceAjustes.php';

final class EnlaceAjustesTest extends TestCase
{
    public function testDestinoInternoYOrigenLimitado(): void
    {
        $base = 'index.php?action=admin_configuracion&seccion=suscripcion';
        $this->assertSame($base . '#suscripcion', EnlaceAjustes::suscripcion());
        $this->assertSame($base . '&desde=inicio#suscripcion', EnlaceAjustes::suscripcion(['desde' => 'inicio']));
        foreach (['configuracion', 'https://ajeno.test', '<script>', ['inicio']] as $origen) {
            $this->assertSame($base . '#suscripcion', EnlaceAjustes::suscripcion(['desde' => $origen]));
        }
    }

    public function testPaginacionRetornoYParametrosAjenos(): void
    {
        $referencia = 'zk_' . str_repeat('a', 48);
        $url = EnlaceAjustes::suscripcion(['desde' => 'inicio', 'pagina' => '2', 'referencia' => $referencia, 'id' => 'externo', 'status' => 'APPROVED']);
        parse_str(parse_url($url, PHP_URL_QUERY), $parametros);
        $this->assertSame(['action' => 'admin_configuracion', 'seccion' => 'suscripcion', 'desde' => 'inicio', 'pagina' => '2', 'vista_suscripcion' => 'historial', 'referencia' => $referencia], $parametros);
        $this->assertSame('suscripcion', parse_url($url, PHP_URL_FRAGMENT));
        foreach (['0', '-1', '100001', '2.5', ['2']] as $pagina) {
            $url = EnlaceAjustes::suscripcion(['pagina' => $pagina, 'referencia' => 'id_ajeno']);
            $this->assertStringNotContainsString('pagina=', $url);
            $this->assertStringNotContainsString('referencia=', $url);
        }
        $this->assertStringContainsString('vista_suscripcion=historial', EnlaceAjustes::suscripcion(['vista_suscripcion' => 'historial']));
    }
}
