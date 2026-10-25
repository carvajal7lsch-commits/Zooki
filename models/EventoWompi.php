<?php
require_once __DIR__ . '/../helpers/Transaccion.php';
require_once __DIR__ . '/../helpers/FirmaWompi.php';
require_once __DIR__ . '/../helpers/VigenciaSuscripcion.php';
require_once __DIR__ . '/Auditoria.php';

/** Inbox durable: nunca confirmar HTTP antes del commit (RE-0.5.12/14). */
final class EventoWompi
{
    public function __construct(private PDO $db, private mixed $reloj = null)
    {
    }

    private function ahora(): DateTimeImmutable
    {
        return $this->reloj ? ($this->reloj)()->setTimezone(new DateTimeZone(PoliticaSuscripcion::ZONA)) : new DateTimeImmutable('now', new DateTimeZone(PoliticaSuscripcion::ZONA));
    }

    public function recibir(array $evento): bool
    {
        try {
            return Transaccion::ejecutar($this->db, function () use ($evento): bool {
                $q = $this->db->prepare('SELECT 1 FROM wompi_eventos WHERE entorno=? AND clave_evento=?');
                $q->execute([$evento['entorno'], $evento['clave_evento']]);
                if ($q->fetchColumn()) {
                    return false;
                }
                $estado = $evento['tipo'] === 'transaction.updated' ? 'pendiente' : 'ignorado';
                $this->db->prepare('INSERT INTO wompi_eventos (clave_evento,entorno,tipo,id_transaccion_wompi,timestamp_proveedor,checksum,huella_datos_firmados,datos_minimos,estado,recibido_en) VALUES (?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$evento['clave_evento'], $evento['entorno'], $evento['tipo'], $evento['id_transaccion_wompi'], $evento['timestamp_proveedor'], $evento['checksum'], $evento['huella_datos_firmados'], '{}', $estado, $this->ahora()->format('Y-m-d H:i:s')]);
                $this->auditar((int) $this->db->lastInsertId(), [], ['estado' => $estado], null);
                return true;
            });
        } catch (PDOException $error) {
            // Una carrera de inserción solo es duplicado si la fila quedó durable.
            if (in_array((string) $error->getCode(), ['23000', '23505'], true)) {
                $q = $this->db->prepare('SELECT 1 FROM wompi_eventos WHERE entorno=? AND clave_evento=?');
                $q->execute([$evento['entorno'], $evento['clave_evento']]);
                if ($q->fetchColumn()) {
                    return false;
                }
            }
            throw $error;
        }
    }

    public function reclamar(int $id, string $entorno): ?array
    {
        if ($this->db->inTransaction()) {
            throw new LogicException('Conciliación requiere una transacción independiente.');
        }
        return Transaccion::ejecutar($this->db, function () use ($id, $entorno): ?array {
            $q = $this->db->prepare('SELECT * FROM wompi_eventos WHERE id_evento=? AND entorno=?' . ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : ''));
            $q->execute([$id, $entorno]);
            $evento = $q->fetch(PDO::FETCH_ASSOC);
            $ahora = $this->ahora();
            if (!$evento || !in_array($evento['estado'], ['pendiente', 'error', 'procesando'], true)
                || ($evento['proxima_revision'] !== null && VigenciaSuscripcion::fecha($evento['proxima_revision']) > $ahora)
                || ($evento['estado'] === 'procesando' && $evento['lease_hasta'] !== null && VigenciaSuscripcion::fecha($evento['lease_hasta']) > $ahora)) {
                return null;
            }
            $token = bin2hex(random_bytes(32));
            $this->db->prepare("UPDATE wompi_eventos SET estado='procesando',lease_token=?,lease_hasta=?,intentos_proceso=intentos_proceso+1 WHERE id_evento=?")
                ->execute([$token, $ahora->modify('+' . PoliticaSuscripcion::RECLAMACION_SEGUNDOS . ' seconds')->format('Y-m-d H:i:s'), $id]);
            $this->auditar($id, ['estado' => $evento['estado']], ['estado' => 'procesando'], null);
            $evento['lease_token'] = $token;
            return $evento;
        });
    }

    public function terminar(array $evento, string $estado, ?string $codigo = null, ?int $intento = null, ?int $clinica = null): void
    {
        $this->db->prepare('UPDATE wompi_eventos SET estado=?,codigo_error=?,id_intento=?,procesado_en=?,proxima_revision=?,lease_token=NULL,lease_hasta=NULL WHERE id_evento=? AND lease_token=?')
            ->execute([$estado, $codigo, $intento, in_array($estado, ['procesado', 'ignorado', 'revision'], true) ? $this->ahora()->format('Y-m-d H:i:s') : null,
                $estado === 'pendiente' ? $this->ahora()->modify('+' . PoliticaSuscripcion::RECLAMACION_SEGUNDOS . ' seconds')->format('Y-m-d H:i:s') : null,
                $evento['id_evento'], $evento['lease_token']]);
        $this->auditar((int) $evento['id_evento'], ['estado' => 'procesando'], ['estado' => $estado, 'codigo' => $codigo], $clinica);
    }

    private function auditar(int $id, array $antes, array $despues, ?int $clinica): void
    {
        if (!(new Auditoria($this->db))->log(null, 'UPDATE', 'wompi_eventos', $id, $antes, $despues, 'Proveedor/trabajador Wompi (RN-G05)', $clinica)) {
            throw new RuntimeException('No se pudo auditar el evento.');
        }
    }
}
