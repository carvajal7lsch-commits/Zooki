<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../helpers/PresentacionPortal.php';
require_once __DIR__ . '/../../helpers/ProximaDosis.php';

/** M5 C2: agrupaciones del portal, sin base de datos. */
final class PresentacionPortalTest extends TestCase
{
    private const HOY = '2030-01-07';

    private function cita(int $id, string $fecha, string $hora, string $estado): array
    {
        return ['id_cita' => $id, 'fecha' => $fecha, 'hora' => $hora, 'estado' => $estado];
    }

    public function testProximasSonLasAbiertasDeHoyEnAdelanteYLaMasCercanaPrimero(): void
    {
        $citas = [
            $this->cita(1, '2030-01-09', '09:00:00', 'pendiente'),
            $this->cita(2, '2030-01-07', '15:00:00', 'confirmada'),
            $this->cita(3, '2030-01-05', '09:00:00', 'pendiente'),
            $this->cita(4, '2030-01-07', '08:00:00', 'completada'),
            $this->cita(5, '2030-01-06', '10:00:00', 'no_asistio'),
        ];

        $agenda = PresentacionPortal::separarCitas($citas, self::HOY);

        $this->assertSame([2, 1], array_column($agenda['proximas'], 'id_cita'));
        $this->assertSame([4, 5, 3], array_column($agenda['pasadas'], 'id_cita'), 'Una abierta de ayer ya no es próxima.');
    }

    public function testEstadosComoLosVeElPropietarioYSoloAzulOGris(): void
    {
        $this->assertSame('En atención', PresentacionPortal::estado('sin_cerrar'));
        $this->assertSame('Activa', PresentacionPortal::estado('confirmada'));
        $this->assertSame('tag-mini tag-mini--azul', PresentacionPortal::claseEstado('pendiente'));
        $this->assertSame('tag-mini', PresentacionPortal::claseEstado('completada'));
    }

    public function testEspecieYRazaConLaRazaPorConfirmar(): void
    {
        $this->assertSame('Canino · Criollo', PresentacionPortal::especieYRaza(['nombre_especie' => 'Canino', 'nombre_raza' => 'Criollo']));
        $this->assertSame('Felino · Bengalí (por confirmar)', PresentacionPortal::especieYRaza(['nombre_especie' => 'Felino', 'nombre_raza' => null, 'raza_indicada' => 'Bengalí']));
    }

    public function testLaProximaDosisEsLaMasCercanaYUnaRenovadaYaNoCuenta(): void
    {
        $vacunas = [
            ['id_mascota' => 1, 'nombre_vacuna' => 'Rabia', 'fecha_aplicacion' => '2029-01-01', 'fecha_proxima_dosis' => '2030-01-20', 'clinica_nombre' => 'Norte'],
            // Renovada: la de 2029-06 manda; su próxima es 2030-06-01.
            ['id_mascota' => 1, 'nombre_vacuna' => 'Rabia', 'fecha_aplicacion' => '2029-06-01', 'fecha_proxima_dosis' => '2030-06-01', 'clinica_nombre' => 'Sur'],
            ['id_mascota' => 1, 'nombre_vacuna' => 'Moquillo', 'fecha_aplicacion' => '2029-01-01', 'fecha_proxima_dosis' => '2029-12-01', 'clinica_nombre' => 'Norte'],
            ['id_mascota' => 2, 'nombre_vacuna' => 'Rabia', 'fecha_aplicacion' => '2029-01-01', 'fecha_proxima_dosis' => null, 'clinica_nombre' => 'Norte'],
        ];
        $desparasitaciones = [
            ['id_mascota' => 1, 'tipo' => 'interna', 'producto' => 'Drontal', 'fecha_aplicacion' => '2029-12-01', 'fecha_proxima' => '2030-03-01', 'clinica_nombre' => 'Sur'],
        ];

        $proximas = ProximaDosis::porMascota($vacunas, $desparasitaciones, self::HOY);

        $this->assertSame(['nombre' => 'Drontal', 'fecha' => '2030-03-01', 'clinica' => 'Sur'], $proximas[1]);
        $this->assertArrayNotHasKey(2, $proximas, 'Sin fecha próxima no hay dosis pendiente.');
    }
    /** M5 C4a: todas las dosis por aplicar, por fecha, con la misma regla de la dosis renovada. */
    public function testLasDosisPendientesSonTodasLasDeHoyEnAdelantePorFecha(): void
    {
        $vacunas = [
            ['id_mascota' => 1, 'nombre_vacuna' => 'Rabia', 'fecha_aplicacion' => '2029-01-01', 'fecha_proxima_dosis' => '2030-01-20', 'clinica_nombre' => 'Norte'],
            ['id_mascota' => 1, 'nombre_vacuna' => 'Rabia', 'fecha_aplicacion' => '2029-06-01', 'fecha_proxima_dosis' => '2030-06-01', 'clinica_nombre' => 'Sur'],
            ['id_mascota' => 2, 'nombre_vacuna' => 'Moquillo', 'fecha_aplicacion' => '2029-01-01', 'fecha_proxima_dosis' => '2030-02-01', 'clinica_nombre' => 'Norte'],
            ['id_mascota' => 2, 'nombre_vacuna' => 'Parvovirus', 'fecha_aplicacion' => '2029-01-01', 'fecha_proxima_dosis' => '2029-12-01', 'clinica_nombre' => 'Norte'],
        ];

        $pendientes = ProximaDosis::pendientes($vacunas, [], self::HOY);

        $this->assertSame(['Moquillo', 'Rabia'], array_column($pendientes, 'nombre'), 'La Rabia renovada manda (junio); la vencida no entra.');
        $this->assertSame(['2030-02-01', '2030-06-01'], array_column($pendientes, 'fecha'));
    }

    /** M5 C4a (punto 4): citas y dosis juntas, por fecha y hora, hasta el tope, solo de sus mascotas. */
    public function testProximoEnSaludJuntaCitasYDosisPorFechaHastaElTope(): void
    {
        $mascotas = [1 => ['nombre' => 'Luna'], 2 => ['nombre' => 'Toby']];
        $citas = [
            ['id_cita' => 7, 'id_mascota' => 1, 'fecha' => '2030-01-09', 'hora' => '15:00:00', 'nombre_tipo' => 'Control', 'clinica_nombre' => 'Norte'],
            ['id_cita' => 8, 'id_mascota' => 2, 'fecha' => '2030-01-09', 'hora' => '09:00:00', 'nombre_tipo' => null, 'clinica_nombre' => 'Sur'],
            ['id_cita' => 9, 'id_mascota' => 50, 'fecha' => '2030-01-08', 'hora' => '09:00:00', 'nombre_tipo' => 'Ajena', 'clinica_nombre' => 'Sur'],
        ];
        $dosis = [
            ['id_mascota' => 1, 'nombre' => 'Rabia', 'fecha' => '2030-01-08', 'clinica' => 'Norte'],
            ['id_mascota' => 2, 'nombre' => 'Moquillo', 'fecha' => '2030-03-01', 'clinica' => 'Norte'],
            ['id_mascota' => 50, 'nombre' => 'Ajena', 'fecha' => '2030-01-07', 'clinica' => 'Sur'],
        ];

        $eventos = PresentacionPortal::proximoEnSalud($citas, $dosis, $mascotas, 3);

        $this->assertSame(['Rabia · Luna', 'Cita · Toby', 'Control · Luna'], array_map(fn ($e) => $e['titulo'] . ' · ' . $e['mascota'], $eventos));
        $this->assertSame(['dosis', 'cita', 'cita'], array_column($eventos, 'tipo'));
        $this->assertSame([0, 8, 7], array_column($eventos, 'id_cita'));
        $this->assertNotContains('Ajena', array_column($eventos, 'titulo'), 'RN-G02: una mascota ajena no entra aunque llegue en la lista.');
    }
}
