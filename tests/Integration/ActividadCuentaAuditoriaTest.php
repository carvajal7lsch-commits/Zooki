<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../models/Auditoria.php';

/**
 * HU-42: la actividad de «Mi perfil» sale de la auditoría y solo muestra lo
 * que corresponde a la propia cuenta.
 */
class ActividadCuentaAuditoriaTest extends TestCase
{
    private PDO $db;
    private Auditoria $auditoria;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->exec("CREATE TABLE auditoria_sistema (
            id_auditoria INTEGER PRIMARY KEY, usuario_doc TEXT, ip_address TEXT, fecha_hora TEXT,
            accion TEXT, tabla_afectada TEXT, registro_id TEXT, datos_anteriores TEXT, datos_nuevos TEXT, descripcion TEXT)");

        $filas = [
            ['U1', '2026-09-10 08:00:00', 'LOGIN', 'usuarios', 'U1', 'Inicio de sesión exitoso'],
            ['U1', '2026-09-12 09:00:00', 'LOGIN_FAIL', 'usuarios', 'U1', 'Intento de login fallido: credenciales incorrectas'],
            ['U1', '2026-09-13 10:00:00', 'UPDATE', 'usuarios', 'U1', 'Cambio de contraseña'],
            ['U1', '2026-09-14 11:00:00', 'UPDATE', 'mascotas', '7', 'Edición de mascota'],
            ['U1', '2026-09-14 12:00:00', 'LOGOUT', 'usuarios', 'U1', 'Cierre de sesión'],
            ['U2', '2026-09-15 07:00:00', 'LOGIN', 'usuarios', 'U2', 'Inicio de sesión exitoso'],
            ['U1', '2026-09-15 07:30:00', 'LOGIN', 'usuarios', 'U1', 'Inicio de sesion con Google'],
        ];
        $stmt = $this->db->prepare("INSERT INTO auditoria_sistema (usuario_doc, fecha_hora, accion, tabla_afectada, registro_id, descripcion) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($filas as $fila) {
            $stmt->execute($fila);
        }

        $this->auditoria = new Auditoria($this->db);
    }

    public function testSoloAccesosYCambiosDeLaPropiaCuentaDelMasNuevoAlMasViejo(): void
    {
        $actividad = $this->auditoria->actividadDeCuenta('U1');

        $this->assertSame(
            ['Inicio de sesion con Google', 'Cambio de contraseña', 'Intento de login fallido: credenciales incorrectas', 'Inicio de sesión exitoso'],
            array_column($actividad, 'descripcion')
        );
    }

    public function testRespetaElLimite(): void
    {
        $this->assertCount(2, $this->auditoria->actividadDeCuenta('U1', 2));
    }

    public function testCuentaLosIntentosFallidosDesdeUnaFecha(): void
    {
        $this->assertSame(1, $this->auditoria->contarAccesosFallidos('U1', '2026-09-01 00:00:00'));
        $this->assertSame(0, $this->auditoria->contarAccesosFallidos('U1', '2026-09-13 00:00:00'));
        $this->assertSame(0, $this->auditoria->contarAccesosFallidos('U2', '2026-09-01 00:00:00'));
    }
}
