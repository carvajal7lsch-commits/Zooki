<?php
require_once __DIR__ . '/Transaccion.php';
require_once __DIR__ . '/Contexto.php';
require_once __DIR__ . '/Roles.php';
require_once __DIR__ . '/../models/Auditoria.php';
require_once __DIR__ . '/../models/NotificacionInterna.php';

final class LimiteAlcanzado extends InvalidArgumentException
{
    public function __construct(public readonly int $clinica, public readonly string $recurso, public readonly int $uso, public readonly int $limite)
    {
        $propietario = (Contexto::actual()['tipo'] ?? null) === Contexto::PROPIETARIO;
        parent::__construct($propietario ? 'No es posible completar la acción ahora' : "Alcanzaste el límite de $recurso de tu plan ($uso/$limite). Mejora tu plan para seguir.");
    }
}

/** HU-0.4: un único cálculo por clínica; el panel recibe solo agregados numéricos. */
final class LimitePlan
{
    private const COLUMNAS = ['personal' => 'limite_personal', 'mascotas' => 'limite_mascotas', 'citas' => 'limite_citas_mes'];

    public function __construct(private PDO $db, private int $clinica)
    {
    }

    public function resumen(?string $fecha = null): array
    {
        $plan = $this->plan();
        $resultado = [];
        foreach (self::COLUMNAS as $recurso => $columna) {
            $resultado[$recurso] = ['uso' => $this->uso($recurso, $fecha), 'limite' => $plan[$columna] === null ? null : (int) $plan[$columna]];
        }
        return $resultado;
    }

    /** RE-6.2.10: el plan vigente que aplica (el gratuito si no hay suscripción de pago al día). */
    public function nombrePlan(): string
    {
        return (string) $this->plan()['nombre'];
    }

    private function plan(): array
    {
        $stmt = $this->db->prepare('SELECT p.*, c.id_plan FROM clinicas c LEFT JOIN planes p ON p.id_plan = c.id_plan WHERE c.id_clinica = ?');
        $stmt->execute([$this->clinica]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$plan) {
            throw new InvalidArgumentException('Clínica no disponible.');
        }
        $suscripcion = $this->db->prepare('SELECT estado, al_dia FROM suscripciones WHERE id_clinica = ? ORDER BY id_suscripcion DESC LIMIT 1');
        $suscripcion->execute([$this->clinica]);
        $fila = $suscripcion->fetch(PDO::FETCH_ASSOC);
        // Sin suscripción de pago vigente, RN-007/013 conserva los datos y usa el gratuito.
        if (!$fila || $fila['estado'] !== 'activa' || (int) $fila['al_dia'] !== 1 || !$plan['id_plan']) {
            $plan = $this->db->query("SELECT * FROM planes WHERE nombre = 'Gratuito'")->fetch(PDO::FETCH_ASSOC);
        }
        if (!$plan) {
            throw new RuntimeException('Falta el plan gratuito en la semilla.');
        }
        return $plan;
    }

    private function uso(string $recurso, ?string $fecha, ?int $excluirCita = null): int
    {
        if ($recurso === 'personal') {
            // UNION evita contar dos veces la activación (ya tiene usuario_clinica).
            $sql = "SELECT COUNT(*) FROM (
                SELECT uc.id_usuario FROM usuario_clinica uc JOIN usuarios u ON u.id_usuario = uc.id_usuario
                WHERE uc.id_clinica = ? AND uc.estado = 'activo' AND u.estado = 1
                UNION SELECT id_usuario FROM verificaciones_email WHERE id_clinica_vinculo = ?
                AND proposito IN ('activacion_personal', 'invitacion_personal') AND used = 0 AND expires_at > ?
            ) personal_del_plan";
            $parametros = [$this->clinica, $this->clinica, date('Y-m-d H:i:s')];
        } elseif ($recurso === 'mascotas') {
            $sql = "SELECT COUNT(*) FROM mascota_clinica WHERE id_clinica = ? AND estado = 'activo'";
            $parametros = [$this->clinica];
        } else {
            $mes = new DateTimeImmutable($fecha ?? date('Y-m-d'));
            $inicio = $mes->modify('first day of this month')->format('Y-m-d');
            $fin = $mes->modify('first day of next month')->format('Y-m-d');
            $sql = "SELECT COUNT(*) FROM citas WHERE id_clinica = ? AND fecha >= ? AND fecha < ? AND estado <> 'cancelada'";
            $parametros = [$this->clinica, $inicio, $fin];
            if ($excluirCita !== null) {
                $sql .= ' AND id_cita <> ?';
                $parametros[] = $excluirCita;
            }
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametros);
        return (int) $stmt->fetchColumn();
    }

    public function consumir(string $recurso, callable $operacion, ?string $fecha = null, string $prioridad = 'verde'): mixed
    {
        if (!isset(self::COLUMNAS[$recurso])) {
            throw new InvalidArgumentException('Recurso del plan no válido.');
        }
        return $this->transaccion(function () use ($recurso, $operacion, $fecha, $prioridad): mixed {
            // RN-420: el motor clínico debe entregar su clasificación antes de este punto.
            if ($prioridad !== 'rojo') {
                $valor = $this->plan()[self::COLUMNAS[$recurso]];
                $cupo = ['uso' => $this->uso($recurso, $fecha), 'limite' => $valor === null ? null : (int) $valor];
                if ($cupo['limite'] !== null && $cupo['uso'] >= $cupo['limite']) {
                    throw new LimiteAlcanzado($this->clinica, $recurso, $cupo['uso'], $cupo['limite']);
                }
            }
            return $operacion();
        });
    }

    /** B1: el destino reemplaza una reserva legítima; nunca suma dos veces su ID. */
    public function comprobarReprogramacion(int $idCita, string $fecha, string $prioridad): void
    {
        if (!$this->db->inTransaction()) {
            throw new LogicException('La cuota de reprogramación exige la unidad coordinada.');
        }
        $limite = $this->plan()['limite_citas_mes'];
        $uso = $this->uso('citas', $fecha, $idCita);
        if ($prioridad !== 'rojo' && $limite !== null && $uso >= (int) $limite) {
            throw new LimiteAlcanzado($this->clinica, 'citas', $uso, (int) $limite);
        }
    }

    public function transaccion(callable $operacion): mixed
    {
        $exterior = !$this->db->inTransaction();
        try {
            return Transaccion::ejecutar($this->db, function () use ($operacion): mixed {
                // Serializa comprobación + alta contra otra alta y contra cambios del plan.
                $bloqueo = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
                $stmt = $this->db->prepare('SELECT id_clinica FROM clinicas WHERE id_clinica = ?' . $bloqueo);
                $stmt->execute([$this->clinica]);
                if (!$stmt->fetchColumn()) {
                    throw new InvalidArgumentException('Clínica no disponible.');
                }
                return $operacion();
            });
        } catch (LimiteAlcanzado $error) {
            if ($exterior) {
                // Fuera del rollback de negocio: el intento bloqueado debe conservarse.
                $this->registrarRechazo($error);
            }
            throw $error;
        }
    }
    /** C2: conservar el intento después del rollback exterior del coordinador. */
    public function registrarRechazo(LimiteAlcanzado $error): void
    {
        Transaccion::ejecutar($this->db, function () use ($error): void {
            $datos = ['recurso' => $error->recurso, 'uso' => $error->uso, 'limite' => $error->limite];
            if (!(new Auditoria($this->db))->log(Contexto::idUsuario(), 'OTHER', 'planes', null, null, $datos, 'Acción bloqueada por límite del plan (RE-0.4.2/3)', $this->clinica)) {
                throw new RuntimeException('No se pudo auditar el límite del plan.');
            }
            if ((Contexto::actual()['tipo'] ?? null) === Contexto::PROPIETARIO) {
                (new NotificacionInterna($this->db))->crearParaRol($this->clinica, Roles::ADMIN, 'LIMITE_PLAN', 'Límite del plan alcanzado', "Un propietario no pudo completar una acción de {$error->recurso} ({$error->uso}/{$error->limite}). Gestiona el plan de la clínica.");
            }
        });
    }

}
