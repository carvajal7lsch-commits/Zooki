<?php
require_once __DIR__ . '/ModeloClinica.php';
require_once __DIR__ . '/OperacionAgenda.php';
require_once __DIR__ . '/../helpers/CoordinadorAgenda.php';
require_once __DIR__ . '/../helpers/ValidadorHorarioVeterinario.php';

/** C2, RN-706/703: configuración y marcas; C3 calcula disponibilidad y C5 reajusta. */
final class CoordinacionHorarios extends ModeloClinica
{
    private function consultar(string $sql, array $parametros = []): array
    {
        $q = $this->conn->prepare($sql);
        $q->execute($parametros);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    private function exigirRol(array $roles): void
    {
        if ((Contexto::actual()['tipo'] ?? '') !== Contexto::CLINICA || !in_array(Contexto::rolActivo(), $roles, true)) {
            $this->denegarAcceso('horarios_veterinario', null, 'Gestión de horarios fuera del contexto (RN-706)');
        }
    }

    private function vigente(): void
    {
        $sufijo = (new CoordinadorAgenda($this->conn))->sufijo();
        $filas = $this->consultar("SELECT uc.id_rol FROM usuario_clinica uc JOIN usuarios u ON u.id_usuario=uc.id_usuario
            JOIN clinicas c ON c.id_clinica=uc.id_clinica WHERE uc.id_clinica=? AND uc.id_usuario=?
            AND uc.estado='activo' AND u.estado=1 AND c.estado='activa'" . $sufijo, [$this->clinica(), Contexto::idUsuario()]);
        if (!$filas || (int) $filas[0]['id_rol'] !== Contexto::rolActivo()) {
            throw new AccesoDenegado(403, 'No tienes acceso a esta configuración.');
        }
    }

    private function recursos(): array
    {
        $vets = $this->consultar('SELECT id_usuario FROM usuario_clinica WHERE id_clinica=? ORDER BY id_usuario', [$this->clinica()]);
        $citas = $this->consultar("SELECT id_veterinario,id_mascota FROM citas WHERE id_clinica=? AND estado IN ('pendiente','confirmada','en_curso','pausada','sin_cerrar')", [$this->clinica()]);
        return ['usuarios' => [...array_column($vets, 'id_usuario'), ...array_column($citas, 'id_veterinario')],
            'mascotas' => array_column($citas, 'id_mascota')];
    }

    private function escribir(array $roles, string $accion, array $entrada, ?string $clave, callable $guardar): array
    {
        $this->exigirRol($roles);
        try {
            return (new CoordinadorAgenda($this->conn))->ejecutar($this->clinica(), fn () => $this->recursos(), function () use ($accion, $entrada, $clave, $guardar) {
                $this->consultar('SELECT id_usuario FROM usuario_clinica WHERE id_clinica=? ORDER BY id_usuario' . (new CoordinadorAgenda($this->conn))->sufijo(), [$this->clinica()]);
                $this->vigente();
                $resultado = (new OperacionAgenda($this->conn))->ejecutar($accion, $clave, $entrada, function () use ($guardar) {
                    $resultado = $guardar();
                    // B1: el registro durable conserva referencias, no copias de configuración.
                    unset($resultado['franjas'], $resultado['horarios']);
                    return $resultado;
                });
                $vista = $this->leer($resultado['id_veterinario'] ?? null);
                if ($accion === 'guardar_horario_vet') {
                    $resultado['franjas'] = $vista['franjas'];
                }
                if (in_array($accion, ['guardar_apertura', 'restaurar_apertura'], true)) {
                    $resultado['horarios'] = $this->apertura();
                }
                return $resultado + ['vista' => $vista];
            });
        } catch (AccesoDenegado $error) {
            $this->denegarAcceso('horarios_veterinario', null, 'Configuración denegada tras revalidar (RN-706)');
        }
    }

    private function veterinario(int $id): void
    {
        $filas = $this->consultar("SELECT uc.id_usuario FROM usuario_clinica uc JOIN usuarios u ON u.id_usuario=uc.id_usuario
            WHERE uc.id_clinica=? AND uc.id_usuario=? AND uc.estado='activo' AND uc.id_rol=2 AND u.estado=1" . (new CoordinadorAgenda($this->conn))->sufijo(), [$this->clinica(), $id]);
        if (!$filas) {
            $this->denegarAcceso('horarios_veterinario', $id, 'Veterinario no disponible en el contexto activo (RE-T.15.2, RN-G13)');
        }
    }

    public function leer(?int $id = null): array
    {
        $this->exigirRol([Roles::ADMIN, Roles::VETERINARIO]);
        $this->vigente();
        $vets = $this->consultar("SELECT u.id_usuario,u.nombre_completo FROM usuario_clinica uc JOIN usuarios u ON u.id_usuario=uc.id_usuario
            WHERE uc.id_clinica=? AND uc.id_rol=2 AND uc.estado='activo' AND u.estado=1 ORDER BY u.id_usuario", [$this->clinica()]);
        if (Contexto::rolActivo() === Roles::VETERINARIO) {
            $id = Contexto::idUsuario();
            $vets = array_values(array_filter($vets, static fn ($v) => (int) $v['id_usuario'] === $id));
        }
        $id ??= (int) ($vets[0]['id_usuario'] ?? 0);
        if ($id) {
            $this->veterinario($id);
        }
        $propias = Contexto::rolActivo() === Roles::VETERINARIO ? ' AND id_veterinario=' . (int) Contexto::idUsuario() : '';
        return ['id_clinica' => $this->clinica(), 'id_veterinario' => $id, 'veterinarios' => $vets,
            'franjas' => $this->consultar('SELECT dia_semana,hora_inicio,hora_fin FROM horarios_veterinario WHERE id_clinica=? AND id_veterinario=? AND activo=1 ORDER BY dia_semana,hora_inicio', [$this->clinica(), $id]),
            'propuestas' => $this->consultar('SELECT * FROM propuestas_horario WHERE id_clinica=?' . $propias . ' ORDER BY id_propuesta DESC', [$this->clinica()]),
            'cierres' => $this->consultar('SELECT * FROM cierres_clinica WHERE id_clinica=? ORDER BY inicio,id_cierre', [$this->clinica()]),
            'afectadas' => $this->consultar("SELECT id_cita,id_veterinario,fecha,hora,estado_reajuste,motivo_reajuste,version_agenda FROM citas WHERE id_clinica=? AND estado_reajuste<>'ninguno'" . $propias . ' ORDER BY fecha,hora,id_cita', [$this->clinica()])];
    }

    private function apertura(): array
    {
        return $this->consultar('SELECT * FROM horarios_clinica WHERE id_clinica=? ORDER BY dia_semana', [$this->clinica()]);
    }

    public static function contiene(array $apertura, int $dia, string $inicio, string $fin): bool
    {
        foreach ($apertura as $fila) {
            if ((int) $fila['dia_semana'] !== $dia || !(int) $fila['activo']) {
                continue;
            }
            foreach (['morning', 'afternoon'] as $bloque) {
                $desde = $fila['bloque_' . $bloque . '_inicio'] ?? '';
                $hasta = $fila['bloque_' . $bloque . '_fin'] ?? '';
                $desde = strlen($desde) === 5 ? $desde . ':00' : $desde;
                $hasta = strlen($hasta) === 5 ? $hasta . ':00' : $hasta;
                if ((int) $fila['bloque_' . $bloque . '_activo'] && $inicio >= $desde && $fin <= $hasta) {
                    return true;
                }
            }
        }
        return false;
    }

    private function validarActual(int $id, array $franjas): void
    {
        $this->veterinario($id);
        $apertura = $this->apertura();
        // Excepción mínima RN-706: solo día e intervalos; nunca identidad de la clínica ajena.
        $ajenas = $this->consultar('SELECT dia_semana,hora_inicio,hora_fin FROM horarios_veterinario WHERE id_veterinario=? AND id_clinica<>? AND activo=1', [$id, $this->clinica()]);
        foreach ($franjas as $franja) {
            if (!self::contiene($apertura, $franja['dia_semana'], $franja['hora_inicio'], $franja['hora_fin'])) {
                throw new CampoInvalido('franjas', 'Fuera del horario de apertura.', 'La franja debe caber completa en un bloque abierto de la clínica.');
            }
            foreach ($ajenas as $otra) {
                if ((int) $otra['dia_semana'] === $franja['dia_semana'] && $franja['hora_inicio'] < $otra['hora_fin'] && $franja['hora_fin'] > $otra['hora_inicio']) {
                    throw new CampoInvalido('franjas', 'Conflicto de horario.', 'Choca con otro compromiso del veterinario.');
                }
            }
        }
    }

    private function auditar(string $tabla, ?int $id, mixed $antes, array $despues): void
    {
        if (!(new Auditoria($this->conn))->log(Contexto::idUsuario(), 'UPDATE', $tabla, $id, $antes, $despues, 'Coordinación de horarios (RE-4.11.4/5, RE-4.8.2)', $this->clinica())) {
            throw new RuntimeException('No se pudo auditar el horario.');
        }
    }

    private function aplicarVet(int $id, array $franjas): array
    {
        $this->validarActual($id, $franjas);
        $antes = $this->consultar('SELECT * FROM horarios_veterinario WHERE id_clinica=? AND id_veterinario=? AND activo=1 ORDER BY id', [$this->clinica(), $id]);
        $this->conn->prepare('UPDATE horarios_veterinario SET activo=0 WHERE id_clinica=? AND id_veterinario=?')->execute([$this->clinica(), $id]);
        $q = $this->conn->prepare('INSERT INTO horarios_veterinario (id_clinica,id_veterinario,dia_semana,hora_inicio,hora_fin,activo) VALUES (?,?,?,?,?,1)');
        foreach ($franjas as $f) {
            $q->execute([$this->clinica(), $id, $f['dia_semana'], $f['hora_inicio'], $f['hora_fin']]);
        }
        $afectadas = $this->marcar('Cambio de horario del veterinario', $id, null, $franjas);
        $this->auditar('horarios_veterinario', null, $antes, ['id_veterinario' => $id, 'franjas' => $franjas, 'afectadas' => $afectadas]);
        return ['id_veterinario' => $id, 'franjas' => $franjas, 'afectadas' => $afectadas];
    }

    public function guardarVeterinario(int $id, array $entrada, ?string $clave = null): array
    {
        $this->exigirRol([Roles::ADMIN]);
        $franjas = ValidadorHorarioVeterinario::franjas($entrada);
        return $this->escribir([Roles::ADMIN], 'guardar_horario_vet', [$id, $franjas], $clave, fn () => $this->aplicarVet($id, $franjas));
    }

    public function proponer(int $id, array $entrada, ?string $clave = null): array
    {
        $this->exigirRol([Roles::VETERINARIO]);
        if ($id !== Contexto::idUsuario()) {
            $this->denegarAcceso('propuestas_horario', null, 'Propuesta de otro veterinario');
        }
        $franjas = ValidadorHorarioVeterinario::franjas($entrada);
        return $this->escribir([Roles::VETERINARIO], 'proponer_horario', [$id, $franjas], $clave, function () use ($id, $franjas) {
            $this->validarActual($id, $franjas);
            $this->conn->prepare('INSERT INTO propuestas_horario (id_clinica,id_veterinario,franjas) VALUES (?,?,?)')->execute([$this->clinica(), $id, json_encode($franjas, JSON_THROW_ON_ERROR)]);
            $idPropuesta = (int) $this->conn->lastInsertId();
            $resultado = ['id_propuesta' => $idPropuesta, 'estado' => 'pendiente'];
            $this->auditar('propuestas_horario', $idPropuesta, null, $resultado);
            return $resultado;
        });
    }

    public function resolver(int $id, bool $aprobar, string $motivo = '', ?string $clave = null): array
    {
        $this->exigirRol([Roles::ADMIN]);
        $motivo = $aprobar ? '' : ValidadorHorarioVeterinario::motivo($motivo);
        return $this->escribir([Roles::ADMIN], 'resolver_horario', [$id, $aprobar, $motivo], $clave, function () use ($id, $aprobar, $motivo) {
            $filas = $this->consultar('SELECT * FROM propuestas_horario WHERE id_clinica=? AND id_propuesta=?', [$this->clinica(), $id]);
            if (!$filas) {
                throw new AccesoDenegado(403, 'Propuesta no disponible.');
            }
            $propuesta = $filas[0];
            if ($propuesta['estado'] !== 'pendiente') {
                throw new InvalidArgumentException('La propuesta ya fue resuelta. Recarga la lista.');
            }
            $this->veterinario((int) $propuesta['id_veterinario']);
            $afectadas = [];
            if ($aprobar) {
                $resultado = $this->aplicarVet((int) $propuesta['id_veterinario'], ValidadorHorarioVeterinario::franjas(json_decode($propuesta['franjas'], true, 512, JSON_THROW_ON_ERROR)));
                $afectadas = $resultado['afectadas'];
            }
            $estado = $aprobar ? 'aprobada' : 'rechazada';
            $this->conn->prepare('UPDATE propuestas_horario SET estado=?,id_revisor=?,motivo_rechazo=?,fecha_revision=CURRENT_TIMESTAMP WHERE id_clinica=? AND id_propuesta=?')
                ->execute([$estado, Contexto::idUsuario(), $aprobar ? null : $motivo, $this->clinica(), $id]);
            $resultado = ['id_propuesta' => $id, 'estado' => $estado, 'afectadas' => $afectadas];
            $this->auditar('propuestas_horario', $id, $propuesta, $resultado);
            return $resultado;
        });
    }

    public function guardarClinica(array $filas, callable $guardar, ?string $clave = null, bool $restaurar = false): array
    {
        return $this->escribir([Roles::ADMIN], $restaurar ? 'restaurar_apertura' : 'guardar_apertura', $filas, $clave, function () use ($guardar) {
            $antes = $this->apertura();
            $guardar();
            $afectadas = $this->marcar('Cambio de apertura de la clínica');
            $resultado = ['horarios' => $this->apertura(), 'afectadas' => $afectadas];
            $this->auditar('horarios_clinica', null, $antes, $resultado);
            return $resultado;
        });
    }

    public function cerrar(mixed $inicio, mixed $fin, mixed $motivo, ?string $clave = null): array
    {
        $this->exigirRol([Roles::ADMIN]);
        $inicio = ValidadorHorarioVeterinario::fecha($inicio);
        $fin = ValidadorHorarioVeterinario::fecha($fin, 'fin');
        $motivo = ValidadorHorarioVeterinario::motivo($motivo);
        if ($fin <= $inicio) {
            throw new CampoInvalido('fin', 'Fin no válido.', 'El fin debe ser posterior al inicio.');
        }
        return $this->escribir([Roles::ADMIN], 'cerrar_clinica', [$inicio, $fin, $motivo], $clave, function () use ($inicio, $fin, $motivo) {
            $this->conn->prepare('INSERT INTO cierres_clinica (id_clinica,inicio,fin,motivo,id_autor) VALUES (?,?,?,?,?)')->execute([$this->clinica(), $inicio, $fin, $motivo, Contexto::idUsuario()]);
            $id = (int) $this->conn->lastInsertId();
            $afectadas = $this->marcar('Cierre puntual de clínica', null, [$inicio, $fin]);
            $resultado = ['id_cierre' => $id, 'afectadas' => $afectadas];
            $this->auditar('cierres_clinica', $id, null, $resultado);
            return $resultado;
        });
    }

    public function retirarCierre(int $id, ?string $clave = null): array
    {
        return $this->escribir([Roles::ADMIN], 'retirar_cierre', [$id], $clave, function () use ($id) {
            $filas = $this->consultar('SELECT * FROM cierres_clinica WHERE id_clinica=? AND id_cierre=?' . (new CoordinadorAgenda($this->conn))->sufijo(), [$this->clinica(), $id]);
            if (!$filas) {
                throw new AccesoDenegado(403, 'Cierre no disponible.');
            }
            $afectadas = $this->marcar('Retirada de cierre puntual', null, [$filas[0]['inicio'], $filas[0]['fin']]);
            $this->conn->prepare('UPDATE cierres_clinica SET activo=0 WHERE id_clinica=? AND id_cierre=?')->execute([$this->clinica(), $id]);
            $resultado = ['id_cierre' => $id, 'activo' => 0, 'afectadas' => $afectadas];
            $this->auditar('cierres_clinica', $id, $filas[0], $resultado);
            return $resultado;
        });
    }

    private function marcar(string $causa, ?int $vet = null, ?array $cierre = null, ?array $franjas = null): array
    {
        $sufijo = (new CoordinadorAgenda($this->conn))->sufijo();
        $filas = $this->consultar("SELECT * FROM citas WHERE id_clinica=? AND estado IN ('pendiente','confirmada','en_curso','pausada','sin_cerrar') ORDER BY id_cita" . $sufijo, [$this->clinica()]);
        $apertura = $this->apertura();
        $afectadas = [];
        foreach ($filas as $cita) {
            if ($vet !== null && (int) $cita['id_veterinario'] !== $vet) {
                continue;
            }
            $inicio = new DateTimeImmutable($cita['fecha'] . ' ' . $cita['hora']);
            $duracion = $cita['duracion_minutos'] === null || (int) $cita['duracion_minutos'] <= 0 ? null : (int) $cita['duracion_minutos'] + (int) $cita['margen_minutos'];
            $fin = $duracion === null ? null : $inicio->modify('+' . $duracion . ' minutes');
            $fuera = $fin === null;
            if ($cierre !== null) {
                $fuera = $inicio->format('Y-m-d H:i:s') < $cierre[1]
                    && ($fin === null ? $inicio->format('Y-m-d') >= substr($cierre[0], 0, 10) : $fin->format('Y-m-d H:i:s') > $cierre[0]);
            } elseif ($fin !== null) {
                $dia = (int) $inicio->format('N');
                $fuera = $fin->format('Y-m-d') !== $inicio->format('Y-m-d') || !self::contiene($apertura, $dia, $inicio->format('H:i:s'), $fin->format('H:i:s'));
                if ($franjas !== null && !$fuera) {
                    $dentro = false;
                    foreach ($franjas as $f) {
                        $dentro = $dentro || ($f['dia_semana'] === $dia && $inicio->format('H:i:s') >= $f['hora_inicio'] && $fin->format('H:i:s') <= $f['hora_fin']);
                    }
                    $fuera = !$dentro;
                }
            }
            if ($fuera) {
                $this->conn->prepare('UPDATE citas SET estado_reajuste=?,motivo_reajuste=?,version_agenda=version_agenda+1 WHERE id_clinica=? AND id_cita=?')
                    ->execute([$fin === null ? 'pendiente_revision' : 'pendiente_calculo', $causa, $this->clinica(), $cita['id_cita']]);
                $afectadas[] = (int) $cita['id_cita'];
            }
        }
        return $afectadas;
    }
}
