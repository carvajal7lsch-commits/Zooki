<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/ConfiguracionClinica.php';
require_once __DIR__ . '/../helpers/RespuestaJson.php';
require_once __DIR__ . '/../helpers/Csrf.php';
require_once __DIR__ . '/../helpers/ValidadorClinico.php';

/** C1: rutas administrativas, con la clínica resuelta por el modelo autorizado. */
final class ConfiguracionClinicaController
{
    private ConfiguracionClinica $configuracion;
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? (new Database())->getConnection();
        $this->configuracion = new ConfiguracionClinica($this->db);
    }

    public function vista(): void
    {
        $configuracion = $this->configuracion->leer();
        $planSuscripcion = $this->planEfectivo();
        $content_view = __DIR__ . '/../views/admin/configuracion.php';
        require __DIR__ . '/../views/admin/layout.php';
    }

    /** Rediseño (2026-10-26): la entrada a Suscripción muestra el plan; si no se puede leer, la entrada sigue sin él. */
    private function planEfectivo(): ?string
    {
        require_once __DIR__ . '/../models/Suscripcion.php';
        try {
            return (new Suscripcion($this->db, (int) Contexto::clinicaActiva()))->estado()['plan_efectivo']['nombre'];
        } catch (Throwable $error) {
            error_log('Configuración: plan de la suscripción: ' . $error->getMessage());
            return null;
        }
    }

    public function obtenerAjax(): void
    {
        RespuestaJson::consulta(fn () => RespuestaJson::enviar(['success' => true] + $this->configuracion->leer()), 'C1 configuración');
    }

    private function modificar(callable $accion): void
    {
        RespuestaJson::modificacion(function () use ($accion): array {
            if (!Csrf::validate()) {
                throw new AccesoDenegado(403, 'La sesión del formulario venció. Recarga la página.');
            }
            return $accion();
        }, 'C1 configuración');
    }

    private function idTipo(bool $obligatorio): ?int
    {
        $valor = $_POST['id_tipo_cita'] ?? '';
        if ($valor === '' && !$obligatorio) {
            return null;
        }
        $id = ValidadorClinico::id($valor);
        if ($id === null) {
            throw new CampoInvalido('id_tipo_cita', 'Tipo no válido.', 'Tipo no válido.');
        }
        return $id;
    }

    public function guardarMarcaAjax(): void
    {
        $this->modificar(function (): array {
            $marca = $this->configuracion->guardarMarca($_POST);
            $_SESSION['contexto']['clinica'] = $marca['nombre'];
            return ['marca' => $marca, 'message' => 'Datos de la clínica guardados.'];
        });
    }

    public function validarCampoAjax(): void
    {
        $this->modificar(function (): array {
            $this->configuracion->leer();
            $campo = $_POST['campo'] ?? '';
            if (!is_string($campo)) {
                throw new InvalidArgumentException('Campo no válido.');
            }
            $error = ValidadorConfiguracionClinica::campo($campo, $_POST['valor'] ?? '');
            return ['valido' => $error === null, 'aviso' => $error ?? ''];
        });
    }

    public function guardarTipoAjax(): void
    {
        $this->modificar(fn (): array => ['tipo' => $this->configuracion->guardarTipo($this->idTipo(false), $_POST),
            'message' => 'Tipo de cita guardado.']);
    }

    public function cambiarEstadoAjax(): void
    {
        $this->modificar(fn (): array => ['tipo' => $this->configuracion->cambiarEstado($this->idTipo(true), $_POST),
            'message' => 'Estado del tipo actualizado.']);
    }
}
