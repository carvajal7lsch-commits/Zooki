<?php
require_once __DIR__ . '/ServicioAgendaClinica.php';
require_once __DIR__ . '/CascadaAgenda.php';

/** RN-414: solicitud vet sin bloqueo, aprobación administrativa y cobertura sin veto. */
final class AusenciaAgenda extends ServicioAgendaClinica
{
    public function listar(): array
    {
        $this->autorizar([Roles::ADMIN, Roles::VETERINARIO]);
        $sql = 'SELECT a.*,u.nombre_completo AS veterinario FROM ausencias_veterinario a JOIN usuarios u ON u.id_usuario=a.id_veterinario WHERE a.id_clinica=?';
        $p = [$this->clinica()];
        if (Contexto::rolActivo() === Roles::VETERINARIO) {
            $sql .= ' AND a.id_veterinario=?';
            $p[] = Contexto::idUsuario();
        }
        return $this->filas($sql . ' ORDER BY a.fecha_hora_inicio,a.id', $p);
    }

    public function registrar(array $datos): array
    {
        $this->autorizar([Roles::ADMIN, Roles::VETERINARIO]);
        $vet = (int) ($datos['id_veterinario'] ?? Contexto::idUsuario());
        if (Contexto::rolActivo() === Roles::VETERINARIO && $vet !== Contexto::idUsuario()) {
            $this->denegarAcceso('ausencias_veterinario', null, 'Solo se solicita la ausencia propia');
        }
        $this->veterinario($vet);
        $inicio = ValidadorAgendaClinica::instante((string) ($datos['inicio'] ?? ''));
        $fin = ValidadorAgendaClinica::instante((string) ($datos['fin'] ?? ''));
        if ($fin <= $inicio) {
            throw new InvalidArgumentException('El fin debe ser posterior al inicio de la ausencia.');
        }
        $motivo = ValidadorAgendaClinica::motivo($datos['motivo'] ?? '', false);
        return $this->unidad([Roles::ADMIN, Roles::VETERINARIO], 'registrar_ausencia', [$vet, $inicio, $fin, $motivo], $datos['clave_operacion'] ?? null, fn () => $this->recursos([], [], [$inicio, $fin]), function () use ($vet, $inicio, $fin, $motivo): array {
            $this->veterinario($vet);
            $admin = Contexto::rolActivo() === Roles::ADMIN;
            $estado = $admin ? 'aprobada' : 'solicitada';
            $this->conn->prepare('INSERT INTO ausencias_veterinario (id_clinica,id_veterinario,fecha_hora_inicio,fecha_hora_fin,motivo,estado,id_solicitante,id_revisor,fecha_revision) VALUES (?,?,?,?,?,?,?,?,?)')->execute([$this->clinica(), $vet, $inicio, $fin, $motivo, $estado, Contexto::idUsuario(), $admin ? Contexto::idUsuario() : null, $admin ? ($this->reloj)()->format('Y-m-d H:i:s') : null]);
            $id = (int) $this->conn->lastInsertId();
            $this->auditar('ausencias_veterinario', $id, null, ['estado' => $estado, 'inicio' => $inicio, 'fin' => $fin], 'Ausencia administrativa o solicitud propia (RE-4.12.1)');
            $afectadas = $admin ? $this->cubrir($id) : [];
            $this->avisar($vet, $admin, 'Ausencia ' . $estado);
            return ['id_ausencia' => $id, 'estado' => $estado, 'afectadas' => $afectadas, 'message' => $admin ? 'Ausencia efectiva; cobertura procesada.' : 'Solicitud enviada. El horario no cambia hasta su aprobación.'];
        });
    }

    private function avisar(int $vet, bool $alVeterinario, string $mensaje): void
    {
        // No inventar una solicitud clínica para un aviso administrativo sin cita.
        $avisos = new NotificacionInterna($this->conn);
        $enlace = 'index.php?action=' . ($alVeterinario ? 'mi_perfil' : 'configuracion');
        $ok = $alVeterinario
            ? $avisos->crearParaUsuario($this->clinica(), $vet, 'ausencia', 'Ausencias', $mensaje, $enlace)
            : $avisos->crearParaRol($this->clinica(), Roles::ADMIN, 'ausencia', 'Ausencias', $mensaje, $enlace);
        if (!$ok) {
            throw new RuntimeException('No se pudo registrar el aviso de ausencia.');
        }
    }

    public function resolver(int $id, bool $aprobar, string $motivo, ?string $clave): array
    {
        $this->autorizar([Roles::ADMIN]);
        $a = $this->obtener($id);
        $motivo = ValidadorAgendaClinica::motivo($motivo, !$aprobar);
        return $this->unidad([Roles::ADMIN], 'resolver_ausencia', [$id, $aprobar, $motivo], $clave, fn () => $this->recursos([], [], [$a['fecha_hora_inicio'], $a['fecha_hora_fin']]), function () use ($id, $aprobar, $motivo): array {
            $a = $this->obtener($id);
            $this->veterinario((int) $a['id_veterinario']);
            if ($a['estado'] !== 'solicitada') {
                throw new InvalidArgumentException('La solicitud ya fue resuelta.');
            }
            $estado = $aprobar ? 'aprobada' : 'rechazada';
            $this->conn->prepare('UPDATE ausencias_veterinario SET estado=?,id_revisor=?,fecha_revision=?,motivo_resolucion=? WHERE id=? AND id_clinica=?')->execute([$estado, Contexto::idUsuario(), ($this->reloj)()->format('Y-m-d H:i:s'), $motivo, $id, $this->clinica()]);
            $afectadas = $aprobar ? $this->cubrir($id) : [];
            $this->avisar((int) $a['id_veterinario'], true, 'Ausencia ' . $estado);
            $this->auditar('ausencias_veterinario', $id, ['estado' => 'solicitada'], ['estado' => $estado, 'motivo' => $motivo], 'Resolución de ausencia (RN-414)');
            return ['id_ausencia' => $id, 'estado' => $estado, 'afectadas' => $afectadas, 'message' => $aprobar ? 'Ausencia aprobada; cobertura procesada.' : 'Solicitud rechazada. El horario se conserva.'];
        });
    }

    private function obtener(int $id): array
    {
        $a = $this->filas('SELECT * FROM ausencias_veterinario WHERE id=? AND id_clinica=?', [$id, $this->clinica()]);
        if (!$a) {
            $this->denegarAcceso('ausencias_veterinario', $id, 'Ausencia de otra clínica');
        }
        return $a[0];
    }

    private function cubrir(int $id): array
    {
        $a = $this->obtener($id);
        $citas = $this->filas("SELECT * FROM citas WHERE id_clinica=? AND id_veterinario=? AND fecha>=? AND fecha<=? AND estado NOT IN ('cancelada','no_asistio','completada') ORDER BY fecha,hora,id_cita", [$this->clinica(), $a['id_veterinario'], substr($a['fecha_hora_inicio'], 0, 10), substr($a['fecha_hora_fin'], 0, 10)]);
        $afectadas = [];
        foreach ($citas as $cita) {
            if ((int)$cita['duracion_minutos'] <= 0 || $cita['margen_minutos'] === null) {
                $afectadas[] = (int)$cita['id_cita'];
                (new CascadaAgenda($this->conn,$this->reloj))->cubrirAusencia((int)$cita['id_cita']);
                continue;
            }
            $inicio = $cita['inicio_estimado'] ?? $cita['fecha'] . ' ' . PoliticaTemporalAgenda::hora($cita['hora']);
            $fin = $cita['fin_estimado'] ?? PoliticaTemporalAgenda::sello(IntervalosAgenda::fecha($inicio)->getTimestamp() + (int) $cita['duracion_minutos'] * 60);
            $extremo = PoliticaTemporalAgenda::sello(IntervalosAgenda::fecha($fin)->getTimestamp() + (int) $cita['margen_minutos'] * 60);
            if ($inicio >= $a['fecha_hora_fin'] || $extremo <= $a['fecha_hora_inicio']) {
                continue;
            }
            $afectadas[] = (int) $cita['id_cita'];
            (new CascadaAgenda($this->conn, $this->reloj))->cubrirAusencia((int) $cita['id_cita']);
        }
        return $afectadas;
    }
}
