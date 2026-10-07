<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Mascota.php';
require_once __DIR__ . '/../models/Cita.php';
require_once __DIR__ . '/../models/Vacuna.php';
require_once __DIR__ . '/../models/Consulta.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/Desparasitacion.php';
require_once __DIR__ . '/../models/Auditoria.php';
require_once __DIR__ . '/../config/EmailService.php';
require_once __DIR__ . '/../helpers/PoliticaPassword.php';
require_once __DIR__ . '/../models/PropietarioClinica.php';
require_once __DIR__ . '/../helpers/EnlaceCuenta.php';
require_once __DIR__ . '/../helpers/Csrf.php';

class PropietarioController {
    private $db;
    private $mascotaModel;
    private $citaModel;
    private $vacunaModel;
    private $consultaModel;
    private $desparasitacionModel;
    private $usuarioModel;
    private $auditoria;

    public function __construct(?PDO $db = null, private $enviarCorreo = null) {
        $this->db = $db ?? (new Database())->getConnection();
        $this->mascotaModel = new Mascota($this->db);
        $this->citaModel = new Cita($this->db);
        $this->vacunaModel = new Vacuna($this->db);
        $this->consultaModel = new Consulta($this->db);
        $this->desparasitacionModel = new Desparasitacion($this->db);
        $this->usuarioModel = new Usuario($this->db);
        $this->auditoria = new Auditoria($this->db);
    }

    /**
     * Valida los datos de alta de un propietario (HU-1.2).
     *
     * M1-03 — Antes no se comprobaba nada: los campos se leian directo de
     * $_POST y el documento o el correo duplicados llegaban hasta la base de
     * datos, donde la restriccion de unicidad lanzaba una PDOException sin
     * capturar. El resultado era una respuesta vacia que rompia el `.json()`
     * del navegador, sin decirle al usuario que el propietario ya existia.
     *
     * Devuelve el mensaje de error, o null si todo esta correcto.
     */
    private function validarDatosPropietario(array $entrada, ?array &$limpios): ?string {
        $limpios = [];

        $requeridos = [
            'documento'       => 'El documento es obligatorio.',
            'tipo_documento'  => 'El tipo de documento es obligatorio.',
            'nombre_completo' => 'El nombre completo es obligatorio.',
            'telefono'        => 'El telefono es obligatorio.',
            'email'           => 'El correo electronico es obligatorio.',
        ];

        foreach ($requeridos as $campo => $mensaje) {
            $valor = trim((string) ($entrada[$campo] ?? ''));
            if ($valor === '') return $mensaje;
            $limpios[$campo] = $valor;
        }

        if (!preg_match('/^\d{5,15}$/', $limpios['documento'])) {
            return 'El documento debe tener entre 5 y 15 digitos.';
        }
        if (!filter_var($limpios['email'], FILTER_VALIDATE_EMAIL)) {
            return 'El correo electronico no tiene un formato valido.';
        }
        if (!preg_match('/^[0-9+\s-]{7,20}$/', $limpios['telefono'])) {
            return 'El telefono no tiene un formato valido.';
        }
        if (mb_strlen($limpios['nombre_completo']) < 3 || mb_strlen($limpios['nombre_completo']) > 100) {
            return 'El nombre completo debe tener entre 3 y 100 caracteres.';
        }
        if (!in_array($limpios['tipo_documento'], ['CC', 'CE', 'TI', 'PP', 'NIT'], true)) {
            return 'El tipo de documento no es valido.';
        }

        // RE-1.2.2 / RN-G06: ni documento ni correo duplicados.
        if ($this->usuarioModel->buscarPorDocumento($limpios['documento'])) {
            return 'Ese documento ya esta registrado en el sistema.';
        }
        if ($this->usuarioModel->buscarPorEmail($limpios['email'])) {
            return 'Ese correo electronico ya esta registrado en el sistema.';
        }

        return null;
    }

    public function registrarAjax(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); return; }
        try {
            $error=$this->validarDatosPropietario($_POST,$datos);
            if ($error !== null) throw new InvalidArgumentException($error);
            // RE-T.19.1: el titular acepta en esta pantalla presencial; no el empleado.
            $datos['acepta_politica']=(string)($_POST['acepta_politica'] ?? '');
            $datos['titular_presente']=(string)($_POST['titular_presente'] ?? '');
            EnlaceCuenta::base(); // Validar destino antes de crear cuenta o tokens.
            $alta=(new PropietarioClinica($this->db))->registrar($datos,self::versionPolitica(),Auditoria::ipCliente());
            $enlace=EnlaceCuenta::crear('reset_password',$alta['id_enlace'],$alta['token']);
            $enviado=$this->correo($datos['email'],$datos['nombre_completo'],'Crea tu contraseña en Zooki',
                'Tu cuenta fue registrada con tu aceptación presencial. Crea tu contraseña desde este enlace; vence en 24 horas.',$enlace);
            echo json_encode(['success'=>true,'id_usuario'=>$alta['id_usuario'],'message'=>$enviado
                ? 'Propietario registrado. Se envió el enlace para crear su contraseña.'
                : 'Propietario registrado. No se pudo enviar el enlace; el titular puede solicitarlo desde Recuperar contraseña.']);
        } catch (AccesoDenegado $e) { throw $e; }
        catch (InvalidArgumentException $e) { http_response_code(422); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        catch (Throwable $e) { error_log('Alta de propietario: ' . $e->getMessage()); http_response_code(500); echo json_encode(['success'=>false,'message'=>'No se pudo registrar al propietario.']); }
    }

    /** La versión identifica el contenido mostrado; la gestión de versiones llega en D. */
    public static function versionPolitica(): string
    {
        return substr(hash_file('sha256',__DIR__ . '/../views/legal/privacidad.php'),0,20);
    }

    private function correo(string $email,string $nombre,string $asunto,string $mensaje,string $enlace): bool
    {
        $html='<p>' . htmlspecialchars($mensaje,ENT_QUOTES,'UTF-8') . '</p><p><a href="' .
            htmlspecialchars($enlace,ENT_QUOTES,'UTF-8') . '">Continuar</a></p>';
        if ($this->enviarCorreo !== null) return (bool)($this->enviarCorreo)($email,$nombre,$asunto,$html);
        return (new EmailService())->enviarCorreoPersonalizado($email,$nombre,$asunto,$html);
    }

    public function solicitarVinculoAjax(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); return; }
        try {
            EnlaceCuenta::base();
            $solicitud=(new PropietarioClinica($this->db))->solicitarVinculo((string)($_POST['identificador'] ?? ''));
            $enlace=EnlaceCuenta::crear('confirmar_vinculo_propietario',$solicitud['id_enlace'],$solicitud['token']);
            $enviado=$this->correo($solicitud['email'],$solicitud['nombre'],'Confirma tu vínculo con ' . $solicitud['clinica'],
                'El personal de ' . $solicitud['clinica'] . ' solicita vincular tu cuenta. Confirma solo si elegiste atenderte allí. El enlace vence en 24 horas.',$enlace);
            // Nunca devolver el token ni el enlace al personal que hizo la solicitud.
            echo json_encode(['success'=>$enviado,'message'=>$enviado
                ? 'Se envió la solicitud al propietario. Vuelve a buscarlo cuando confirme.'
                : 'No se pudo enviar el correo. No se creó el vínculo; puedes reintentar.']);
        } catch (AccesoDenegado $e) { throw $e; }
        catch (InvalidArgumentException $e) { http_response_code(422); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        catch (Throwable $e) { error_log('Solicitud de vínculo: ' . $e->getMessage()); http_response_code(500); echo json_encode(['success'=>false,'message'=>'No se pudo solicitar la vinculación.']); }
    }

    public function confirmarVinculo(): void
    {
        $id=(int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $token=(string)($_POST['token'] ?? $_GET['token'] ?? '');
        $resultado=null;
        // GET no consume el enlace: los filtros de correo suelen abrirlo automáticamente.
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $resultado=(new PropietarioClinica($this->db))->confirmarVinculo($id,$token);
        }
        require __DIR__ . '/../views/auth/confirmar_vinculo.php';
    }

    public function index() {
        if (!isset($_SESSION['usuario_id_rol']) || $_SESSION['usuario_id_rol'] != 4) {
            header("Location: index.php?action=login");
            exit();
        }

        $doc_propietario = $_SESSION['usuario_doc'];
        $mascotas = $this->mascotaModel->getByPropietario($doc_propietario);

        foreach ($mascotas as &$m) {
            $m['proxima_cita'] = $this->citaModel->getProximaByMascota($m['id_mascota']);
        }
        unset($m);

        // Obtener todas las citas, vacunas y desparasitaciones de todas las mascotas
        $todas_citas = [];
        $todas_vacunas = [];
        $todas_desparasitaciones = [];
        foreach ($mascotas as $m) {
            $citasMascota = $this->citaModel->getByMascota($m['id_mascota']);
            if (is_array($citasMascota)) {
                foreach ($citasMascota as $c) {
                    $c['nombre_mascota'] = $m['nombre'];
                    $c['foto_mascota'] = $m['url_foto'] ? 'uploads/mascotas/' . htmlspecialchars($m['url_foto']) : null;
                    $todas_citas[] = $c;
                }
            }

            $vacunasMascota = $this->vacunaModel->findByMascota($m['id_mascota']);
            if (is_array($vacunasMascota)) {
                foreach ($vacunasMascota as $v) {
                    $v['nombre_mascota'] = $m['nombre'];
                    $v['foto_mascota'] = $m['url_foto'] ? 'uploads/mascotas/' . htmlspecialchars($m['url_foto']) : null;
                    $todas_vacunas[] = $v;
                }
            }

            $desparasitacionesMascota = $this->desparasitacionModel->findByMascota($m['id_mascota']);
            if (is_array($desparasitacionesMascota)) {
                foreach ($desparasitacionesMascota as $d) {
                    $d['nombre_mascota'] = $m['nombre'];
                    $d['foto_mascota'] = $m['url_foto'] ? 'uploads/mascotas/' . htmlspecialchars($m['url_foto']) : null;
                    $todas_desparasitaciones[] = $d;
                }
            }
        }

        // Ordenar todas las citas por fecha/hora descendente
        usort($todas_citas, function($a, $b) {
            return strtotime($b['fecha'] . ' ' . $b['hora']) - strtotime($a['fecha'] . ' ' . $a['hora']);
        });

        // Ordenar todas las vacunas por fecha descendente
        usort($todas_vacunas, function($a, $b) {
            return strtotime($b['fecha_aplicacion']) - strtotime($a['fecha_aplicacion']);
        });

        // Ordenar todas las desparasitaciones por fecha descendente
        usort($todas_desparasitaciones, function($a, $b) {
            return strtotime($b['fecha_aplicacion']) - strtotime($a['fecha_aplicacion']);
        });

        $nombre = $_SESSION['usuario_nombre'] ?? 'Propietario';
        $primer_nombre = explode(' ', trim($nombre))[0];
        
        // Obtener más detalles del usuario (correo, teléfono)
        $usuarioData = $this->usuarioModel->getUserByDocumento($doc_propietario);

        // Horario real de la clínica (HU-43) en lugar del banner de «24 horas».
        require_once __DIR__ . '/../helpers/HorarioAtencion.php';
        try {
            $filas = $this->db->query("SELECT * FROM horarios_clinica ORDER BY dia_semana ASC")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('Portal: no se pudo leer el horario de la clínica: ' . $e->getMessage());
            $filas = [];
        }
        $horario_semana = HorarioAtencion::semana($filas);
        $horario_ahora = HorarioAtencion::ahora($horario_semana, new DateTimeImmutable('now'));

        // Especies del formulario de mascota, en la página: sin llamadas extra al abrirlo.
        $catalogo_especies = $this->mascotaModel->getEspecies();

        $view = '../views/portal/index.php';
        require_once __DIR__ . '/../views/portal/layout.php';
    }

    public function verDetalleMascotaAjax() {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['usuario_id_rol']) || $_SESSION['usuario_id_rol'] != 4) {
            echo json_encode(['success' => false, 'message' => 'No autorizado']);
            exit();
        }

        $id_mascota = $_GET['id_mascota'];
        $doc_propietario = $_SESSION['usuario_doc'];

        // SEGURIDAD: Verificar que la mascota pertenece al propietario
        $mascota = $this->mascotaModel->getById($id_mascota);
        if (!$mascota || $mascota['doc_propietario'] !== $doc_propietario) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acceso denegado. Esta mascota no le pertenece.']);
            exit();
        }

        // Obtener historial, citas y vacunas
        $historial = $this->consultaModel->findByMascota($id_mascota);
        $citas = $this->citaModel->getByMascota($id_mascota);
        $vacunas = $this->vacunaModel->findByMascota($id_mascota);
        $desparasitaciones = $this->desparasitacionModel->findByMascota($id_mascota);

        // Adjuntar archivos a cada consulta en el historial
        foreach ($historial as &$h) {
            $h['archivos'] = $this->consultaModel->getArchivosByConsulta($h['id_consulta']);
        }
        unset($h);

        $mascota['especie'] = $mascota['nombre_especie'] ?? '';
        $mascota['raza'] = $mascota['nombre_raza'] ?? '';
        // HU-15: la raza que escribió el propietario se muestra hasta que la clínica la confirme.
        if (!empty($mascota['raza_indicada'])) {
            $mascota['raza'] = $mascota['raza_indicada'] . ' (por confirmar)';
        }

        echo json_encode([
            'success' => true,
            'mascota' => $mascota,
            'historial' => $historial,
            'citas' => $citas,
            'vacunas' => $vacunas,
            'desparasitaciones' => $desparasitaciones
        ]);
        exit();
    }

    /**
     * Valida lo que manda el formulario de mascota del portal (RE-15.9):
     * formato (ValidadorMascota) y que especie y raza existan. El color no
     * lo pide el portal: lo registra la clínica en la consulta.
     * El propietario ya no crea razas: si la suya no está, elige la criolla
     * o «Sin raza definida» y la clínica la ajusta en la consulta.
     *
     * @return array{datos: array<string, mixed>, error: ?string}
     */
    private function validarMascotaPortal(): array {
        require_once __DIR__ . '/../helpers/ValidadorMascota.php';
        $hoy = new DateTimeImmutable('today', new DateTimeZone('America/Bogota'));
        $resultado = ValidadorMascota::validar($_POST, $hoy);
        if ($resultado['error']) {
            return $resultado;
        }
        $datos = $resultado['datos'];

        if (!$this->mascotaModel->especieExiste($datos['especie'])) {
            return ['datos' => [], 'error' => 'Elige la especie de la lista.'];
        }
        if ($datos['raza'] === null) {
            // «Mi raza no está en la lista»: queda como «Sin raza definida» más lo que escribió.
            $datos['raza'] = $this->mascotaModel->idSinRazaDefinida($datos['especie']);
            if ($datos['raza'] === null) {
                return ['datos' => [], 'error' => 'Esta especie todavía no permite indicar otra raza. Elige la más parecida y avísale a la clínica en la consulta.'];
            }
        } elseif (!$this->mascotaModel->razaEsDeEspecie($datos['raza'], $datos['especie'])) {
            return ['datos' => [], 'error' => 'Elige una raza de la lista para esa especie.'];
        }
        return ['datos' => $datos, 'error' => null];
    }

    private function responder(bool $ok, string $mensaje = ''): void {
        echo json_encode($mensaje === '' ? ['success' => $ok] : ['success' => $ok, 'message' => $mensaje]);
        exit();
    }

    public function registrarMascotaAjax() {
        header('Content-Type: application/json');

        if (!isset($_SESSION['usuario_id_rol']) || $_SESSION['usuario_id_rol'] != 4) {
            $this->responder(false, 'No autorizado');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->responder(false, 'Método no permitido');
        }

        ['datos' => $datos, 'error' => $error] = $this->validarMascotaPortal();
        if ($error) {
            $this->responder(false, $error);
        }

        require_once __DIR__ . '/../helpers/FotoMascota.php';
        $foto = FotoMascota::guardar($_FILES['foto'] ?? null, $datos['nombre'], $errorFoto);
        if ($foto === false) {
            $this->responder(false, $errorFoto);
        }

        $newId = $this->mascotaModel->insert([
            'numero_historia_clinica' => '',
            'doc_propietario' => $_SESSION['usuario_doc'],
            'nombre' => $datos['nombre'],
            'id_especie' => $datos['especie'],
            'id_raza' => $datos['raza'],
            'fecha_nacimiento' => $datos['fecha_nacimiento'],
            'peso' => $datos['peso'],
            'sexo' => $datos['sexo'],
            'color' => '',
            'url_foto' => $foto
        ]);

        if (!$newId) {
            // La foto ya se había guardado: no dejarla huérfana.
            if ($foto) {
                FotoMascota::eliminarAnterior($foto, '');
            }
            $this->responder(false, 'Error al guardar en base de datos.');
        }

        $this->mascotaModel->guardarRazaIndicada((int) $newId, $datos['raza_indicada']);
        $this->responder(true);
    }

    public function actualizarMascotaAjax() {
        header('Content-Type: application/json');

        if (!isset($_SESSION['usuario_id_rol']) || $_SESSION['usuario_id_rol'] != 4) {
            $this->responder(false, 'No autorizado');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->responder(false, 'Método no permitido');
        }

        $id = (string) ($_POST['id_mascota'] ?? '');
        $doc_propietario = $_SESSION['usuario_doc'];

        $oldData = ctype_digit($id) ? $this->mascotaModel->getById((int) $id) : null;
        if (!$oldData || $oldData['doc_propietario'] !== $doc_propietario) {
            $this->responder(false, 'Acceso denegado. Esta mascota no le pertenece.');
        }

        ['datos' => $datos, 'error' => $error] = $this->validarMascotaPortal();
        if ($error) {
            $this->responder(false, $error);
        }

        $newData = [
            'id_mascota' => (int) $id,
            'nombre' => $datos['nombre'],
            'id_especie' => $datos['especie'],
            'id_raza' => $datos['raza'],
            // Sin fecha en el formulario se conserva la que tenía.
            'fecha_nacimiento' => $datos['fecha_nacimiento'] ?? ($oldData['fecha_nacimiento'] ?? null),
            'peso' => $datos['peso'],
            'sexo' => $datos['sexo'],
            'color' => $oldData['color'] ?? '',
            'estado' => $oldData['estado'] ?? 1, // Mantener el estado actual
            'url_foto' => $oldData['url_foto'] ?? null
        ];

        require_once __DIR__ . '/../helpers/FotoMascota.php';
        $foto = FotoMascota::guardar($_FILES['foto'] ?? null, $datos['nombre'], $errorFoto);
        if ($foto === false) {
            $this->responder(false, $errorFoto);
        }
        if ($foto) {
            $newData['url_foto'] = $foto;
        }

        if (!$this->mascotaModel->update($newData)) {
            if ($foto) {
                FotoMascota::eliminarAnterior($foto, '');
            }
            $this->responder(false, 'Error al actualizar la mascota.');
        }

        // La foto anterior solo se borra cuando la nueva ya quedó guardada.
        if ($foto) {
            FotoMascota::eliminarAnterior($oldData['url_foto'] ?? null, $foto);
        }
        $this->mascotaModel->guardarRazaIndicada((int) $id, $datos['raza_indicada']);

        // Los colores no se tocan: los registra la clínica. Antes, guardar
        // desde el portal sin marcar ninguno borraba los que tenía.

        // Auditoría de cambios
        $campos = ['nombre', 'id_especie', 'id_raza', 'peso', 'sexo'];
        foreach ($campos as $c) {
            if (isset($oldData[$c]) && isset($newData[$c]) && $oldData[$c] != $newData[$c]) {
                $this->mascotaModel->registrarAuditoria((int) $id, $doc_propietario, $c, $oldData[$c], $newData[$c]);
            }
        }

        $this->responder(true);
    }

    public function getDetalleCitaClinicaAjax() {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['usuario_id_rol']) || $_SESSION['usuario_id_rol'] != 4) {
            echo json_encode(['success' => false, 'message' => 'No autorizado']);
            exit();
        }

        $id_cita = $_GET['id_cita'] ?? null;
        if (!$id_cita) {
            echo json_encode(['success' => false, 'message' => 'ID de cita requerido']);
            exit();
        }

        $cita = $this->citaModel->getById($id_cita);
        if (!$cita) {
            echo json_encode(['success' => false, 'message' => 'Cita no encontrada']);
            exit();
        }

        $mascota = $this->mascotaModel->getById($cita['id_mascota']);
        if (!$mascota || $mascota['doc_propietario'] !== $_SESSION['usuario_doc']) {
            echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
            exit();
        }

        $tipoInfo = null;
        if (!empty($cita['id_tipo_cita'])) {
            $tipoInfo = $this->citaModel->getTipoCitaById($cita['id_tipo_cita']);
        }
        $nombreTipo = $tipoInfo ? ($tipoInfo['nombre_tipo'] ?? $tipoInfo['nombre'] ?? 'Consulta') : 'Consulta';

        $response = [
            'success' => true,
            'cita' => [
                'id_cita' => $cita['id_cita'],
                'fecha' => $cita['fecha'],
                'hora' => $cita['hora'],
                'estado' => $cita['estado'],
                'nombre_mascota' => $mascota['nombre'],
                'foto_mascota' => $mascota['url_foto'] ? 'uploads/mascotas/' . $mascota['url_foto'] : null,
                'nombre_tipo' => $nombreTipo,
                'veterinario' => $cita['veterinario_nombre'] ?? 'Veterinario'
            ],
            'consulta' => null,
            'tratamientos' => []
        ];

        if ($cita['estado'] === 'completada') {
            $consulta = $this->consultaModel->findByCita($id_cita);
            if ($consulta) {
                $response['consulta'] = [
                    'motivo_consulta' => $consulta['motivo_consulta'],
                    'anamnesis' => $consulta['anamnesis'],
                    'peso' => $consulta['peso'],
                    'temperatura' => $consulta['temperatura'],
                    'frecuencia_cardiaca' => $consulta['frecuencia_cardiaca'],
                    'diagnostico' => $consulta['diagnostico'],
                    'plan_tratamiento' => $consulta['plan_tratamiento'],
                    'fecha_hora' => $consulta['fecha_hora']
                ];

                require_once __DIR__ . '/../models/Tratamiento.php';
                $tratamientoModel = new Tratamiento($this->db);
                $response['tratamientos'] = $tratamientoModel->findByConsulta($consulta['id_consulta']);
            }
        }

        echo json_encode($response);
        exit();
    }

    public function imprimirHistorial() {
        if (!isset($_SESSION['usuario_id_rol']) || $_SESSION['usuario_id_rol'] != 4) {
            header("Location: index.php?action=login");
            exit();
        }

        $id_mascota = $_GET['id_mascota'] ?? null;
        if (!$id_mascota) {
            echo "Mascota no especificada.";
            exit();
        }

        $mascota = $this->mascotaModel->getById($id_mascota);
        if (!$mascota || $mascota['doc_propietario'] !== $_SESSION['usuario_doc']) {
            echo "Acceso denegado o mascota no encontrada.";
            exit();
        }

        $vacunas = $this->vacunaModel->findByMascota($id_mascota) ?: [];
        $desparasitaciones = $this->desparasitacionModel->findByMascota($id_mascota) ?: [];
        $consultas = $this->consultaModel->findByMascota($id_mascota) ?: [];

        require_once __DIR__ . '/../views/portal/imprimir_historial.php';
    }

    public function actualizarDatosContactoAjax() {
        header('Content-Type: application/json');

        if (!isset($_SESSION['usuario_id_rol']) || $_SESSION['usuario_id_rol'] != 4) {
            echo json_encode(['success' => false, 'message' => 'No autorizado']);
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            exit();
        }

        $email = trim($_POST['email'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $documento = $_SESSION['usuario_doc'];

        if (empty($email) || empty($telefono)) {
            echo json_encode(['success' => false, 'message' => 'El correo y el teléfono son requeridos.']);
            exit();
        }

        if (mb_strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'El correo electrónico no es válido.']);
            exit();
        }

        // La misma regla del registro de propietarios (validarDatosPropietario).
        $telefono = preg_replace('/\s+/', ' ', $telefono);
        if (!preg_match('/^[0-9+\s-]{7,20}$/', $telefono)) {
            echo json_encode(['success' => false, 'message' => 'El teléfono solo puede tener números, espacios, + y guiones (de 7 a 20 caracteres).']);
            exit();
        }

        $existente = $this->usuarioModel->getUserByEmailExcluding($email, $documento);
        if ($existente) {
            echo json_encode(['success' => false, 'message' => 'El correo electrónico ya está registrado por otro usuario.']);
            exit();
        }

        if ($this->usuarioModel->updateContactInfo($documento, $email, $telefono)) {
            echo json_encode([
                'success' => true,
                'message' => 'Datos de contacto actualizados correctamente.',
                'email' => $email,
                'telefono' => $telefono
            ]);
            exit();
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al guardar los datos de contacto.']);
            exit();
        }
    }
}
?>
