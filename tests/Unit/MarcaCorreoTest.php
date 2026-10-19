<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../config/EmailService.php';

/** C1.1, RE-7.4.1/4: inspección del correo compuesto, sin SMTP ni CSS de la web. */
final class MarcaCorreoTest extends TestCase
{
    private function servicio(): EmailService
    {
        return new EmailService(['SMTP_FROM' => 'no-reply@example.com']);
    }

    private function marca(string $nombre = 'Norte'): array
    {
        return ['nombre' => $nombre . ' <b> & "equipo"', 'direccion' => 'Calle <10> & Avenida',
            'telefono' => '3001234567 & extensión 1', 'email_contacto' => 'norte@example.com',
            'telefono_urgencias' => '3007654321 <urgencias>', 'url_logo' => 'https://example.com/logo.png?a=1&b=2'];
    }

    private function documento(string $html): DOMXPath
    {
        $dom = new DOMDocument();
        $estado = libxml_use_internal_errors(true);
        try {
            $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($estado);
        }
        return new DOMXPath($dom);
    }

    private function componer(EmailService $servicio, ?array $marca): string
    {
        return $servicio->obtenerPlantillaBaseHTML('Fabio', 'Aviso', '<p>Contenido</p>', null, null, $marca);
    }

    public function testCorreoRealSeparaCadaCampoYConservaLogoProporcionalYEscapes(): void
    {
        $marca = $this->marca();
        $html = $this->componer($this->servicio(), $marca);
        $xpath = $this->documento($html);
        $tablas = $xpath->query('//table[@class="marca-clinica-correo"]');
        $this->assertCount(1, $tablas);
        $tabla = $tablas->item(0);
        $this->assertSame('presentation', $tabla->getAttribute('role'));
        $celdas = $xpath->query('./tbody/tr/td', $tabla);
        $this->assertCount(6, $celdas);
        $esperado = [$marca['nombre'], $marca['direccion'], $marca['telefono'], $marca['email_contacto'],
            'Urgencias: ' . $marca['telefono_urgencias']];
        foreach ($esperado as $indice => $valor) {
            $this->assertSame($valor, $celdas->item($indice + 1)->textContent);
        }
        $logo = $xpath->query('./tbody/tr[1]/td/img', $tabla);
        $this->assertCount(1, $logo);
        $this->assertSame('96', $logo->item(0)->getAttribute('width'));
        $this->assertFalse($logo->item(0)->hasAttribute('height'), 'La altura natural conserva la proporción.');
        $this->assertSame($marca['url_logo'], $logo->item(0)->getAttribute('src'));
        $this->assertSame('Logo de ' . $marca['nombre'], $logo->item(0)->getAttribute('alt'));
        $this->assertCount(0, $xpath->query('.//b | .//script | .//style | .//*[@style]', $tabla));
        $this->assertStringContainsString('Norte &lt;b&gt; &amp; &quot;equipo&quot;', $html);
        $this->assertStringContainsString('a=1&amp;b=2', $html);
        $this->assertCount(0, $xpath->query('//link[contains(@href,"marca-clinica.css")]'));
    }

    public function testCorreoRealOmiteContactosAusentesYLogoInvalidoSinFilasVacias(): void
    {
        $servicio = $this->servicio();
        foreach (['javascript:alert(1)', '//example.com/logo.png', ''] as $logo) {
            $marca = ['nombre' => 'Sur', 'url_logo' => $logo, 'direccion' => '', 'telefono' => null];
            $xpath = $this->documento($this->componer($servicio, $marca));
            $tabla = $xpath->query('//table[@class="marca-clinica-correo"]')->item(0);
            $this->assertNotNull($tabla);
            $celdas = $xpath->query('./tbody/tr/td', $tabla);
            $this->assertCount(1, $celdas);
            $this->assertSame('Sur', $celdas->item(0)->textContent);
            $this->assertCount(0, $xpath->query('.//img', $tabla));
        }
    }

    public function testServicioNoMezclaNorteSurNiMensajesGlobalesEntreLlamadas(): void
    {
        $servicio = $this->servicio();
        $norte = $this->marca();
        $sur = array_replace($this->marca('Sur'), ['email_contacto' => 'sur@example.com', 'url_logo' => 'https://example.com/sur.png']);
        foreach ([$norte, $sur, null, $norte, null] as $marca) {
            $html = $this->componer($servicio, $marca);
            $xpath = $this->documento($html);
            $tablas = $xpath->query('//table[@class="marca-clinica-correo"]');
            $this->assertCount($marca === null ? 0 : 1, $tablas);
            $this->assertStringContainsString('Zooki', $html);
            if ($marca === null) {
                $this->assertStringNotContainsString('norte@example.com', $html);
                $this->assertStringNotContainsString('sur@example.com', $html);
                $this->assertStringNotContainsString('https://example.com/', $html);
                $this->assertStringNotContainsString(htmlspecialchars($norte['nombre'], ENT_QUOTES, 'UTF-8'), $html);
                $this->assertStringNotContainsString(htmlspecialchars($sur['nombre'], ENT_QUOTES, 'UTF-8'), $html);
                continue;
            }
            $this->assertSame($marca['nombre'], $xpath->query('./tbody/tr[2]/td/strong', $tablas->item(0))->item(0)->textContent);
            $this->assertSame($marca['url_logo'], $xpath->query('.//img', $tablas->item(0))->item(0)->getAttribute('src'));
            $this->assertStringContainsString($marca['email_contacto'], $html);
            $this->assertStringNotContainsString($marca === $norte ? 'sur@example.com' : 'norte@example.com', $html);
            $this->assertStringNotContainsString(htmlspecialchars(($marca === $norte ? $sur : $norte)['nombre'], ENT_QUOTES, 'UTF-8'), $html);
        }
    }

    public function testSalidaWebConservaSuEstructuraYNoUsaLaTablaDeCorreo(): void
    {
        $marca = $this->marca();
        $html = MarcaClinica::html($marca);
        $e = static fn (string $valor): string => htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
        $esperado = '<div class="marca-clinica"><img class="marca-clinica-logo" width="96" src="'
            . $e($marca['url_logo']) . '" alt="Logo de ' . $e($marca['nombre']) . '"><strong>' . $e($marca['nombre']) . '</strong>';
        foreach (['direccion', 'telefono', 'email_contacto'] as $campo) {
            $esperado .= '<span>' . $e($marca[$campo]) . '</span>';
        }
        $esperado .= '<span>Urgencias: ' . $e($marca['telefono_urgencias']) . '</span></div>';
        $this->assertSame($esperado, $html);
        $this->assertSame('', MarcaClinica::html(null, true));
    }
}
