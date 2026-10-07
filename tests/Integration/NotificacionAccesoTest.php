<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/NotificacionInterna.php';

/**
 * HU-T.6 (T-01 / VD-SEG-04) en la v2 — Una notificación interna pertenece a
 * una clínica y va dirigida a una persona o a un rol dentro de ella. Nadie
 * puede leer ni marcar las de otra persona, ni las de otra clínica aunque
 * tenga el mismo rol (RN-G13).
 */
class NotificacionAccesoTest extends TestCase
{
    private const ADMIN = Roles::ADMIN;
    private const VET = Roles::VETERINARIO;
    private const NORTE = DosClinicas::NORTE;
    private const SUR = DosClinicas::SUR;

    private PDO $db;
    private NotificacionInterna $modelo;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        $this->modelo = new NotificacionInterna($this->db);
    }

    private function idDeLaUltima(): int
    {
        return (int) $this->db->lastInsertId();
    }

    private function estaLeida(int $id): bool
    {
        $stmt = $this->db->prepare('SELECT leida FROM notificaciones_internas WHERE id = ?');
        $stmt->execute([$id]);

        return (bool) $stmt->fetchColumn();
    }

    public function testElDestinatarioReconoceSuPropiaNotificacion(): void
    {
        $this->modelo->crearParaUsuario(self::NORTE, 2, 'NUEVA_CITA', 'Cita', 'Tienes una cita');
        $this->assertTrue($this->modelo->perteneceA($this->idDeLaUltima(), self::NORTE, 2, self::VET));
    }

    /** El caso que motivo el hallazgo: notificacion de otra persona. */
    public function testUnUsuarioNoPuedeTocarLaNotificacionDeOtro(): void
    {
        $this->modelo->crearParaUsuario(self::NORTE, 2, 'NUEVA_CITA', 'Cita', 'Privada');
        $id = $this->idDeLaUltima();

        $this->assertFalse($this->modelo->perteneceA($id, self::NORTE, 8, self::VET));
        $this->modelo->marcarLeida($id, self::NORTE, 8, self::VET);
        $this->assertFalse($this->estaLeida($id), 'Un tercero no debe poder marcar como leida una notificacion ajena');
    }

    public function testLaNotificacionDeRolLlegaAQuienTieneEseRolEnLaClinica(): void
    {
        $this->modelo->crearParaRol(self::NORTE, self::ADMIN, 'ALERTA', 'Aviso', 'Para administradores');
        $id = $this->idDeLaUltima();

        $this->assertTrue($this->modelo->perteneceA($id, self::NORTE, 1, self::ADMIN));
        $this->assertFalse($this->modelo->perteneceA($id, self::NORTE, 2, self::VET));
    }

    /** RN-G13: el mismo rol en otra clínica no da acceso. */
    public function testElMismoRolEnOtraClinicaNoVeLaNotificacion(): void
    {
        $this->modelo->crearParaRol(self::NORTE, self::ADMIN, 'ALERTA', 'Aviso', 'Para administradores de Norte');
        $id = $this->idDeLaUltima();

        $this->assertFalse($this->modelo->perteneceA($id, self::SUR, 3, self::ADMIN));
        $this->assertSame([], $this->modelo->obtenerParaUsuario(self::SUR, 3, self::ADMIN));
        $this->assertSame(0, $this->modelo->contarNoLeidas(self::SUR, 3, self::ADMIN));
        $this->modelo->marcarTodasLeidas(self::SUR, 3, self::ADMIN);
        $this->assertFalse($this->estaLeida($id));
    }

    /** Una persona con roles en dos clínicas ve en cada contexto solo los avisos de esa clínica. */
    public function testLaPersonaConDosClinicasVeCadaUnaPorSeparado(): void
    {
        $this->modelo->crearParaUsuario(self::NORTE, DosClinicas::DOBLE, 'X', 'Norte', 'n');
        $this->modelo->crearParaUsuario(self::SUR, DosClinicas::DOBLE, 'X', 'Sur', 's');

        $this->assertSame(['Norte'], array_column($this->modelo->obtenerParaUsuario(self::NORTE, DosClinicas::DOBLE, self::VET), 'titulo'));
        $this->assertSame(['Sur'], array_column($this->modelo->obtenerParaUsuario(self::SUR, DosClinicas::DOBLE, self::ADMIN), 'titulo'));
    }

    public function testElDestinatarioSiPuedeMarcarlaComoLeida(): void
    {
        $this->modelo->crearParaUsuario(self::NORTE, 2, 'NUEVA_CITA', 'Cita', 'Tienes una cita');
        $id = $this->idDeLaUltima();

        $this->modelo->marcarLeida($id, self::NORTE, 2, self::VET);
        $this->assertTrue($this->estaLeida($id));
    }

    /**
     * Volver a marcar algo ya leido no es un intento de acceso ajeno: se
     * comprueba con perteneceA() y no con las filas afectadas por el UPDATE.
     */
    public function testMarcarDosVecesSigueSiendoValido(): void
    {
        $this->modelo->crearParaUsuario(self::NORTE, 2, 'NUEVA_CITA', 'Cita', 'Tienes una cita');
        $id = $this->idDeLaUltima();

        $this->modelo->marcarLeida($id, self::NORTE, 2, self::VET);
        $this->assertTrue($this->modelo->perteneceA($id, self::NORTE, 2, self::VET));
        $this->assertTrue($this->estaLeida($id));
    }

    public function testUnIdInexistenteNoPerteneceANadie(): void
    {
        $this->assertFalse($this->modelo->perteneceA(4242, self::NORTE, 1, self::ADMIN));
    }

    public function testMarcarTodasSoloAfectaLasPropias(): void
    {
        $this->modelo->crearParaUsuario(self::NORTE, 2, 'X', 'Mia', 'm');
        $mia = $this->idDeLaUltima();
        $this->modelo->crearParaUsuario(self::NORTE, 8, 'X', 'Ajena', 'a');
        $ajena = $this->idDeLaUltima();

        $this->modelo->marcarTodasLeidas(self::NORTE, 2, self::VET);
        $this->assertTrue($this->estaLeida($mia));
        $this->assertFalse($this->estaLeida($ajena));
    }

    public function testElContadorDeNoLeidasEsUnEntero(): void
    {
        $this->modelo->crearParaUsuario(self::NORTE, 2, 'X', 'Mia', 'm');
        $this->assertSame(1, $this->modelo->contarNoLeidas(self::NORTE, 2, self::VET));
    }

    /** El aviso de una cita que ya pasó no se muestra ni cuenta como nuevo. */
    public function testUnaNotificacionVencidaNoSeMuestraNiSeCuenta(): void
    {
        $this->modelo->crearParaUsuario(self::NORTE, 2, 'NUEVA_CITA', 'Cita', 'Pasada', null, 5, '2000-01-01 08:00:00');

        $this->assertSame([], $this->modelo->obtenerParaUsuario(self::NORTE, 2, self::VET));
        $this->assertSame(0, $this->modelo->contarNoLeidas(self::NORTE, 2, self::VET));
    }

    public function testUnaNotificacionDeCitaFuturaSiSeMuestra(): void
    {
        $this->modelo->crearParaUsuario(self::NORTE, 2, 'NUEVA_CITA', 'Cita', 'Futura', null, 5, '2999-01-01 08:00:00');

        $this->assertCount(1, $this->modelo->obtenerParaUsuario(self::NORTE, 2, self::VET));
        $this->assertSame(1, $this->modelo->contarNoLeidas(self::NORTE, 2, self::VET));
    }

    /** Al cancelar o atender una cita se retiran solo los avisos de esa cita. */
    public function testExpirarDeCitaRetiraSoloLosAvisosDeEsaCita(): void
    {
        $this->modelo->crearParaUsuario(self::NORTE, 2, 'NUEVA_CITA', 'Cita 5', 'a', null, 5, '2999-01-01 08:00:00');
        $this->modelo->crearParaUsuario(self::NORTE, 2, 'NUEVA_CITA', 'Cita 6', 'b', null, 6, '2999-01-01 08:00:00');

        $this->modelo->expirarDeCita(5);

        $this->assertSame(['Cita 6'], array_column($this->modelo->obtenerParaUsuario(self::NORTE, 2, self::VET), 'titulo'));
    }
}
