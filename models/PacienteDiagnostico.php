<?php
require_once __DIR__ . '/ModeloHistoria.php';
require_once __DIR__ . '/AlertaMedica.php';
require_once __DIR__ . '/../helpers/ConocimientoClinico.php';

/** HU-2.8/HU-2.11: ficha y alertas globales; RN-G02 en el portal, RN-113 en clínica. */
final class PacienteDiagnostico extends ModeloHistoria
{
    public function contexto(int $idMascota, ConocimientoClinico $grafo, DateTimeImmutable $fecha, array $declarados): array
    {
        $propietario = (Contexto::actual()['tipo'] ?? null) === Contexto::PROPIETARIO;
        if (!$propietario) {
            $mascota = $this->exigirMascotaVinculada($idMascota);
            if ($mascota['vinculo'] !== 'activo') {
                $this->denegarAcceso('mascotas', $idMascota, 'Apoyo clínico sin vínculo activo (RN-113, RN-115)');
            }
        }
        $consulta = $this->conn->prepare("SELECT m.fecha_nacimiento, m.sexo, m.esterilizado,
                ne.codigo AS especie_codigo, nr.codigo AS raza_codigo
            FROM mascotas m
            " . ($propietario ? '' : "JOIN mascota_clinica mc ON mc.id_mascota = m.id_mascota AND mc.id_clinica = ? AND mc.estado = 'activo'") . "
            LEFT JOIN grafo_nodos ne ON ne.id_especie = m.id_especie AND ne.tipo = 'especie' AND ne.activo = 1
            LEFT JOIN grafo_nodos nr ON nr.id_raza = m.id_raza AND nr.tipo = 'raza' AND nr.activo = 1
            WHERE m.id_mascota = ?" . ($propietario ? ' AND m.id_propietario = ?' : ''));
        $consulta->execute($propietario ? [$idMascota, Contexto::idUsuario()] : [$this->clinica(), $idMascota]);
        $ficha = $consulta->fetch(PDO::FETCH_ASSOC);
        if ($ficha === false) {
            $this->denegarAcceso('mascotas', $idMascota, 'Ficha no autorizada para apoyo clínico (RN-G02, RN-113)');
        }
        $raza = $grafo->nodos[$ficha['raza_codigo']] ?? null;
        // Solo los códigos globales activos que usan los motores, sin leer historia protegida.
        $consultaAlertas = $this->conn->prepare('SELECT n.codigo FROM alertas_medicas a JOIN grafo_nodos n ON n.id_nodo = a.id_nodo AND n.activo = 1 WHERE a.id_mascota = ? AND a.activa = 1 ORDER BY a.id_alerta');
        $consultaAlertas->execute([$idMascota]);
        $alertas = $consultaAlertas->fetchAll(PDO::FETCH_ASSOC);
        $codigos = [];
        foreach ($alertas as $alerta) {
            if (isset($grafo->nodos[$alerta['codigo']])) {
                $codigos[] = $alerta['codigo'];
            }
        }
        return [
            'especie' => $ficha['especie_codigo'],
            'raza' => $raza === null ? null : ['codigo' => $raza['codigo'], 'atributos' => $raza['metadatos']],
            'edad_meses' => self::edadMeses($ficha['fecha_nacimiento'], $fecha),
            'sexo' => $ficha['sexo'] === 'Desconocido' ? null : $ficha['sexo'],
            'esterilizado' => $ficha['esterilizado'] === null ? null : (bool) $ficha['esterilizado'],
            'vacunacion' => $declarados['vacunacion'] ?? $this->vacunacionRegistrada($idMascota, $fecha),
            'dias_desde_celo' => $declarados['dias_desde_celo'] ?? null,
            'alertas_activas' => array_values(array_unique($codigos)),
            'exposiciones' => null,
        ];
    }

    /** RN-416/RN-113: después de autorizar la mascota, sus vacunas son globales entre clínicas. */
    private function vacunacionRegistrada(int $idMascota, DateTimeImmutable $fecha): ?string
    {
        $consulta = $this->conn->prepare('SELECT COUNT(*) AS total, MAX(CASE WHEN fecha_proxima_dosis < ? THEN 1 ELSE 0 END) AS vencida FROM vacunas WHERE id_mascota = ?');
        $consulta->execute([$fecha->format('Y-m-d'), $idMascota]);
        $vacunas = $consulta->fetch(PDO::FETCH_ASSOC);
        if ((int) $vacunas['total'] === 0) {
            return null;
        }
        return (int) $vacunas['vencida'] === 1 ? 'incompleta' : 'completa';
    }

    private static function edadMeses(?string $nacimiento, DateTimeImmutable $fecha): ?int
    {
        if ($nacimiento === null) {
            return null;
        }
        $inicio = DateTimeImmutable::createFromFormat('!Y-m-d', $nacimiento, $fecha->getTimezone());
        if ($inicio === false || $inicio->format('Y-m-d') !== $nacimiento || $inicio > $fecha) {
            return null;
        }
        $edad = $inicio->diff($fecha);
        return $edad->y * 12 + $edad->m;
    }
}
