<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../../helpers/CorreoAgenda.php';

final class CorreoAgendaTest extends TestCase
{
    public function testAvisoDistinguePactadaYEstimadaSinCambiarDatosNiIntroducirHtml(): void
    {
        $datos = ['mascota_nombre'=>'Luna <b>','fecha'=>'2030-01-07','hora'=>'10:03:30','hora_pactada'=>'09:00:00','inicio_estimado'=>'2030-01-07 10:03:30','veterinario_nombre'=>'Beto & Norte'];
        $html = CorreoAgenda::contenido($datos,'Actualización <urgente>');
        $this->assertStringContainsString('<dt>Hora pactada</dt><dd>09:00:00</dd>',$html);
        $this->assertStringContainsString('<dt>Inicio estimado</dt><dd>2030-01-07 10:03:30</dd>',$html);
        $this->assertStringContainsString('Luna &lt;b&gt;',$html);
        $this->assertStringContainsString('Beto &amp; Norte',$html);
        $this->assertStringNotContainsString('<urgente>',$html);
        $this->assertStringNotContainsString('style=',$html);
    }
}
