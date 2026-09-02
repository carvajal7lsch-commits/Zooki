<?php
/**
 * HU-38 (VD-SEG-05) — Contador de intentos del lado del servidor.
 *
 * Sustituye al contador en $_SESSION, que se evadia simplemente no enviando
 * la cookie: cada peticion sin cookie abria una sesion nueva con el contador
 * en cero, asi que el limite de 5 intentos no existia para un script.
 *
 * El contador se guarda por identificador (IP, cuenta o verificacion), con
 * una ventana deslizante: si el ultimo intento quedo fuera de la ventana, la
 * cuenta se reinicia sola.
 */
class IntentoLogin
{
    private $conn;
    private $table_name = 'intentos_login';

    /** Se recuerda si la tabla falta para no reintentar el DDL en cada golpe. */
    private static $tablaDisponible = null;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Segundos que faltan para que se levante el bloqueo, o 0 si no lo hay.
     */
    public function segundosDeBloqueo(string $identificador): int
    {
        if (!$this->tablaLista()) return 0;

        $stmt = $this->conn->prepare(
            "SELECT bloqueado_hasta FROM {$this->table_name}
             WHERE identificador = :id AND bloqueado_hasta IS NOT NULL"
        );
        $stmt->execute([':id' => $identificador]);
        $hasta = $stmt->fetchColumn();

        if (!$hasta) return 0;

        $restante = strtotime($hasta) - time();

        return $restante > 0 ? $restante : 0;
    }

    /**
     * Suma un intento fallido y bloquea si se pasa del maximo dentro de la
     * ventana. Devuelve los segundos de bloqueo aplicados (0 si aun no).
     *
     * @param int $maximo   intentos permitidos dentro de la ventana
     * @param int $ventana  duracion de la ventana, en segundos
     * @param int $castigo  cuanto dura el bloqueo, en segundos
     */
    public function registrarFallo(string $identificador, int $maximo, int $ventana, int $castigo): int
    {
        if (!$this->tablaLista()) return 0;

        $ahora = date('Y-m-d H:i:s');

        $stmt = $this->conn->prepare(
            "SELECT intentos, primer_intento, bloqueado_hasta
             FROM {$this->table_name} WHERE identificador = :id"
        );
        $stmt->execute([':id' => $identificador]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            $ins = $this->conn->prepare(
                "INSERT INTO {$this->table_name}
                 (identificador, intentos, primer_intento, ultimo_intento)
                 VALUES (:id, 1, :ahora, :ahora)"
            );
            $ins->execute([':id' => $identificador, ':ahora' => $ahora]);

            return 0;
        }

        // Ventana vencida: el contador arranca de nuevo.
        $venceEn = strtotime($fila['primer_intento']) + $ventana;
        $intentos = (time() > $venceEn) ? 1 : ((int) $fila['intentos'] + 1);
        $primero  = (time() > $venceEn) ? $ahora : $fila['primer_intento'];

        $bloqueo = null;
        $segundos = 0;
        if ($intentos >= $maximo) {
            $bloqueo = date('Y-m-d H:i:s', time() + $castigo);
            $segundos = $castigo;
        }

        $upd = $this->conn->prepare(
            "UPDATE {$this->table_name}
             SET intentos = :intentos, primer_intento = :primero,
                 ultimo_intento = :ahora, bloqueado_hasta = :bloqueo
             WHERE identificador = :id"
        );
        $upd->execute([
            ':intentos' => $intentos,
            ':primero'  => $primero,
            ':ahora'    => $ahora,
            ':bloqueo'  => $bloqueo,
            ':id'       => $identificador,
        ]);

        return $segundos;
    }

    /** Borra el contador (se llama tras un inicio de sesion correcto). */
    public function limpiar(string $identificador): void
    {
        if (!$this->tablaLista()) return;

        $stmt = $this->conn->prepare("DELETE FROM {$this->table_name} WHERE identificador = :id");
        $stmt->execute([':id' => $identificador]);
    }

    /**
     * Housekeeping: quita contadores viejos para que la tabla no crezca sin
     * limite. Se invoca de forma oportunista, no en cada peticion.
     */
    public function purgar(int $antiguedadSegundos = 86400): void
    {
        if (!$this->tablaLista()) return;

        // La hora va como parametro y no con NOW(): esa funcion es de MySQL y
        // rompe en SQLite, que es lo que usan las pruebas.
        $limite = date('Y-m-d H:i:s', time() - $antiguedadSegundos);
        $ahora = date('Y-m-d H:i:s');
        $stmt = $this->conn->prepare(
            "DELETE FROM {$this->table_name}
             WHERE ultimo_intento < :limite
               AND (bloqueado_hasta IS NULL OR bloqueado_hasta < :ahora)"
        );
        $stmt->execute([':limite' => $limite, ':ahora' => $ahora]);
    }

    /**
     * Comprueba que la tabla exista y, si falta, intenta crearla.
     *
     * El despliegue puede llegar antes que la migracion, y dejar el login
     * inutilizable seria peor que el problema que se quiere resolver: por eso
     * aqui se degrada en vez de fallar, y el llamador vuelve al contador en
     * sesion. Queda registrado en el log para que no pase inadvertido.
     */
    private function tablaLista(): bool
    {
        if (self::$tablaDisponible !== null) return self::$tablaDisponible;

        try {
            $this->conn->query("SELECT 1 FROM {$this->table_name} LIMIT 1");
            self::$tablaDisponible = true;
        } catch (PDOException $e) {
            try {
                $this->conn->exec(
                    "CREATE TABLE IF NOT EXISTS {$this->table_name} (
                        id_intento INT(11) NOT NULL AUTO_INCREMENT,
                        identificador VARCHAR(120) NOT NULL,
                        intentos INT(11) NOT NULL DEFAULT 0,
                        bloqueado_hasta DATETIME DEFAULT NULL,
                        primer_intento DATETIME NOT NULL,
                        ultimo_intento DATETIME NOT NULL,
                        PRIMARY KEY (id_intento),
                        UNIQUE KEY uq_intentos_identificador (identificador),
                        KEY idx_intentos_ultimo (ultimo_intento)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
                );
                self::$tablaDisponible = true;
            } catch (PDOException $e2) {
                error_log(
                    'HU-38: no existe la tabla intentos_login y no se pudo crear (' . $e2->getMessage()
                    . '). El limite de intentos queda degradado al contador en sesion; '
                    . 'ejecuta database/04_intentos_login.sql.'
                );
                self::$tablaDisponible = false;
            }
        }

        return self::$tablaDisponible;
    }

    /** Solo para pruebas: olvida el resultado cacheado de la deteccion. */
    public static function olvidarEstadoDeTabla(): void
    {
        self::$tablaDisponible = null;
    }
}
