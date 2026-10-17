<?php
require_once __DIR__ . '/../helpers/MapaConocimiento.php';
require_once __DIR__ . '/../helpers/ConocimientoClinico.php';

/**
 * HU-2.12 — Lectura del conocimiento global para el mapa de la plataforma.
 * Solo lee las tablas del grafo, el catálogo de razas (para ubicar cada raza en su especie)
 * y la última carga auditada; nunca mascotas, consultas, tratamientos, citas ni alertas
 * (RE-2.12.5, RN-004). No escribe.
 */
final class GrafoConocimiento
{
    /** Reserva si falta el manifiesto: nunca se presenta el conjunto como validado (RE-2.12.6). */
    public const LIMITACION_RESERVA = 'Conocimiento pendiente de validación veterinaria. Es apoyo a la decisión (RN-209): decide el veterinario.';

    private string $manifiesto;
    private ?ConocimientoClinico $instantanea = null;

    public function __construct(private PDO $db, ?string $manifiesto = null)
    {
        $this->manifiesto = $manifiesto ?? __DIR__ . '/../database/conocimiento/manifiesto.json';
    }

    public function mapa(): array
    {
        $nodos = $this->db->query(
            'SELECT n.codigo, n.tipo, n.etiqueta, n.nivel_triage, n.alarma_universal, n.moderado, n.metadatos, n.fuentes, n.estado_validacion, n.id_especie, r.id_especie AS especie_raza
             FROM grafo_nodos n
             LEFT JOIN razas r ON r.id_raza = n.id_raza
             WHERE n.activo = 1
             ORDER BY n.tipo, n.etiqueta'
        )->fetchAll(PDO::FETCH_ASSOC);
        // Una arista hacia un nodo retirado tampoco se muestra.
        $aristas = $this->db->query(
            'SELECT a.codigo, o.codigo AS origen, d.codigo AS destino, a.tipo_relacion, a.signo, a.peso, a.efecto, a.condiciones, a.fuentes, a.estado_validacion
             FROM grafo_aristas a
             JOIN grafo_nodos o ON o.id_nodo = a.id_origen AND o.activo = 1
             JOIN grafo_nodos d ON d.id_nodo = a.id_destino AND d.activo = 1
             WHERE a.activo = 1
             ORDER BY a.codigo'
        )->fetchAll(PDO::FETCH_ASSOC);
        $sinonimos = $this->db->query(
            'SELECT n.codigo AS nodo, s.texto
             FROM grafo_sinonimos s
             JOIN grafo_nodos n ON n.id_nodo = s.id_nodo AND n.activo = 1
             WHERE s.activo = 1
             ORDER BY s.texto'
        )->fetchAll(PDO::FETCH_ASSOC);
        $reglas = $this->db->query(
            'SELECT codigo, motor, condiciones, resultado, fuentes, estado_validacion
             FROM grafo_reglas
             WHERE activo = 1
             ORDER BY motor, codigo'
        )->fetchAll(PDO::FETCH_ASSOC);
        return MapaConocimiento::construir($nodos, $aristas, $sinonimos, $reglas, $this->versionCargada(), $this->limitaciones());
    }

    /** C2: reutiliza la lectura activa de C6 sin cambiar la respuesta del mapa. */
    public function instantanea(): ConocimientoClinico
    {
        if ($this->instantanea === null) {
            // En InnoDB la lectura repetible evita mezclar versiones si se carga otro conjunto.
            $propia = !$this->db->inTransaction();
            if ($propia) {
                $this->db->beginTransaction();
            }
            try {
                $mapa = $this->mapa();
                $instantanea = new ConocimientoClinico($mapa['nodes'], $mapa['edges'], $mapa['reglas'], $mapa['version']['hash'] ?? null, $mapa['limitaciones']);
                if ($propia) {
                    $this->db->commit();
                }
                $this->instantanea = $instantanea;
            } catch (Throwable $error) {
                if ($propia && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw $error;
            }
        }
        return $this->instantanea;
    }

    /** La versión de la última carga auditada por el cargador (RN-212). */
    private function versionCargada(): ?string
    {
        $datos = $this->db->query(
            "SELECT datos_nuevos FROM auditoria_sistema
             WHERE tabla_afectada = 'grafo_nodos' AND descripcion = 'Carga de conocimiento global (RN-212)'
             ORDER BY id_auditoria DESC LIMIT 1"
        )->fetchColumn();
        if ($datos === false) {
            return null;
        }
        $version = json_decode((string) $datos, true)['version'] ?? null;
        return is_string($version) && preg_match('/^[a-f0-9]{64}$/', $version) ? $version : null;
    }

    /** Las limitaciones viajan en el manifiesto versionado, no en la base. */
    private function limitaciones(): string
    {
        $texto = is_readable($this->manifiesto) ? file_get_contents($this->manifiesto) : false;
        $manifiesto = $texto === false ? null : json_decode($texto, true);
        $limitaciones = is_array($manifiesto) ? ($manifiesto['limitaciones'] ?? null) : null;
        return is_string($limitaciones) && trim($limitaciones) !== '' ? trim($limitaciones) : self::LIMITACION_RESERVA;
    }
}
