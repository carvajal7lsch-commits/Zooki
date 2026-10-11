<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../../helpers/Turnstile.php';

final class TurnstileTest extends TestCase
{
    public function testExigeTokenYNoConsultaSiEsVacioOLargo(): void
    {
        $llamadas = 0;
        $captcha = new Turnstile(static function () use (&$llamadas): bool {
            $llamadas++;
            return true;
        });
        $this->assertFalse($captcha->validar('', '192.0.2.10'));
        $this->assertFalse($captcha->validar(str_repeat('x', 2049), '192.0.2.10'));
        $this->assertSame(0, $llamadas);
    }

    public function testProveedorSustituibleRecibeTokenEIp(): void
    {
        $captcha = new Turnstile(function ($token, $ip): bool {
            $this->assertSame('token', $token);
            $this->assertSame('192.0.2.10', $ip);
            return true;
        });
        $this->assertTrue($captcha->validar('token', '192.0.2.10'));
        $this->assertFalse((new Turnstile(static fn () => false))->validar('token', '192.0.2.10'));
    }

    public function testFalloDelProveedorRechazaEnLugarDeAbrirAcceso(): void
    {
        $captcha = new Turnstile(static function (): bool {
            throw new RuntimeException('Proveedor simulado sin conexión.');
        });
        $this->assertFalse($captcha->validar('token', '192.0.2.10'));
    }
}
