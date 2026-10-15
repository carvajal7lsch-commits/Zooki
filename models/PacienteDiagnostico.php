<?php
require_once __DIR__ . '/ModeloHistoria.php';
require_once __DIR__ . '/AlertaMedica.php';
require_once __DIR__ . '/../helpers/ConocimientoClinico.php';

/** HU-2.8 / RN-113: solo la ficha y alertas globales, nunca consultas protegidas. */
final class PacienteDiagnostico extends ModeloHistoria
{
    public function contexto(int $idMascota, ConocimientoClinico $grafo, DateTimeImmutable $fecha, array $declarados): array
    {
        $mascota = $this->exigirMascotaVinculada($idMascota);
        if ($mascota['vinculo'] !== 'activo') {
            $this->denegarAcceso('mascotas', $idMascota, 'Sugerencia de diagnósticos sin vínculo activo (RN-113, RN-115)');
        }
        $consulta = $this->conn->prepare("SELECT m.fecha_nacimiento, m.sexo, m.esterilizado,
                ne.codigo AS especie_codigo, nr.codigo AS raza_codigo
            FROM mascotas m
            JOIN mascota_clinica mc ON mc.id_mascota = m.id_mascota AND mc.id_clinica = ? AND mc.estado = 'activo'
            LEFT JOIN grafo_nodos ne ON ne.id_especie = m.id_especie AND ne.tipo = 'especie' AND ne.activo = 1
            LEFT JOIN grafo_nodos nr ON nr.id_raza = m.id_raza AND nr.tipo = 'raza' AND nr.activo = 1
            WHERE m.id_mascota = ?");
        $consulta->execute([$this->clinica(), $idMascota]);
        $ficha = $consulta->fetch(PDO::FETCH_ASSOC);
        if ($ficha === false) {
            $this->denegarAcceso('mascotas', $idMascota, 'El vínculo dejó de estar activo (RN-113)');
        }
        $raza = $grafo->nodos[$ficha['raza_codigo']] ?? null;
        $alertas = (new AlertaMedica($this->conn))->deMascota($idMascota)['activas'];
        $codigos = [];
        foreach ($alertas as $alerta) {
            if (isset($grafo->nodos[$alerta['nodo_codigo']])) {
                $codigos[] = $alerta['nodo_codigo'];
            }
        }
        return [
            'especie' => $ficha['especie_codigo'],
            'raza' => $raza === null ? null : ['codigo' => $raza['codigo'], 'atributos' => $raza['metadatos']],
            'edad_meses' => self::edadMeses($ficha['fecha_nacimiento'], $fecha),
            'sexo' => $ficha['sexo'] === 'Desconocido' ? null : $ficha['sexo'],
            'esterilizado' => $ficha['esterilizado'] === null ? null : (bool) $ficha['esterilizado'],
            'vacunacion' => $declarados['vacunacion'] ?? null,
            'dias_desde_celo' => $declarados['dias_desde_celo'] ?? null,
            'alertas_activas' => array_values(array_unique($codigos)),
            'exposiciones' => null,
        ];
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
