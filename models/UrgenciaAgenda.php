<?php
require_once __DIR__ . '/ServicioAgendaClinica.php';
require_once __DIR__ . '/CascadaAgenda.php';

/** C7a: presencia física, decisión clínica y segmentos reales; nunca cuotas de urgencia. */
final class UrgenciaAgenda extends ServicioAgendaClinica
{
    public function catalogos(): array
    {
        $this->autorizar([Roles::ADMIN,Roles::VETERINARIO]);
        return [
            'mascotas'=>$this->filas("SELECT m.id_mascota,m.id_propietario,m.nombre FROM mascotas m JOIN usuarios u ON u.id_usuario=m.id_propietario AND u.estado=1 JOIN propietario_clinica pc ON pc.id_propietario=m.id_propietario AND pc.id_clinica=? AND pc.estado='activo' LEFT JOIN mascota_clinica mc ON mc.id_mascota=m.id_mascota AND mc.id_clinica=pc.id_clinica WHERE m.estado=1 AND m.ficha_por_completar=0 AND (mc.estado='activo' OR mc.id_mascota IS NULL) ORDER BY m.nombre,m.id_mascota",[$this->clinica()]),
            'propietarios'=>$this->filas("SELECT u.id_usuario,u.nombre_completo FROM usuarios u JOIN propietario_clinica pc ON pc.id_propietario=u.id_usuario WHERE pc.id_clinica=? AND pc.estado='activo' AND u.estado=1 ORDER BY u.nombre_completo,u.id_usuario",[$this->clinica()]),
            'tipos'=>$this->filas('SELECT id_tipo_cita,nombre_tipo FROM tipos_cita WHERE id_clinica=? AND activo=1 ORDER BY id_tipo_cita',[$this->clinica()]),
            'especies'=>$this->filas('SELECT id_especie,nombre_especie FROM especies ORDER BY id_especie'),
            'razas'=>$this->filas('SELECT id_raza,id_especie,nombre_raza FROM razas ORDER BY id_raza'),
        ];
    }

    public function listar(): array
    {
        $this->autorizar([Roles::ADMIN, Roles::VETERINARIO]);
         $filas = $this->filas("SELECT s.id_solicitud,s.id_mascota,s.id_ingreso,s.id_cita,s.fecha_llegada,s.estado,s.estado_revision,s.huella_revision,s.version_solicitud,s.orden_espera,m.nombre FROM solicitudes_agenda s JOIN mascotas m ON m.id_mascota=s.id_mascota WHERE s.id_clinica=? AND COALESCE(s.nivel_aprobado,s.nivel_calculado)='rojo' AND s.estado NOT IN ('finalizada','retirada') ORDER BY CASE WHEN s.fecha_llegada IS NULL THEN 1 ELSE 0 END,s.orden_espera,s.fecha_llegada,s.id_solicitud", [$this->clinica()]);
        require_once __DIR__ . '/ConciliacionAgenda.php';
        foreach ($filas as &$fila) {
            $fila['revision_vigente'] = ConciliacionAgenda::revisionVigente($this->conn,$this->clinica(),$fila);
            unset($fila['huella_revision']);
        }
        unset($fila);
        return $filas;
    }

    private function solicitud(int $id): array
    {
        $s = $this->filas('SELECT * FROM solicitudes_agenda WHERE id_solicitud=? AND id_clinica=?', [$id,$this->clinica()])[0] ?? null;
        if (!$s) {
            $this->denegarAcceso('solicitudes_agenda', $id, 'Llegada de otra clínica');
        }
        return $s;
    }

    /** Una derivación existente no cambia de identidad ni evalúa de nuevo la clínica. */
    public function acreditarLlegada(int $id, ?string $clave): array
    {
        $this->autorizar([Roles::ADMIN, Roles::VETERINARIO]);
        $s = $this->solicitud($id);
        return $this->unidad([Roles::ADMIN, Roles::VETERINARIO], 'llegada_roja', [$id], $clave, fn () => $this->recursos([(int)$s['id_mascota']]), function () use ($id): array {
            $s = $this->solicitud($id);
            if (in_array($s['estado'], ['finalizada','retirada'], true) || ($s['nivel_aprobado'] ?? $s['nivel_calculado']) !== 'rojo') {
                throw new InvalidArgumentException('La solicitud no admite una llegada roja.');
            }
            if ($s['fecha_llegada'] === null) {
                $this->presencia($id);
            }
            return ['id_solicitud'=>$id,'message'=>'Llegada física registrada; pendiente de atención veterinaria.'];
        });
    }

    private function presencia(int $id): void
    {
        $ahora = ($this->reloj)()->format('Y-m-d H:i:s');
        $siguiente = (int)$this->filas("SELECT COALESCE(MAX(orden_espera),0)+1 AS siguiente FROM solicitudes_agenda WHERE id_clinica=? AND fecha_llegada IS NOT NULL", [$this->clinica()])[0]['siguiente'];
        $this->conn->prepare("UPDATE solicitudes_agenda SET fecha_llegada=?,id_receptor=?,estado='espera_presencial',orden_espera=?,version_solicitud=version_solicitud+1 WHERE id_solicitud=? AND id_clinica=?")->execute([$ahora,Contexto::idUsuario(),$siguiente,$id,$this->clinica()]);
        $this->auditar('solicitudes_agenda',$id,null,['fecha_llegada'=>$ahora,'orden'=>$siguiente],'Presencia física, no llegada remota (RE-4.15.2)');
        $s = $this->solicitud($id);
        (new EventoAgenda($this->conn,$this->clinica()))->interno('ROJO_PRESENTE',null,$id,(int)$s['version_solicitud'],null,Roles::VETERINARIO);
    }

    public function ingresar(array $entrada): array
    {
        $this->autorizar([Roles::VETERINARIO]);
        $mascota = (int)($entrada['id_mascota'] ?? 0);
        $motivo = ValidadorAgendaClinica::motivo($entrada['motivo'] ?? '');
        [$nombre,$telefono] = ValidadorAgendaClinica::contacto($entrada);
        $nombreMascota = trim((string)($entrada['nombre_mascota'] ?? ''));
        require_once __DIR__ . '/../helpers/ValidadorMascota.php';
        if ($nombreMascota !== '' && (mb_strlen($nombreMascota)>ValidadorMascota::NOMBRE_MAX || !preg_match("/^[\\p{L}\\p{N}][\\p{L}\\p{N} .'\\-]*$/u",$nombreMascota))) {
            throw new InvalidArgumentException('Revisa el nombre de la mascota.');
        }
        return $this->unidad([Roles::VETERINARIO], 'ingreso_rojo', [$mascota,$motivo,$nombreMascota,$nombre,$telefono], $entrada['clave_operacion'] ?? null, fn () => $this->recursos($mascota>0 ? [$mascota] : []), function () use ($mascota,$motivo,$nombreMascota,$nombre,$telefono): array {
            $ingreso = null;
            if ($mascota > 0) {
                $verificada = $this->filas("SELECT 1 FROM mascotas m JOIN usuarios u ON u.id_usuario=m.id_propietario AND u.estado=1 JOIN propietario_clinica pc ON pc.id_propietario=u.id_usuario AND pc.id_clinica=? AND pc.estado='activo' WHERE m.id_mascota=? AND m.ficha_por_completar=0",[$this->clinica(),$mascota]);
                if (!$verificada) {
                    throw new InvalidArgumentException('La relación no está verificada. Registra una ficha provisional sin atribuir titularidad.');
                }
                $vinculo = $this->filas('SELECT estado FROM mascota_clinica WHERE id_mascota=? AND id_clinica=?',[$mascota,$this->clinica()])[0] ?? null;
                if (!$vinculo) {
                    $this->conn->prepare("INSERT INTO mascota_clinica (id_mascota,id_clinica,estado) VALUES (?,?,'activo')")->execute([$mascota,$this->clinica()]);
                    $this->auditar('mascotas',$mascota,null,['vinculo_urgente'=>true],'Vínculo rojo con cuenta y propietario legítimos; excepción a cuota/mora');
                }
                $this->exigirMascotaActiva($mascota);
            } else {
                // Cuenta desconocida o relación no verificada: ninguna atribución ni cuenta automática.
                $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
                $this->conn->prepare("INSERT INTO mascotas (id_propietario,id_clinica_registro,token_carnet,carnet_activo,nombre,ficha_por_completar,estado) VALUES (NULL,?,?,0,?,1,1)")->execute([$this->clinica(),$token,$nombreMascota ?: null]);
                $mascota = (int)$this->conn->lastInsertId();
                (new CoordinadorAgenda($this->conn))->registrarMascotaCreada($mascota);
                $this->conn->prepare("INSERT INTO mascota_clinica (id_mascota,id_clinica,estado) VALUES (?,?,'activo')")->execute([$mascota,$this->clinica()]);
                $this->conn->prepare('INSERT INTO ingresos_emergencia (id_clinica,id_mascota_provisional,nombre_acompanante,telefono_acompanante,fecha_ingreso) VALUES (?,?,?,?,?)')->execute([$this->clinica(),$mascota,$nombre,$telefono,($this->reloj)()->format('Y-m-d H:i:s')]);
                $ingreso = (int)$this->conn->lastInsertId();
                $this->auditar('ingresos_emergencia',$ingreso,null,['id_mascota_provisional'=>$mascota,'estado'=>'pendiente'],'Ficha provisional sin propietario ni carnet (RE-4.15.3)');
            }
            $contexto = json_encode(['id_mascota_evaluada'=>$mascota,'fuente'=>'clinico_presencial','motivo'=>$motivo],JSON_THROW_ON_ERROR);
            $this->conn->prepare("INSERT INTO solicitudes_agenda (id_clinica,id_mascota,id_actor,origen,estado,estado_revision,nivel_aprobado,contexto_triage,huella_entrada,huella_revision,id_revisor,fecha_revision,motivo_revision,fuente_nivel) VALUES (?,?,?,'llegada','abierta','resuelta','rojo',?,?,?,?,?,?,'clinico_presencial')")->execute([$this->clinica(),$mascota,Contexto::idUsuario(),$contexto,hash('sha256',$contexto),hash('sha256',$contexto),Contexto::idUsuario(),($this->reloj)()->format('Y-m-d H:i:s'),$motivo]);
            $id = (int)$this->conn->lastInsertId();
            if ($ingreso !== null) {
                $this->conn->prepare('UPDATE solicitudes_agenda SET id_ingreso=? WHERE id_solicitud=? AND id_clinica=?')->execute([$ingreso,$id,$this->clinica()]);
            }
            $this->presencia($id);
            $this->auditar('solicitudes_agenda',$id,null,['prioridad'=>'rojo','id_mascota'=>$mascota],'Juicio clínico presencial; excepción plan/mora (RN-420)');
            return ['id_solicitud'=>$id,'message'=>'Rojo presencial registrado. Selecciona atender cuando comience la atención.'];
        });
    }

    /**
     * D (decisión del dueño, 2026-10-21): una cita reclasificada a rojo entra sola a la cola de Urgencias.
     * Con llegada registrada queda presente en espera; sin llegada queda derivada y avisa a la clínica.
     * La ejecuta el comando que reclasifica, dentro de su unidad coordinada.
     */
    public function derivarCita(array $cita, string $motivo): int
    {
        if (!$this->conn->inTransaction()) {
            throw new LogicException('La derivación roja exige la unidad del comando que reclasifica.');
        }
        $presente = $cita['hora_llegada'] !== null;
        $contexto = json_encode(['id_mascota_evaluada' => (int) $cita['id_mascota'], 'fuente' => 'reclasificacion_veterinaria', 'id_cita_origen' => (int) $cita['id_cita'], 'motivo' => $motivo], JSON_THROW_ON_ERROR);
        $ahora = ($this->reloj)()->format('Y-m-d H:i:s');
        $this->conn->prepare("INSERT INTO solicitudes_agenda (id_clinica,id_mascota,id_actor,origen,estado,estado_revision,nivel_aprobado,contexto_triage,huella_entrada,huella_revision,id_revisor,fecha_revision,motivo_revision,fuente_nivel) VALUES (?,?,?,?,?,'resuelta','rojo',?,?,?,?,?,?,'clinico_presencial')")
            ->execute([$this->clinica(), $cita['id_mascota'], Contexto::idUsuario(), $presente ? 'llegada' : 'interno', $presente ? 'abierta' : 'derivada', $contexto, hash('sha256', $contexto), hash('sha256', $contexto), Contexto::idUsuario(), $ahora, $motivo]);
        $id = (int) $this->conn->lastInsertId();
        $this->auditar('solicitudes_agenda', $id, null, ['prioridad' => 'rojo', 'id_cita_origen' => (int) $cita['id_cita'], 'presente' => $presente], 'Reclasificación a rojo: entra a Urgencias (RN-419, RN-420)');
        if ($presente) {
            // La llegada ya acreditada se conserva como presencia física; no se reinicia el orden.
            $this->presencia($id);
            $this->conn->prepare('UPDATE solicitudes_agenda SET fecha_llegada=? WHERE id_solicitud=? AND id_clinica=?')->execute([$cita['hora_llegada'], $id, $this->clinica()]);
        } else {
            (new EventoAgenda($this->conn, $this->clinica()))->interno('DERIVACION_ROJA', null, $id, 0, null, Roles::VETERINARIO);
        }
        return $id;
    }

    public function revisarContexto(int $id, int $version, string $motivo, ?string $clave): array
    {
        $this->autorizar([Roles::VETERINARIO]);
        $s = $this->solicitud($id);
        $motivo = ValidadorAgendaClinica::motivo($motivo);
        return $this->unidad([Roles::VETERINARIO], 'revisar_rojo_actual', [$id,$version,$motivo], $clave, fn () => $this->recursos([(int)$s['id_mascota']]), function () use ($id,$version,$motivo): array {
            $s = $this->solicitud($id);
            if ((int)$s['version_solicitud'] !== $version) {
                throw new InvalidArgumentException('La solicitud cambió. Revisa el contexto actual.');
            }
            require_once __DIR__ . '/ConciliacionAgenda.php';
            if (ConciliacionAgenda::revisionVigente($this->conn,$this->clinica(),$s) || in_array($s['estado'],['finalizada','retirada'],true) || ($s['nivel_aprobado'] ?? $s['nivel_calculado']) !== 'rojo') {
                throw new InvalidArgumentException('La solicitud no requiere esta revisión clínica.');
            }
            $this->exigirMascotaActiva((int)$s['id_mascota']);
            require_once __DIR__ . '/ConciliacionAgenda.php';
            $huella = ConciliacionAgenda::huellaContexto($this->conn,$s);
            $this->auditar('solicitudes_agenda',$id,['estado_revision'=>$s['estado_revision'],'huella_revision'=>$s['huella_revision'],'revisor'=>$s['id_revisor'],'fecha'=>$s['fecha_revision'],'motivo'=>$s['motivo_revision']],['estado_revision'=>'resuelta','huella_revision'=>$huella,'motivo'=>$motivo],'Revisión humana del contexto vigente, sin recalcular el pasado (B1.1)');
            $this->conn->prepare("UPDATE solicitudes_agenda SET estado_revision='resuelta',huella_revision=?,id_revisor=?,fecha_revision=?,motivo_revision=?,version_solicitud=version_solicitud+1 WHERE id_solicitud=? AND id_clinica=?")->execute([$huella,Contexto::idUsuario(),($this->reloj)()->format('Y-m-d H:i:s'),$motivo,$id,$this->clinica()]);
            return ['id_solicitud'=>$id,'message'=>'Contexto actual revisado por el veterinario.'];
        });
    }

    public function reordenar(int $id, string $motivo, ?string $clave): array
    {
        $motivo = ValidadorAgendaClinica::motivo($motivo);
        return $this->unidad([Roles::VETERINARIO], 'priorizar_rojo', [$id,$motivo], $clave, fn () => $this->recursos(), function () use ($id,$motivo): array {
            $s = $this->solicitud($id);
            $this->exigirPresente($s);
            $primero = (int)$this->filas('SELECT COALESCE(MIN(orden_espera),0)-1 AS primero FROM solicitudes_agenda WHERE id_clinica=? AND fecha_llegada IS NOT NULL',[$this->clinica()])[0]['primero'];
            $this->conn->prepare('UPDATE solicitudes_agenda SET orden_espera=?,version_solicitud=version_solicitud+1 WHERE id_solicitud=? AND id_clinica=?')->execute([$primero,$id,$this->clinica()]);
            $this->auditar('solicitudes_agenda',$id,['orden'=>$s['orden_espera']],['orden'=>$primero,'motivo'=>$motivo],'Reordenamiento clínico motivado de rojos presentes');
            return ['id_solicitud'=>$id,'message'=>'Orden clínico actualizado.'];
        });
    }

    private function exigirPresente(array $s): void
    {
        if ($s['fecha_llegada'] === null || $s['estado'] !== 'espera_presencial' || ($s['nivel_aprobado'] ?? $s['nivel_calculado']) !== 'rojo') {
            throw new InvalidArgumentException('Solo un rojo presente y en espera puede atenderse.');
        }
    }

    public function atender(int $id, int $tipo, string $decision, ?string $clave): array
    {
        $this->autorizar([Roles::VETERINARIO]);
        $s = $this->solicitud($id);
        return $this->unidad([Roles::VETERINARIO], 'atender_rojo', [$id,$tipo,$decision], $clave, fn () => $this->recursos([(int)$s['id_mascota']]), function () use ($id,$tipo,$decision): array {
            $s = $this->solicitud($id);
            $this->exigirPresente($s);
            $primera = $this->filas("SELECT id_solicitud FROM solicitudes_agenda WHERE id_clinica=? AND estado='espera_presencial' AND fecha_llegada IS NOT NULL AND COALESCE(nivel_aprobado,nivel_calculado)='rojo' ORDER BY orden_espera,fecha_llegada,id_solicitud LIMIT 1",[$this->clinica()])[0];
            if ((int)$primera['id_solicitud'] !== $id) {
                throw new InvalidArgumentException('Hay otro rojo presente antes. Reordena con motivo clínico si corresponde.');
            }
            require_once __DIR__ . '/ConciliacionAgenda.php';
            if (!ConciliacionAgenda::revisionVigente($this->conn,$this->clinica(),$s)) {
                throw new InvalidArgumentException('Revisa el contexto vigente antes de iniciar esta atención.');
            }
            $vet = Contexto::idUsuario();
            $this->veterinario($vet);
            $t = $this->filas('SELECT * FROM tipos_cita WHERE id_tipo_cita=? AND id_clinica=? AND activo=1',[$tipo,$this->clinica()])[0] ?? null;
            if (!$t) {
                throw new InvalidArgumentException('Elige un tipo activo de tu clínica.');
            }
            $ahora = ($this->reloj)()->format('Y-m-d H:i:s');
            $abiertas = $this->filas("SELECT c.*,t.pausable FROM citas c LEFT JOIN tipos_cita t ON t.id_tipo_cita=c.id_tipo_cita AND t.id_clinica=c.id_clinica WHERE c.id_clinica=? AND c.id_veterinario=? AND c.estado IN ('en_curso','sin_cerrar','pausada') ORDER BY c.id_cita",[$this->clinica(),$vet]);
            $pausadas = array_filter($abiertas,static fn ($c) => $c['estado']==='pausada');
            $activas = array_values(array_filter($abiertas,static fn ($c) => $c['estado']!=='pausada'));
            if ($pausadas && $decision === '') {
                throw new InvalidArgumentException('Ya existe una atención pausada. Registra una decisión clínica explícita.');
            }
            if ($pausadas && $decision !== '') {
                ValidadorAgendaClinica::motivo($decision);
                $this->auditar('solicitudes_agenda',$id,null,['decision'=>$decision],'Decisión clínica ante otro rojo, sin pausa anidada');
            }
            if ($activas && ($pausadas || count($activas)>1 || !(bool)$activas[0]['pausable'])) {
                return ['id_solicitud'=>$id,'espera'=>true,'message'=>'Rojo en espera. La atención actual no se puede interrumpir.'];
            }
            try {
                (new DisponibilidadAgenda($this->conn,$this->reloj))->exigirCapacidadUrgente($vet,(int)$s['id_mascota'],((int)$t['duracion_minutos']+(int)$t['margen_minutos'])*60);
            } catch (HorarioOcupado $error) {
                return ['id_solicitud'=>$id,'espera'=>true,'message'=>$error->getMessage()];
            }
            if ($activas) {
                $cita = $activas[0];
                (new EventoAgenda($this->conn,$this->clinica()))->exigirSinDespacho((int)$cita['id_cita']);
                $this->conn->prepare('INSERT INTO pausas_cita (id_clinica,id_cita,id_solicitud_roja,inicio,id_autor,motivo) VALUES (?,?,?,?,?,?)')->execute([$this->clinica(),$cita['id_cita'],$id,$ahora,$vet,'Atención roja presencial']);
                $this->conn->prepare("UPDATE citas SET estado='pausada',estado_reajuste='pendiente_calculo',version_agenda=version_agenda+1 WHERE id_cita=? AND id_clinica=?")->execute([$cita['id_cita'],$this->clinica()]);
                $this->auditar('citas',(int)$cita['id_cita'],['estado'=>$cita['estado']],['estado'=>'pausada','inicio'=>$ahora],'Pausa real para rojo presente (RN-427)');
            }
            $fin = ($this->reloj)()->modify('+'.(int)$t['duracion_minutos'].' minutes')->format('H:i:s');
            $this->conn->prepare("INSERT INTO citas (id_clinica,id_mascota,id_veterinario,id_tipo_cita,fecha,hora,hora_fin,hora_llegada,hora_inicio_real,motivo,prioridad,estado,duracion_minutos,margen_minutos,duracion_activa_objetivo_segundos,politica_temporal,origen,estado_reajuste) VALUES (?,?,?,?,?,?,?,?,?,?,'rojo','en_curso',?,?,?,'M','urgencia','pendiente_calculo')")->execute([$this->clinica(),$s['id_mascota'],$vet,$tipo,substr($ahora,0,10),substr($ahora,11),$fin,$s['fecha_llegada'],$ahora,'Urgencia presencial',(int)$t['duracion_minutos'],(int)$t['margen_minutos'],(int)$t['duracion_minutos']*60]);
            $cita = (int)$this->conn->lastInsertId();
            $this->conn->prepare("UPDATE solicitudes_agenda SET id_cita=?,estado='en_atencion',version_solicitud=version_solicitud+1 WHERE id_solicitud=? AND id_clinica=?")->execute([$cita,$id,$this->clinica()]);
            $this->auditar('citas',$cita,null,['estado'=>'en_curso','id_solicitud'=>$id],'Inicio real de urgencia sin cuota ni mora (RE-4.15.7)');
            (new CascadaAgenda($this->conn,$this->reloj))->procesar(substr($ahora,0,10));
            return ['id_solicitud'=>$id,'id_cita'=>$cita,'espera'=>false,'message'=>'Atención roja iniciada.'];
        });
    }

    /** El cierre clínico y la reanudación comparten la transacción y los recursos exteriores. */
    public function despuesDeCompletar(int $id): void
    {
        if (!$this->conn->inTransaction()) {
            throw new LogicException('Reanudar exige la unidad del cierre clínico.');
        }
        $solicitud = $this->filas('SELECT * FROM solicitudes_agenda WHERE id_clinica=? AND id_cita=?',[$this->clinica(),$id])[0] ?? null;
        if (!$solicitud) {
            return;
        }
        $this->conn->prepare("UPDATE solicitudes_agenda SET estado='finalizada',version_solicitud=version_solicitud+1 WHERE id_solicitud=? AND id_clinica=?")->execute([$solicitud['id_solicitud'],$this->clinica()]);
        $this->auditar('solicitudes_agenda',(int)$solicitud['id_solicitud'],['estado'=>$solicitud['estado']],['estado'=>'finalizada'],'Cierre de atención, sin reabrir revisión histórica');
        if ($solicitud['id_ingreso'] !== null) {
            require_once __DIR__ . '/ConciliacionAgenda.php';
            (new ConciliacionAgenda($this->conn,$this->reloj))->limpiarContacto((int)$solicitud['id_ingreso']);
        }
        $pausas = $this->filas('SELECT p.* FROM pausas_cita p JOIN citas c ON c.id_cita=p.id_cita AND c.id_clinica=p.id_clinica WHERE p.id_clinica=? AND p.id_solicitud_roja=? AND p.fin IS NULL AND c.estado=\'pausada\'',[$this->clinica(),$solicitud['id_solicitud']]);
        foreach ($pausas as $p) {
            $ahora = ($this->reloj)()->format('Y-m-d H:i:s');
            if ($ahora <= $p['inicio']) {
                throw new InvalidArgumentException('La pausa todavía no tiene un tramo transcurrido. Vuelve a confirmar el cierre.');
            }
            $this->conn->prepare('UPDATE pausas_cita SET fin=? WHERE id_pausa=? AND id_clinica=? AND fin IS NULL')->execute([$ahora,$p['id_pausa'],$this->clinica()]);
            $this->conn->prepare("UPDATE citas SET estado='en_curso',estado_reajuste='pendiente_calculo',version_agenda=version_agenda+1 WHERE id_cita=? AND id_clinica=? AND estado='pausada'")->execute([$p['id_cita'],$this->clinica()]);
            $this->auditar('citas',(int)$p['id_cita'],['estado'=>'pausada'],['estado'=>'en_curso','fin_pausa'=>$ahora],'Reanudación antes de siguientes citas (RN-427)');
        }
    }

    public function exigirInicioOrdinario(int $vet): void
    {
        if ($this->filas("SELECT 1 FROM citas WHERE id_clinica=? AND id_veterinario=? AND estado IN ('en_curso','sin_cerrar','pausada')",[$this->clinica(),$vet])) {
            throw new InvalidArgumentException('Completa o reanuda primero la atención interrumpida.');
        }
        if ($this->filas("SELECT 1 FROM solicitudes_agenda WHERE id_clinica=? AND fecha_llegada IS NOT NULL AND estado='espera_presencial' AND COALESCE(nivel_aprobado,nivel_calculado)='rojo'",[$this->clinica()])) {
            throw new InvalidArgumentException('Hay un rojo presente pendiente de atención veterinaria.');
        }
    }
}
