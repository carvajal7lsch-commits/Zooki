<?php
require_once __DIR__ . '/GuionDemo.php';
require_once __DIR__ . '/LimpiezaDemo.php';
require_once __DIR__ . '/../../helpers/PoliticaPassword.php';
require_once __DIR__ . '/../../models/ConsentimientoDatos.php';
require_once __DIR__ . '/../../helpers/Migrador.php';
require_once __DIR__ . '/../../helpers/InicializadorClinica.php';
require_once __DIR__ . '/../../helpers/ValidadorTelefono.php';
require_once __DIR__ . '/../../helpers/MotorDiagnosticos.php';
require_once __DIR__ . '/../../helpers/MotorPrescripcion.php';
require_once __DIR__ . '/../../models/RegistroClinica.php';
require_once __DIR__ . '/../../models/Plataforma.php';
require_once __DIR__ . '/../../models/Suscripcion.php';
require_once __DIR__ . '/../../models/Mascota.php';
require_once __DIR__ . '/../../models/VinculosPropietario.php';
require_once __DIR__ . '/../../models/PacienteDiagnostico.php';
require_once __DIR__ . '/../../models/SolicitudAgenda.php';
require_once __DIR__ . '/../../models/HorarioClinica.php';

/** Orquestación CLI de datos ficticios; ninguna regla ni catálogo de producción se modifica. */
final class SemillaDemo
{
    private Closure $reloj;
    private ?ConocimientoClinico $grafo = null;
    private array $clinicas = [];
    private array $usuarios = [];
    private array $mascotas = [];
    private array $cuentas = [];
    private array $nodos = [];

    public function __construct(private PDO $db, ?callable $reloj = null)
    {
        $this->reloj = Closure::fromCallable($reloj ?? static fn (): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('America/Bogota')));
    }

    public function revisar(): array
    {
        if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            throw new RuntimeException('La demo requiere MySQL/MariaDB y sus restricciones reales.');
        }
        $carpeta = dirname(__DIR__, 2) . '/database';
        try {
            (new Migrador($this->db, $carpeta))->migrar(true);
            $aplicadas = array_column($this->filas('SELECT archivo FROM schema_migraciones'), 'archivo');
        } catch (Throwable $error) {
            throw new RuntimeException('No se pudo verificar el esquema v2. Ejecuta php scripts/migrar.php. ' . $error->getMessage(), 0, $error);
        }
        // El migrador puede omitir la revisión si otro proceso tiene su candado; no equivale a estar al día.
        $archivos = array_values(array_filter(scandir($carpeta), static fn (string $archivo): bool => preg_match('/^(\d{2})_.+\.sql$/D', $archivo, $numero) && (int) $numero[1] >= Migrador::PRIMERA));
        $pendientes = array_diff($archivos, $aplicadas);
        if ($pendientes !== []) {
            throw new RuntimeException('Faltan migraciones: ' . implode(', ', $pendientes) . '. Ejecuta php scripts/migrar.php');
        }
        $migraciones = $this->filas("SELECT archivo FROM schema_migraciones WHERE archivo='18_razas_mdr1.sql'");
        if ($migraciones === []) {
            throw new RuntimeException('Falta la migración18. Ejecuta php scripts/migrar.php');
        }
        $this->grafo = (new GrafoConocimiento($this->db))->instantanea();
        $codigos = [...GuionDemo::CODIGOS, ...array_map(static fn (string $d): string => 'diagnostico.' . $d, [...GuionDemo::DIAGNOSTICOS_PERRO, ...GuionDemo::DIAGNOSTICOS_GATO])];
        $faltan = array_diff(array_unique($codigos), array_keys($this->grafo->nodos));
        if ($faltan !== [] || !$this->grafo->version) {
            throw new RuntimeException('Falta conocimiento cargado: ' . implode(', ', $faltan) . '. Ejecuta php scripts/cargar_conocimiento.php');
        }
        foreach ($this->filas('SELECT id_nodo,codigo FROM grafo_nodos WHERE activo=1') as $nodo) {
            $this->nodos[$nodo['codigo']] = (int) $nodo['id_nodo'];
        }
        foreach (GuionDemo::mascotas() as [, $especie, $raza]) {
            if ($this->filas('SELECT r.id_raza FROM razas r JOIN especies e ON e.id_especie=r.id_especie WHERE e.nombre_especie=? AND r.nombre_raza=?', [$especie, $raza]) === []) {
                throw new RuntimeException('Falta el catálogo ' . $especie . '/' . $raza . '. Ejecuta php scripts/migrar.php');
            }
        }
        $gratis = $this->filas("SELECT limite_personal FROM planes WHERE nombre='Gratuito' AND estado=1")[0] ?? null;
        if (!$gratis || (int) $gratis['limite_personal'] !== 2 || $this->filas("SELECT id_plan FROM planes WHERE nombre='Profesional' AND estado=1") === []) {
            throw new RuntimeException('Los planes no permiten el guion Profesional/Gratuito2/2; no se alteran catálogos globales.');
        }
        $super = $this->filas('SELECT id_usuario FROM usuarios WHERE es_super_admin=1 AND estado=1 ORDER BY id_usuario LIMIT 1');
        if ($super === []) {
            throw new RuntimeException('Falta un super-administrador activo para las concesiones administrativas. Ejecuta php scripts/crear_superadmin.php');
        }
        (new LimpiezaDemo($this->db))->ambito();
        foreach (GuionDemo::personas() as $persona) {
            if (!ValidadorTelefono::esValido($persona['telefono'])) {
                throw new LogicException('Teléfono ficticio no válido.');
            }
            $existente = (new Usuario($this->db))->buscarPorDocumento($persona['documento']);
            if ($existente && !str_starts_with(strtolower((string) $existente['email']), GuionDemo::PREFIJO_CORREO)) {
                throw new RuntimeException('Un documento reservado para la demo pertenece a una persona ajena. No se modifica.');
            }
        }
        return ['base' => $this->db->query('SELECT DATABASE()')->fetchColumn(), 'version' => $this->grafo->version, 'super' => (int) $super[0]['id_usuario'], 'clinicas' => 5, 'cuentas' => count(GuionDemo::personas()), 'mascotas' => count(GuionDemo::mascotas())];
    }

    public function crear(string $dia, ?string $clave = null): array
    {
        $fecha = self::fecha($dia);
        $revision = $this->revisar();
        $claves = [];
        foreach (GuionDemo::personas() as $alias => $persona) {
            $valor = $clave ?? PoliticaPassword::generarTemporal();
            $motivo = PoliticaPassword::validar($valor, [$persona['nombre'], $persona['documento'], $persona['email']]);
            if ($motivo !== null) {
                throw new InvalidArgumentException('La clave no cumple la política para ' . $persona['nombre'] . ': ' . $motivo);
            }
            $claves[$alias] = $valor;
        }
        foreach (GuionDemo::mascotas() as $mascota) {
            if ($fecha->modify('-' . $mascota[4] . ' months') > new DateTimeImmutable('today', new DateTimeZone('America/Bogota'))) {
                throw new InvalidArgumentException('El día elegido produciría nacimientos futuros. No se modifica la demo.');
            }
        }
        return $this->exclusiva(function () use ($fecha, $claves, $revision): array {
            $creada = false;
            try {
                Transaccion::ejecutar($this->db, function () use ($fecha, $claves, $revision): void {
                    (new LimpiezaDemo($this->db))->borrar();
                    $this->clinicas = [];
                    $this->usuarios = [];
                    $this->mascotas = [];
                    $this->cuentas = [];
                    $this->crearClinicas($claves, $revision['super']);
                    $this->crearPersonas($claves);
                    $this->crearMascotas($fecha);
                    $this->crearHistoria($fecha);
                });
                $creada = true;
                $this->horarios();
                $agenda = new AgendaDemo($this->db, $this->grafo, $this->clinicas, $this->usuarios, $this->mascotas, $this->reloj, $this->consultaHistorica(...));
                $niveles = $agenda->preparar($fecha);
                return ['cuentas' => $this->cuentas, 'conteos' => $this->conteos(), 'niveles' => $niveles, 'version' => $this->grafo->version];
            } catch (Throwable $error) {
                if ($creada) {
                    // La fase previa es completa, pero no se publica una demo sin agenda si falla su preparación.
                    Transaccion::ejecutar($this->db, fn () => (new LimpiezaDemo($this->db))->borrar());
                }
                throw $error;
            }
        });
    }

    public function borrar(): array
    {
        $this->revisar();
        return $this->exclusiva(function (): array {
            Transaccion::ejecutar($this->db, fn () => (new LimpiezaDemo($this->db))->borrar());
            return ['conteos' => $this->conteos()];
        });
    }

    public function escena(): array
    {
        $this->revisar();
        return $this->exclusiva(function (): array {
            $this->leerIdentidades();
            return (new AgendaDemo($this->db, $this->grafo, $this->clinicas, $this->usuarios, $this->mascotas, $this->reloj, $this->consultaHistorica(...)))->escena();
        });
    }

    private function exclusiva(callable $accion): array
    {
        $clave = 'zooki_demo_' . substr(hash('sha256', (string) $this->db->query('SELECT DATABASE()')->fetchColumn()), 0, 32);
        $q = $this->db->prepare('SELECT GET_LOCK(?,0)');
        $q->execute([$clave]);
        if ((int) $q->fetchColumn() !== 1) {
            throw new RuntimeException('Otra preparación de demo está en curso.');
        }
        $sesion = $_SESSION ?? [];
        try {
            return $accion();
        } finally {
            $_SESSION = $sesion;
            $q = $this->db->prepare('SELECT RELEASE_LOCK(?)');
            $q->execute([$clave]);
        }
    }

    public static function como(int $usuario, ?int $clinica, int $rol, string $nombre = 'Demo'): void
    {
        $_SESSION = ['id_usuario' => $usuario, 'nombre_completo' => $nombre];
        Contexto::activar($rol === Roles::SUPER_ADMIN ? Contexto::dePlataforma() : ($rol === Roles::PROPIETARIO ? Contexto::dePropietario() : Contexto::deClinica((int) $clinica, $nombre, $rol)), 1);
    }

    private function crearClinicas(array $claves, int $super): void
    {
        $personas = GuionDemo::personas();
        foreach (GuionDemo::CLINICAS as $clave => [$nombre, , $direccion]) {
            $alias = $clave . '-admin';
            $persona = $personas[$alias];
            $entrada = ['nombre_clinica' => $nombre, 'nit' => GuionDemo::nit($clave), 'direccion' => $direccion, 'telefono_clinica' => $persona['telefono'], 'email_clinica' => GuionDemo::correo($clave . '-clinica'), 'nombre_completo' => $persona['nombre'], 'tipo_documento' => 'CC', 'documento' => $persona['documento'], 'telefono' => $persona['telefono'], 'email' => $persona['email'], 'password' => $claves[$alias], 'confirm_password' => $claves[$alias], 'acepta_datos' => '1'];
            $ip = '192.0.2.' . (231 + count($this->clinicas));
            $registro = new RegistroClinica($this->db);
            $resultado = $registro->registrar($entrada, $ip);
            $id = (int) $this->filas('SELECT id_clinica FROM clinicas WHERE nit=?', [ValidadorNit::normalizarEntrada(GuionDemo::nit($clave))])[0]['id_clinica'];
            $this->clinicas[$clave] = $id;
            $usuario = (int) (new Usuario($this->db))->buscarPorEmail($persona['email'])['id_usuario'];
            $this->usuarios[$alias] = $usuario;
            $this->cuentas[] = $this->cuenta($persona, $claves[$alias]);
            if ($clave === 'petcare') {
                continue;
            }
            $registro->activar($resultado['enlace']['id'], $resultado['enlace']['token'], ['acepta_datos' => '1'], $ip);
            (new InicializadorClinica($this->db))->copiar($id);
            self::como($super, null, Roles::SUPER_ADMIN);
            $pro = $clave !== 'huellitas';
            $plan = (int) $this->filas('SELECT id_plan FROM planes WHERE nombre=?', [$pro ? 'Profesional' : 'Gratuito'])[0]['id_plan'];
            $ahora = ($this->reloj)();
            $inicio = $clave === 'estrella' ? $ahora->modify('-6 months') : $ahora;
            $fin = $clave === 'estrella' ? $ahora->modify('-10 days')->format('Y-m-d') : ($pro ? $ahora->modify('+1 year')->format('Y-m-d') : null);
            (new Suscripcion($this->db, $id, static fn () => $inicio))->ajustar($plan, 'activa', 'Semilla demo: concesión administrativa ficticia, sin pago Wompi.', $fin);
            (new Suscripcion($this->db, $id, $this->reloj))->sincronizar();
            if ($clave === 'estrella') {
                (new Plataforma($this->db))->cambiarEstado($id, 'suspendida', 'Semilla demo: mora de la concesión vencida, sin pagos ficticios.');
            }
        }
    }

    private function cuenta(array $persona, string $clave): array
    {
        return ['rol' => Roles::nombre($persona['rol']), 'clinica' => GuionDemo::CLINICAS[$persona['clinica']][0], 'correo' => $persona['email'], 'clave' => $clave];
    }

    private function crearPersonas(array $claves): void
    {
        $modelo = new Usuario($this->db);
        foreach (GuionDemo::personas() as $alias => $persona) {
            if (isset($this->usuarios[$alias])) {
                continue;
            }
            $id = $modelo->crear(['nombre_completo' => $persona['nombre'], 'documento' => $persona['documento'], 'tipo_documento' => 'CC', 'telefono' => $persona['telefono'], 'email' => $persona['email'], 'password' => password_hash($claves[$alias], PASSWORD_DEFAULT)]);
            $modelo->marcarCambioPassword($id, false);
            (new ConsentimientoDatos($this->db))->registrar($id, PoliticaDatos::MEDIO_FORMULARIO, '192.0.2.231');
            $this->usuarios[$alias] = $id;
            $clinica = $this->clinicas[$persona['clinica']];
            if ($persona['rol'] === Roles::PROPIETARIO) {
                self::como($id, null, Roles::PROPIETARIO);
                (new VinculosPropietario($this->db))->vincular($clinica);
                if ($alias === 'portal') {
                    (new VinculosPropietario($this->db))->vincular($this->clinicas['huellitas']);
                }
            } else {
                $modelo->asignarRolEnClinica($id, $clinica, $persona['rol']);
            }
            $this->cuentas[] = $this->cuenta($persona, $claves[$alias]);
        }
    }

    private function crearMascotas(DateTimeImmutable $dia): void
    {
        foreach (GuionDemo::mascotas() as [$nombre, $especie, $raza, $sexo, $meses, $peso, $esterilizado, $alias]) {
            $claveClinica = str_starts_with($alias, 'huellitas-') ? 'huellitas' : 'sanroque';
            $clinica = $this->clinicas[$claveClinica];
            self::como($this->usuarios[$claveClinica . '-admin'], $clinica, Roles::ADMIN);
            $taxonomia = $this->filas('SELECT r.id_raza,r.id_especie FROM razas r JOIN especies e ON e.id_especie=r.id_especie WHERE e.nombre_especie=? AND r.nombre_raza=?', [$especie, $raza])[0];
            $datos = ['nombre' => $nombre, 'id_propietario' => $this->usuarios[$alias], 'id_especie' => $taxonomia['id_especie'], 'id_raza' => $taxonomia['id_raza'], 'sexo' => $sexo, 'fecha_nacimiento' => $dia->modify('-' . $meses . ' months')->format('Y-m-d'), 'peso' => $peso, 'esterilizado' => $esterilizado];
            $id = (new Mascota($this->db))->insert($datos);
            $this->mascotas[$nombre] = ['id' => $id, 'clinica' => $claveClinica, 'especie' => $especie, 'peso' => $peso, 'propietario' => $this->usuarios[$alias]];
        }
        self::como($this->usuarios['huellitas-admin'], $this->clinicas['huellitas'], Roles::ADMIN);
        (new Mascota($this->db))->vincular($this->mascotas['Nala']['id'], $this->usuarios['portal']);
    }

    private function crearHistoria(DateTimeImmutable $dia): void
    {
        $principal = array_values(array_filter($this->mascotas, static fn (array $m): bool => $m['clinica'] === 'sanroque'));
        $adultos = array_values(array_filter($principal, fn (array $m): bool => $m['id'] !== $this->mascotas['Kira']['id']));
        foreach ([['Mishi', 'alergia', 'farmaco.amoxicilina_clavulanato'], ['Toby', 'alergia', 'farmaco.meloxicam'], ['Bruno', 'alergia', 'farmaco.doxiciclina'], ['Mila', 'condicion_cronica', 'condicion.epilepsia'], ['Max', 'medicacion_continua', 'farmaco.meloxicam']] as [$nombre, $tipo, $codigo]) {
            $this->insertar('alertas_medicas', ['id_mascota' => $this->mascotas[$nombre]['id'], 'id_clinica' => $this->clinicas['sanroque'], 'id_veterinario' => $this->usuarios['sanroque-vet-1'], 'tipo' => $tipo, 'id_nodo' => $this->nodos[$codigo], 'descripcion' => $this->grafo->nodos[$codigo]['etiqueta'] . ': antecedente ficticio para la demostración.', 'fecha' => $dia->modify('-60 days')->format('Y-m-d H:i:s')]);
        }
        foreach (range(0, 118) as $i) {
            $mascota = $adultos[$i % count($adultos)];
            $fecha = $dia->modify('-6 months')->modify('+' . (int) floor($i * 170 / 119) . ' days')->setTime(8 + $i % 3, 0);
            $pool = $mascota['especie'] === 'Canino' ? GuionDemo::DIAGNOSTICOS_PERRO : GuionDemo::DIAGNOSTICOS_GATO;
            $this->consultaHistorica($mascota, $this->clinicas['sanroque'], $this->usuarios['sanroque-vet-' . (1 + $i % 3)], $fecha, 'diagnostico.' . $pool[$i % count($pool)]);
        }
        // Este episodio mantiene meloxicam vigente para la interacción del guion de Max.
        $this->consultaHistorica($this->mascotas['Max'], $this->clinicas['sanroque'], $this->usuarios['sanroque-vet-1'], $dia->modify('-3 days')->setTime(9, 0), 'diagnostico.osteoartritis', true);
        $huellitas = array_values(array_filter($this->mascotas, static fn (array $m): bool => $m['clinica'] === 'huellitas'));
        $huellitas[] = $this->mascotas['Nala'];
        foreach ($huellitas as $i => $mascota) {
            $this->consultaHistorica($mascota, $this->clinicas['huellitas'], $this->usuarios['huellitas-vet-1'], $dia->modify('-' . (20 + $i * 3) . ' days')->setTime(9, 0), 'diagnostico.gastritis_aguda');
        }
        foreach (array_values($this->mascotas) as $i => $mascota) {
            $clinica = $this->clinicas[$mascota['clinica']];
            $vet = $this->usuarios[$mascota['clinica'] . '-vet-1'];
            if ($mascota['id'] !== $this->mascotas['Kira']['id']) {
                $proxima = $dia->modify($i % 3 === 0 ? '+7 days' : ($i % 3 === 1 ? '-14 days' : '+180 days'));
                $this->insertar('vacunas', ['id_clinica' => $clinica, 'id_mascota' => $mascota['id'], 'id_veterinario' => $vet, 'nombre_vacuna' => $mascota['especie'] === 'Canino' ? 'Rabia' : 'Triple Felina', 'laboratorio' => 'Laboratorio de demostración', 'lote' => 'DEMO-' . ($i + 1), 'fecha_aplicacion' => $dia->modify('-5 months')->format('Y-m-d'), 'fecha_proxima_dosis' => $proxima->format('Y-m-d'), 'observaciones' => 'Registro ficticio de demostración.']);
            }
            $this->insertar('desparasitaciones', ['id_clinica' => $clinica, 'id_mascota' => $mascota['id'], 'id_veterinario' => $vet, 'tipo' => $i % 2 === 0 ? 'interna' : 'externa', 'producto' => $i % 2 === 0 ? 'Fenbendazol' : 'Selamectina', 'periodicidad' => 'trimestral', 'fecha_aplicacion' => $dia->modify('-45 days')->format('Y-m-d'), 'fecha_proxima' => $dia->modify('+45 days')->format('Y-m-d'), 'observaciones' => 'Registro ficticio para demostración, sin pauta nueva.']);
        }
    }

    public function consultaHistorica(array $mascota, int $clinica, int $vet, DateTimeImmutable $fecha, string $diagnostico, bool $vigente = false, int $minutosReales = 30): int
    {
        self::como($vet, $clinica, Roles::VETERINARIO);
        $contexto = (new PacienteDiagnostico($this->db))->contexto($mascota['id'], $this->grafo, $fecha, []);
        $sintomas = [];
        foreach ($this->grafo->aristas as $arista) {
            if ($arista['tipo_relacion'] === 'sugiere' && $arista['destino'] === $diagnostico && $arista['signo'] === 'positivo' && EvaluadorCondiciones::evaluar($arista['condiciones'] ?? null, $contexto, $fecha)['valor'] === true) {
                $sintomas[$arista['origen']] = ['tiempo' => 'conocido', 'minutos' => 120];
            }
        }
        $contexto['sintomas'] = $sintomas;
        $candidatos = MotorDiagnosticos::sugerir($this->grafo, $contexto, $fecha)['candidatos'];
        if (!in_array($diagnostico, array_column($candidatos, 'codigo'), true)) {
            throw new RuntimeException('El grafo no respalda el diagnóstico histórico ' . $diagnostico . '; no se fuerza.');
        }
        $alergias = [];
        foreach ($this->filas("SELECT n.codigo FROM alertas_medicas a JOIN grafo_nodos n ON n.id_nodo=a.id_nodo WHERE a.id_mascota=? AND a.tipo='alergia' AND a.activa=1", [$mascota['id']]) as $alergia) {
            $alergias[] = ['farmaco' => $alergia['codigo']];
        }
        $sugerencia = MotorPrescripcion::sugerir($diagnostico, (float) $mascota['peso'], $contexto, $alergias, [], $this->grafo, $fecha);
        $tipo = $this->tipo($clinica);
        $cita = $this->insertar('citas', ['id_clinica' => $clinica, 'id_mascota' => $mascota['id'], 'id_veterinario' => $vet, 'id_tipo_cita' => $tipo, 'fecha' => $fecha->format('Y-m-d'), 'hora' => $fecha->format('H:i:s'), 'hora_fin' => $fecha->modify('+30 minutes')->format('H:i:s'), 'motivo' => 'Control histórico: ' . $this->grafo->nodos[$diagnostico]['etiqueta'], 'duracion_minutos' => 30, 'margen_minutos' => 10, 'prioridad' => 'verde', 'estado' => 'completada', 'origen' => 'reserva', 'hora_inicio_real' => $fecha->format('Y-m-d H:i:s'), 'hora_fin_real' => $fecha->modify('+' . $minutosReales . ' minutes')->format('Y-m-d H:i:s'), 'duracion_activa_objetivo_segundos' => 1800, 'politica_temporal' => 'M']);
        $consulta = $this->insertar('consultas', ['id_clinica' => $clinica, 'id_cita' => $cita, 'id_mascota' => $mascota['id'], 'id_veterinario' => $vet, 'fecha_hora' => $fecha->format('Y-m-d H:i:s'), 'motivo_consulta' => $this->grafo->nodos[$diagnostico]['etiqueta'], 'anamnesis' => 'Historia ficticia para la sustentación; evolución y controles registrados.', 'peso' => $mascota['peso'], 'temperatura' => 38.5, 'frecuencia_cardiaca' => $mascota['especie'] === 'Canino' ? 90 : 170, 'frecuencia_respiratoria' => 22, 'diagnostico' => $this->grafo->nodos[$diagnostico]['etiqueta'], 'plan_tratamiento' => implode("\n", [...$sugerencia['plan']['examenes'], ...$sugerencia['plan']['recomendaciones']]), 'observaciones' => 'Datos ficticios de demostración.']);
        foreach ($sintomas as $codigo => $episodio) {
            $this->insertar('consulta_sintomas', ['id_consulta' => $consulta, 'id_nodo' => $this->nodos[$codigo], 'detalles' => json_encode($episodio, JSON_THROW_ON_ERROR)]);
        }
        foreach ($sugerencia['tratamientos'] as $pauta) {
            if (!$pauta['precargar'] || $pauta['seguridad']['bloqueada'] || $pauta['seguridad']['requiere_decision'] || $pauta['seguridad']['evaluacion_incompleta']) {
                continue;
            }
            $fin = preg_match('/^(\d+) días$/uD', $pauta['duracion'], $dias) ? $fecha->modify('+' . $dias[1] . ' days') : $fecha->modify('+7 days');
            $this->insertar('tratamientos', ['id_consulta' => $consulta, 'id_nodo_farmaco' => $this->nodos[$pauta['farmaco']], 'medicamento' => $pauta['medicamento'], 'dosis' => $pauta['dosis'], 'via_administracion' => $pauta['via_administracion'], 'duracion' => $pauta['duracion'], 'fecha_inicio' => $fecha->format('Y-m-d'), 'fecha_fin' => $vigente ? null : $fin->format('Y-m-d'), 'observaciones' => $pauta['observaciones'] . ($vigente ? '' : ' Episodio ficticio finalizado según evolución.')]);
        }
        $numero = count($this->filas('SELECT id_mascota FROM mascota_clinica WHERE id_clinica=? AND numero_historia_clinica IS NOT NULL', [$clinica])) + 1;
        $this->db->prepare('UPDATE mascota_clinica SET numero_historia_clinica=? WHERE id_clinica=? AND id_mascota=? AND numero_historia_clinica IS NULL')->execute([sprintf('HC-%06d', $numero), $clinica, $mascota['id']]);
        return $cita;
    }

    private function horarios(): void
    {
        foreach (['sanroque', 'huellitas'] as $clave) {
            $clinica = $this->clinicas[$clave];
            self::como($this->usuarios[$clave . '-admin'], $clinica, Roles::ADMIN);
            $usuarios = array_values($this->usuarios);
            $mascotas = array_column($this->mascotas, 'id');
            (new CoordinadorAgenda($this->db))->ejecutar($clinica, static fn (): array => ['usuarios' => $usuarios, 'mascotas' => $mascotas], function () use ($clave): void {
                $semana = ValoresInicialesClinica::HORARIOS;
                $semana[5] = [6, 1, 1, 1, '08:00:00', '12:00:00', '14:00:00', '18:00:00'];
                (new HorarioClinica($this->db))->guardar($semana);
                $franjas = [];
                foreach (range(1, 6) as $dia) {
                    foreach ([['08:00', '12:00'], ['14:00', '18:00']] as [$inicio, $fin]) {
                        $franjas[] = ['dia_semana' => $dia, 'hora_inicio' => $inicio, 'hora_fin' => $fin];
                    }
                }
                foreach ($this->usuarios as $alias => $usuario) {
                    if (str_starts_with($alias, $clave . '-vet-')) {
                        (new CoordinacionHorarios($this->db))->guardarVeterinario($usuario, $franjas);
                    }
                }
            });
        }
    }

    private function leerIdentidades(): void
    {
        foreach (array_keys(GuionDemo::CLINICAS) as $clave) {
            $fila = $this->filas('SELECT id_clinica FROM clinicas WHERE nit=?', [ValidadorNit::normalizarEntrada(GuionDemo::nit($clave))]);
            if ($fila === []) {
                throw new RuntimeException('Falta la demo; ejecuta primero --si.');
            }
            $this->clinicas[$clave] = (int) $fila[0]['id_clinica'];
        }
        foreach (GuionDemo::personas() as $alias => $persona) {
            $usuario = (new Usuario($this->db))->buscarPorEmail($persona['email']);
            if (!$usuario) {
                throw new RuntimeException('Falta una cuenta demo; ejecuta primero --si.');
            }
            $this->usuarios[$alias] = (int) $usuario['id_usuario'];
        }
        foreach ($this->filas('SELECT m.*,e.nombre_especie FROM mascotas m JOIN usuarios u ON u.id_usuario=m.id_propietario JOIN especies e ON e.id_especie=m.id_especie WHERE u.email LIKE ?', [GuionDemo::PREFIJO_CORREO . '%@gmail.com']) as $mascota) {
            $this->mascotas[$mascota['nombre']] = ['id' => (int) $mascota['id_mascota'], 'especie' => $mascota['nombre_especie'], 'peso' => $mascota['peso'], 'propietario' => (int) $mascota['id_propietario']];
        }
    }

    public function conteos(): array
    {
        $ambito = (new LimpiezaDemo($this->db))->ambito();
        $resultado = ['clinicas' => count($ambito['clinicas']), 'usuarios' => count($ambito['usuarios']), 'mascotas' => count($ambito['mascotas'])];
        foreach (['usuario_clinica', 'propietario_clinica', 'mascota_clinica', 'consultas', 'vacunas', 'desparasitaciones', 'alertas_medicas', 'citas', 'solicitudes_agenda'] as $tabla) {
            $resultado[$tabla] = $ambito['clinicas'] === [] ? 0 : count($this->filas('SELECT id_clinica FROM ' . $tabla . ' WHERE id_clinica IN (' . implode(',', array_fill(0, count($ambito['clinicas']), '?')) . ')', $ambito['clinicas']));
        }
        return $resultado;
    }

    private function tipo(int $clinica): int
    {
        return (int) $this->filas('SELECT id_tipo_cita FROM tipos_cita WHERE id_clinica=? AND nombre_tipo=?', [$clinica, 'Consulta general/Enfermedad'])[0]['id_tipo_cita'];
    }

    private function insertar(string $tabla, array $datos): int
    {
        $columnas = implode(',', array_keys($datos));
        $this->db->prepare('INSERT INTO ' . $tabla . ' (' . $columnas . ') VALUES (' . implode(',', array_fill(0, count($datos), '?')) . ')')->execute(array_values($datos));
        return (int) $this->db->lastInsertId();
    }

    private function filas(string $sql, array $parametros = []): array
    {
        $q = $this->db->prepare($sql);
        $q->execute($parametros);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function fecha(string $dia): DateTimeImmutable
    {
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $dia, new DateTimeZone('America/Bogota'));
        if (!$fecha || $fecha->format('Y-m-d') !== $dia) {
            throw new InvalidArgumentException('Usa --dia=AAAA-MM-DD con una fecha válida.');
        }
        return $fecha;
    }
}
require_once __DIR__ . '/AgendaDemo.php';
