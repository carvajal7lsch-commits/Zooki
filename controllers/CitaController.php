<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Cita.php';
require_once __DIR__ . '/../models/Vacuna.php';
require_once __DIR__ . '/../models/Desparasitacion.php';
require_once __DIR__ . '/../models/NotificacionInterna.php';
require_once __DIR__ . '/../models/Mascota.php';
require_once __DIR__ . '/../models/MascotaPropietario.php';
require_once __DIR__ . '/../models/VinculosPropietario.php';
require_once __DIR__ . '/../models/HorarioClinica.php';
require_once __DIR__ . '/../helpers/Transaccion.php';
require_once __DIR__ . '/../helpers/Roles.php';
require_once __DIR__ . '/../helpers/ReglaAtencion.php';
require_once __DIR__ . '/../helpers/RespuestaJson.php';
require_once __DIR__ . '/../helpers/ValidadorClinico.php';
require_once __DIR__ . '/HorarioClinicaController.php';

/**
 * C5: agenda v1 sobre el modelo v2. El controlador valida la petición y el
 * rol; Cita aplica la clínica activa (RN-G13) y el horario libre (RN-401).
 * Triage, sobrecupos y el resto del Grafo II son del módulo 4 de la v2.
 */
class CitaController
{
    private PDO $db;
    private Cita $citas;

    /** @var callable(): DateTimeImmutable hora actual de la clínica; las pruebas la fijan */
    private $reloj;

    public function __construct(?PDO $db = null, ?callable $reloj = null)
    {
        $this->db = $db ?? (new Database())->getConnection();
        $this->citas = new Cita($this->db, $reloj);
        $this->reloj = $reloj ?? static fn (): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone(ReglaAtencion::ZONA));
    }

    // ── Agendar ─────────────────────────────────────────────────────────

    /** HU-4.1: el veterinario agenda para sí mismo; el administrador elige el veterinario. */
    public function registrarAjax(): void
    {
        RespuestaJson::modificacion(fn (): array => $this->registrar($_POST), 'C5 citas');
    }

    private function solicitudes(array $entrada): SolicitudAgenda
    {
        require_once __DIR__ . '/../models/SolicitudAgenda.php';
        $modelo = new SolicitudAgenda($this->db, $this->reloj);
        return Contexto::rolActivo() === Roles::PROPIETARIO
            ? $modelo->enClinicaDelPropietario($this->clinicaDelPortal($entrada)) : $modelo;
    }

    public function revisarTriageAjax(): void
    {
        RespuestaJson::modificacion(fn (): array => $this->solicitudes($_POST)->revisar((int) ($_POST['id_solicitud'] ?? 0), (string) ($_POST['nivel'] ?? ''), (string) ($_POST['motivo'] ?? ''), (string) ($_POST['huella'] ?? ''), $_POST['clave_operacion'] ?? null), 'C4 revisión');
    }

    public function listarSolicitudesAjax(): void
    {
        RespuestaJson::consulta(function (): void {
            $id = ValidadorClinico::id($_GET['id_solicitud'] ?? null);
            RespuestaJson::enviar(['success' => true] + $this->solicitudes($_GET)->listar($id, ($_GET['historial'] ?? '') === '1'));
        }, 'C4 solicitudes');
    }

    public function reordenarSobrecupoAjax(): void
    {
        RespuestaJson::modificacion(fn (): array => $this->citas->reordenarSobrecupo($this->idDeLaPeticion(), (int) ($_POST['posicion'] ?? 0), (string) ($_POST['motivo'] ?? ''), $_POST['clave_operacion'] ?? null), 'C4 orden naranja');
    }

    private function registrar(array $entrada): array
    {
        require_once __DIR__ . '/../models/SolicitudAgenda.php';
        return (new SolicitudAgenda($this->db, $this->reloj))->reservar($entrada);
    }

    /** El veterinario solo agenda para sí mismo; el administrador elige uno de la clínica. */
    private function veterinarioDeLaReserva($pedido): int
    {
        if (Contexto::rolActivo() === Roles::VETERINARIO) {
            $propio = (int) Contexto::idUsuario();
            if ($pedido !== null && $pedido !== '' && (int) $pedido !== $propio) {
                throw new InvalidArgumentException('No puedes agendar citas para otros veterinarios.');
            }
            return $propio;
        }

        $idVeterinario = ValidadorClinico::id($pedido);
        if ($idVeterinario === null) {
            throw new InvalidArgumentException('Selecciona el veterinario responsable de la cita.');
        }
        // Antes del aviso de «otra cita ese día»: un veterinario ajeno se rechaza primero.
        if (!$this->citas->esVeterinarioDeLaClinica($idVeterinario)) {
            throw new InvalidArgumentException('El veterinario seleccionado no existe o no está activo en la clínica.');
        }
        return $idVeterinario;
    }

    // ── Calendario y catálogos ──────────────────────────────────────────

    /**
     * RE-4.1.5: eventos del calendario de la clínica activa. El veterinario
     * ve sus citas; el administrador, todas. Vacunas y desparasitaciones
     * próximas salen de los modelos de C4 (lo que aplicó esta clínica).
     */
    public function listarSemanaAjax(): void
    {
        $this->revisarAtencionesAbiertas();

        $hoy = $this->ahora();
        $inicio = ValidadorClinico::fecha($_GET['inicio'] ?? null) ?? $hoy->format('Y-m-d');
        $fin = ValidadorClinico::fecha($_GET['fin'] ?? null) ?? $hoy->modify('+7 days')->format('Y-m-d');
        $idVeterinario = Contexto::rolActivo() === Roles::VETERINARIO ? Contexto::idUsuario() : null;

        $eventos = [];
        foreach ($this->citas->listarRango($inicio, $fin, $idVeterinario) as $cita) {
            $eventos[] = $this->eventoDeCita($cita);
        }
        foreach ((new Vacuna($this->db))->pendientesEntre($inicio, $fin) as $vacuna) {
            $eventos[] = $this->eventoDePrevencion($vacuna['id_vacuna'], $vacuna['fecha_proxima_dosis'], 'vacunacion', $vacuna['nombre_vacuna'], $vacuna);
        }
        foreach ((new Desparasitacion($this->db))->pendientesEntre($inicio, $fin) as $desparasitacion) {
            $motivo = $desparasitacion['tipo'] . ' - ' . $desparasitacion['producto'];
            $eventos[] = $this->eventoDePrevencion($desparasitacion['id_desparasitacion'], $desparasitacion['fecha_proxima'], 'desparasitacion', $motivo, $desparasitacion);
        }

        RespuestaJson::enviar($eventos);
    }

    /** Solo lo que el calendario pinta: sin correos ni otros datos del propietario. */
    private function eventoDeCita(array $cita): array
    {
        return [
            'id_cita' => (int) $cita['id_cita'],
            'prioridad' => $cita['prioridad'],
            'inicio_estimado' => $cita['inicio_estimado'],
            'fin_estimado' => $cita['fin_estimado'],
            'estado_reajuste' => $cita['estado_reajuste'],
            'motivo_reajuste' => $cita['motivo_reajuste'],
            'es_sobrecupo' => (bool) $cita['es_sobrecupo'],
            'orden_sobrecupo' => $cita['orden_sobrecupo'],
            'tipo' => 'cita',
            'fecha' => $cita['fecha'],
            'hora' => $cita['hora'],
            'estado' => $cita['estado'],
            'motivo' => $cita['motivo'],
            'id_veterinario' => (int) $cita['id_veterinario'],
            'veterinario_nombre' => $cita['veterinario_nombre'],
            'mascota_nombre' => $cita['mascota_nombre'],
            'propietario_nombre' => $cita['propietario_nombre'],
            'tipo_cita_nombre' => $cita['tipo_cita_nombre'],
        ];
    }

    private function eventoDePrevencion($id, string $fecha, string $tipo, string $motivo, array $fila): array
    {
        return [
            'id_cita' => (int) $id,
            'tipo' => $tipo,
            'fecha' => $fecha,
            'estado' => 'pendiente',
            'motivo' => $motivo,
            'id_mascota' => (int) $fila['id_mascota'],
            'mascota_nombre' => $fila['nombre_mascota'],
            'propietario_nombre' => $fila['propietario'],
            'veterinario_nombre' => '',
        ];
    }

    /** Veterinarios activos de la clínica activa (usuario_clinica). */
    public function listarVeterinariosAjax(): void
    {
        RespuestaJson::enviar($this->citas->veterinariosActivos());
    }

    public function listarTiposCitaAjax(): void
    {
        RespuestaJson::enviar(['success' => true, 'tipos' => $this->citas->getTiposCita()]);
    }

    public function getCitaAjax(): void
    {
        $idCita = ValidadorClinico::id($_GET['id'] ?? null);
        if ($idCita === null) {
            RespuestaJson::error(422, 'ID no proporcionado.');
            return;
        }

        $cita = $this->citaDelContexto($idCita);
        unset($cita['email'], $cita['veterinario_email']);
        RespuestaJson::enviar(['success' => true, 'cita' => $cita]);
    }

    public function getSugerenciasHorarioAjax(): void
    {
        RespuestaJson::consulta(function (): void {
            $horas = $this->horasSolicitadas($this->citas);
            RespuestaJson::enviar(['success' => true, 'sugerencias' => $horas, 'horas' => $horas]);
        }, 'C3 disponibilidad interna');
    }

    private function horasSolicitadas(Cita $citas): array
    {
        $vet = ValidadorClinico::id($_GET['id_veterinario'] ?? null);
        $mascota = ValidadorClinico::id($_GET['id_mascota'] ?? null);
        $tipo = ValidadorClinico::id($_GET['id_tipo_cita'] ?? null);
        $fecha = ValidadorClinico::fecha($_GET['fecha'] ?? null);
        $excluir = ValidadorClinico::id($_GET['id_cita_excluir'] ?? null);
        if ($vet === null || $mascota === null || $fecha === null || ($tipo === null && $excluir === null)) {
            throw new InvalidArgumentException('Selecciona mascota, tipo, veterinario y fecha.');
        }
        if ($excluir === null) {
            require_once __DIR__ . '/../models/SolicitudAgenda.php';
            $solicitudes = new SolicitudAgenda($this->db, $this->reloj);
            if (Contexto::rolActivo() === Roles::PROPIETARIO) {
                $solicitudes = $solicitudes->enClinicaDelPropietario($this->clinicaDelPortal($_GET));
            }
            return $solicitudes->horas((int) ($_GET['id_solicitud'] ?? 0), $_GET);
        }
        return $citas->sugerencias($vet, $mascota, $tipo ?? 0, $fecha, $excluir);
    }

    /** Supervisión del administrador: todas las citas de la clínica activa. */
    public function listarTodasCitasAjax(): void
    {
        $citas = [];
        foreach ($this->citas->listarTodas() as $cita) {
            $citas[] = [
                'id_cita' => (int) $cita['id_cita'],
                'fecha' => $cita['fecha'],
                'hora' => $cita['hora'],
                'motivo' => $cita['motivo'],
                'estado' => $cita['estado'],
                'id_veterinario' => (int) $cita['id_veterinario'],
                'id_tipo_cita' => $cita['id_tipo_cita'],
                'mascota_nombre' => $cita['mascota_nombre'],
                'propietario_nombre' => $cita['propietario_nombre'],
                'veterinario_nombre' => $cita['veterinario_nombre'],
                'tipo_cita_nombre' => $cita['tipo_cita_nombre'],
            ];
        }
        RespuestaJson::enviar(['success' => true, 'citas' => $citas]);
    }

    // ── Portal del propietario (C6: HU-5.3, RE-5.3.5, RE-5.9.3) ─────────
    // El propietario no tiene clínica activa: elige una de las suyas y
    // enClinicaDelPropietario() valida en el modelo que sea de un vínculo
    // activo (no se confía en el parámetro). Fuera de eso, 403 auditado.

    /** Veterinarios de la clínica elegida; sin clínica, los de todas sus clínicas (pantalla Servicios). */
    public function portalVeterinariosAjax(): void
    {
        RespuestaJson::consulta(fn () => $this->portalVeterinarios(), 'C6 portal veterinarios');
    }

    private function portalVeterinarios(): void
    {
        $idClinica = ValidadorClinico::id($_GET['id_clinica'] ?? null);
        if ($idClinica !== null) {
            RespuestaJson::enviar($this->citas->enClinicaDelPropietario($idClinica)->veterinariosActivos());
            return;
        }

        $veterinarios = [];
        foreach ((new VinculosPropietario($this->db))->clinicas() as $clinica) {
            $deLaClinica = $this->citas->enClinicaDelPropietario((int) $clinica['id_clinica'])->veterinariosActivos();
            foreach ($deLaClinica as $veterinario) {
                $veterinarios[] = $veterinario + ['clinica_nombre' => $clinica['nombre']];
            }
        }
        RespuestaJson::enviar($veterinarios);
    }

    public function portalTiposCitaAjax(): void
    {
        RespuestaJson::consulta(function (): void {
            $citas = $this->citas->enClinicaDelPropietario($this->clinicaDelPortal($_GET));
            RespuestaJson::enviar(['success' => true, 'tipos' => $citas->getTiposCita()]);
        }, 'C6 portal tipos');
    }

    /** Horas de atención de la clínica elegida ese día (RE-5.1.10). */
    public function portalHorasAjax(): void
    {
        RespuestaJson::consulta(fn () => $this->portalHoras(), 'C6 portal horas');
    }

    private function portalHoras(): void
    {
        $citas = $this->citas->enClinicaDelPropietario($this->clinicaDelPortal($_GET));
        $horas = $this->horasSolicitadas($citas);
        RespuestaJson::enviar(['success' => true, 'horas' => $horas, 'sugerencias' => $horas]);
    }

    /** RE-5.3.3: ambos accesos usan el mismo cálculo autorizado. */
    public function portalSugerenciasAjax(): void
    {
        RespuestaJson::consulta(fn () => $this->portalHoras(), 'C3 portal disponibilidad');
    }

    /**
     * HU-5.3 y RN-110: agenda en una clínica vinculada; si la mascota aún no
     * está vinculada a esa clínica, se vincula en la misma transacción. Usa
     * las mismas validaciones de la agenda (horario, solapamiento, doble
     * reserva). El límite del plan es de la etapa E; el triage, del módulo 4.
     */
    public function agendarDesdePortalAjax(): void
    {
        RespuestaJson::modificacion(fn (): array => $this->agendarDesdePortal($_POST), 'C6 portal citas');
    }

    private function agendarDesdePortal(array $entrada): array
    {
        require_once __DIR__ . '/../models/SolicitudAgenda.php';
        return (new SolicitudAgenda($this->db, $this->reloj))->enClinicaDelPropietario($this->clinicaDelPortal($entrada))->reservar($entrada);
    }

    /** La clínica que nombra la petición; su vínculo lo valida el modelo. */
    private function clinicaDelPortal(array $entrada): int
    {
        $idClinica = ValidadorClinico::id($entrada['id_clinica'] ?? null);
        if ($idClinica === null) {
            throw new InvalidArgumentException('Elige la clínica.');
        }
        return $idClinica;
    }

    // ── Cambios de una cita ─────────────────────────────────────────────

    /** HU-4.2: el administrador reprograma cualquiera (y reasigna el veterinario); el veterinario, las suyas. */
    public function reprogramarCitaAjax(): void
    {
        RespuestaJson::modificacion(function (): array {
            $cita = $this->citaDelContexto($this->idDeLaPeticion());
            $esAdmin = Contexto::rolActivo() === Roles::ADMIN;
            if (!$esAdmin && Contexto::rolActivo() !== Roles::PROPIETARIO && !$this->esSuCita($cita)) {
                throw new AccesoDenegado(403, 'Solo el administrador o el veterinario de la cita pueden reprogramarla.');
            }
            // RE-4.9.3: solo el comando nuevo valida estado/tiempo dentro de la unidad.
            // El modelo recupera antes el replay tras revalidar acceso y huella actuales.
            [$fecha, $hora] = $this->fechaYHoraValidas($_POST['fecha'] ?? '', $_POST['hora'] ?? '');

            $idVeterinario = (int) $cita['id_veterinario'];
            $pedido = ValidadorClinico::id($_POST['id_veterinario'] ?? null);
            if (($esAdmin || Contexto::rolActivo() === Roles::PROPIETARIO) && $pedido !== null) {
                $idVeterinario = $pedido;
            }

            $modelo = Contexto::rolActivo() === Roles::PROPIETARIO
                ? $this->citas->enClinicaDelPropietario((int) $cita['id_clinica']) : $this->citas;
            $nueva = $modelo->reprogramar($cita, $idVeterinario, $fecha, $hora, $_POST['clave_operacion'] ?? null);
            if (!$nueva) {
                return ['id_cita' => (int) $cita['id_cita'], 'message' => 'Cita reprogramada correctamente. Se notificará al propietario.'];
            }
            // Avisos durables guardados por el modelo en la misma transacción.

            return ['message' => 'Cita reprogramada correctamente. Se notificará al propietario.', 'id_cita' => (int) $cita['id_cita']];
        }, 'C5 citas');
    }

    /**
     * RN-405: cancela una cita pendiente o confirmada. El veterinario, las
     * suyas; el administrador, cualquiera de la clínica; el propietario, las
     * de sus mascotas en clínicas con vínculo activo (RN-G02).
     */
    public function cancelarAjax(): void
    {
        RespuestaJson::modificacion(function (): array {
            $cita = $this->citaDelContexto($this->idDeLaPeticion());
            $modelo = Contexto::rolActivo() === Roles::PROPIETARIO
                ? $this->citas->enClinicaDelPropietario((int) $cita['id_clinica']) : $this->citas;
            $modelo->cancelar($cita, $_POST['clave_operacion'] ?? null);
            return ['message' => 'Cita cancelada correctamente.', 'id_cita' => (int) $cita['id_cita']];
        }, 'C4 cancelación');
    }

    /** Solo se confirma una cita pendiente: el veterinario, las suyas; el administrador, cualquiera. */
    public function confirmarAjax(): void
    {
        RespuestaJson::modificacion(function (): array {
            $cita = $this->citas->getById($this->idDeLaPeticion());
            if (Contexto::rolActivo() === Roles::VETERINARIO && !$this->esSuCita($cita)) {
                throw new AccesoDenegado(403, 'No tienes permiso para confirmar esta cita. Solo puedes confirmar tus propias citas.');
            }
            if ($cita['estado'] !== 'pendiente' || !$this->citas->confirmar($cita)) {
                throw new InvalidArgumentException('Solo se puede confirmar una cita pendiente.');
            }

            return ['message' => 'Cita confirmada y notificada al propietario.', 'id_cita' => (int) $cita['id_cita']];
        }, 'C5 citas');
    }

    // ── Atención (RN-406, RN-407, RN-408) ───────────────────────────────

    public function iniciarAtencionAjax(): void
    {
        RespuestaJson::modificacion(function (): array {
            $cita = $this->citas->getById($this->idDeLaPeticion());
            $this->exigirVeterinarioAsignado($cita, 'Solo el veterinario asignado puede iniciar la atención de esta cita.');
            $urlAtencion = 'index.php?action=vet_atencion&id_cita=' . (int) $cita['id_cita'];

            // Ya iniciada o sin cerrar: se retoma donde quedó (RN-409).
            if (in_array($cita['estado'], Cita::ESTADOS_EN_ATENCION, true)) {
                return ['message' => 'Retomando la atención...', 'estado' => 'en_curso', 'redirect_url' => $urlAtencion];
            }
            $this->exigirAbierta($cita);

            $ahora = $this->ahora();
            $hoy = $ahora->format('Y-m-d');
            if ($cita['fecha'] > $hoy) {
                throw new InvalidArgumentException('Esta cita es del ' . date('d/m/Y', strtotime($cita['fecha'])) . '. La atención solo se puede iniciar el día de la cita.');
            }
            if ($cita['fecha'] < $hoy) {
                throw new InvalidArgumentException('Esta cita ya pasó. Si el paciente no vino, márcala como "No asistió".');
            }
            if (!ReglaAtencion::puedeIniciar($cita['fecha'], $cita['hora'], $ahora)) {
                $desde = ReglaAtencion::iniciaDesde($cita['fecha'], $cita['hora']);
                throw new InvalidArgumentException('La atención de esta cita se puede iniciar desde las ' . $desde->format('g:i A') . '.');
            }

            if (!$this->citas->iniciarAtencion($cita, $ahora->format('Y-m-d H:i:s'))) {
                throw new InvalidArgumentException('No se pudo iniciar la atención. Intenta nuevamente.');
            }
            $this->retirarAvisosCita((int) $cita['id_cita']);

            return ['message' => 'Atención iniciada. Redirigiendo...', 'estado' => 'en_curso', 'redirect_url' => $urlAtencion];
        }, 'C5 citas');
    }

    /**
     * RN-406: lo normal es completar la cita al guardar su consulta. Este
     * endpoint solo cierra una cita cuya consulta ya existe y quedó abierta
     * (datos de antes de v1.9.0).
     */
    public function completarAtencionAjax(): void
    {
        RespuestaJson::modificacion(function (): array {
            $cita = $this->citas->getById($this->idDeLaPeticion());
            $this->exigirVeterinarioAsignado($cita, 'Solo el veterinario asignado puede cerrar esta cita.');

            if ($cita['estado'] === 'completada') {
                return ['message' => 'La cita ya estaba marcada como completada.', 'estado' => 'completada'];
            }
            if (!in_array($cita['estado'], Cita::ESTADOS_EN_ATENCION, true)) {
                throw new InvalidArgumentException('La atención de esta cita no está en curso.');
            }

            require_once __DIR__ . '/../models/Consulta.php';
            $consulta = (new Consulta($this->db))->findByCita((int) $cita['id_cita']);
            if (!$consulta) {
                throw new InvalidArgumentException('Registra la consulta de la cita para completarla.');
            }
            if (!$this->citas->completarAtencion($cita, $this->ahora()->format('Y-m-d H:i:s'))) {
                throw new InvalidArgumentException('No se pudo completar la cita. Intenta nuevamente.');
            }
            $this->retirarAvisosCita((int) $cita['id_cita']);

            return [
                'message' => 'Cita marcada como completada. Consulta #' . $consulta['id_consulta'] . ' vinculada.',
                'estado' => 'completada',
                'id_consulta' => (int) $consulta['id_consulta'],
            ];
        }, 'C5 citas');
    }

    /** RN-408: el veterinario de la cita la marca cuando su hora ya pasó; libera el horario. */
    public function marcarNoAsistioAjax(): void
    {
        RespuestaJson::modificacion(function (): array {
            $cita = $this->citas->getById($this->idDeLaPeticion());
            $this->exigirVeterinarioAsignado($cita, 'Solo el veterinario asignado puede marcar la inasistencia.');
            $this->exigirAbierta($cita);

            $inicio = ReglaAtencion::inicio($cita['fecha'], $cita['hora']);
            if ($inicio > $this->ahora()) {
                throw new InvalidArgumentException('Todavía no es la hora de la cita: la inasistencia se marca cuando esa hora ya pasó.');
            }
            if (!$this->citas->marcarNoAsistio($cita)) {
                throw new InvalidArgumentException('No se pudo marcar la inasistencia. Intenta nuevamente.');
            }
            $this->retirarAvisosCita((int) $cita['id_cita']);

            return ['message' => 'La cita quedó marcada como no asistida.', 'estado' => 'no_asistio'];
        }, 'C5 citas');
    }

    /** Pantalla integral de atención de una cita: solo del veterinario asignado (RN-407). */
    public function atencion(): void
    {
        $idCita = ValidadorClinico::id($_GET['id_cita'] ?? null);
        if ($idCita === null) {
            header('Location: index.php?action=vet_agenda');
            exit;
        }

        $cita = $this->citas->getById($idCita);
        if (!$this->esSuCita($cita)) {
            header('Location: index.php?action=vet_agenda');
            exit;
        }

        require_once __DIR__ . '/../models/Mascota.php';
        require_once __DIR__ . '/../models/Consulta.php';
        require_once __DIR__ . '/../models/Tratamiento.php';
        require_once __DIR__ . '/../helpers/ResumenClinico.php';

        $ahora = $this->ahora();
        // RN-407: la vista solo ofrece «Iniciar atención» desde 15 minutos antes.
        $esDiaDeLaCita = ReglaAtencion::puedeIniciar($cita['fecha'], $cita['hora'], $ahora);

        $mascota = (new Mascota($this->db))->getById($cita['id_mascota']);
        $consultaModel = new Consulta($this->db);
        // C5a (RN-210): el historial de Atención muestra los síntomas marcados.
        $consultas = $consultaModel->conSintomas($consultaModel->findByMascota($cita['id_mascota']));
        $vacunas = (new Vacuna($this->db))->findByMascota($cita['id_mascota']);
        $desparasitaciones = (new Desparasitacion($this->db))->findByMascota($cita['id_mascota']);
        $propietario = [
            'nombre_completo' => $cita['propietario_nombre'],
            'telefono' => $cita['propietario_telefono'],
        ];

        // A.6 riesgo 10: los tipos salen del catálogo de la clínica (C2), sin
        // la columna `estado` que no existe y dejaba el selector vacío.
        $tiposCita = $this->citas->getTiposCita();
        $tipoCitaNombre = (string) ($cita['tipo_cita_nombre'] ?? '');

        // Consulta ya registrada para esta cita: la pantalla queda en modo lectura.
        $consultaCita = $consultaModel->findByCita($idCita) ?: null;
        $tratamientosCita = [];
        if ($consultaCita) {
            $idConsulta = (int) $consultaCita['id_consulta'];
            $tratamientosCita = (new Tratamiento($this->db))->findByConsultas([$idConsulta])[$idConsulta] ?? [];
        }

        $resumenClinico = ResumenClinico::construir($consultas, $vacunas, $desparasitaciones, $ahora);
        $edadMascota = ResumenClinico::edadLegible($mascota['fecha_nacimiento'] ?? null, $ahora);
        $fotoMascota = !empty($mascota['url_foto'])
            ? 'uploads/mascotas/' . rawurlencode($mascota['url_foto'])
            : 'img/default-pet.svg';

        $content_view = __DIR__ . '/../views/vet/atencion.php';
        require_once __DIR__ . '/../views/vet/layout.php';
    }

    // ── Correo al propietario ───────────────────────────────────────────

    /**
     * Envía el correo de una cita (RE-4.1.4, RE-4.2.2). El envío por SMTP
     * tarda: se libera la sesión, se responde de inmediato y el correo sale
     * después de cerrar la respuesta (RE-4.4.3).
     */
    public function enviarEmailAjax(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            RespuestaJson::error(405, 'Método no permitido.');
            return;
        }

        $idCita = ValidadorClinico::id($_POST['id_cita'] ?? null);
        $tipo = (string) ($_POST['tipo'] ?? 'confirmacion');
        // El acceso se comprueba antes de responder: sin permiso, 403.
        $cita = $idCita === null ? null : $this->citaDelContexto($idCita);

        session_write_close();
        $this->responderYSeguir(['success' => true]);

        if ($cita !== null) {
            require_once __DIR__ . '/../helpers/CorreoAgenda.php';
            $q = $this->db->prepare("SELECT id_evento FROM eventos_agenda WHERE id_clinica=? AND id_cita=? AND canal='correo' AND estado IN ('pendiente','error') ORDER BY id_evento");
            $q->execute([(int) $cita['id_clinica'], (int) $cita['id_cita']]);
            foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $evento) {
                (new EventoAgenda($this->db, (int) $cita['id_clinica']))->despachar((int) $evento, [CorreoAgenda::class, 'enviar']);
            }
        }
        exit;
    }

    private function enviarCorreoCita(array $cita, string $tipo): bool
    {
        if (empty($cita['email'])) {
            return false;
        }
        // M5 C3 (RE-T.19.1): un cliente que aún no activa su cuenta solo recibe el correo de activación.
        if ((int) ($cita['propietario_estado'] ?? 1) !== 1) {
            return false;
        }

        $textos = [
            'confirmacion_nueva' => ['¡Cita Confirmada!', 'Confirmación de Cita Veterinaria - Zooki', 'Queremos confirmarte que la cita para tu mascota ha sido programada con éxito.'],
            'confirmacion' => ['Tu cita ha sido confirmada', 'Confirmación de Cita - Zooki', 'Te confirmamos los detalles finales de tu cita programada:'],
            'reprogramacion' => ['Tu cita ha sido reprogramada', 'Actualización de Cita Veterinaria - Zooki', 'Te informamos que tu cita ha sido reprogramada con los siguientes detalles:'],
            'cancelacion' => ['Tu cita ha sido cancelada', 'Cancelación de Cita Veterinaria - Zooki', 'Te informamos que la cita programada ha sido cancelada. Por favor, contáctanos si deseas reprogramarla.'],
        ];
        [$titulo, $asunto, $mensaje] = $textos[$tipo] ?? $textos['cancelacion'];

        $contenido = '
        <p style="font-size:15px;line-height:22px;color:#454545;margin:0 0 16px 0;">' . $mensaje . '</p>
        <div style="background-color:#f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 24px 0;">
            <p style="margin: 0 0 8px 0; font-size: 15px; color: #1d1c1d;"><strong>Mascota:</strong> ' . htmlspecialchars($cita['mascota_nombre']) . '</p>
            <p style="margin: 0 0 8px 0; font-size: 15px; color: #1d1c1d;"><strong>Fecha:</strong> ' . date('d/m/Y', strtotime($cita['fecha'])) . '</p>
            <p style="margin: 0 0 8px 0; font-size: 15px; color: #1d1c1d;"><strong>Hora:</strong> ' . htmlspecialchars(substr($cita['hora'], 0, 5)) . '</p>
            <p style="margin: 0 0 8px 0; font-size: 15px; color: #1d1c1d;"><strong>Veterinario:</strong> Dr(a). ' . htmlspecialchars($cita['veterinario_nombre']) . '</p>
            <p style="margin: 0; font-size: 15px; color: #1d1c1d;"><strong>Clínica:</strong> ' . htmlspecialchars($cita['clinica_nombre']) . '</p>
        </div>';
        if ($tipo !== 'cancelacion') {
            $contenido .= '
            <p style="font-size:15px;line-height:22px;color:#454545;margin:0 0 16px 0;">Te esperamos puntual para brindar el mejor cuidado a tu mascota.</p>';
        }

        require_once __DIR__ . '/../config/EmailService.php';
        $correo = new EmailService();
        $html = $correo->obtenerPlantillaBaseHTML($cita['propietario_nombre'], $titulo, $contenido, 'Ver en mi Portal', $this->urlApp(), MarcaClinica::delRegistro($cita));
        return $correo->enviarCorreoPersonalizado($cita['email'], $cita['propietario_nombre'], $asunto, $html);
    }

    private function urlApp(): string
    {
        $archivo = __DIR__ . '/../.env';
        $env = file_exists($archivo) ? parse_ini_file($archivo) : [];
        $base = rtrim((string) ($env['APP_URL'] ?? 'https://zooki.secarvajal.com'), '/');
        return $base . '/index.php';
    }

    /** Entrega la respuesta JSON y cierra la conexión, pero deja que el script siga. */
    private function responderYSeguir(array $respuesta): void
    {
        ignore_user_abort(true);
        set_time_limit(60);

        $cuerpo = json_encode($respuesta);
        header('Content-Type: application/json');
        header('Content-Length: ' . strlen($cuerpo));
        header('Connection: close');
        echo $cuerpo;

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
            return;
        }
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();
    }

    // ── Avisos internos de citas ────────────────────────────────────────
    // Cada aviso queda en la clínica de la cita, enlazado a ella, y vence a
    // la hora de la cita. No se avisa a quien hizo el cambio. Un fallo al
    // avisar se registra pero no tumba la operación, que ya se guardó.

    private function avisarCita(string $tipo, string $titulo, array $cita, bool $aAdministradores): void
    {
        try {
            $vigencia = ReglaAtencion::inicio($cita['fecha'], $cita['hora'])->format('Y-m-d H:i:s');
            if ($vigencia <= $this->ahora()->format('Y-m-d H:i:s')) {
                return;
            }

            $notificaciones = new NotificacionInterna($this->db);
            $idClinica = (int) $cita['id_clinica'];
            $idCita = (int) $cita['id_cita'];
            $mensaje = $this->describirCita($cita);

            if ((int) $cita['id_veterinario'] !== Contexto::idUsuario()) {
                $notificaciones->crearParaUsuario($idClinica, (int) $cita['id_veterinario'], $tipo, $titulo, $mensaje, 'index.php?action=vet_agenda', $idCita, $vigencia);
            }
            if ($aAdministradores && Contexto::rolActivo() !== Roles::ADMIN) {
                $mensajeAdministrador = 'Dr(a). ' . $cita['veterinario_nombre'] . ' · ' . $mensaje;
                $notificaciones->crearParaRol($idClinica, Roles::ADMIN, $tipo, $titulo, $mensajeAdministrador, 'index.php?action=admin_citas', $idCita, $vigencia);
            }
        } catch (Throwable $e) {
            error_log('No se pudo crear la notificación de la cita ' . ($cita['id_cita'] ?? '?') . ': ' . $e->getMessage());
        }
    }

    /** La cita se canceló, se reprogramó o ya se atiende: sus avisos anteriores vencen. */
    private function retirarAvisosCita(int $idCita): void
    {
        try {
            (new NotificacionInterna($this->db))->expirarDeCita($idCita);
        } catch (Throwable $e) {
            error_log('No se pudieron retirar las notificaciones de la cita ' . $idCita . ': ' . $e->getMessage());
        }
    }

    /** "Max · 21/07/2026 a las 08:30 — Vacunación" */
    private function describirCita(array $cita): string
    {
        $texto = date('d/m/Y', strtotime($cita['fecha'])) . ' a las ' . date('H:i', strtotime($cita['hora']));
        if (!empty($cita['mascota_nombre'])) {
            $texto = $cita['mascota_nombre'] . ' · ' . $texto;
        }
        if (!empty($cita['motivo'])) {
            $texto .= ' — ' . $cita['motivo'];
        }
        return $texto;
    }

    // ── Apoyo ───────────────────────────────────────────────────────────

    /**
     * RN-409: antes de mostrar la agenda avisa de las atenciones abiertas y
     * pasa a "sin cerrar" las de días anteriores, aunque la tarea
     * programada no esté activa. Un fallo aquí no impide ver la agenda.
     */
    private function revisarAtencionesAbiertas(): void
    {
        try {
            require_once __DIR__ . '/../helpers/VigilanteAtenciones.php';
            (new VigilanteAtenciones($this->db))->revisar($this->ahora());
        } catch (Throwable $e) {
            error_log('No se pudieron revisar las atenciones abiertas: ' . $e->getMessage());
        }
    }

    /** Cita según el contexto: la de la clínica activa o, en el portal, la de una mascota propia. */
    private function citaDelContexto(int $idCita): array
    {
        $contexto = Contexto::actual();
        if ($contexto !== null && $contexto['tipo'] === Contexto::PROPIETARIO) {
            return $this->citas->paraPropietario($idCita, (int) Contexto::idUsuario());
        }
        return $this->citas->getById($idCita);
    }

    private function idDeLaPeticion(): int
    {
        $idCita = ValidadorClinico::id($_POST['id_cita'] ?? null);
        if ($idCita === null) {
            throw new InvalidArgumentException('Identificador de cita no proporcionado.');
        }
        return $idCita;
    }

    private function esSuCita(array $cita): bool
    {
        return Contexto::rolActivo() === Roles::VETERINARIO && (int) $cita['id_veterinario'] === Contexto::idUsuario();
    }

    /** RN-407: iniciar, completar y marcar "no asistió" son del veterinario asignado. */
    private function exigirVeterinarioAsignado(array $cita, string $mensaje): void
    {
        if (!$this->esSuCita($cita)) {
            throw new AccesoDenegado(403, $mensaje);
        }
    }

    private function exigirAbierta(array $cita): void
    {
        if (!in_array($cita['estado'], Cita::ESTADOS_ABIERTOS, true)) {
            throw new InvalidArgumentException($this->mensajeCitaCerrada($cita['estado']));
        }
    }

    /** Fecha y hora válidas que no hayan pasado (hora de la clínica). */
    private function fechaYHoraFuturas($fecha, $hora, bool $repetida = false): array
    {
        [$fecha, $hora] = $this->fechaYHoraValidas($fecha, $hora);
        $ahora = $this->ahora();
        if (!$repetida && $fecha < $ahora->format('Y-m-d')) {
            throw new InvalidArgumentException('No puedes agendar citas en fechas que ya pasaron.');
        }
        if (!$repetida && ReglaAtencion::inicio($fecha, $hora) < $ahora) {
            throw new InvalidArgumentException('No puedes agendar citas en horas que ya pasaron. Son las ' . $ahora->format('H:i') . '.');
        }
        return [$fecha, $hora];
    }

    private function fechaYHoraValidas($fecha, $hora): array
    {
        $fecha = (string) $fecha;
        $hora = (string) $hora;
        if ($fecha === '' || $hora === '') {
            throw new InvalidArgumentException('Selecciona la fecha y uno de los horarios disponibles.');
        }
        if (ValidadorClinico::fecha($fecha) === null) {
            throw new InvalidArgumentException('La fecha de la cita no es válida.');
        }
        $hora = Cita::normalizarHora($hora);

        return [$fecha, $hora];
    }

    private function mensajeCitaCerrada(string $estado): string
    {
        $mensajes = [
            'en_curso' => 'La atención de esta cita ya está en curso.',
            'completada' => 'Esta cita ya fue atendida.',
            'cancelada' => 'Esta cita fue cancelada.',
            'no_asistio' => 'Esta cita quedó marcada como no asistida.',
            'sin_cerrar' => 'La atención de esta cita quedó sin cerrar.',
        ];
        return $mensajes[$estado] ?? 'La cita no está en un estado válido para esta acción.';
    }

    private function ahora(): DateTimeImmutable
    {
        return ($this->reloj)();
    }
}
