<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../helpers/LimitePlan.php';
require_once __DIR__ . '/../../models/Mascota.php';
require_once __DIR__ . '/../../models/MascotaPropietario.php';
require_once __DIR__ . '/../../models/Cita.php';
require_once __DIR__ . '/../../models/CuentaTitular.php';

final class LimitePlanTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearCatalogosSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        DosClinicas::poblarAgenda($this->db);
        $this->db->exec('UPDATE clinicas SET id_plan = 1 WHERE id_clinica = 1');
        $this->comoPersonal();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
    }

    private function comoPersonal(): void
    {
        $_SESSION = ['id_usuario' => 1];
        Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::ADMIN), 1);
    }

    private function bloquear(callable $operacion, string $recurso, int $uso, int $limite): void
    {
        try {
            $operacion();
            $this->fail('Debió bloquear el cupo.');
        } catch (LimiteAlcanzado $error) {
            $this->assertSame("Alcanzaste el límite de $recurso de tu plan ($uso/$limite). Mejora tu plan para seguir.", $error->getMessage());
        }
    }

    private function mascota(): array
    {
        return ['nombre' => 'Mora', 'id_propietario' => 6, 'id_especie' => 1, 'id_raza' => 49, 'sexo' => 'Hembra', 'fecha_nacimiento' => '2024-01-01', 'peso' => '3', 'colores' => [1]];
    }

    private function llenarMascotas(): void
    {
        $stmt = $this->db->prepare('INSERT INTO mascotas (id_propietario, id_clinica_registro, token_carnet, nombre) VALUES (6, 1, ?, ?)');
        $vinculo = $this->db->prepare('INSERT INTO mascota_clinica (id_mascota, id_clinica) VALUES (?, 1)');
        for ($numero = 2; $numero <= 50; $numero++) {
            $stmt->execute([bin2hex(random_bytes(32)), 'Mascota ' . $numero]);
            $vinculo->execute([(int) $this->db->lastInsertId()]);
        }
    }

    private function cita(string $fecha = '2027-02-15'): array
    {
        return ['id_mascota' => 1, 'id_veterinario' => 2, 'fecha' => $fecha, 'hora' => '08:00', 'motivo' => 'Control', 'id_tipo_cita' => 1];
    }

    private function llenarCitas(): void
    {
        $stmt = $this->db->prepare("INSERT INTO citas (id_clinica, id_mascota, id_veterinario, fecha, hora, motivo, estado) VALUES (1, 1, 2, '2027-02-01', ?, 'Cita del mes', 'completada')");
        for ($numero = 0; $numero < 100; $numero++) {
            $hora = sprintf('%02d:%02d:00', intdiv($numero, 60), $numero % 60);
            $stmt->execute([$hora]);
        }
    }

    public function testMascota51CreadaYVinculadaRechazadasSinDuplicacion(): void
    {
        $this->llenarMascotas();
        $modelo = new Mascota($this->db);
        $this->bloquear(fn () => $modelo->insert($this->mascota()), 'mascotas', 50, 50);
        $this->db->exec("INSERT INTO mascotas (id_mascota, id_propietario, token_carnet, nombre) VALUES (100, 6, 'global-sin-vinculo', 'Global')");
        $this->bloquear(fn () => $modelo->vincular(100, 6), 'mascotas', 50, 50);
        $modelo->vincular(1, 6);
        $this->assertSame(50, (int) $this->db->query('SELECT COUNT(*) FROM mascota_clinica')->fetchColumn());
        $this->assertSame(51, (int) $this->db->query('SELECT COUNT(*) FROM mascotas')->fetchColumn());
        $this->assertSame(2, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE descripcion LIKE 'Acción bloqueada%'")->fetchColumn());
    }

    public function testMascota50CabeYNoSeBloqueaEditarAlLimite(): void
    {
        $this->llenarMascotas();
        $this->db->exec('DELETE FROM mascota_clinica WHERE id_mascota = 50');
        $modelo = new Mascota($this->db);
        $this->assertGreaterThan(0, $modelo->insert($this->mascota()));
        $this->assertTrue($modelo->update(['id_mascota' => 1, 'peso' => '9']));
        $this->assertSame(50, (new LimitePlan($this->db, 1))->resumen()['mascotas']['uso']);
    }

    public function testUnIdDeMascotaAjenoSigueDando403AunqueNoHayaCupo(): void
    {
        $_SESSION = ['id_usuario' => 4];
        Contexto::activar(Contexto::deClinica(2, 'Sur', Roles::VETERINARIO), 1);
        $this->db->exec('UPDATE clinicas SET id_plan = 1 WHERE id_clinica = 2');
        $this->db->exec('UPDATE planes SET limite_citas_mes = 0 WHERE id_plan = 1');
        $datos = $this->cita();
        $datos['id_veterinario'] = 4;
        try {
            (new Cita($this->db))->registrar($datos);
            $this->fail('Debió rechazar la mascota ajena.');
        } catch (AccesoDenegado $error) {
            $this->assertSame(403, $error->codigo());
        }
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE id_clinica = 2 AND tabla_afectada = 'mascotas'")->fetchColumn());
    }

    public function testInvitacionesYActivacionesPendientesConsumenSinContarDosVeces(): void
    {
        $this->db->exec("UPDATE usuario_clinica SET estado = 'inactivo' WHERE id_clinica = 1 AND id_usuario IN (2, 5)");
        $datos = ['nombre_completo' => 'Lucía Nueva', 'tipo_documento' => 'CC', 'documento' => '1234567890', 'email' => 'lucia@zooki.test', 'telefono' => '3001234567', 'id_rol' => 2];
        (new CuentaTitular($this->db))->crearPersonal($datos, 1);
        $this->assertSame(2, (new LimitePlan($this->db, 1))->resumen()['personal']['uso']);
        $otro = $datos;
        $otro['documento'] = '1234567891';
        $otro['email'] = 'otra@zooki.test';
        $this->bloquear(fn () => (new CuentaTitular($this->db))->crearPersonal($otro, 1), 'personal', 2, 2);
        $persona = (new Usuario($this->db))->buscarPorId(4);
        $this->bloquear(fn () => (new InvitacionPersonal($this->db))->crear($persona, 1, $otro), 'personal', 2, 2);
        $this->db->exec("UPDATE verificaciones_email SET expires_at = '2000-01-01' WHERE proposito = 'activacion_personal'");
        (new InvitacionPersonal($this->db))->crear($persona, 1, $otro);
        $this->assertSame(2, (new LimitePlan($this->db, 1))->resumen()['personal']['uso']);
        $invitacion = $this->db->query("SELECT id FROM verificaciones_email WHERE proposito = 'invitacion_personal'")->fetchColumn();
        (new InvitacionPersonal($this->db))->reenviar((int) $invitacion, 1);
        $this->assertSame(2, (new LimitePlan($this->db, 1))->resumen()['personal']['uso']);
        $pendiente = (new Usuario($this->db))->buscarPorEmail('lucia@zooki.test');
        $this->bloquear(fn () => (new CuentaTitular($this->db))->restablecerPersonal((int) $pendiente['id_usuario'], 1), 'personal', 2, 2);
    }

    public function testCita101NoCabeCancelacionLiberaYMesSiguienteEmpiezaEnCero(): void
    {
        $this->llenarCitas();
        $modelo = new Cita($this->db);
        $this->bloquear(fn () => $modelo->registrar($this->cita()), 'citas', 100, 100);
        $this->db->exec("UPDATE citas SET estado = 'cancelada' WHERE id_cita = 1");
        $id = $modelo->registrar($this->cita());
        $this->assertGreaterThan(0, $id);
        $this->assertSame(100, (new LimitePlan($this->db, 1))->resumen('2027-02-15')['citas']['uso']);
        $this->assertSame(0, (new LimitePlan($this->db, 1))->resumen('2027-03-01')['citas']['uso']);
        $this->assertGreaterThan($id, $modelo->registrar($this->cita('2027-03-01')));
    }

    public function testReactivarPersonalPorEstadoOModalRespetaCupoYConservaLosDatos(): void
    {
        $this->db->exec("UPDATE usuario_clinica SET estado = 'inactivo' WHERE id_usuario = 2 AND id_clinica = 1");
        $usuario = new Usuario($this->db);
        $antes = $usuario->buscarPorId(2);
        $datos = $antes;
        $datos['nombre_completo'] = 'Beto Cambiado';
        $datos['id_rol'] = Roles::VETERINARIO;
        $datos['estado'] = 1;
        $this->bloquear(fn () => $usuario->cambiarEstadoEnClinica(2, 1, true), 'personal', 2, 2);
        $this->bloquear(fn () => $usuario->editarPersonalEnClinica(2, 1, $datos), 'personal', 2, 2);
        require_once __DIR__ . '/../../controllers/UsuarioController.php';
        $controller = new UsuarioController($this->db);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $datos + ['id_usuario' => 2];
        foreach (['cambiarEstadoAjax', 'actualizarAjax'] as $metodo) {
            ob_start();
            $controller->$metodo();
            $respuesta = json_decode(ob_get_clean(), true);
            $this->assertFalse($respuesta['success']);
            $this->assertSame('Alcanzaste el límite de personal de tu plan (2/2). Mejora tu plan para seguir.', $respuesta['message']);
        }
        $this->assertSame($antes, $usuario->buscarPorId(2));
        $this->assertSame('inactivo', $this->db->query('SELECT estado FROM usuario_clinica WHERE id_usuario = 2 AND id_clinica = 1')->fetchColumn());
        $this->assertSame(4, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE descripcion LIKE 'Acción bloqueada%'")->fetchColumn());
        $usuario->cambiarEstadoEnClinica(5, 1, false);
        $usuario->editarPersonalEnClinica(2, 1, $datos);
        $usuario->cambiarEstadoEnClinica(2, 1, true);
        $this->assertSame(2, (new LimitePlan($this->db, 1))->resumen()['personal']['uso']);
        $this->assertSame('Beto Cambiado', $usuario->buscarPorId(2)['nombre_completo']);
        $this->assertSame('activo', $this->db->query('SELECT estado FROM usuario_clinica WHERE id_usuario = 5 AND id_clinica = 2')->fetchColumn());
        unset($_SERVER['REQUEST_METHOD']);
    }

    public function testProfesionalSinLimiteMoraYUrgenciaRojaConservanElUso(): void
    {
        $this->llenarCitas();
        $this->db->exec('UPDATE clinicas SET id_plan = 2 WHERE id_clinica = 1');
        $plan = new LimitePlan($this->db, 1);
        $this->assertNull($plan->resumen('2027-02-15')['citas']['limite']);
        $modelo = new Cita($this->db);
        $this->assertGreaterThan(0, $modelo->registrar($this->cita()));
        $this->db->exec('UPDATE suscripciones SET al_dia = 0 WHERE id_clinica = 1');
        $this->assertSame(100, $plan->resumen('2027-02-16')['citas']['limite']);
        $this->bloquear(fn () => $modelo->registrar($this->cita('2027-02-16')), 'citas', 101, 100);
        $id = $modelo->registrarEvaluada($this->cita('2027-02-16'), 'rojo');
        $this->assertSame('rojo', $modelo->getById($id)['prioridad']);
        $this->assertSame(102, $plan->resumen('2027-02-16')['citas']['uso']);
        $datos = $this->cita('2027-02-17') + ['prioridad' => 'rojo'];
        $this->bloquear(fn () => $modelo->registrar($datos), 'citas', 102, 100);
    }

    public function testPortalMensajeNeutralAvisoSoloASuClinicaYRollbackDeVinculo(): void
    {
        $this->llenarCitas();
        $_SESSION = ['id_usuario' => 6];
        Contexto::activar(Contexto::dePropietario(), 1);
        $mascotas = (new Mascota($this->db))->enClinicaDelPropietario(1);
        $citas = (new Cita($this->db))->enClinicaDelPropietario(1);
        $this->db->exec("INSERT INTO mascotas (id_mascota, id_propietario, token_carnet, nombre) VALUES (100, 6, 'del-portal', 'Portal')");
        $datos = $this->cita() + [];
        $datos['id_mascota'] = 100;
        try {
            (new LimitePlan($this->db, 1))->transaccion(function () use ($mascotas, $citas, $datos): void {
                $mascotas->vincular(100, 6);
                $citas->registrar($datos);
            });
            $this->fail('Debió bloquear la cita del portal.');
        } catch (LimiteAlcanzado $error) {
            $this->assertSame('No es posible completar la acción ahora', $error->getMessage());
        }
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM mascota_clinica WHERE id_mascota = 100')->fetchColumn());
        $aviso = $this->db->query('SELECT * FROM notificaciones_internas')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(1, (int) $aviso['id_clinica']);
        $this->assertSame(Roles::ADMIN, (int) $aviso['id_rol_destino']);
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE descripcion LIKE 'Acción bloqueada%'")->fetchColumn());
        $this->assertSame(0, (new LimitePlan($this->db, 2))->resumen('2027-02-15')['citas']['uso']);
        // La mascota global sin vincular no consume el plan de ninguna clínica.
        $this->assertGreaterThan(0, (new MascotaPropietario($this->db))->registrar($this->mascota()));
    }
}
