<?php
class Cita {
    private $conn;
    private $table_name = "citas";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function insert($data) {
        // Calcular hora_fin basado en la duración del tipo de cita
        $hora_fin = null;
        if (isset($data['duracion_minutos']) && $data['duracion_minutos']) {
            $hora_fin = date('H:i:s', strtotime($data['hora'] . ' +' . $data['duracion_minutos'] . ' minutes'));
        }
        
        $estado = isset($data['estado']) ? $data['estado'] : 'pendiente';

        $query = "INSERT INTO " . $this->table_name . " 
                  (id_mascota, doc_veterinario, fecha, hora, hora_fin, motivo, id_tipo_cita, duracion_minutos, estado) 
                  VALUES (:id_mascota, :doc_veterinario, :fecha, :hora, :hora_fin, :motivo, :id_tipo_cita, :duracion_minutos, :estado)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_mascota', $data['id_mascota']);
        $stmt->bindParam(':doc_veterinario', $data['doc_veterinario']);
        $stmt->bindParam(':fecha', $data['fecha']);
        $stmt->bindParam(':hora', $data['hora']);
        $stmt->bindParam(':hora_fin', $hora_fin);
        $stmt->bindParam(':motivo', $data['motivo']);
        $stmt->bindParam(':id_tipo_cita', $data['id_tipo_cita']);
        $stmt->bindParam(':duracion_minutos', $data['duracion_minutos']);
        $stmt->bindParam(':estado', $estado);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function checkDisponibilidad($doc_veterinario, $fecha, $hora, $duracion_minutos = 0, $id_cita_excluir = null) {
        // Usar timestamps numéricos para comparar horas de forma fiable
        // (evita bugs por diferencias de formato: '08:00' vs '08:00:00')
        $nueva_inicio = strtotime(date('Y-m-d') . ' ' . $hora);
        $nueva_fin    = $nueva_inicio + (max((int)$duracion_minutos, 1) * 60);

        $query = "SELECT hora, hora_fin, duracion_minutos FROM " . $this->table_name . "
                  WHERE doc_veterinario = :doc_vet
                  AND fecha = :fecha
                  AND estado NOT IN ('cancelada', 'no_asistio', 'cerrada_sin_consulta')";

        if ($id_cita_excluir) {
            $query .= " AND id_cita != :id_cita_excluir";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':doc_vet', $doc_veterinario);
        $stmt->bindParam(':fecha', $fecha);
        if ($id_cita_excluir) {
            $stmt->bindParam(':id_cita_excluir', $id_cita_excluir);
        }
        $stmt->execute();
        $citas_existentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($citas_existentes as $cita) {
            $cita_inicio_ts = strtotime(date('Y-m-d') . ' ' . $cita['hora']);

            if ($cita['hora_fin']) {
                $cita_fin_ts = strtotime(date('Y-m-d') . ' ' . $cita['hora_fin']);
            } else {
                $dur = !empty($cita['duracion_minutos']) ? (int)$cita['duracion_minutos'] : 30;
                $cita_fin_ts = $cita_inicio_ts + ($dur * 60);
            }

            // Solapamiento: nueva_inicio < cita_fin  AND  nueva_fin > cita_inicio
            if ($nueva_inicio < $cita_fin_ts && $nueva_fin > $cita_inicio_ts) {
                return false;
            }
        }

        return true;
    }

    public function checkMascotaDisponible($id_mascota, $fecha, $hora, $duracion_minutos = 0, $id_cita_excluir = null) {
        $nueva_inicio = strtotime(date('Y-m-d') . ' ' . $hora);
        $nueva_fin    = $nueva_inicio + (max((int)$duracion_minutos, 1) * 60);

        $query = "SELECT hora, hora_fin, duracion_minutos FROM " . $this->table_name . "
                  WHERE id_mascota = :id_mascota
                  AND fecha = :fecha
                  AND estado NOT IN ('cancelada', 'no_asistio', 'cerrada_sin_consulta')";

        if ($id_cita_excluir) {
            $query .= " AND id_cita != :id_cita_excluir";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_mascota', $id_mascota);
        $stmt->bindParam(':fecha', $fecha);
        if ($id_cita_excluir) {
            $stmt->bindParam(':id_cita_excluir', $id_cita_excluir);
        }
        $stmt->execute();
        $citas_existentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($citas_existentes as $cita) {
            $cita_inicio_ts = strtotime(date('Y-m-d') . ' ' . $cita['hora']);

            if ($cita['hora_fin']) {
                $cita_fin_ts = strtotime(date('Y-m-d') . ' ' . $cita['hora_fin']);
            } else {
                $dur = !empty($cita['duracion_minutos']) ? (int)$cita['duracion_minutos'] : 30;
                $cita_fin_ts = $cita_inicio_ts + ($dur * 60);
            }

            // Solapamiento: nueva_inicio < cita_fin  AND  nueva_fin > cita_inicio
            if ($nueva_inicio < $cita_fin_ts && $nueva_fin > $cita_inicio_ts) {
                return false; // Conflicto de horario para la misma mascota
            }
        }
        return true;
    }
    
    public function getTiposCita() {
        $query = "SELECT * FROM tipos_cita WHERE activo = 1 ORDER BY duracion_minutos ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Mapear nombre_tipo a nombre para compatibilidad
        return array_map(function($tipo) {
            return [
                'id_tipo_cita' => $tipo['id_tipo_cita'],
                'nombre' => $tipo['nombre_tipo'] ?? $tipo['nombre'] ?? '',
                'nombre_tipo' => $tipo['nombre_tipo'] ?? $tipo['nombre'] ?? '',
                'duracion_minutos' => $tipo['duracion_minutos'],
                'activo' => $tipo['activo']
            ];
        }, $tipos);
    }
    
    public function getTipoCitaById($id_tipo_cita) {
        $query = "SELECT * FROM tipos_cita WHERE id_tipo_cita = :id_tipo_cita";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_tipo_cita', $id_tipo_cita);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getSugerenciasHorario($doc_veterinario, $fecha, $duracion_minutos, $id_cita_excluir = null) {
        $duracion_minutos = max((int)$duracion_minutos, 1);

        $tz = new DateTimeZone('America/Bogota');
        $now = new DateTime('now', $tz);
        $hoy = $now->format('Y-m-d');
        $hora_actual_ts = null;
        if ($fecha === $hoy) {
            $hora_actual_ts = $now->getTimestamp();
        }

        // Obtener citas existentes
        $query = "SELECT hora, hora_fin, duracion_minutos as dur FROM " . $this->table_name . "
                  WHERE doc_veterinario = :doc_vet
                  AND fecha = :fecha
                  AND estado NOT IN ('cancelada', 'no_asistio', 'cerrada_sin_consulta')";
        if ($id_cita_excluir) {
            $query .= " AND id_cita != :id_cita_excluir";
        }
        $query .= " ORDER BY hora ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':doc_vet', $doc_veterinario);
        $stmt->bindParam(':fecha', $fecha);
        if ($id_cita_excluir) {
            $stmt->bindParam(':id_cita_excluir', $id_cita_excluir);
        }
        $stmt->execute();
        $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Construir lista de intervalos bloqueados como timestamps
        $bloqueados = [];
        foreach ($citas as $c) {
            $inicioDt = new DateTime($fecha . ' ' . $c['hora'], $tz);
            $ini = $inicioDt->getTimestamp();

            if (!empty($c['hora_fin'])) {
                $finDt = new DateTime($fecha . ' ' . $c['hora_fin'], $tz);
            } else {
                $dur = !empty($c['dur']) ? (int)$c['dur'] : 30;
                $finDt = (clone $inicioDt)->modify('+' . $dur . ' minutes');
            }

            $bloqueados[] = [$ini, $finDt->getTimestamp()];
        }

        // Recorrer el horario de atención slot por slot
        $apertura_ts = (new DateTime($fecha . ' 08:00', $tz))->getTimestamp();
        $cierre_ts   = (new DateTime($fecha . ' 18:00', $tz))->getTimestamp();
        $paso        = $duracion_minutos * 60;

        $sugerencias = [];
        $slot_ts = $apertura_ts;

        while ($slot_ts + $paso <= $cierre_ts) {
            if ($hora_actual_ts !== null && $slot_ts < $hora_actual_ts) {
                $slot_ts += $paso;
                continue;
            }

            $slot_fin = $slot_ts + $paso;
            $disponible = true;

            foreach ($bloqueados as $blq) {
                // Solapamiento: nueva_inicio < cita_fin  AND  nueva_fin > cita_inicio
                if ($slot_ts < $blq[1] && $slot_fin > $blq[0]) {
                    $disponible = false;
                    break;
                }
            }

            if ($disponible) {
                $slotDate = new DateTime('@' . $slot_ts);
                $slotDate->setTimezone($tz);
                $sugerencias[] = $slotDate->format('H:i');
            }

            $slot_ts += $paso;
        }

        return $sugerencias;
    }

    public function getByFecha($fecha_inicio, $fecha_fin, $doc_veterinario = null) {
        $query = "SELECT c.*, m.nombre as mascota_nombre, u.nombre_completo as veterinario_nombre, p.nombre_completo as propietario_nombre
                  FROM " . $this->table_name . " c
                  JOIN mascotas m ON c.id_mascota = m.id_mascota
                  JOIN usuarios u ON c.doc_veterinario = u.documento
                  JOIN usuarios p ON m.doc_propietario = p.documento
                  WHERE c.fecha BETWEEN :fecha_inicio AND :fecha_fin";
        
        if ($doc_veterinario) {
            $query .= " AND c.doc_veterinario = :doc_veterinario";
        }
        
        $query .= " ORDER BY c.fecha ASC, c.hora ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':fecha_inicio', $fecha_inicio);
        $stmt->bindParam(':fecha_fin', $fecha_fin);
        
        if ($doc_veterinario) {
            $stmt->bindParam(':doc_veterinario', $doc_veterinario);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id_cita) {
        $query = "SELECT c.*, m.nombre as mascota_nombre, u.nombre_completo as veterinario_nombre, p.nombre_completo as propietario_nombre, p.email
                  FROM " . $this->table_name . " c
                  JOIN mascotas m ON c.id_mascota = m.id_mascota
                  JOIN usuarios u ON c.doc_veterinario = u.documento
                  JOIN usuarios p ON m.doc_propietario = p.documento
                  WHERE c.id_cita = :id_cita";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_cita', $id_cita);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function update($id_cita, $doc_veterinario, $fecha, $hora, $motivo, $id_tipo_cita = null, $duracion_minutos = null) {
        // Calcular hora_fin si se proporciona duración
        $hora_fin = null;
        if ($duracion_minutos) {
            $hora_fin = date('H:i:s', strtotime($hora . ' +' . $duracion_minutos . ' minutes'));
        }

        $query = "UPDATE " . $this->table_name . " 
                  SET doc_veterinario = :doc_vet, fecha = :fecha, hora = :hora, motivo = :motivo";
        
        if ($id_tipo_cita) {
            $query .= ", id_tipo_cita = :id_tipo_cita";
        }
        if ($duracion_minutos) {
            $query .= ", duracion_minutos = :duracion_minutos, hora_fin = :hora_fin";
        }
        
        $query .= " WHERE id_cita = :id_cita";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_cita', $id_cita);
        $stmt->bindParam(':doc_vet', $doc_veterinario);
        $stmt->bindParam(':fecha', $fecha);
        $stmt->bindParam(':hora', $hora);
        $stmt->bindParam(':motivo', $motivo);
        if ($id_tipo_cita) {
            $stmt->bindParam(':id_tipo_cita', $id_tipo_cita);
        }
        if ($duracion_minutos) {
            $stmt->bindParam(':duracion_minutos', $duracion_minutos);
            $stmt->bindParam(':hora_fin', $hora_fin);
        }
        return $stmt->execute();
    }

    public function getByMascota($id_mascota) {
        $query = "SELECT c.*, u.nombre_completo as veterinario_nombre
                  FROM " . $this->table_name . " c
                  JOIN usuarios u ON c.doc_veterinario = u.documento
                  WHERE c.id_mascota = :id_mascota
                    AND c.estado != 'cancelada'
                  ORDER BY c.fecha DESC, c.hora DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_mascota', $id_mascota);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProximaByMascota($id_mascota) {
        $query = "SELECT c.*, u.nombre_completo as veterinario_nombre
                  FROM " . $this->table_name . " c
                  JOIN usuarios u ON c.doc_veterinario = u.documento
                  WHERE c.id_mascota = :id_mascota
                    AND c.fecha >= CURDATE()
                    AND c.estado IN ('pendiente', 'confirmada')
                  ORDER BY c.fecha ASC, c.hora ASC
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_mascota', $id_mascota);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // Valores del ENUM de citas.estado (database/07, 09 y 12).
    public const ESTADOS = ['pendiente', 'confirmada', 'en_curso', 'cancelada', 'completada', 'no_asistio', 'sin_cerrar', 'cerrada_sin_consulta'];

    // Estados en los que la cita todavía no se ha resuelto: desde aquí se
    // puede iniciar, cancelar, reprogramar o marcar como no asistida.
    public const ESTADOS_ABIERTOS = ['pendiente', 'confirmada'];

    // RN-410: atenciones iniciadas que todavía no se cierran. Desde aquí se
    // retoma la atención, se registra la consulta o se cierra sin consulta.
    public const ESTADOS_EN_ATENCION = ['en_curso', 'sin_cerrar'];

    /**
     * RN-408 / HU-27 — Inicia la atención y sella la hora real de inicio.
     * El WHERE exige que la cita siga abierta: si se canceló entre tanto, o se
     * inicia dos veces desde dos pestañas, no se pisa el estado.
     */
    public function iniciarAtencion($id_cita, string $ahora): bool {
        return $this->transicion($id_cita, self::ESTADOS_ABIERTOS, "estado = 'en_curso', hora_inicio_real = :ahora", [':ahora' => $ahora]);
    }

    /**
     * RN-406 / HU-27 — Completa una cita en curso y sella la hora real de fin.
     * RN-410: también una que quedó sin cerrar, para no perder su consulta.
     */
    public function completarAtencion($id_cita, string $ahora): bool {
        return $this->transicion($id_cita, self::ESTADOS_EN_ATENCION, "estado = 'completada', hora_fin_real = :ahora", [':ahora' => $ahora]);
    }

    /** RN-409 / HU-29 — El paciente no llegó: la cita deja de ocupar la agenda. */
    public function marcarNoAsistio($id_cita): bool {
        return $this->transicion($id_cita, self::ESTADOS_ABIERTOS, "estado = 'no_asistio'", []);
    }

    /** RN-410 — Terminado el día, una atención que sigue en curso queda "sin cerrar". */
    public function marcarSinCerrar($id_cita): bool {
        return $this->transicion($id_cita, ['en_curso'], "estado = 'sin_cerrar'", []);
    }

    /**
     * RN-411 — El veterinario cierra sin consulta una atención abierta, con su
     * motivo. La cita no se reabre y deja libre su horario.
     */
    public function cerrarSinConsulta($id_cita, string $motivo, string $ahora): bool {
        return $this->transicion(
            $id_cita,
            self::ESTADOS_EN_ATENCION,
            "estado = 'cerrada_sin_consulta', motivo_cierre = :motivo, hora_fin_real = :ahora",
            [':motivo' => $motivo, ':ahora' => $ahora]
        );
    }

    /**
     * RN-410 — Sella el aviso de "atención abierta". Solo escribe si el aviso
     * no se había enviado: si la tarea programada y el calendario revisan a la
     * vez, uno solo lo reclama y lo envía.
     */
    public function sellarAvisoAtencionAbierta($id_cita, string $ahora): bool {
        $stmt = $this->conn->prepare(
            "UPDATE " . $this->table_name . " SET aviso_atencion_abierta = :ahora
             WHERE id_cita = :id_cita AND estado = 'en_curso' AND aviso_atencion_abierta IS NULL"
        );
        $stmt->execute([':ahora' => $ahora, ':id_cita' => (int) $id_cita]);
        return $stmt->rowCount() === 1;
    }

    /** RN-410 — Atenciones en curso, con lo necesario para avisar al veterinario. */
    public function getAtencionesEnCurso(): array {
        $query = "SELECT c.id_cita, c.fecha, c.hora, c.hora_fin, c.duracion_minutos, c.estado,
                         c.aviso_atencion_abierta, c.doc_veterinario, m.nombre AS mascota_nombre,
                         v.nombre_completo AS veterinario_nombre, v.email AS veterinario_email
                  FROM " . $this->table_name . " c
                  LEFT JOIN mascotas m ON m.id_mascota = c.id_mascota
                  LEFT JOIN usuarios v ON v.documento = c.doc_veterinario
                  WHERE c.estado = 'en_curso'";
        return $this->conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cambia el estado solo si la cita está en uno de los estados de origen.
     * Devuelve false si no se tocó ninguna fila: la cita no existe o ya no
     * estaba en un estado desde el que se permite ese cambio.
     */
    private function transicion($id_cita, array $desde, string $set, array $params): bool {
        $marcadores = [];
        foreach (array_values($desde) as $i => $estado) {
            $marcadores[] = ':desde' . $i;
            $params[':desde' . $i] = $estado;
        }
        $params[':id_cita'] = (int) $id_cita;

        $query = "UPDATE " . $this->table_name . " SET " . $set
               . " WHERE id_cita = :id_cita AND estado IN (" . implode(', ', $marcadores) . ")";
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->rowCount() === 1;
    }

    /**
     * Un estado fuera del ENUM se rechaza aquí: MySQL sin modo estricto no da
     * error, guarda una cadena vacía, y así fue como "en_curso" dejaba citas
     * sin estado y sin forma de completarlas.
     */
    public function cambiarEstado($id_cita, $estado) {
        if (!in_array($estado, self::ESTADOS, true)) {
            error_log('Estado de cita no válido: ' . var_export($estado, true));
            return false;
        }

        $query = "UPDATE " . $this->table_name . " SET estado = :estado WHERE id_cita = :id_cita";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_cita', $id_cita);
        $stmt->bindParam(':estado', $estado);
        return $stmt->execute();
    }
}
