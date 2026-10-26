<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/EmailService.php';
require_once __DIR__ . '/../models/Plataforma.php';
require_once __DIR__ . '/../helpers/Csrf.php';
require_once __DIR__ . '/../helpers/PresentacionPlataforma.php';
require_once __DIR__ . '/../models/ConciliadorPago.php';
require_once __DIR__ . '/../helpers/ClienteWompiHttp.php';

final class PlataformaController
{
    private Plataforma $modelo;

    public function __construct(?PDO $db = null, private mixed $correo = null, private ?ConciliadorPago $conciliador = null)
    {
        $db ??= (new Database())->getConnection();
        $this->modelo = new Plataforma($db);
        $this->db = $db;
    }

    private PDO $db;

    public function inicio(): void
    {
        $buscar = (string) ($_GET['buscar'] ?? '');
        $estado = (string) ($_GET['estado'] ?? '');
        $plan = (string) ($_GET['plan'] ?? '');
        $error = null;
        // M0.2: la lista llega filtrada solo sin JavaScript (con él, plataforma.js filtra al escribir).
        $clinicas = $this->modelo->clinicas();
        if ($buscar !== '' || $estado !== '' || $plan !== '') {
            try {
                $clinicas = $this->modelo->clinicas($buscar, $estado, $plan);
            } catch (InvalidArgumentException $e) {
                http_response_code(422);
                $error = $e->getMessage();
                $clinicas = [];
            }
        }
        $planes = $this->modelo->planes();
        $casos = $this->modelo->casos();
        $ips = $this->modelo->ips();
        $contadores = PresentacionPlataforma::contadores($casos, $ips);
        require __DIR__ . '/../views/plataforma/inicio.php';
    }

    public function modificar(string $accion): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !Csrf::validate()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'La operación requiere POST y un token de seguridad válido.']);
            return;
        }
        try {
            $id = (int) ($_POST['id_clinica'] ?? 0);
            $mensaje = 'Cambio guardado.';
            switch ($accion) {
                case 'plataforma_conciliar_ajax':
                    if (!is_string($_POST['id_transaccion'] ?? null) || !is_string($_POST['motivo'] ?? null)) {
                        throw new InvalidArgumentException('Completa el ID de transacción Wompi y el motivo.');
                    }
                    $configuracion = new ConfiguracionWompi();
                    $conciliador = $this->conciliador ?? new ConciliadorPago($this->db, new ClienteWompiHttp($configuracion), $configuracion);
                    $resuelto = $this->modelo->conciliarPago($_POST['id_transaccion'], $_POST['motivo'], $conciliador);
                    $mensaje = $resuelto ? 'Consulta completada. Revisa el estado del pago y el periodo concedido.' : 'Sin nueva acreditación. El pago puede seguir pendiente o requerir revisión; consulta el historial.';
                    break;
                case 'plataforma_incidencia_ajax':
                    $evento = filter_var($_POST['id_evento'] ?? '', FILTER_VALIDATE_INT);
                    $clinica = filter_var($_POST['id_clinica'] ?? '', FILTER_VALIDATE_INT);
                    if ($evento === false || $clinica === false || !is_string($_POST['motivo'] ?? null)) {
                        throw new InvalidArgumentException('Revisa la incidencia, la clínica y la nota.');
                    }
                    $this->modelo->revisarIncidencia($evento, $clinica, $_POST['motivo']);
                    $mensaje = 'Incidencia marcada como revisada.';
                    break;
                case 'plataforma_suscripcion_ajax':
                    $this->modelo->cambiarSuscripcion($id, (int) ($_POST['id_plan'] ?? 0), (string) ($_POST['estado'] ?? ''), (string) ($_POST['motivo'] ?? ''), (string) ($_POST['fecha_fin'] ?? ''), (string) ($_POST['ciclo'] ?? 'mensual'));
                    break;
                case 'plataforma_estado_ajax':
                    $this->modelo->cambiarEstado($id, (string) ($_POST['estado'] ?? ''), (string) ($_POST['motivo'] ?? ''));
                    break;
                case 'plataforma_caso_ajax':
                    $this->modelo->cerrarCaso((int) ($_POST['id_caso'] ?? 0), (string) ($_POST['estado'] ?? ''), (string) ($_POST['motivo'] ?? ''));
                    break;
                case 'plataforma_desbloquear_ajax':
                    $this->modelo->desbloquear((string) ($_POST['ip'] ?? ''));
                    $mensaje = 'IP desbloqueada.';
                    break;
                case 'plataforma_aviso_ajax':
                    $enviado = $this->modelo->avisar((string) ($_POST['ip'] ?? ''), (string) ($_POST['destinatario'] ?? ''), (string) ($_POST['texto'] ?? ''), function (array $admin, string $texto): bool {
                        $this->correo ??= new EmailService();
                        $this->correo->limpiarDirecciones();
                        $asunto = 'Aviso de seguridad · ' . $admin['clinica'];
                        $html = $this->correo->obtenerPlantillaBaseHTML($admin['nombre_completo'], $asunto, '<p>' . htmlspecialchars($texto, ENT_QUOTES, 'UTF-8') . '</p>');
                        return $this->correo->enviarCorreoPersonalizado($admin['email'], $admin['nombre_completo'], $asunto, $html);
                    });
                    $mensaje = $enviado ? 'Aviso enviado al administrador elegido.' : 'No se pudo enviar el aviso. Puedes intentarlo de nuevo.';
                    echo json_encode(['success' => $enviado, 'message' => $mensaje]);
                    return;
                default:
                    throw new AccesoDenegado(403, 'Acción no permitida.');
            }
            echo json_encode(['success' => true, 'message' => $mensaje]);
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        } catch (AccesoDenegado $e) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        } catch (Throwable $e) {
            error_log('E1 plataforma: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'No se pudo completar la operación. Inténtalo nuevamente.']);
        }
    }

    public function pagos(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
                throw new AccesoDenegado(403, 'La consulta requiere GET.');
            }
            $id = filter_var($_GET['id_clinica'] ?? '', FILTER_VALIDATE_INT);
            $pagina = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT);
            if ($id === false || $pagina === false) {
                throw new InvalidArgumentException('La clínica o la página no son válidas.');
            }
            $finanzas = $this->modelo->finanzas($id, $pagina);
            require __DIR__ . '/../views/plataforma/pagos.php';
        } catch (InvalidArgumentException $error) {
            http_response_code(422);
            echo htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8');
        } catch (AccesoDenegado $error) {
            http_response_code(403);
            echo 'Esta consulta requiere plataforma.';
        } catch (Throwable $error) {
            http_response_code(500);
            error_log('S4: no se pudo consultar el panel financiero.');
            echo 'No se pudo consultar el historial. Inténtalo nuevamente.';
        }
    }
}
