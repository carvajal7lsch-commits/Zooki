<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/Consulta.php';
require_once __DIR__ . '/../../controllers/ConsultaController.php';
require_once __DIR__ . '/../../helpers/ResumenClinico.php';

/**
 * M2 C5b — peso desconocido (decisión del usuario, 2026-10-15) y filas de
 * tratamiento con su error junto a la fila (HU-2.4, RE-2.4.1).
 */
final class ConsultaTratamientoTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        $_SESSION = ['id_usuario' => DosClinicas::VET_NORTE];
        Contexto::activar(Contexto::deClinica(DosClinicas::NORTE, 'Clínica Norte', Roles::VETERINARIO), 1);
        $_POST = [];
        $_FILES = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_FILES = [];
        http_response_code(200);
    }

    // ── Peso: «No se pudo pesar» ─────────────────────────────────────────

    public function testLaCasillaGuardaElPesoNullYSinCasillaElPesoSePide(): void
    {
        $_POST = $this->post(['peso' => '', 'peso_desconocido' => '1']);
        $respuesta = $this->ejecutar();
        $this->assertTrue($respuesta['success'], $respuesta['message'] ?? '');
        $this->assertNull($this->db->query('SELECT peso FROM consultas WHERE id_consulta = ' . (int) $respuesta['id_consulta'])->fetchColumn(), 'NULL, nunca 0');

        // La casilla manda: un valor que llegue igual no se guarda como peso.
        $_POST = $this->post(['peso' => '9', 'peso_desconocido' => '1']);
        $this->assertNull($this->db->query('SELECT peso FROM consultas WHERE id_consulta = ' . (int) $this->ejecutar()['id_consulta'])->fetchColumn());

        foreach ([[], ['peso' => ''], ['peso' => '', 'peso_desconocido' => 'si']] as $cambio) {
            $_POST = $this->post($cambio);
            unset($_POST['peso']);
            $_POST = array_replace($_POST, $cambio);
            $respuesta = $this->ejecutar();
            $this->assertSame(422, http_response_code());
            $this->assertSame('peso', $respuesta['campo']);
            $this->assertSame(ValidadorClinico::AVISO_PESO_REQUERIDO, $respuesta['aviso']);
        }
        $this->assertSame(2, (int) $this->db->query('SELECT COUNT(*) FROM consultas')->fetchColumn());
    }

    /** Ningún cálculo toma el peso NULL como 0: el resumen usa el último peso conocido. */
    public function testElResumenUsaElUltimoPesoConocidoYNoUnNull(): void
    {
        $resumen = ResumenClinico::construir([
            ['fecha_hora' => '2026-10-15 10:00:00', 'diagnostico' => 'Sin pesar', 'veterinario' => 'Beto', 'peso' => null],
            ['fecha_hora' => '2026-09-01 10:00:00', 'diagnostico' => 'Control', 'veterinario' => 'Beto', 'peso' => '12.40'],
        ], [], [], new DateTimeImmutable('2026-10-15'));
        $this->assertSame(12.4, $resumen['ultimo_peso']);
        $this->assertSame('Sin pesar', $resumen['ultima_consulta']['diagnostico']);
    }

    // ── Filas de tratamiento: el error va junto a su fila ───────────────

    public function testElFinAnteriorAlInicioYLosCamposFaltantesDicenSuFilaYCampo(): void
    {
        $casos = [
            'tratamientos.1.fecha_fin' => [1, ['med_fin' => '2026-10-01']],
            'tratamientos.0.dosis' => [0, ['med_dosis' => '']],
            'tratamientos.0.via_administracion' => [0, ['med_via' => 'Por la oreja']],
            'tratamientos.1.duracion' => [1, ['med_duracion' => '  ']],
            'tratamientos.0.medicamento' => [0, ['med_nombre' => str_repeat('m', ValidadorTratamiento::MEDICAMENTO_MAX + 1)]],
        ];
        foreach ($casos as $campo => [$fila, $cambio]) {
            $_POST = $this->post() + $this->dosFilas();
            foreach ($cambio as $clave => $valor) {
                $_POST[$clave][$fila] = $valor;
            }
            $respuesta = $this->ejecutar();
            $this->assertSame(422, http_response_code(), $campo);
            $this->assertSame($campo, $respuesta['campo']);
            $this->assertNotSame('', $respuesta['aviso']);
        }
        $this->assertSame(ValidadorTratamiento::AVISOS['fecha_fin'], (function (): string {
            $_POST = $this->post() + $this->dosFilas();
            $_POST['med_fin'][1] = '2026-10-01';
            return $this->ejecutar()['aviso'];
        })());
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM consultas')->fetchColumn());
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM tratamientos')->fetchColumn());
    }

    /** Una fila sin medicamento no se envía: la numeración es la de las filas con medicamento. */
    public function testLasFilasSinMedicamentoNoCuentanEnLaNumeracion(): void
    {
        $_POST = $this->post() + $this->dosFilas();
        array_unshift($_POST['med_nombre'], '');
        foreach (['med_dosis', 'med_via', 'med_duracion', 'med_inicio', 'med_fin', 'med_obs'] as $clave) {
            array_unshift($_POST[$clave], '');
        }
        $_POST['med_fin'][2] = '2026-10-01';
        $respuesta = $this->ejecutar();
        $this->assertSame('tratamientos.1.fecha_fin', $respuesta['campo']);
    }

    public function testFinYObservacionesSeGuardanConLaFila(): void
    {
        $_POST = $this->post() + $this->dosFilas();
        $respuesta = $this->ejecutar();
        $this->assertTrue($respuesta['success'], $respuesta['message'] ?? '');
        $filas = $this->db->query('SELECT medicamento, via_administracion, fecha_inicio, fecha_fin, observaciones FROM tratamientos ORDER BY id_tratamiento')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertSame(['Amoxicilina', 'Subcutánea', '2026-10-15', '2026-10-21', 'Con comida'], array_values($filas[0]));
        $this->assertNull($filas[1]['fecha_fin'], 'Sin fin: vigencia desconocida, no inventada');
    }

    private function post(array $cambios = []): array
    {
        return array_replace(['id_mascota' => '1', 'motivo' => 'Control', 'diagnostico' => 'Sano', 'peso' => '8.5'], $cambios);
    }

    private function dosFilas(): array
    {
        return [
            'med_nombre' => ['Amoxicilina', 'Suero oral'],
            'med_dosis' => ['1 tableta cada 12 h', '50 ml'],
            'med_via' => ['Subcutánea', 'Oral'],
            'med_duracion' => ['7 días', 'Hasta mejorar'],
            'med_inicio' => ['2026-10-15', '2026-10-15'],
            'med_fin' => ['2026-10-21', ''],
            'med_obs' => ['Con comida', ''],
        ];
    }

    private function ejecutar(): array
    {
        $controlador = new ConsultaController($this->db, 'copy', sys_get_temp_dir());
        http_response_code(200);
        ob_start();
        try {
            $controlador->registrarAjax();
        } finally {
            $salida = ob_get_clean();
        }
        return json_decode($salida, true);
    }
}
