<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/Auditoria.php';

/**
 * HU-T.5 y HU-T.8 en la v2: la actividad de «Mi perfil» es de la persona
 * (id_usuario) y el panel de auditoría solo ve su clínica (RN-G13).
 */
class ActividadCuentaAuditoriaTest extends TestCase
{
    private PDO $db;
    private Auditoria $auditoria;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->db = DosClinicas::sqlite();

        // [id_clinica, id_usuario, fecha, accion, tabla, registro, descripcion]
        $filas = [
            [null, 1, '2026-09-10 08:00:00', 'LOGIN', 'usuarios', '1', 'Inicio de sesión exitoso'],
            [null, 1, '2026-09-12 09:00:00', 'LOGIN_FAIL', 'usuarios', '1', 'Intento de login fallido: credenciales incorrectas'],
            [1, 1, '2026-09-13 10:00:00', 'UPDATE', 'usuarios', '1', 'Cambio de contraseña'],
            [1, 1, '2026-09-14 11:00:00', 'UPDATE', 'mascotas', '7', 'Edición de mascota'],
            [1, 1, '2026-09-14 12:00:00', 'LOGOUT', 'usuarios', '1', 'Cierre de sesión'],
            [null, 2, '2026-09-15 07:00:00', 'LOGIN', 'usuarios', '2', 'Inicio de sesión exitoso'],
            [null, 1, '2026-09-15 07:30:00', 'LOGIN', 'usuarios', '1', 'Inicio de sesion con Google'],
            [2, 3, '2026-09-15 08:00:00', 'UPDATE', 'usuarios', '4', 'Personal actualizado'],
        ];
        $stmt = $this->db->prepare("INSERT INTO auditoria_sistema (id_clinica, id_usuario, fecha_hora, accion, tabla_afectada, registro_id, descripcion) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($filas as $fila) {
            $stmt->execute($fila);
        }

        $this->auditoria = new Auditoria($this->db);
    }

    public function testSoloAccesosYCambiosDeLaPropiaCuentaDelMasNuevoAlMasViejo(): void
    {
        $this->assertSame(
            ['Inicio de sesion con Google', 'Cambio de contraseña', 'Intento de login fallido: credenciales incorrectas', 'Inicio de sesión exitoso'],
            array_column($this->auditoria->actividadDeCuenta(1), 'descripcion')
        );
    }

    public function testRespetaElLimite(): void
    {
        $this->assertCount(2, $this->auditoria->actividadDeCuenta(1, 2));
    }

    public function testCuentaLosIntentosFallidosDesdeUnaFecha(): void
    {
        $this->assertSame(1, $this->auditoria->contarAccesosFallidos(1, '2026-09-01 00:00:00'));
        $this->assertSame(0, $this->auditoria->contarAccesosFallidos(1, '2026-09-13 00:00:00'));
        $this->assertSame(0, $this->auditoria->contarAccesosFallidos(2, '2026-09-01 00:00:00'));
    }

    /** RN-G13: el panel de la clínica solo ve sus registros, nunca los de otra ni los de la plataforma. */
    public function testElPanelSoloVeLaClinicaActiva(): void
    {
        $norte = $this->auditoria->getLogs(DosClinicas::NORTE);
        $this->assertSame(['Cierre de sesión', 'Edición de mascota', 'Cambio de contraseña'], array_column($norte, 'descripcion'));
        $this->assertSame(3, $this->auditoria->countLogs(DosClinicas::NORTE));
        $this->assertSame(['Personal actualizado'], array_column($this->auditoria->getLogs(DosClinicas::SUR), 'descripcion'));
    }

    public function testLaPantallaDeAuditoriaFuncionaYElMenuIncluyeSuEnlace(): void
    {
        require_once __DIR__ . '/../../controllers/AuditoriaController.php';
        Contexto::iniciarIdentidad(1, 'Ana Norte', false, 'password');
        Contexto::activar(Contexto::deClinica(1, 'Clínica Norte', Roles::ADMIN), 1);
        $_GET = ['action' => 'admin_auditoria'];
        ob_start();
        try {
            (new AuditoriaController($this->db))->listar();
            $html = ob_get_contents();
        } finally {
            ob_end_clean();
            $_GET = [];
        }
        $this->assertStringContainsString('action=admin_auditoria', $html);
        $this->assertStringContainsString('js/auditoria.js', $html);
        $this->assertStringContainsString('Cambio de contraseña', $html);
        $this->assertStringNotContainsString('Personal actualizado', $html);
    }

    /** RE-T.8.3: el filtro por persona busca por nombre, documento o correo. */
    public function testElFiltroPorPersonaUsaNombreDocumentoOCorreo(): void
    {
        foreach (['Ana', '1000000001', 'ana@zooki.test'] as $persona) {
            $this->assertSame(3, $this->auditoria->countLogs(DosClinicas::NORTE, ['usuario' => $persona]), $persona);
        }
        $this->assertSame(0, $this->auditoria->countLogs(DosClinicas::NORTE, ['usuario' => 'Carla']));
        $this->assertSame('Ana Norte', $this->auditoria->getLogs(DosClinicas::NORTE)[0]['usuario_nombre']);
    }

    /** log() toma la clínica del contexto activo, salvo que se indique otra o ninguna. */
    public function testElRegistroTomaLaClinicaDelContexto(): void
    {
        $_SESSION = ['id_usuario' => 1];
        Contexto::activar(Contexto::deClinica(DosClinicas::SUR, 'Sur', Roles::ADMIN), 1);

        $this->auditoria->log(3, 'UPDATE', 'usuarios', 4, null, null, 'En el contexto');
        $this->auditoria->log(3, 'LOGIN', 'usuarios', 3, null, null, 'De la plataforma', null);

        $filas = $this->db->query("SELECT descripcion, id_clinica FROM auditoria_sistema WHERE descripcion IN ('En el contexto', 'De la plataforma') ORDER BY id_auditoria")->fetchAll(PDO::FETCH_KEY_PAIR);
        $this->assertSame(DosClinicas::SUR, (int) $filas['En el contexto']);
        $this->assertNull($filas['De la plataforma']);
    }

    /** C9: la auditoría recibe el identificador estable y conserva la persona. */
    public function testElRegistroRecibeElIdUsuarioEstable(): void
    {
        $this->auditoria->log(1, 'UPDATE', 'usuarios', 1, null, null, 'Identidad v2', null);
        $this->assertSame(1, (int) $this->db->query("SELECT id_usuario FROM auditoria_sistema WHERE descripcion='Identidad v2'")->fetchColumn());
    }
}
