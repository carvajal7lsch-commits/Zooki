<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../Support/ConocimientoRealPrueba.php';
require_once __DIR__ . '/../../controllers/PrescripcionController.php';
require_once __DIR__ . '/../../controllers/ConsultaController.php';
require_once __DIR__ . '/../../models/Usuario.php';

final class PrescripcionTest extends TestCase
{
    private PDO $db;
    private DateTimeImmutable $fecha;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        DosClinicas::vincularLunaASur($this->db);
        ConocimientoRealPrueba::taxonomia($this->db);
        (new CargadorConocimiento($this->db))->cargar(ConocimientoRealPrueba::CARPETA);
        $this->fecha = new DateTimeImmutable('now', new DateTimeZone('America/Bogota'));
        $usuarios = new Usuario($this->db);
        Security::definirFuenteDeContextos(fn (int $id): ?array => $usuarios->contextosDe($id));
        $_SESSION = ['id_usuario' => 2, 'csrf_default' => 'token'];
        Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::VETERINARIO), 1);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['csrf_token' => 'token', 'id_mascota' => 1];
        $_FILES = [];
        http_response_code(200);
    }

    protected function tearDown(): void
    {
        Security::definirFuenteDeContextos(null);
        $_SESSION = [];
        $_POST = [];
        $_FILES = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        http_response_code(200);
    }

    private function tratamiento(?string $codigo = 'farmaco.prednisona'): array
    {
        return ['farmaco' => $codigo, 'medicamento' => 'Nombre manual', 'dosis' => 'Indicada por el veterinario', 'via_administracion' => 'Oral', 'duracion' => 'Según valoración', 'fecha_inicio' => $this->fecha->format('Y-m-d'), 'fecha_fin' => $this->fecha->modify('+2 days')->format('Y-m-d')];
    }

    private function evaluar(array $filas): array
    {
        return (new ValidacionPrescripcion($this->db))->evaluar(1, $filas, $this->fecha);
    }

    private function guardar(array $filas, ?callable $adjunto = null): int
    {
        return (new Consulta($this->db))->registrar(['id_mascota' => 1, 'peso' => '8.5', 'motivo' => 'Prueba', 'diagnostico' => 'Decisión veterinaria'], $filas, $adjunto === null ? [] : [[]], $adjunto ?? static fn (): array => []);
    }

    private function sembrarMedicacion(?string $farmaco, int $clinica = 2, ?string $inicio = null, ?string $fin = null): int
    {
        $this->db->prepare("INSERT INTO consultas (id_clinica, id_mascota, id_veterinario, fecha_hora, motivo_consulta, anamnesis, diagnostico, plan_tratamiento) VALUES (?, 1, ?, ?, 'Reservado', '', 'Reservado', '')")
            ->execute([$clinica, $clinica === 1 ? 2 : 4, $this->fecha->format('Y-m-d H:i:s')]);
        $idConsulta = (int) $this->db->lastInsertId();
        $idNodo = null;
        if ($farmaco !== null) {
            $nodo = $this->db->prepare('SELECT id_nodo FROM grafo_nodos WHERE codigo = ?');
            $nodo->execute([$farmaco]);
            $idNodo = $nodo->fetchColumn();
        }
        $this->db->prepare("INSERT INTO tratamientos (id_consulta, medicamento, dosis, via_administracion, duracion, fecha_inicio, fecha_fin, id_nodo_farmaco) VALUES (?, 'Nombre protegido secreto', 'Dosis protegida secreta', 'Via reservada', 'Duración reservada', ?, ?, ?)")
            ->execute([$idConsulta, $inicio ?? $this->fecha->modify('-1 day')->format('Y-m-d'), $fin, $idNodo]);
        return (int) $this->db->lastInsertId();
    }

    private function api(): array
    {
        Security::autorizar('validar_prescripcion_ajax');
        ob_start();
        try {
            (new PrescripcionController($this->db))->validarAjax();
        } finally {
            $salida = ob_get_clean();
        }
        return json_decode($salida, true, 64, JSON_THROW_ON_ERROR);
    }

    public function testVigenciaIncluyeFinExcluyeTerminadosYFuturosYConservaDesconocida(): void
    {
        $hoy = $this->fecha->format('Y-m-d');
        $vigente = $this->sembrarMedicacion('farmaco.prednisona', 2, null, $hoy);
        $this->sembrarMedicacion('farmaco.prednisona', 2, null, $this->fecha->modify('-1 day')->format('Y-m-d'));
        $this->sembrarMedicacion('farmaco.prednisona', 2, $this->fecha->modify('+1 day')->format('Y-m-d'), $this->fecha->modify('+2 days')->format('Y-m-d'));
        $desconocido = $this->sembrarMedicacion('farmaco.prednisona');
        $this->db->exec("INSERT INTO alertas_medicas (id_mascota, id_clinica, id_veterinario, tipo, id_nodo, descripcion) SELECT 1, 2, 4, 'medicacion_continua', id_nodo, 'Continua' FROM grafo_nodos WHERE codigo = 'farmaco.fluoxetina'");
        $medicacion = (new MedicacionVigente($this->db))->leer(1, $this->fecha)['vigentes'];
        $this->assertCount(3, $medicacion);
        $this->assertSame([$vigente, $desconocido], array_map('intval', array_column(array_slice($medicacion, 0, 2), 'id')));
        $this->assertSame(['vigente', 'desconocida', 'vigente'], array_column($medicacion, 'vigencia'));
        $this->assertTrue($medicacion[2]['visible']);
    }

    public function testPrivacidadYRevocacionInvalidanLaHuellaAntesDeGuardar(): void
    {
        $this->sembrarMedicacion('farmaco.prednisona', 2, null, $this->fecha->modify('+2 days')->format('Y-m-d'));
        $fila = $this->tratamiento('farmaco.meloxicam');
        $this->db->exec('UPDATE propietario_clinica SET autoriza_historia_compartida = 1 WHERE id_propietario = 6 AND id_clinica = 1');
        $visible = $this->evaluar([$fila])[0]['publica'];
        $this->assertStringContainsString('Prednisona', json_encode($visible, JSON_UNESCAPED_UNICODE));
        $this->db->exec('UPDATE propietario_clinica SET autoriza_historia_compartida = 0 WHERE id_propietario = 6 AND id_clinica = 1');
        $fila += ['decision' => 'confirmar', 'huella' => $visible['huella']];
        try {
            $this->guardar([$fila]);
            $this->fail('Una huella anterior a la revocación permitió guardar.');
        } catch (PrescripcionRechazada $error) {
            $this->assertTrue($error->publica['requiere_conciliacion']);
            $this->assertStringNotContainsString('prednisona', strtolower(json_encode($error->publica)));
            $this->assertNotSame($visible['huella'], $error->publica['huella']);
        }
        $actual = $this->evaluar([$fila])[0]['publica'];
        $fila['huella'] = $actual['huella'];
        try {
            $this->guardar([$fila]);
            $this->fail('Sin conciliación permitió guardar.');
        } catch (PrescripcionRechazada $error) {
            $this->assertStringContainsString('conciliación', $error->getMessage());
        }
        $fila['conciliacion'] = 'Revisado con el propietario antes de confirmar.';
        $this->sembrarMedicacion('farmaco.prednisona', 2, null, $this->fecha->modify('+3 days')->format('Y-m-d'));
        try {
            $this->guardar([$fila]);
            $this->fail('Una interacción nueva conservó la confirmación anterior.');
        } catch (PrescripcionRechazada $error) {
            $this->assertNotSame($fila['huella'], $error->publica['huella']);
            $this->assertStringNotContainsString('prednisona', strtolower(json_encode($error->publica)));
        }
        $fila['huella'] = $this->evaluar([$fila])[0]['publica']['huella'];
        $carga = $this->db->prepare("UPDATE auditoria_sistema SET datos_nuevos = ? WHERE descripcion = 'Carga de conocimiento global (RN-212)'");
        $carga->execute([json_encode(['version' => str_repeat('a', 64)])]);
        try {
            $this->guardar([$fila]);
            $this->fail('Una versión nueva conservó la confirmación anterior.');
        } catch (PrescripcionRechazada $error) {
            $this->assertNotSame($fila['huella'], $error->publica['huella']);
        }
        $fila['huella'] = $this->evaluar([$fila])[0]['publica']['huella'];
        $id = $this->guardar([$fila]);
        $auditoria = $this->db->query("SELECT datos_nuevos FROM auditoria_sistema WHERE descripcion = 'Decisión de prescripción (RE-2.9.3)'")->fetchColumn();
        $datos = json_decode($auditoria, true);
        $this->assertSame($fila['conciliacion'], $datos['conciliacion']);
        $this->assertSame(64, strlen($datos['version']));
        $this->assertSame($id, (int) $this->db->query('SELECT MAX(id_consulta) FROM consultas')->fetchColumn());
    }

    public function testBloqueoDirectoNoGuardaNiMueveAdjuntosYDejaAuditoriaDeRechazo(): void
    {
        $this->db->exec('UPDATE mascotas SET id_especie = 2, id_raza = 50 WHERE id_mascota = 1');
        $movido = false;
        try {
            $this->guardar([$this->tratamiento('farmaco.paracetamol')], function () use (&$movido): array {
                $movido = true;
                return [];
            });
            $this->fail('La toxicidad permitió guardar.');
        } catch (PrescripcionRechazada $error) {
            $this->assertTrue($error->publica['bloqueada']);
        }
        $this->assertFalse($movido);
        foreach (['consultas', 'tratamientos', 'archivos_clinicos'] as $tabla) {
            $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM ' . $tabla)->fetchColumn());
        }
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE descripcion = 'Prescripción rechazada (RE-2.9.2, RE-2.9.3)'")->fetchColumn());
    }

    public function testAdvertenciaRequiereDecisionYGuardaNodoFinYAuditoria(): void
    {
        $fila = $this->tratamiento('farmaco.paracetamol');
        try {
            $this->guardar([$fila]);
            $this->fail('La advertencia sin decisión permitió guardar.');
        } catch (PrescripcionRechazada $error) {
            $this->assertTrue($error->publica['requiere_decision']);
        }
        $fila += ['decision' => 'confirmar', 'huella' => $this->evaluar([$fila])[0]['publica']['huella']];
        $id = $this->guardar([$fila]);
        $guardada = $this->db->query('SELECT t.*, n.codigo FROM tratamientos t JOIN grafo_nodos n ON n.id_nodo = t.id_nodo_farmaco WHERE t.id_consulta = ' . $id)->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('farmaco.paracetamol', $guardada['codigo']);
        $this->assertSame($fila['fecha_fin'], $guardada['fecha_fin']);
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE descripcion = 'Decisión de prescripción (RE-2.9.3)'")->fetchColumn());
    }

    public function testDosFilasInteractuanYUnaAlergiaNuevaInvalidaLaConfirmacion(): void
    {
        $filas = [$this->tratamiento('farmaco.meloxicam'), $this->tratamiento('farmaco.prednisona')];
        $evaluadas = $this->evaluar($filas);
        foreach ($filas as $i => &$fila) {
            $this->assertTrue($evaluadas[$i]['publica']['requiere_decision']);
            $fila += ['decision' => 'confirmar', 'huella' => $evaluadas[$i]['publica']['huella']];
        }
        unset($fila);
        $this->db->exec("INSERT INTO alertas_medicas (id_mascota, id_clinica, id_veterinario, tipo, id_nodo, descripcion) SELECT 1, 2, 4, 'alergia', id_nodo, 'Alergia individual' FROM grafo_nodos WHERE codigo = 'farmaco.prednisona'");
        try {
            $this->guardar($filas);
            $this->fail('La alergia nueva no se releyó.');
        } catch (PrescripcionRechazada $error) {
            $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM consultas')->fetchColumn());
        }
    }

    public function testLegadoSinCodigoSeGuardaYFinInvalidoONodoRetiradoSeRechazan(): void
    {
        $fila = $this->tratamiento(null);
        unset($fila['fecha_fin']);
        $id = $this->guardar([$fila]);
        $this->assertNull($this->db->query('SELECT id_nodo_farmaco FROM tratamientos WHERE id_consulta = ' . $id)->fetchColumn());
        foreach ([array_replace($this->tratamiento(), ['fecha_fin' => '2000-01-01']), $this->tratamiento('farmaco.retirado')] as $invalida) {
            try {
                $this->guardar([$invalida]);
                $this->fail('Aceptó fin o nodo inválido.');
            } catch (InvalidArgumentException $error) {
                $this->assertNotSame('', $error->getMessage());
            }
        }
    }

    public function testApiNuncaExponeClavesProtegidasY403SinVinculoYConOtrosRolesOCsrf(): void
    {
        $this->sembrarMedicacion('farmaco.prednisona');
        $_POST['tratamientos'] = [['farmaco' => 'farmaco.meloxicam']];
        $respuesta = $this->api();
        $this->assertTrue($respuesta['success']);
        $this->assertCount(1, $respuesta['filas'][0]['advertencias']);
        $permitidas = ['success', 'filas', 'bloqueada', 'motivos', 'advertencias', 'evaluacion_incompleta', 'requiere_decision', 'requiere_conciliacion', 'tipo', 'mensaje', 'huella'];
        $revisar = function (array $objeto) use (&$revisar, $permitidas): void {
            foreach ($objeto as $clave => $valor) {
                if (is_string($clave)) {
                    $this->assertContains($clave, $permitidas);
                }
                if (is_array($valor)) {
                    $revisar($valor);
                }
            }
        };
        $revisar($respuesta);
        foreach (['prednisona', 'Nombre protegido secreto', 'Dosis protegida secreta', 'arista.', 'id_clinica', 'fecha_inicio'] as $secreto) {
            $this->assertStringNotContainsString($secreto, json_encode($respuesta));
        }
        foreach ([[1, Contexto::deClinica(1, 'Norte', Roles::ADMIN)], [6, Contexto::dePropietario()], [7, Contexto::dePlataforma()]] as [$id, $contexto]) {
            $_SESSION['id_usuario'] = $id;
            Contexto::activar($contexto, 1);
            try {
                $this->api();
                $this->fail('Entró un rol distinto de veterinario.');
            } catch (AccesoDenegado $error) {
                $this->assertSame(403, $error->codigo());
            }
        }
        $_SESSION['id_usuario'] = 2;
        Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::VETERINARIO), 1);
        unset($_POST['csrf_token']);
        try {
            $this->api();
            $this->fail('Sin CSRF entró.');
        } catch (AccesoDenegado $error) {
            $this->assertSame(403, $error->codigo());
        }
        $_POST['csrf_token'] = 'token';
        $this->db->exec('DELETE FROM mascota_clinica WHERE id_mascota = 1 AND id_clinica = 1');
        try {
            $this->api();
            $this->fail('Sin vínculo entró.');
        } catch (AccesoDenegado $error) {
            $this->assertSame(403, $error->codigo());
            $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE tabla_afectada = 'mascotas' AND accion = 'OTHER'")->fetchColumn());
        }
    }

    public function testControladorGuardarDirectoResponde422JuntoALaFila(): void
    {
        $this->db->exec('UPDATE mascotas SET id_especie = 2, id_raza = 50 WHERE id_mascota = 1');
        $_POST += ['peso' => '8.5', 'motivo' => 'Control', 'diagnostico' => 'Veterinario', 'med_nombre' => ['Texto manual'], 'med_dosis' => ['Indicada'], 'med_via' => ['Oral'], 'med_duracion' => ['Indicada'], 'med_inicio' => [$this->fecha->format('Y-m-d')], 'med_farmaco' => ['farmaco.paracetamol']];
        ob_start();
        (new ConsultaController($this->db))->registrarAjax();
        $respuesta = json_decode(ob_get_clean(), true);
        $this->assertSame(422, http_response_code());
        $this->assertSame(0, $respuesta['fila']);
        $this->assertSame('tratamientos.0', $respuesta['campo']);
        $this->assertTrue($respuesta['prescripcion']['bloqueada']);
    }

    public function testConciliacionTieneMaximoSinRecorteYFalloDeAuditoriaRevierteTodo(): void
    {
        $fila = $this->tratamiento('farmaco.paracetamol');
        $fila += ['decision' => 'confirmar', 'huella' => $this->evaluar([$fila])[0]['publica']['huella'], 'conciliacion' => str_repeat('x', 501)];
        try {
            $this->guardar([$fila]);
            $this->fail('Se recortó o aceptó una conciliación demasiado larga.');
        } catch (CampoInvalido $error) {
            $this->assertStringContainsString('conciliacion', $error->campo);
        }
        $fila['conciliacion'] = str_repeat('x', 500);
        $this->db->exec("CREATE TRIGGER fallar_decision BEFORE INSERT ON auditoria_sistema WHEN NEW.descripcion = 'Decisión de prescripción (RE-2.9.3)' BEGIN SELECT RAISE(ABORT, 'fallo de auditoria de prueba'); END");
        try {
            $this->guardar([$fila]);
            $this->fail('Se guardó sin auditoría.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('auditar', $error->getMessage());
        }
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM consultas')->fetchColumn());
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM tratamientos')->fetchColumn());
    }

    // ── M2 C5b: presentación en la consulta ─────────────────────────────

    /** Nota de C3: la toxicidad nombra la especie del paciente y su fuente (conocimiento global). */
    public function testToxicidadNombraLaEspecieYSuFuenteYLaInteraccionVisibleSuFarmaco(): void
    {
        $this->db->exec('UPDATE mascotas SET id_especie = 2, id_raza = 50 WHERE id_mascota = 1');
        $_POST['tratamientos'] = [['farmaco' => 'farmaco.paracetamol']];
        $fila = $this->api()['filas'][0];
        $this->assertTrue($fila['bloqueada']);
        $motivo = $fila['motivos'][0];
        $this->assertSame('toxicidad', $motivo['tipo']);
        $this->assertSame($this->db->query("SELECT etiqueta FROM grafo_nodos WHERE codigo = 'especie.felino'")->fetchColumn(), $motivo['paciente']);
        $this->assertNotEmpty($motivo['fuentes']);
        foreach ($motivo['fuentes'] as $fuente) {
            $this->assertSame(['entidad', 'titulo', 'url', 'fundamento'], array_keys($fuente));
            $this->assertTrue($fuente['url'] === null || str_starts_with($fuente['url'], 'https://'));
        }

        // Visible (misma clínica): dice con qué fármaco interactúa y su fuente.
        $this->db->exec('UPDATE mascotas SET id_especie = 1, id_raza = NULL WHERE id_mascota = 1');
        $this->sembrarMedicacion('farmaco.prednisona', 1, null, $this->fecha->modify('+3 days')->format('Y-m-d'));
        $_POST['tratamientos'] = [['farmaco' => 'farmaco.meloxicam']];
        $advertencia = $this->api()['filas'][0]['advertencias'][0];
        $this->assertSame('interaccion', $advertencia['tipo']);
        $this->assertSame($this->db->query("SELECT etiqueta FROM grafo_nodos WHERE codigo = 'farmaco.prednisona'")->fetchColumn(), $advertencia['farmaco']);
        $this->assertNotEmpty($advertencia['fuentes']);
    }

    /** RE-2.9.3 por el formulario: la huella, la decisión y la conciliación de cada fila llegan y se auditan. */
    public function testDecisionYConciliacionDeCadaFilaLleganPorElFormularioYSeAuditan(): void
    {
        $this->sembrarMedicacion('farmaco.prednisona');
        $hoy = $this->fecha->format('Y-m-d');
        $base = $_POST + ['motivo' => 'Dolor', 'peso' => '8.5', 'diagnostico' => 'Artrosis', 'med_nombre' => ['Meloxicam', 'Suero oral'],
            'med_dosis' => ['Según peso', '50 ml'], 'med_via' => ['Oral', 'Oral'], 'med_duracion' => ['5 días', '1 día'],
            'med_inicio' => [$hoy, $hoy], 'med_fin' => [$this->fecha->modify('+4 days')->format('Y-m-d'), $hoy], 'med_farmaco' => ['farmaco.meloxicam', '']];
        $_POST = $base;
        $primera = $this->registrarPorFormulario();
        $this->assertSame('tratamientos.0', $primera['campo']);
        $this->assertTrue($primera['prescripcion']['requiere_conciliacion']);
        $this->assertStringNotContainsString('prednisona', strtolower(json_encode($primera)));

        $_POST = $base + ['med_decision' => ['confirmar', ''], 'med_huella' => [$primera['prescripcion']['huella'], ''], 'med_conciliacion' => ['', '']];
        $sinConciliar = $this->registrarPorFormulario();
        $this->assertSame('tratamientos.0', $sinConciliar['campo']);
        $this->assertStringContainsString('conciliación', $sinConciliar['message']);

        $_POST['med_conciliacion'] = ['El propietario confirma un antiinflamatorio de otra clínica; se ajusta el plan.', ''];
        $guardada = $this->registrarPorFormulario();
        $this->assertTrue($guardada['success'], $guardada['message'] ?? '');
        $auditoria = $this->db->query("SELECT datos_nuevos FROM auditoria_sistema WHERE descripcion = 'Decisión de prescripción (RE-2.9.3)'")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertCount(1, $auditoria, 'Solo la fila con decisión se audita');
        $datos = json_decode($auditoria[0], true);
        $this->assertSame('confirmar', $datos['decision']);
        $this->assertStringContainsString('El propietario confirma', $datos['conciliacion']);
    }

    public function testBuscadorDeFarmacosSoloOfreceFarmacosActivosPorEtiquetaYSinonimoYSoloAlVeterinario(): void
    {
        $sinonimo = $this->db->query("SELECT s.texto, n.codigo FROM grafo_sinonimos s JOIN grafo_nodos n ON n.id_nodo = s.id_nodo
            WHERE n.tipo = 'farmaco' AND n.activo = 1 AND s.activo = 1 ORDER BY s.id_sinonimo LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $sustancia = $this->db->query("SELECT etiqueta FROM grafo_nodos WHERE tipo = 'sustancia' AND activo = 1 ORDER BY id_nodo LIMIT 1")->fetchColumn();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        try {
            $_GET = ['q' => 'paracet'];
            $this->assertContains('farmaco.paracetamol', array_column($this->buscarFarmacos()['farmacos'], 'codigo'));
            $_GET = ['q' => $sinonimo['texto']];
            $this->assertContains($sinonimo['codigo'], array_column($this->buscarFarmacos()['farmacos'], 'codigo'));
            $_GET = ['q' => mb_substr($sustancia, 0, 6)];
            $this->assertSame([], array_values(array_diff(array_column($this->buscarFarmacos()['farmacos'], 'tipo'), ['farmaco'])), 'Ni sustancias ni otros tipos');
            $_GET = ['q' => 'retirado'];
            $this->assertNotContains('farmaco.retirado', array_column($this->buscarFarmacos()['farmacos'], 'codigo'));

            $_SESSION['id_usuario'] = 1;
            Contexto::activar(Contexto::deClinica(1, 'Norte', Roles::ADMIN), 1);
            try {
                $this->buscarFarmacos();
                $this->fail('El administrador no prescribe.');
            } catch (AccesoDenegado $error) {
                $this->assertSame(403, $error->codigo());
            }
        } finally {
            $_GET = [];
        }
    }

    private function registrarPorFormulario(): array
    {
        ob_start();
        try {
            (new ConsultaController($this->db))->registrarAjax();
        } finally {
            $salida = ob_get_clean();
        }
        return json_decode($salida, true);
    }

    private function buscarFarmacos(): array
    {
        Security::autorizar('buscar_farmaco_ajax');
        ob_start();
        try {
            (new PrescripcionController($this->db))->buscarFarmacosAjax();
        } finally {
            $salida = ob_get_clean();
        }
        return json_decode($salida, true, 64, JSON_THROW_ON_ERROR);
    }
}
