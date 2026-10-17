<?php
require_once __DIR__ . '/ModeloClinica.php';

/** RN-211 / RN-G13: excepción interna mínima; nunca se usa para abrir la historia compartida. */
final class MedicacionVigente extends ModeloClinica
{
    public function leer(int $idMascota, DateTimeImmutable $fecha): array
    {
        $mascota = $this->exigirMascotaVinculada($idMascota);
        if ($mascota['vinculo'] !== 'activo') {
            $this->denegarAcceso('mascotas', $idMascota, 'Prescripción sin vínculo activo (RN-211)');
        }
        $permiso = $this->conn->prepare("SELECT 1 FROM propietario_clinica WHERE id_propietario = ? AND id_clinica = ? AND estado = 'activo' AND autoriza_historia_compartida = 1");
        $permiso->execute([$mascota['id_propietario'], $this->clinica()]);
        $autorizada = (bool) $permiso->fetchColumn();
        $consulta = $this->conn->prepare("SELECT t.id_tratamiento AS id, c.id_clinica, t.fecha_inicio, t.fecha_fin, n.codigo AS farmaco
            FROM tratamientos t
            JOIN consultas c ON c.id_consulta = t.id_consulta
            LEFT JOIN grafo_nodos n ON n.id_nodo = t.id_nodo_farmaco AND n.activo = 1 AND n.tipo = 'farmaco'
            WHERE c.id_mascota = ? AND t.fecha_inicio <= ? AND (t.fecha_fin IS NULL OR t.fecha_fin >= ?)
            ORDER BY t.id_tratamiento");
        $hoy = $fecha->format('Y-m-d');
        $consulta->execute([$idMascota, $hoy, $hoy]);
        $vigentes = [];
        foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $fila['visible'] = (int) $fila['id_clinica'] === $this->clinica() || $autorizada;
            $fila['vigencia'] = $fila['fecha_fin'] === null ? 'desconocida' : 'vigente';
            $fila['origen'] = 'tratamiento';
            $vigentes[] = $fila;
        }
        $alertas = $this->conn->prepare("SELECT a.id_alerta AS id, a.tipo, a.id_clinica, n.codigo AS farmaco
            FROM alertas_medicas a
            LEFT JOIN grafo_nodos n ON n.id_nodo = a.id_nodo AND n.activo = 1
            WHERE a.id_mascota = ? AND a.activa = 1 AND a.tipo IN ('alergia', 'medicacion_continua') ORDER BY a.id_alerta");
        $alertas->execute([$idMascota]);
        $alergias = [];
        foreach ($alertas->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            if ($fila['tipo'] === 'alergia') {
                $alergias[] = $fila;
            } else {
                // Las alertas médicas son globales y visibles sin autorización (RN-113).
                $fila += ['visible' => true, 'vigencia' => 'vigente', 'origen' => 'alerta'];
                $vigentes[] = $fila;
            }
        }
        return ['vigentes' => $vigentes, 'alergias' => $alergias];
    }
}
