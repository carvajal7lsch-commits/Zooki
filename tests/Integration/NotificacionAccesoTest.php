<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../models/NotificacionInterna.php';

/**
 * T-01 (VD-SEG-04) — Aislamiento de notificaciones entre usuarios.
 *
 * RN-G02: nadie puede tocar informacion que no le pertenece. La matriz RBAC
 * concede `marcar_notificacion_leida_ajax` a los cuatro roles, asi que el
 * control por rol no basta: hace falta comprobar el destinatario de cada
 * notificacion. Estas pruebas blindan esa comprobacion.
 */
class NotificacionAccesoTest extends TestCase
{
    private const ADMIN       = 1;
    private const PROPIETARIO = 4;

    private PDO $db;
    private NotificacionInterna $modelo;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->exec("
            CREATE TABLE notificaciones_internas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                doc_usuario TEXT NULL,
                id_rol_destino INTEGER NULL,
                tipo TEXT,
                titulo TEXT,
                mensaje TEXT,
                enlace TEXT NULL,
                id_cita INTEGER NULL,
                vigente_hasta TEXT NULL,
                leida INTEGER DEFAULT 0,
                fecha_creacion TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

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
        $this->modelo->crearParaUsuario('111', 'NUEVA_CITA', 'Cita', 'Tienes una cita');
        $id = $this->idDeLaUltima();

        $this->assertTrue($this->modelo->perteneceA($id, '111', self::PROPIETARIO));
    }

    /** El caso que motivo el hallazgo: notificacion de otra persona. */
    public function testUnUsuarioNoPuedeTocarLaNotificacionDeOtro(): void
    {
        $this->modelo->crearParaUsuario('111', 'NUEVA_CITA', 'Cita', 'Privada');
        $id = $this->idDeLaUltima();

        $this->assertFalse($this->modelo->perteneceA($id, '999', self::PROPIETARIO));

        $this->modelo->marcarLeida($id, '999', self::PROPIETARIO);
        $this->assertFalse(
            $this->estaLeida($id),
            'Un tercero no debe poder marcar como leida una notificacion ajena'
        );
    }

    public function testLaNotificacionDeRolLlegaAQuienTieneEseRol(): void
    {
        $this->modelo->crearParaRol(self::ADMIN, 'ALERTA', 'Aviso', 'Para administradores');
        $id = $this->idDeLaUltima();

        $this->assertTrue($this->modelo->perteneceA($id, '111', self::ADMIN));
        $this->assertFalse($this->modelo->perteneceA($id, '111', self::PROPIETARIO));
    }

    public function testElDestinatarioSiPuedeMarcarlaComoLeida(): void
    {
        $this->modelo->crearParaUsuario('111', 'NUEVA_CITA', 'Cita', 'Tienes una cita');
        $id = $this->idDeLaUltima();

        $this->modelo->marcarLeida($id, '111', self::PROPIETARIO);
        $this->assertTrue($this->estaLeida($id));
    }

    /**
     * Volver a marcar algo ya leido no es un intento de acceso ajeno. Se
     * comprueba con perteneceA() y no con las filas afectadas por el UPDATE
     * justamente por esto: MySQL no cuenta como afectada una fila que ya tenia
     * el valor final.
     */
    public function testMarcarDosVecesSigueSiendoValido(): void
    {
        $this->modelo->crearParaUsuario('111', 'NUEVA_CITA', 'Cita', 'Tienes una cita');
        $id = $this->idDeLaUltima();

        $this->modelo->marcarLeida($id, '111', self::PROPIETARIO);

        $this->assertTrue($this->modelo->perteneceA($id, '111', self::PROPIETARIO));
        $this->assertTrue($this->estaLeida($id));
    }

    public function testUnIdInexistenteNoPerteneceANadie(): void
    {
        $this->assertFalse($this->modelo->perteneceA(4242, '111', self::ADMIN));
    }

    public function testMarcarTodasSoloAfectaLasPropias(): void
    {
        $this->modelo->crearParaUsuario('111', 'X', 'Mia', 'm');
        $mia = $this->idDeLaUltima();
        $this->modelo->crearParaUsuario('999', 'X', 'Ajena', 'a');
        $ajena = $this->idDeLaUltima();

        $this->modelo->marcarTodasLeidas('111', self::PROPIETARIO);

        $this->assertTrue($this->estaLeida($mia));
        $this->assertFalse($this->estaLeida($ajena));
    }

    public function testElContadorDeNoLeidasEsUnEntero(): void
    {
        $this->modelo->crearParaUsuario('111', 'X', 'Mia', 'm');

        $this->assertSame(1, $this->modelo->contarNoLeidas('111', self::PROPIETARIO));
    }

    /** El aviso de una cita que ya pasó no se muestra ni cuenta como nuevo. */
    public function testUnaNotificacionVencidaNoSeMuestraNiSeCuenta(): void
    {
        $this->modelo->crearParaUsuario('111', 'NUEVA_CITA', 'Cita', 'Pasada', null, 5, '2000-01-01 08:00:00');

        $this->assertSame([], $this->modelo->obtenerParaUsuario('111', self::PROPIETARIO));
        $this->assertSame(0, $this->modelo->contarNoLeidas('111', self::PROPIETARIO));
    }

    public function testUnaNotificacionDeCitaFuturaSiSeMuestra(): void
    {
        $this->modelo->crearParaUsuario('111', 'NUEVA_CITA', 'Cita', 'Futura', null, 5, '2999-01-01 08:00:00');

        $this->assertCount(1, $this->modelo->obtenerParaUsuario('111', self::PROPIETARIO));
        $this->assertSame(1, $this->modelo->contarNoLeidas('111', self::PROPIETARIO));
    }

    /** Al cancelar o atender una cita se retiran solo los avisos de esa cita. */
    public function testExpirarDeCitaRetiraSoloLosAvisosDeEsaCita(): void
    {
        $this->modelo->crearParaUsuario('111', 'NUEVA_CITA', 'Cita 5', 'm', null, 5, '2999-01-01 08:00:00');
        $this->modelo->crearParaUsuario('111', 'NUEVA_CITA', 'Cita 6', 'm', null, 6, '2999-01-01 09:00:00');

        $this->modelo->expirarDeCita(5);

        $visibles = $this->modelo->obtenerParaUsuario('111', self::PROPIETARIO);
        $this->assertCount(1, $visibles);
        $this->assertSame('Cita 6', $visibles[0]['titulo']);
    }
}
