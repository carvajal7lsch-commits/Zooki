<?php
require_once __DIR__ . '/ServicioAgendaClinica.php';
require_once __DIR__ . '/../helpers/ValidadorMascota.php';

/** B1.1/RE-4.15.3: identidad vigente única sin reescribir episodios ni contactos en bitácoras. */
final class ConciliacionAgenda extends ServicioAgendaClinica
{
    /** La huella liga revisión humana y ficha vigente; nunca vuelve a calcular el episodio. */
    public static function huellaContexto(PDO $db, array $s): string
    {
        $q = $db->prepare('SELECT * FROM mascotas WHERE id_mascota=?');
        $q->execute([$s['id_mascota']]);
        return hash('sha256',json_encode([(int)$s['id_solicitud'],(int)$s['id_mascota'],$q->fetch(PDO::FETCH_ASSOC)],JSON_THROW_ON_ERROR));
    }

    public static function revisionVigente(PDO $db, int $clinica, array $s): bool
    {
        if ($s['estado_revision'] === 'pendiente') {
            return false;
        }
        if ($s['id_ingreso'] === null || in_array($s['estado'],['finalizada','retirada'],true)) {
            return true;
        }
        $q = $db->prepare('SELECT estado FROM ingresos_emergencia WHERE id_ingreso=? AND id_clinica=?');
        $q->execute([$s['id_ingreso'],$clinica]);
        $estado = $q->fetchColumn();
        return $estado === 'pendiente' || ($s['estado_revision'] === 'resuelta' && is_string($s['huella_revision']) && hash_equals($s['huella_revision'],self::huellaContexto($db,$s)));
    }

    public function listar(): array
    {
        $this->autorizar([Roles::ADMIN,Roles::VETERINARIO]);
        return $this->filas("SELECT i.id_ingreso,i.id_mascota_provisional,i.id_mascota_final,i.estado,i.fecha_ingreso,i.nombre_acompanante,i.telefono_acompanante,i.motivo_conservacion_contacto,m.nombre FROM ingresos_emergencia i JOIN mascotas m ON m.id_mascota=i.id_mascota_provisional WHERE i.id_clinica=? AND (i.estado='pendiente' OR i.contacto_anonimizado_en IS NULL) ORDER BY i.fecha_ingreso,i.id_ingreso",[$this->clinica()]);
    }

    private function ingreso(int $id): array
    {
        $fila = $this->filas('SELECT * FROM ingresos_emergencia WHERE id_ingreso=? AND id_clinica=?',[$id,$this->clinica()])[0] ?? null;
        if (!$fila) {
            $this->denegarAcceso('ingresos_emergencia',$id,'Ingreso de otra clínica');
        }
        return $fila;
    }

    /** Evidencia existente: cuenta activada y vínculo legítimo activo; no bastan contacto o IDs. */
    private function titular(int $id): array
    {
        $fila = $this->filas("SELECT u.id_usuario,pc.fecha_vinculo FROM usuarios u JOIN propietario_clinica pc ON pc.id_propietario=u.id_usuario WHERE u.id_usuario=? AND u.estado=1 AND pc.id_clinica=? AND pc.estado='activo'",[$id,$this->clinica()])[0] ?? null;
        if (!$fila) {
            throw new InvalidArgumentException('Falta cuenta y vínculo verificados vigentes. Usa la confirmación habitual del titular; conserva la ficha provisional.');
        }
        return ['cuenta_activa'=>$id,'vinculo_clinica'=>$this->clinica(),'fecha_vinculo'=>$fila['fecha_vinculo']];
    }

    public function resolver(int $id, array $datos): array
    {
        $this->autorizar([Roles::ADMIN,Roles::VETERINARIO]);
        $ingreso = $this->ingreso($id);
        $provisional = (int)$ingreso['id_mascota_provisional'];
        $final = (int)($datos['id_mascota_final'] ?? $provisional);
        $titular = (int)($datos['id_propietario'] ?? 0);
        $motivo = ValidadorAgendaClinica::motivo($datos['evidencia'] ?? '');
        $conservar = ValidadorAgendaClinica::motivo($datos['motivo_conservacion'] ?? '',false);
        $entrada = array_intersect_key($datos,array_flip(['id_mascota_final','id_propietario','evidencia','motivo_conservacion','nombre','especie','raza','raza_indicada','sexo','peso','fecha_nacimiento']));
        $resultado = $this->unidad([Roles::ADMIN,Roles::VETERINARIO],'conciliar_ingreso',[$id,$entrada],$datos['clave_operacion'] ?? null,fn () => $this->recursos([$provisional,$final],[$titular]),function () use ($id,$provisional,$final,$titular,$motivo,$conservar,$datos): array {
            $ingreso = $this->ingreso($id);
            if ($ingreso['estado'] !== 'pendiente' || $ingreso['id_mascota_final'] !== null) {
                throw new InvalidArgumentException('El ingreso ya tiene identidad resuelta. Recarga la tarea.');
            }
            $p = $this->filas('SELECT * FROM mascotas WHERE id_mascota=?',[$provisional])[0];
            if (!(bool)$p['ficha_por_completar'] || $p['id_propietario'] !== null || (int)$p['id_clinica_registro'] !== $this->clinica()) {
                throw new InvalidArgumentException('La procedencia provisional requiere revisión.');
            }
            $evidencia = $this->titular($titular);
            $evidencia['referencia_operador'] = $motivo;
            $this->exigirMascotaActiva($provisional);
            // Guardia global mínima: no trasladar actos ni casos de otra clínica por inferencia.
            foreach (['citas','consultas','alertas_medicas','vacunas','desparasitaciones','solicitudes_agenda'] as $tabla) {
                if ($this->filas("SELECT 1 FROM $tabla WHERE id_mascota=? AND id_clinica<>?",[$provisional,$this->clinica()])) {
                    throw new InvalidArgumentException('La ficha tiene referencias que requieren revisión antes de conciliar.');
                }
            }
            $citas = $this->filas('SELECT id_cita,version_agenda FROM citas WHERE id_mascota=? AND id_clinica=?',[$provisional,$this->clinica()]);
            $eventos = new EventoAgenda($this->conn,$this->clinica());
            foreach ($citas as $cita) {
                $eventos->exigirSinDespacho((int)$cita['id_cita']);
            }
            $solicitudes = $this->filas('SELECT id_solicitud FROM solicitudes_agenda WHERE id_mascota=? AND id_clinica=?',[$provisional,$this->clinica()]);
            foreach ($solicitudes as $s) {
                if ($this->filas("SELECT 1 FROM eventos_agenda WHERE id_clinica=? AND id_solicitud=? AND estado IN ('reclamado','incierto')",[$this->clinica(),$s['id_solicitud']])) {
                    throw new InvalidArgumentException('Hay una comunicación en curso. Resuélvela antes de conciliar.');
                }
            }
            $hcP = $this->filas('SELECT numero_historia_clinica FROM mascota_clinica WHERE id_clinica=? AND id_mascota=?',[$this->clinica(),$provisional])[0]['numero_historia_clinica'];
            $hcF = $hcP;
            if ($final === $provisional) {
                $validacion = ValidadorMascota::validar($datos,($this->reloj)());
                if ($validacion['error']) {
                    throw new InvalidArgumentException($validacion['error']);
                }
                $v = $validacion['datos'];
                if (!$this->filas('SELECT 1 FROM especies WHERE id_especie=?',[$v['especie']]) || ($v['raza'] !== null && !$this->filas('SELECT 1 FROM razas WHERE id_raza=? AND id_especie=?',[$v['raza'],$v['especie']]))) {
                    throw new InvalidArgumentException('Especie o raza no vigentes.');
                }
                $this->conn->prepare('UPDATE mascotas SET id_propietario=?,nombre=?,id_especie=?,id_raza=?,raza_indicada=?,sexo=?,peso=?,fecha_nacimiento=?,ficha_por_completar=0,carnet_activo=0 WHERE id_mascota=?')->execute([$titular,$v['nombre'],$v['especie'],$v['raza'],$v['raza_indicada'],$v['sexo'],$v['peso'],$v['fecha_nacimiento'],$provisional]);
                $estado = 'completado';
            } else {
                $this->exigirMascotaActiva($final);
                $f = $this->filas('SELECT id_propietario,ficha_por_completar FROM mascotas WHERE id_mascota=?',[$final])[0];
                if ((int)$f['id_propietario'] !== $titular || (bool)$f['ficha_por_completar']) {
                    throw new InvalidArgumentException('La ficha final no pertenece al titular verificado o está incompleta.');
                }
                $hcF = $this->filas('SELECT numero_historia_clinica FROM mascota_clinica WHERE id_clinica=? AND id_mascota=?',[$this->clinica(),$final])[0]['numero_historia_clinica'];
                if (!$hcF && $hcP) {
                    $this->conn->prepare('UPDATE mascota_clinica SET numero_historia_clinica=NULL WHERE id_mascota=? AND id_clinica=?')->execute([$provisional,$this->clinica()]);
                    $this->conn->prepare('UPDATE mascota_clinica SET numero_historia_clinica=? WHERE id_mascota=? AND id_clinica=?')->execute([$hcP,$final,$this->clinica()]);
                    $hcF = $hcP;
                }
                foreach (['consultas','alertas_medicas','vacunas','desparasitaciones'] as $tabla) {
                    $this->conn->prepare("UPDATE $tabla SET id_mascota=? WHERE id_mascota=? AND id_clinica=?")->execute([$final,$provisional,$this->clinica()]);
                }
                $this->conn->prepare('UPDATE mascotas SET estado=0,carnet_activo=0 WHERE id_mascota=?')->execute([$provisional]);
                $this->conn->prepare("UPDATE mascota_clinica SET estado='inactivo' WHERE id_mascota=? AND id_clinica=?")->execute([$provisional,$this->clinica()]);
                $estado = 'consolidado';
            }
            // IDs, pactadas, reales, snapshots y evaluaciones JSON permanecen estables.
            $this->conn->prepare('UPDATE citas SET id_mascota=?,version_agenda=version_agenda+1 WHERE id_mascota=? AND id_clinica=?')->execute([$final,$provisional,$this->clinica()]);
            $this->conn->prepare("UPDATE solicitudes_agenda SET id_mascota=?,version_solicitud=version_solicitud+1,estado_revision=CASE WHEN estado IN ('finalizada','retirada') THEN estado_revision ELSE 'pendiente' END,huella_revision=CASE WHEN estado IN ('finalizada','retirada') THEN huella_revision ELSE NULL END WHERE id_mascota=? AND id_clinica=?")->execute([$final,$provisional,$this->clinica()]);
            // No cambiar el destinatario estable. El worker debe releer identidad, versión y revisión.
            foreach ($citas as $cita) {
                $this->conn->prepare("UPDATE eventos_agenda SET estado='omitido',error_tecnico='Identidad o contexto cambiaron' WHERE id_clinica=? AND id_cita=? AND estado IN ('pendiente','error')")->execute([$this->clinica(),$cita['id_cita']]);
            }
            foreach ($solicitudes as $s) {
                $this->conn->prepare("UPDATE eventos_agenda SET estado='omitido',error_tecnico='Identidad o contexto cambiaron' WHERE id_clinica=? AND id_solicitud=? AND estado IN ('pendiente','error')")->execute([$this->clinica(),$s['id_solicitud']]);
            }
            $evidencia['mapa'] = ['provisional'=>$provisional,'final'=>$final,'hc_provisional'=>$hcP,'hc_final'=>$hcF];
            $ahora = ($this->reloj)()->format('Y-m-d H:i:s');
            $this->conn->prepare('UPDATE ingresos_emergencia SET id_mascota_final=?,estado=?,id_conciliador=?,fecha_conciliacion=?,evidencia_conciliacion=?,motivo_conservacion_contacto=? WHERE id_mascota_provisional=? AND id_clinica=? AND estado=\'pendiente\'')->execute([$final,$estado,Contexto::idUsuario(),$ahora,json_encode($evidencia,JSON_THROW_ON_ERROR),$conservar ?: null,$provisional,$this->clinica()]);
            $this->auditar('ingresos_emergencia',$id,['id_mascota'=>$provisional],['id_mascota'=>$final,'estado'=>$estado,'mapa'=>$evidencia['mapa']],'Conciliación con cuenta/vínculo vigentes y referencia del operador (B1.1)');
            $this->limpiarContacto($id);
            return ['id_ingreso'=>$id];
        });
        // Replay reconstruido por referencia estable, sin volver a escribir ni devolver contacto antiguo.
        $vigente = $this->ingreso((int)$resultado['id_ingreso']);
        return ['id_ingreso'=>(int)$vigente['id_ingreso'],'id_mascota'=>(int)$vigente['id_mascota_final'],'message'=>'Identidad conciliada; revisa el contexto actual antes de nuevos comandos clínicos.'];
    }

    public function limpiarContacto(int $id): void
    {
        if (!$this->conn->inTransaction()) {
            throw new LogicException('La limpieza pertenece al cierre o conciliación coordinados.');
        }
        $i = $this->ingreso($id);
        if ($i['estado'] === 'pendiente' || $i['contacto_anonimizado_en'] !== null) {
            return;
        }
        if ($this->filas("SELECT 1 FROM solicitudes_agenda s LEFT JOIN citas c ON c.id_cita=s.id_cita AND c.id_clinica=s.id_clinica WHERE s.id_ingreso=? AND s.id_clinica=? AND (s.estado NOT IN ('finalizada','retirada') OR (c.id_cita IS NOT NULL AND c.estado NOT IN ('completada','cancelada','no_asistio')))",[$id,$this->clinica()])) {
            return;
        }
        $ahora = ($this->reloj)()->format('Y-m-d H:i:s');
        $this->conn->prepare('UPDATE ingresos_emergencia SET necesidad_contacto_resuelta_en=? WHERE id_ingreso=? AND id_clinica=?')->execute([$ahora,$id,$this->clinica()]);
        if ($i['motivo_conservacion_contacto']) {
            $this->auditar('ingresos_emergencia',$id,null,['contacto_conservado'=>true],'Conservación justificada; sin valores de contacto en auditoría');
            return;
        }
        $this->conn->prepare('UPDATE ingresos_emergencia SET nombre_acompanante=NULL,documento_acompanante=NULL,telefono_acompanante=NULL,contacto_anonimizado_en=? WHERE id_ingreso=? AND id_clinica=?')->execute([$ahora,$id,$this->clinica()]);
        $this->auditar('ingresos_emergencia',$id,null,['contacto_anonimizado'=>true],'Fin de necesidad de atención e identidad (RN-G16)');
    }
}
