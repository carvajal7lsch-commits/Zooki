<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../helpers/ValidadorTelefono.php';

/** C9.1: el teléfono se valida igual en el servidor y en el formulario. */
final class ValidadorTelefonoTest extends TestCase
{
    public static function casos(): array
    {
        return [
            'celular' => ['3001234567', true],
            'con indicativo y espacios' => ['+57 300 123 4567', true],
            'con guiones' => ['601-234-5678', true],
            'mínimo' => ['1234567', true],
            'máximo' => [str_repeat('1', 20), true],
            'corto' => ['123456', false],
            'largo' => [str_repeat('1', 21), false],
            'letras' => ['300abc4567', false],
            'paréntesis' => ['(300) 1234567', false],
            'puntos' => ['300.123.4567', false],
        ];
    }

    /** @dataProvider casos */
    public function testElServidorYElPatronDelHtmlCoinciden(string $telefono, bool $valido): void
    {
        $this->assertSame($valido, ValidadorTelefono::esValido($telefono));
        // El navegador ancla el atributo pattern como ^(?:patrón)$.
        $html = '/^(?:' . ValidadorTelefono::patronHtml() . ')$/D';
        $this->assertSame($valido, preg_match($html, $telefono) === 1);
    }

    public function testLosCaracteresQueFiltraElJsSonLosQueAceptaElServidor(): void
    {
        $limpio = preg_replace('/[^' . ValidadorTelefono::CARACTERES . ']/', '', '+57 (300) abc-123 4567');
        $this->assertSame('+57 300 -123 4567', $limpio);
        $this->assertTrue(ValidadorTelefono::esValido($limpio));
    }

    public function testNormalizarQuitaEspaciosRepetidosYDeLosBordes(): void
    {
        $this->assertSame('+57 300 123', ValidadorTelefono::normalizar("  +57   300\t123 "));
    }
}
