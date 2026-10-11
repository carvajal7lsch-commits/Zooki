<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/Consulta.php';
require_once __DIR__ . '/../../controllers/ConsultaController.php';

/**
 * Módulo 2 — Registro de la historia clínica en la clínica activa (C4).
 *
 * HU-2.1, HU-2.2, HU-2.4 y HU-2.6 con el fixture de dos clínicas: clínica y
 * veterinario en cada registro (RN-112), número de historia por clínica
 * (RF-2.4, RN-102), atomicidad (RE-2.6.1), diagnóstico (RN-202), mascota
 * activa y vinculada (RN-207), cualquier veterinario (RN-208) y la cita del
 * veterinario asignado (RN-407). La visibilidad entre clínicas está en
 * HistoriaCompartidaTest.
 */
final class ConsultaHistorialTest extends TestCase
{
    private const VET_NORTE_ASIGNADO = DosClinicas::VET_NORTE;
    private const OTRA_VET_NORTE = DosClinicas::DOBLE;

    private PDO $db;
    private Consulta $consultas;
    private ?string $carpetaTemporal = null;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        $this->consultas = new Consulta($this->db);
        $this->comoVeterinario(DosClinicas::NORTE, self::VET_NORTE_ASIGNADO);
        $_POST = [];
        $_GET = [];
        $_FILES = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        $_FILES = [];
        if ($this->carpetaTemporal !== null) {
            array_map('unlink', glob($this->carpetaTemporal . '/*'));
            rmdir($this->carpetaTemporal);
        }
    }

    // ── RE-2.1.1, RN-112: lo que guarda un registro ─────────────────────

    public function testRegistrarGuardaClinicaVeterinarioCamposTratamientoYAdjunto(): void
    {
        $id = $this->registrar(1, ['peso' => '8.5', 'temperatura' => '38.5', 'anamnesis' => 'Come bien'], [$this->tratamiento()], [['nombre' => 'rx.jpg']]);

        $consulta = $this->fila("SELECT * FROM consultas WHERE id_consulta = $id");
        $this->assertSame(DosClinicas::NORTE, (int) $consulta['id_clinica']);
        $this->assertSame(self::VET_NORTE_ASIGNADO, (int) $consulta['id_veterinario']);
        $this->assertSame('Control', $consulta['motivo_consulta']);
        $this->assertSame('Sano', $consulta['diagnostico']);
        $this->assertSame('Come bien', $consulta['anamnesis']);
        $this->assertSame('8.5', (string) $consulta['peso']);

        $tratamiento = $this->fila("SELECT * FROM tratamientos WHERE id_consulta = $id");
        $this->assertSame('Amoxicilina', $tratamiento['medicamento']);
        $this->assertSame('2026-10-07', $tratamiento['fecha_inicio']);

        $this->assertSame(1, $this->contar("SELECT COUNT(*) FROM archivos_clinicos WHERE id_consulta = $id"));
    }

    /** RE-2.1.3 / RN-202 y RE-2.2.2: sin diagnóstico o sin motivo no se guarda nada. */
    public function testSinDiagnosticoNiMotivoNoSeGuarda(): void
    {
        $this->assertRechazo(fn () => $this->registrar(1, ['diagnostico' => '   ']), 'diagnóstico');
        $this->assertRechazo(fn () => $this->registrar(1, ['motivo' => '']), 'motivo');
        $this->assertRechazo(fn () => $this->registrar(1, ['temperatura' => '80']), 'temperatura');

        $this->assertSame(0, $this->contar('SELECT COUNT(*) FROM consultas'));
    }

    /** RE-2.4.1: fecha_inicio es obligatoria; sin ella no queda ni la consulta. */
    public function testTratamientoSinFechaInicioSeRechazaSinGuardarNada(): void
    {
        $sinFecha = $this->tratamiento();
        unset($sinFecha['fecha_inicio']);
        $fechaInvalida = $this->tratamiento(['fecha_inicio' => '2026-02-31']);

        $this->assertRechazo(fn () => $this->registrar(1, [], [$sinFecha]), 'fecha de inicio');
        $this->assertRechazo(fn () => $this->registrar(1, [], [$this->tratamiento(), $fechaInvalida]), 'fecha de inicio');

        $this->assertSame(0, $this->contar('SELECT COUNT(*) FROM consultas'));
        $this->assertSame(0, $this->contar('SELECT COUNT(*) FROM tratamientos'));
        $this->assertNull($this->numeroHistoria(1, DosClinicas::NORTE));
    }

    // ── RF-2.4, RN-102, RE-2.1.2, RE-2.2.3: número de historia ──────────

    public function testElNumeroDeHistoriaSeAsignaUnaVezYNoSeRepiteDentroDeLaClinica(): void
    {
        $this->crearMascota(2, DosClinicas::NORTE);
        DosClinicas::vincularLunaASur($this->db);

        $this->registrar(1);
        $this->assertSame('HC-000001', $this->numeroHistoria(1, DosClinicas::NORTE));

        $this->registrar(1);
        $this->assertSame('HC-000001', $this->numeroHistoria(1, DosClinicas::NORTE), 'La segunda consulta no cambia el número');

        $this->registrar(2);
        $this->assertSame('HC-000002', $this->numeroHistoria(2, DosClinicas::NORTE));

        // El número es por clínica: Sur empieza su propia numeración.
        $this->comoVeterinario(DosClinicas::SUR, DosClinicas::VET_SUR);
        $this->registrar(1);
        $this->assertSame('HC-000001', $this->numeroHistoria(1, DosClinicas::SUR));
        $this->assertSame('HC-000001', $this->numeroHistoria(1, DosClinicas::NORTE));

        $repetidos = $this->contar("SELECT COUNT(*) FROM (SELECT id_clinica, numero_historia_clinica FROM mascota_clinica
            WHERE numero_historia_clinica IS NOT NULL GROUP BY id_clinica, numero_historia_clinica HAVING COUNT(*) > 1) r");
        $this->assertSame(0, $repetidos);
    }

    // ── RE-2.6.1, RE-2.6.3: todo o nada ─────────────────────────────────

    public function testUnFalloAMitadDelRegistroNoDejaDatosParciales(): void
    {
        $idCita = $this->crearCita(DosClinicas::NORTE, 1, self::VET_NORTE_ASIGNADO, 'en_curso');
        $falla = function (int $idConsulta, array $adjunto, int $indice): array {
            if ($indice === 1) {
                throw new RuntimeException('Disco lleno');
            }
            return $this->metadatos($idConsulta, $indice);
        };

        try {
            $this->registrar(1, ['id_cita' => $idCita], [$this->tratamiento()], [['nombre' => 'a.jpg'], ['nombre' => 'b.jpg']], $falla);
            $this->fail('Debió fallar el segundo adjunto.');
        } catch (RuntimeException $e) {
            $this->assertSame('Disco lleno', $e->getMessage());
        }

        $this->assertSinRegistroClinico();
        $this->assertSame('en_curso', $this->db->query("SELECT estado FROM citas WHERE id_cita = $idCita")->fetchColumn());
    }

    /** Un error de la base en el último paso (tratamientos) deshace también la consulta y los adjuntos. */
    public function testUnErrorDeLaBaseAlGuardarTratamientosDeshaceTodo(): void
    {
        $this->db->exec("CREATE TRIGGER falla_tratamiento BEFORE INSERT ON tratamientos BEGIN SELECT RAISE(ABORT, 'falla simulada'); END");

        try {
            $this->registrar(1, [], [$this->tratamiento()], [['nombre' => 'a.jpg']]);
            $this->fail('Debió fallar el tratamiento.');
        } catch (PDOException $e) {
            $this->assertStringContainsString('falla simulada', $e->getMessage());
        }

        $this->assertSinRegistroClinico();
    }

    /** RE-2.6.1 de punta a punta: el controlador borra del disco el adjunto ya movido. */
    public function testElControladorBorraElAdjuntoMovidoSiLaBaseFalla(): void
    {
        $this->db->exec("CREATE TRIGGER falla_tratamiento BEFORE INSERT ON tratamientos BEGIN SELECT RAISE(ABORT, 'falla simulada'); END");
        $this->prepararPostConAdjunto();

        $respuesta = $this->ejecutarControlador();

        $this->assertFalse($respuesta['success']);
        $this->assertSame(500, http_response_code());
        $this->assertSame([], glob($this->carpetaTemporal . '/CLI_*'));
        $this->assertSinRegistroClinico();
    }

    public function testElControladorGuardaElAdjuntoYResponde(): void
    {
        $this->prepararPostConAdjunto();

        $respuesta = $this->ejecutarControlador();

        $this->assertTrue($respuesta['success'], $respuesta['message']);
        $this->assertSame(1, $respuesta['adjuntos_guardados']);
        $this->assertSame(1, $respuesta['tratamientos_guardados']);
        $this->assertCount(1, glob($this->carpetaTemporal . '/CLI_*.png'));
        $this->assertSame('png', $this->db->query('SELECT extension FROM archivos_clinicos')->fetchColumn());
    }

    /** RE-2.4.1 por el controlador: la fila sin fecha de inicio se rechaza con 422. */
    public function testElControladorRechazaUnTratamientoSinFechaInicio(): void
    {
        $_POST = $this->postBasico();
        unset($_POST['med_inicio']);

        $respuesta = $this->ejecutarControlador();

        $this->assertFalse($respuesta['success']);
        $this->assertSame(422, http_response_code());
        $this->assertStringContainsString('fecha de inicio', $respuesta['message']);
        $this->assertSame(0, $this->contar('SELECT COUNT(*) FROM consultas'));
    }

    // ── RN-207, RN-208, RN-407 ──────────────────────────────────────────

    public function testUnaMascotaInactivaNoAdmiteConsultaYUnaNoVinculadaDa403(): void
    {
        $this->db->exec('UPDATE mascotas SET estado = 0 WHERE id_mascota = 1');
        $this->assertRechazo(fn () => $this->registrar(1), 'inactiva');

        $this->comoVeterinario(DosClinicas::SUR, DosClinicas::VET_SUR);
        $this->assertAccesoDenegado(fn () => $this->registrar(1));
        $this->assertSame(1, $this->auditoriasDenegadas(DosClinicas::SUR));
        $this->assertSame(0, $this->contar('SELECT COUNT(*) FROM consultas'));
    }

    /** RN-208: cualquier veterinario de la clínica atiende sin cita y queda como autor. */
    public function testCualquierVeterinarioDeLaClinicaRegistraSinCita(): void
    {
        $this->comoVeterinario(DosClinicas::NORTE, self::OTRA_VET_NORTE);

        $id = $this->registrar(1);

        $this->assertSame(self::OTRA_VET_NORTE, (int) $this->db->query("SELECT id_veterinario FROM consultas WHERE id_consulta = $id")->fetchColumn());
    }

    public function testLaConsultaDeUnaCitaEsDelVeterinarioAsignadoYDeLaClinicaActiva(): void
    {
        DosClinicas::vincularLunaASur($this->db);
        $citaNorte = $this->crearCita(DosClinicas::NORTE, 1, self::VET_NORTE_ASIGNADO, 'en_curso');
        $citaSur = $this->crearCita(DosClinicas::SUR, 1, DosClinicas::VET_SUR, 'en_curso');
        $citaPendiente = $this->crearCita(DosClinicas::NORTE, 1, self::VET_NORTE_ASIGNADO, 'pendiente', '10:00:00');

        $this->comoVeterinario(DosClinicas::NORTE, self::OTRA_VET_NORTE);
        $this->assertRechazo(fn () => $this->registrar(1, ['id_cita' => $citaNorte]), 'veterinario asignado');

        $this->comoVeterinario(DosClinicas::NORTE, self::VET_NORTE_ASIGNADO);
        $this->assertAccesoDenegado(fn () => $this->registrar(1, ['id_cita' => $citaSur]));
        $this->assertRechazo(fn () => $this->registrar(1, ['id_cita' => $citaPendiente]), 'no está en curso');

        $id = $this->registrar(1, ['id_cita' => $citaNorte]);
        $this->assertSame($citaNorte, (int) $this->db->query("SELECT id_cita FROM consultas WHERE id_consulta = $id")->fetchColumn());
        $this->assertSame('completada', $this->db->query("SELECT estado FROM citas WHERE id_cita = $citaNorte")->fetchColumn());
        $this->assertSame('en_curso', $this->db->query("SELECT estado FROM citas WHERE id_cita = $citaSur")->fetchColumn());

        $this->assertRechazo(fn () => $this->registrar(1, ['id_cita' => $citaNorte]), 'ya tiene una consulta');
        $this->assertSame(1, $this->auditoriasDenegadas(DosClinicas::NORTE));
    }

    // ── HU-2.5, RN-206: historial ───────────────────────────────────────

    public function testElHistorialLlegaConElMasRecientePrimeroYNoMezclaMascotas(): void
    {
        $this->crearMascota(2, DosClinicas::NORTE);
        $viejo = $this->registrar(1, ['diagnostico' => 'Primera visita']);
        $nuevo = $this->registrar(1, ['diagnostico' => 'Control']);
        $this->registrar(2, ['diagnostico' => 'De Kira']);
        $this->db->exec("UPDATE consultas SET fecha_hora = '2024-01-01 09:00:00' WHERE id_consulta = $viejo");
        $this->db->exec("UPDATE consultas SET fecha_hora = '2025-06-01 09:00:00' WHERE id_consulta = $nuevo");

        $historial = $this->consultas->historialDeMascota(1);

        $this->assertSame(['Control', 'Primera visita'], array_column($historial, 'diagnostico'));
        $this->assertSame('Beto Norte', $historial[0]['veterinario']);
        $this->assertSame('Clínica Norte', $historial[0]['clinica_nombre']);
        $this->assertTrue($historial[0]['es_propia']);
    }

    /** M2-10: adjuntos y tratamientos llegan agrupados por consulta, sin una consulta SQL por fila. */
    public function testAdjuntosYTratamientosLleganAgrupadosPorConsulta(): void
    {
        $primera = $this->registrar(1, [], [$this->tratamiento(), $this->tratamiento(['medicamento' => 'Meloxicam'])], [['nombre' => 'rx1.jpg'], ['nombre' => 'rx2.jpg']]);
        $segunda = $this->registrar(1);

        $porConsulta = [];
        foreach ($this->consultas->historialDeMascota(1) as $consulta) {
            $porConsulta[(int) $consulta['id_consulta']] = $consulta;
        }

        $this->assertCount(2, $porConsulta[$primera]['tratamientos']);
        $this->assertCount(2, $porConsulta[$primera]['archivos']);
        $this->assertSame([], $porConsulta[$segunda]['tratamientos']);
        $this->assertSame([], $porConsulta[$segunda]['archivos']);
        $this->assertArrayNotHasKey('nombre_servidor', $porConsulta[$primera]['archivos'][0], 'El nombre en disco no sale al navegador');
        $this->assertSame([], (new ArchivoClinico($this->db))->deConsultas([]));
        $this->assertSame([], (new Tratamiento($this->db))->findByConsultas([]));
    }

    /** HU-2.2: el listado es de la clínica activa y distingue las consultas sin cita. */
    public function testElListadoTraeSoloLasConsultasDeLaClinicaActiva(): void
    {
        DosClinicas::vincularLunaASur($this->db);
        $this->registrar(1, ['diagnostico' => 'En Norte']);
        $this->comoVeterinario(DosClinicas::SUR, DosClinicas::VET_SUR);
        $this->registrar(1, ['diagnostico' => 'En Sur']);

        $listado = $this->consultas->listarDeLaClinica();

        $this->assertSame(['En Sur'], array_column($listado, 'diagnostico'));
        $this->assertSame('Fabio Dueño', $listado[0]['nombre_propietario']);
        $this->assertNull($listado[0]['id_cita']);
    }

    // ── Apoyo ───────────────────────────────────────────────────────────

    private function comoVeterinario(int $clinica, int $idUsuario): void
    {
        $_SESSION = ['id_usuario' => $idUsuario];
        Contexto::activar(Contexto::deClinica($clinica, 'Clínica', Roles::VETERINARIO), 1);
    }

    private function registrar(int $idMascota, array $extra = [], array $tratamientos = [], array $adjuntos = [], ?callable $guardar = null): int
    {
        $entrada = array_replace(['id_mascota' => $idMascota, 'motivo' => 'Control', 'diagnostico' => 'Sano'], $extra);
        $guardar ??= fn (int $idConsulta, array $adjunto, int $indice): array => $this->metadatos($idConsulta, $indice);
        return $this->consultas->registrar($entrada, $tratamientos, $adjuntos, $guardar);
    }

    private function tratamiento(array $cambios = []): array
    {
        return array_replace([
            'medicamento' => 'Amoxicilina',
            'dosis' => '1 ml',
            'via_administracion' => 'Oral',
            'duracion' => '5 días',
            'fecha_inicio' => '2026-10-07',
        ], $cambios);
    }

    private function metadatos(int $idConsulta, int $indice): array
    {
        return [
            'nombre_original' => 'rx' . $indice . '.jpg',
            'nombre_servidor' => 'CLI_' . $idConsulta . '_' . $indice . '.jpg',
            'ruta_archivo' => 'uploads/clinicos/CLI_' . $idConsulta . '_' . $indice . '.jpg',
            'tipo_archivo' => 'image/jpeg',
            'extension' => 'jpg',
            'tamano_bytes' => 1024,
        ];
    }

    private function crearMascota(int $id, int $clinica): void
    {
        $this->db->prepare("INSERT INTO mascotas (id_mascota, id_propietario, id_clinica_registro, token_carnet, nombre, id_especie, estado)
            VALUES (?, 6, ?, ?, 'Kira', 1, 1)")->execute([$id, $clinica, str_repeat((string) $id, 43)]);
        $this->db->prepare("INSERT INTO mascota_clinica (id_mascota, id_clinica, estado) VALUES (?, ?, 'activo')")->execute([$id, $clinica]);
    }

    private function crearCita(int $clinica, int $mascota, int $veterinario, string $estado, string $hora = '09:00:00'): int
    {
        $this->db->prepare("INSERT INTO citas (id_clinica, id_mascota, id_veterinario, fecha, hora, motivo, estado)
            VALUES (?, ?, ?, '2026-10-07', ?, 'Control', ?)")->execute([$clinica, $mascota, $veterinario, $hora, $estado]);
        return (int) $this->db->lastInsertId();
    }

    private function prepararPostConAdjunto(): void
    {
        $this->carpetaTemporal = sys_get_temp_dir() . '/zooki_c4_' . bin2hex(random_bytes(4));
        mkdir($this->carpetaTemporal);
        $png = $this->carpetaTemporal . '/subida.tmp';
        file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));

        $_POST = $this->postBasico();
        $_FILES = ['archivos' => [
            'name' => ['radiografia.png'],
            'tmp_name' => [$png],
            'error' => [UPLOAD_ERR_OK],
            'size' => [filesize($png)],
        ]];
    }

    private function postBasico(): array
    {
        return [
            'id_mascota' => '1',
            'motivo' => 'Control',
            'diagnostico' => 'Sano',
            'med_nombre' => ['Amoxicilina'],
            'med_dosis' => ['1 ml'],
            'med_via' => ['Oral'],
            'med_duracion' => ['5 días'],
            'med_inicio' => ['2026-10-07'],
        ];
    }

    private function ejecutarControlador(): array
    {
        $carpeta = $this->carpetaTemporal ?? sys_get_temp_dir();
        $controlador = new ConsultaController($this->db, 'copy', $carpeta);
        http_response_code(200);
        // El error esperado se registra con error_log; no ensucia la salida de PHPUnit.
        $registroAnterior = ini_set('error_log', $carpeta . '/php_errores.log');
        ob_start();
        try {
            $controlador->registrarAjax();
        } finally {
            $salida = ob_get_clean();
            ini_set('error_log', (string) $registroAnterior);
            @unlink($carpeta . '/php_errores.log');
        }
        return json_decode($salida, true);
    }

    private function assertSinRegistroClinico(): void
    {
        $this->assertSame(0, $this->contar('SELECT COUNT(*) FROM consultas'));
        $this->assertSame(0, $this->contar('SELECT COUNT(*) FROM archivos_clinicos'));
        $this->assertSame(0, $this->contar('SELECT COUNT(*) FROM tratamientos'));
        $this->assertNull($this->numeroHistoria(1, DosClinicas::NORTE));
    }

    private function assertRechazo(callable $accion, string $parteDelMensaje): void
    {
        try {
            $accion();
            $this->fail('Debió rechazarse: ' . $parteDelMensaje);
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString($parteDelMensaje, $e->getMessage());
        }
    }

    private function assertAccesoDenegado(callable $accion): void
    {
        try {
            $accion();
            $this->fail('Debió responder 403.');
        } catch (AccesoDenegado $e) {
            $this->assertSame(403, $e->codigo());
        }
    }

    private function numeroHistoria(int $mascota, int $clinica): ?string
    {
        $numero = $this->db->query("SELECT numero_historia_clinica FROM mascota_clinica WHERE id_mascota = $mascota AND id_clinica = $clinica")->fetchColumn();
        return $numero === false ? null : $numero;
    }

    private function auditoriasDenegadas(int $clinica): int
    {
        return $this->contar("SELECT COUNT(*) FROM auditoria_sistema WHERE id_clinica = $clinica AND accion = 'OTHER'");
    }

    private function fila(string $sql): array
    {
        return $this->db->query($sql)->fetch(PDO::FETCH_ASSOC);
    }

    private function contar(string $sql): int
    {
        return (int) $this->db->query($sql)->fetchColumn();
    }
}
