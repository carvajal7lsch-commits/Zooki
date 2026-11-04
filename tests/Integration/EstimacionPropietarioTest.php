<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/CascadaAgenda.php';
require_once __DIR__ . '/../../helpers/CorreoAgenda.php';
require_once __DIR__ . '/../../controllers/NotificacionController.php';

/** RE-4.14.7/5.7.4: una causa durable, ventana compartida y solo el propietario vigente. */
final class EstimacionPropietarioTest extends TestCase
{
    public function testEstimacionNotificaAlPropietarioUnaVezConUmbralVentanaYEnlacePropio(): void
    {
        $db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($db);
        DosClinicas::crearCatalogosSqlite($db);
        DosClinicas::crearHistoriaSqlite($db);
        DosClinicas::poblarAgenda($db);
        $_SESSION = ['id_usuario' => 2];
        Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::VETERINARIO), 1);
        $db->exec("INSERT INTO mascotas(id_mascota,id_propietario,id_clinica_registro,token_carnet,nombre,id_especie,estado) VALUES(2,6,1,'estimacion-segunda-mascota','Luna',1,1)");
        $db->exec("INSERT INTO mascota_clinica(id_mascota,id_clinica,estado) VALUES(2,1,'activo')");
        $db->exec("INSERT INTO citas(id_clinica,id_mascota,id_veterinario,id_tipo_cita,fecha,hora,motivo,estado,duracion_minutos,margen_minutos,duracion_activa_objetivo_segundos,politica_temporal,origen,hora_inicio_real)
            VALUES(1,1,2,1,'2030-01-07','09:00:00','Consulta','en_curso',30,10,1800,'M','reserva','2030-01-07 09:00:00')");
        $db->exec("INSERT INTO citas(id_clinica,id_mascota,id_veterinario,id_tipo_cita,fecha,hora,motivo,estado,duracion_minutos,margen_minutos,duracion_activa_objetivo_segundos,politica_temporal,origen)
            VALUES(1,2,2,1,'2030-01-07','09:40:00','Consulta','confirmada',30,10,1800,'M','reserva')");
        $ahora = '2030-01-07 09:50:00';
        $reloj = static function () use (&$ahora): DateTimeImmutable { return IntervalosAgenda::fecha($ahora); };
        $cascada = new CascadaAgenda($db, $reloj);
        $eventos = new EventoAgenda($db, 1, $reloj);
        $cascada->procesar('2030-01-07');
        $this->assertSame(0, (int) $db->query("SELECT COUNT(*) FROM eventos_agenda WHERE tipo='ESTIMACION_CITA'")->fetchColumn());
        $ahora = '2030-01-07 10:02:00';
        $cascada->procesar('2030-01-07');
        $evento = (int) $db->query("SELECT id_evento FROM eventos_agenda WHERE tipo='ESTIMACION_CITA'")->fetchColumn();
        $this->assertGreaterThan(0, $evento);
        $html = '';
        $this->assertTrue($eventos->despachar($evento, static function (array $contenido) use (&$html): bool {
            $html = CorreoAgenda::contenido($contenido, 'Actualización de hora estimada');
            return true;
        }));
        $this->assertFalse($eventos->despachar($evento, static fn () => true));
        $this->assertStringContainsString('9:40 a. m.', $html);
        $this->assertStringContainsString('10:02 a. m.', $html);
        $this->assertStringContainsString('la atención anterior se extendió', $html);
        $this->assertStringNotContainsString('2030-01-07 10:02:00', $html);
        $this->assertSame(1, (int) $db->query("SELECT COUNT(*) FROM notificaciones_internas WHERE tipo='ESTIMACION_CITA'")->fetchColumn());
        $notificaciones = new NotificacionInterna($db);
        $propias = $notificaciones->obtenerParaUsuario(null, 6, Roles::PROPIETARIO);
        $this->assertCount(1, $propias);
        $this->assertSame('Nueva hora estimada: Luna', $propias[0]['titulo']);
        $this->assertStringContainsString('cita=2&evento_agenda=' . $evento, $propias[0]['enlace']);
        $this->assertStringContainsString('10:02 a. m.', $propias[0]['mensaje']);
        $this->assertSame([], $notificaciones->obtenerParaUsuario(null, 7, Roles::PROPIETARIO));
        $this->assertSame([], $notificaciones->obtenerParaUsuario(2, 6, Roles::PROPIETARIO));
        $this->assertFalse($notificaciones->perteneceA($propias[0]['id'], null, 7, Roles::PROPIETARIO));
        $_SESSION = ['id_usuario' => 6];
        Contexto::activar(Contexto::dePropietario(), 1);
        ob_start();
        (new NotificacionController($db))->obtenerNotificaciones();
        $respuesta = json_decode(ob_get_clean(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $respuesta['no_leidas']);
        $this->assertCount(1, $respuesta['notificaciones']);
        $this->assertTrue($notificaciones->marcarLeida($propias[0]['id'], null, 6, Roles::PROPIETARIO));
        $this->assertSame(0, $notificaciones->contarNoLeidas(null, 6, Roles::PROPIETARIO));
        $_SESSION = ['id_usuario' => 2];
        Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::VETERINARIO), 1);
        $ahora = '2030-01-07 10:31:59';
        $cascada->procesar('2030-01-07');
        $this->assertSame(1, (int) $db->query("SELECT COUNT(*) FROM eventos_agenda WHERE tipo='ESTIMACION_CITA'")->fetchColumn());
        $ahora = '2030-01-07 10:32:00';
        $cascada->procesar('2030-01-07');
        $segundo = (int) $db->query("SELECT MAX(id_evento) FROM eventos_agenda WHERE tipo='ESTIMACION_CITA'")->fetchColumn();
        $this->assertNotSame($evento, $segundo);
        $this->assertTrue($eventos->despachar($segundo, static fn () => true));
        $this->assertSame(1, $notificaciones->contarNoLeidas(null, 6, Roles::PROPIETARIO));
        $db->exec('UPDATE mascotas SET id_propietario=7 WHERE id_mascota=2');
        $this->assertSame([], $notificaciones->obtenerParaUsuario(null, 6, Roles::PROPIETARIO));
        $_SESSION = [];
    }
}
