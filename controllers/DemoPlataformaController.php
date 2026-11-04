<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../helpers/Csrf.php';
require_once __DIR__ . '/../helpers/Contexto.php';
require_once __DIR__ . '/../helpers/CampoInvalido.php';
require_once __DIR__ . '/../helpers/RespuestaJson.php';
require_once __DIR__ . '/../helpers/InterruptorDemo.php';
require_once __DIR__ . '/../helpers/PoliticaPassword.php';
require_once __DIR__ . '/../helpers/PresentacionDemo.php';
require_once __DIR__ . '/../models/Auditoria.php';
require_once __DIR__ . '/../scripts/demo/SemillaDemo.php';

/**
 * Botón de demo del super-administrador (decisión del dueño, 2026-11-03).
 * Llama a SemillaDemo, la misma clase de scripts/semilla_demo.php, sin pasar
 * por la consola: crear equivale a --si, escena a --escena y borrar a --borrar.
 */
final class DemoPlataformaController
{
    private const MODOS = ['plataforma_demo_crear_ajax' => 'si', 'plataforma_demo_escena_ajax' => 'escena', 'plataforma_demo_borrar_ajax' => 'borrar'];

    private PDO $db;
    private Closure $ejecutor;

    /** @param callable(string, array): array|null $ejecutor sustituto de SemillaDemo en las pruebas sin MySQL */
    public function __construct(?PDO $db = null, ?callable $ejecutor = null)
    {
        $this->db = $db ?? (new Database())->getConnection();
        $this->ejecutor = $ejecutor === null ? $this->semilla(...) : Closure::fromCallable($ejecutor);
    }

    public function ejecutar(string $accion): void
    {
        if (!InterruptorDemo::habilitada() || !isset(self::MODOS[$accion])) {
            RespuestaJson::error(404, 'Página no encontrada.');
            return;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !Csrf::validate()) {
            RespuestaJson::error(403, 'La operación requiere POST y un token de seguridad válido.');
            return;
        }
        $modo = self::MODOS[$accion];
        $usuario = Contexto::idUsuario();
        try {
            $opciones = $modo === 'si' ? $this->opcionesCrear() : [];
        } catch (CampoInvalido $error) {
            RespuestaJson::error(422, $error->getMessage(), ['campo' => $error->campo, 'aviso' => $error->aviso]);
            return;
        }
        // Se libera la sesión antes de la ejecución larga: un segundo clic llega al
        // candado GET_LOCK de SemillaDemo y responde «en curso» en vez de esperar en fila
        // al primero y repetirlo. Lo que SemillaDemo escribe en $_SESSION ya no se guarda.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        set_time_limit(0);
        ignore_user_abort(true);
        try {
            $resumen = PresentacionDemo::resumen(($this->ejecutor)($modo, $opciones));
        } catch (PDOException | Error $error) {
            error_log('Demo plataforma ' . $modo . ': ' . $error->getMessage());
            $this->auditar($usuario, $modo, $opciones, ['resultado' => 'error', 'error' => 'Error interno; ver el registro del servidor']);
            RespuestaJson::error(500, 'No se pudo completar la demo por un error interno. Revisa el registro del servidor.');
            return;
        } catch (Exception $error) {
            // Los motivos de SemillaDemo (fuera de horario, falta la demo, otra en curso) se muestran tal cual.
            $this->auditar($usuario, $modo, $opciones, ['resultado' => 'error', 'error' => $error->getMessage()]);
            RespuestaJson::error(422, $error->getMessage());
            return;
        }
        $this->auditar($usuario, $modo, $opciones, ['resultado' => 'ok'] + $resumen);
        RespuestaJson::enviar(['success' => true, 'message' => PresentacionDemo::mensaje($modo, $resumen), 'resultado' => $resumen]);
    }

    private function opcionesCrear(): array
    {
        $dia = trim((string) ($_POST['dia'] ?? ''));
        $clave = is_string($_POST['clave'] ?? null) ? $_POST['clave'] : '';
        try {
            SemillaDemo::fecha($dia);
        } catch (InvalidArgumentException $error) {
            throw new CampoInvalido('dia', 'Elige una fecha válida.', 'Elige una fecha válida para la demo.');
        }
        // Sin clave, SemillaDemo generaría claves al azar que el panel nunca muestra.
        $motivo = PoliticaPassword::validar($clave);
        if ($motivo !== null) {
            throw new CampoInvalido('clave', $motivo, $motivo);
        }
        return ['dia' => $dia, 'clave' => $clave];
    }

    private function semilla(string $modo, array $opciones): array
    {
        $semilla = new SemillaDemo($this->db);
        $resultado = match ($modo) {
            'si' => $semilla->crear($opciones['dia'], $opciones['clave']),
            'escena' => $semilla->escena(),
            'borrar' => $semilla->borrar(),
        };
        return $resultado + ['conteos' => $semilla->conteos()];
    }

    /** Quién, cuándo (fecha_hora), acción y resultado; la clave nunca entra en la auditoría. */
    private function auditar(?int $usuario, string $modo, array $opciones, array $datos): void
    {
        unset($datos['cuentas']);
        $datos = ['modo' => $modo, 'dia' => $opciones['dia'] ?? null] + $datos;
        (new Auditoria($this->db))->log($usuario, 'OTHER', null, null, null, $datos, 'Modo demo: ' . $modo . ' (' . $datos['resultado'] . ')', null);
    }
}
