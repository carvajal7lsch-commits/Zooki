<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Mascota.php';
require_once __DIR__ . '/../models/MascotaPropietario.php';
require_once __DIR__ . '/../models/HistoriaPropietario.php';
require_once __DIR__ . '/../models/VinculosPropietario.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../helpers/HorarioAtencion.php';
require_once __DIR__ . '/../helpers/ReglaAtencion.php';
require_once __DIR__ . '/../helpers/RespuestaJson.php';
require_once __DIR__ . '/../helpers/ValidadorClinico.php';
require_once __DIR__ . '/../helpers/ValidadorMascota.php';
require_once __DIR__ . '/../helpers/FotoMascota.php';

/**
 * C6: portal del propietario en el contexto «propietario» (RN-G01), sin
 * clínica activa. Ve todas sus mascotas (RN-110) y su historia en todas las
 * clínicas (RN-114); solo actúa en las clínicas con vínculo activo. Las
 * citas del portal están en CitaController (agendar, catálogos, cancelar).
 */
class PortalController
{
    /** Estados de cita que el propietario todavía puede cancelar (RN-405). */
    private const CITAS_ABIERTAS = ['pendiente', 'confirmada'];

    private PDO $db;
    private MascotaPropietario $mascotas;
    private HistoriaPropietario $historia;
    private VinculosPropietario $vinculos;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? (new Database())->getConnection();
        $this->mascotas = new MascotaPropietario($this->db);
        $this->historia = new HistoriaPropietario($this->db);
        $this->vinculos = new VinculosPropietario($this->db);
    }

    // ── Pantalla del portal ─────────────────────────────────────────────

    public function index(): void
    {
        $ahora = new DateTimeImmutable('now', new DateTimeZone(ReglaAtencion::ZONA));
        $clinicas = $this->vinculos->clinicas();

        $mascotas = [];
        $todas_citas = [];
        $todas_vacunas = [];
        $todas_desparasitaciones = [];
        foreach ($this->mascotas->listar() as $mascota) {
            $id = (int) $mascota['id_mascota'];
            $foto = $mascota['url_foto'] ? 'uploads/mascotas/' . htmlspecialchars($mascota['url_foto']) : null;
            $citas = $this->historia->citas($id);
            $mascota['especie'] = $mascota['nombre_especie'];
            $mascota['raza'] = $mascota['nombre_raza'];
            $mascota['proxima_cita'] = $this->proximaCita($citas, $ahora);
            $mascotas[] = $mascota;

            // Solo se cancela en una clínica con vínculo activo (Cita::paraPropietario).
            foreach ($this->conCancelacion($citas) as $cita) {
                $cita['nombre_mascota'] = $mascota['nombre'];
                $cita['foto_mascota'] = $foto;
                $cita['nombre_completo'] = $cita['veterinario_nombre'];
                $todas_citas[] = $cita;
            }
            foreach ($this->historia->vacunas($id) as $vacuna) {
                $todas_vacunas[] = $vacuna + ['nombre_mascota' => $mascota['nombre'], 'foto_mascota' => $foto];
            }
            foreach ($this->historia->desparasitaciones($id) as $desparasitacion) {
                $todas_desparasitaciones[] = $desparasitacion + ['nombre_mascota' => $mascota['nombre'], 'foto_mascota' => $foto];
            }
        }
        usort($todas_citas, fn (array $a, array $b): int => strcmp($b['fecha'] . $b['hora'], $a['fecha'] . $a['hora']));
        usort($todas_vacunas, fn (array $a, array $b): int => strcmp($b['fecha_aplicacion'], $a['fecha_aplicacion']));
        usort($todas_desparasitaciones, fn (array $a, array $b): int => strcmp($b['fecha_aplicacion'], $a['fecha_aplicacion']));

        // RE-5.1.7 (decisión C6): el horario de cada clínica vinculada.
        $horarios = $this->vinculos->horarios();
        $clinicas_portal = [];
        foreach ($clinicas as $clinica) {
            $semana = HorarioAtencion::semana($horarios[(int) $clinica['id_clinica']] ?? []);
            $clinica['semana'] = $semana;
            $clinica['ahora'] = HorarioAtencion::ahora($semana, $ahora);
            $clinica['dias_cerrados'] = array_keys(array_filter($semana, fn (array $dia): bool => !$dia['franjas']));
            $clinicas_portal[] = $clinica;
        }
        $clinicas_disponibles = $this->vinculos->disponibles();

        $usuarioData = (new Usuario($this->db))->buscarPorId((int) Contexto::idUsuario()) ?? [];
        $primer_nombre = explode(' ', trim($_SESSION['usuario_nombre'] ?? 'Propietario'))[0];
        $catalogo_especies = (new Mascota($this->db))->getEspecies();

        $view = __DIR__ . '/../views/portal/index.php';
        require_once __DIR__ . '/../views/portal/layout.php';
    }

    /** Marca las citas que el propietario puede cancelar: abiertas y en una clínica con vínculo activo. */
    private function conCancelacion(array $citas): array
    {
        $vinculadas = array_map('intval', array_column($this->vinculos->clinicas(), 'id_clinica'));
        foreach ($citas as &$cita) {
            $abierta = in_array($cita['estado'], self::CITAS_ABIERTAS, true);
            $cita['cancelable'] = $abierta && in_array((int) $cita['id_clinica'], $vinculadas, true);
        }
        unset($cita);
        return $citas;
    }

    /** La primera cita abierta desde hoy, de cualquier clínica. */
    private function proximaCita(array $citas, DateTimeImmutable $ahora): ?array
    {
        $proxima = null;
        $hoy = $ahora->format('Y-m-d');
        foreach ($citas as $cita) {
            if (!in_array($cita['estado'], self::CITAS_ABIERTAS, true) || $cita['fecha'] < $hoy) {
                continue;
            }
            if ($proxima === null || $cita['fecha'] . $cita['hora'] < $proxima['fecha'] . $proxima['hora']) {
                $proxima = $cita;
            }
        }
        return $proxima;
    }

    // ── Mascotas e historia ─────────────────────────────────────────────

    /** RE-5.1.3, RE-5.9.2 y RN-114: ficha e historia completa en todas las clínicas. */
    public function verDetalleMascotaAjax(): void
    {
        RespuestaJson::consulta(function (): void {
            $idMascota = $this->idMascota($_GET);
            $mascota = $this->mascotas->ficha($idMascota);
            $mascota['especie'] = $mascota['nombre_especie'] ?? '';
            $mascota['raza'] = $mascota['nombre_raza'] ?? '';
            // HU-1.5: la raza que escribió el propietario se ve hasta que la clínica la confirme.
            if (!empty($mascota['raza_indicada'])) {
                $mascota['raza'] = $mascota['raza_indicada'] . ' (por confirmar)';
            }
            unset($mascota['token_carnet']);

            RespuestaJson::enviar([
                'success' => true,
                'mascota' => $mascota,
                'historial' => $this->historia->consultas($idMascota),
                'citas' => $this->conCancelacion($this->historia->citas($idMascota)),
                'vacunas' => $this->historia->vacunas($idMascota),
                'desparasitaciones' => $this->historia->desparasitaciones($idMascota),
            ]);
        }, 'C6 portal detalle');
    }

    /** RE-5.5.1–3: versión imprimible con la clínica de cada registro (RN-114). */
    public function imprimirHistorial(): void
    {
        $idMascota = ValidadorClinico::id($_GET['id_mascota'] ?? null);
        if ($idMascota === null) {
            http_response_code(422);
            echo 'Mascota no especificada.';
            return;
        }
        $mascota = $this->mascotas->ficha($idMascota);
        $consultas = $this->historia->consultas($idMascota);
        $vacunas = $this->historia->vacunas($idMascota);
        $desparasitaciones = $this->historia->desparasitaciones($idMascota);

        require_once __DIR__ . '/../views/portal/imprimir_historial.php';
    }

    /** Detalle de una cita propia, con su resultado clínico si ya se atendió. */
    public function getDetalleCitaClinicaAjax(): void
    {
        RespuestaJson::consulta(function (): void {
            $idCita = ValidadorClinico::id($_GET['id_cita'] ?? null);
            if ($idCita === null) {
                throw new InvalidArgumentException('ID de cita requerido.');
            }
            $detalle = $this->historia->detalleCita($idCita);
            $cita = $detalle['cita'];
            RespuestaJson::enviar([
                'success' => true,
                'cita' => [
                    'id_cita' => (int) $cita['id_cita'],
                    'fecha' => $cita['fecha'],
                    'hora' => $cita['hora'],
                    'estado' => $cita['estado'],
                    'nombre_mascota' => $cita['nombre_mascota'],
                    'foto_mascota' => $cita['url_foto'] ? 'uploads/mascotas/' . $cita['url_foto'] : null,
                    'nombre_tipo' => $cita['nombre_tipo'] ?? 'Consulta',
                    'veterinario' => $cita['veterinario_nombre'],
                    'clinica' => $cita['clinica_nombre'],
                ],
                'consulta' => $detalle['consulta'],
                'tratamientos' => $detalle['tratamientos'],
            ]);
        }, 'C6 portal cita');
    }

    /** RE-1.1 del portal (RE-5.1.9): alta de una mascota propia, sin clínica hasta agendar. */
    public function registrarMascotaAjax(): void
    {
        RespuestaJson::modificacion(function (): array {
            $datos = $this->datosDeMascota();
            $foto = FotoMascota::guardar($_FILES['foto'] ?? null, $datos['nombre'], $errorFoto);
            if ($foto === false) {
                throw new InvalidArgumentException($errorFoto);
            }
            $datos['url_foto'] = $foto;

            try {
                $idMascota = $this->mascotas->registrar($datos);
            } catch (Throwable $e) {
                // La foto ya se guardó: no dejarla huérfana.
                if ($foto) {
                    FotoMascota::eliminarAnterior($foto, '');
                }
                throw $e;
            }
            return ['id_mascota' => $idMascota];
        }, 'C6 portal mascota');
    }

    /** RE-5.4.1–3 y RN-110: edita su mascota, incluidos especie, raza, sexo y nacimiento. */
    public function actualizarMascotaAjax(): void
    {
        RespuestaJson::modificacion(function (): array {
            $idMascota = $this->idMascota($_POST);
            $actual = $this->mascotas->ficha($idMascota);
            $datos = $this->datosDeMascota();
            // Sin fecha en el formulario se conserva la que tenía.
            $datos['fecha_nacimiento'] ??= $actual['fecha_nacimiento'];

            $foto = FotoMascota::guardar($_FILES['foto'] ?? null, $datos['nombre'], $errorFoto);
            if ($foto === false) {
                throw new InvalidArgumentException($errorFoto);
            }
            if ($foto) {
                $datos['url_foto'] = $foto;
            }

            try {
                $cambios = $this->mascotas->actualizar($idMascota, $datos);
            } catch (Throwable $e) {
                if ($foto) {
                    FotoMascota::eliminarAnterior($foto, '');
                }
                throw $e;
            }
            // La foto anterior solo se borra cuando la nueva ya quedó guardada.
            if ($foto) {
                FotoMascota::eliminarAnterior($actual['url_foto'] ?? null, $foto);
            }
            return ['cambios' => $cambios];
        }, 'C6 portal mascota');
    }

    /**
     * RE-5.1.9: formato (ValidadorMascota) y catálogo. El propietario no crea
     * razas: «Mi raza no está en la lista» queda como «Sin raza definida» con
     * la raza indicada, que la clínica confirma. El color no lo pide el portal.
     */
    private function datosDeMascota(): array
    {
        $hoy = new DateTimeImmutable('today', new DateTimeZone(ReglaAtencion::ZONA));
        $resultado = ValidadorMascota::validar($_POST, $hoy);
        if ($resultado['error']) {
            throw new InvalidArgumentException($resultado['error']);
        }
        $datos = $resultado['datos'];

        $taxonomia = new Mascota($this->db);
        if (!$taxonomia->especieExiste((int) $datos['especie'])) {
            throw new InvalidArgumentException('Elige la especie de la lista.');
        }
        if ($datos['raza'] === null) {
            $datos['raza'] = $taxonomia->idSinRazaDefinida((int) $datos['especie']);
            if ($datos['raza'] === null) {
                throw new InvalidArgumentException('Esta especie todavía no permite indicar otra raza. Elige la más parecida y avísale a la clínica en la consulta.');
            }
        } elseif (!$taxonomia->razaEsDeEspecie((int) $datos['raza'], (int) $datos['especie'])) {
            throw new InvalidArgumentException('Elige una raza de la lista para esa especie.');
        }

        return [
            'nombre' => $datos['nombre'],
            'id_especie' => (int) $datos['especie'],
            'id_raza' => (int) $datos['raza'],
            'raza_indicada' => $datos['raza_indicada'] ?: null,
            'sexo' => $datos['sexo'],
            'fecha_nacimiento' => $datos['fecha_nacimiento'],
            'peso' => $datos['peso'],
        ];
    }

    // ── Clínicas del propietario (HU-5.12, HU-5.13) ─────────────────────

    public function autorizarHistoriaAjax(): void
    {
        RespuestaJson::modificacion(function (): array {
            $autoriza = ($_POST['autoriza'] ?? '') === '1';
            $this->vinculos->autorizarHistoria($this->idClinica(), $autoriza);
            $mensaje = $autoriza
                ? 'La clínica ya puede ver la historia que registraron otras clínicas.'
                : 'La clínica dejó de ver la historia de otras clínicas.';
            return ['message' => $mensaje];
        }, 'C6 portal autorización');
    }

    public function vincularClinicaAjax(): void
    {
        RespuestaJson::modificacion(function (): array {
            $this->vinculos->vincular($this->idClinica());
            return ['message' => 'Quedaste vinculado a la clínica. Ya puedes agendar allí.'];
        }, 'C6 portal vínculo');
    }

    public function desvincularClinicaAjax(): void
    {
        RespuestaJson::modificacion(function (): array {
            $this->vinculos->desvincular($this->idClinica());
            return ['message' => 'Te desvinculaste de la clínica. Conserva lo que registró, pero ya no ve los datos nuevos de tus mascotas.'];
        }, 'C6 portal vínculo');
    }

    // ── Datos de contacto ───────────────────────────────────────────────

    /**
     * Decisión C6: el propietario cambia su teléfono; el correo queda de solo
     * lectura hasta que la etapa D agregue su verificación (RE-T.5.7).
     */
    public function actualizarDatosContactoAjax(): void
    {
        RespuestaJson::modificacion(function (): array {
            $telefono = preg_replace('/\s+/', ' ', trim((string) ($_POST['telefono'] ?? '')));
            if (!preg_match('/^[0-9+\s-]{7,20}$/', $telefono)) {
                throw new InvalidArgumentException('El teléfono solo puede tener números, espacios, + y guiones (de 7 a 20 caracteres).');
            }

            $usuarios = new Usuario($this->db);
            $idUsuario = (int) Contexto::idUsuario();
            $actual = $usuarios->buscarPorId($idUsuario);
            $usuarios->actualizarContacto($idUsuario, (string) $actual['email'], $telefono);
            (new Auditoria($this->db))->log($idUsuario, 'UPDATE', 'usuarios', $idUsuario, ['telefono' => $actual['telefono']], ['telefono' => $telefono], 'Teléfono cambiado desde el portal', null);

            return ['message' => 'Teléfono actualizado.', 'email' => $actual['email'], 'telefono' => $telefono];
        }, 'C6 portal contacto');
    }

    // ── Apoyo ───────────────────────────────────────────────────────────

    private function idMascota(array $entrada): int
    {
        $id = ValidadorClinico::id($entrada['id_mascota'] ?? null);
        if ($id === null) {
            throw new InvalidArgumentException('Mascota no válida.');
        }
        return $id;
    }

    private function idClinica(): int
    {
        $id = ValidadorClinico::id($_POST['id_clinica'] ?? null);
        if ($id === null) {
            throw new InvalidArgumentException('Elige la clínica.');
        }
        return $id;
    }
}
