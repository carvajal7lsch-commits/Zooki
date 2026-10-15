<?php
require_once __DIR__ . '/../helpers/ArchivosConocimiento.php';
require_once __DIR__ . '/../helpers/ValidadorAlertaMedica.php';

/**
 * RE-2.1.5 / A.2 — Nodos activos del Grafo I que se pueden enlazar a una alerta,
 * buscados por etiqueta y sinónimo. El conocimiento es global (RN-212): solo lee
 * grafo_nodos y grafo_sinonimos, nunca datos de pacientes.
 */
final class NodosEnlazables
{
    public const RESULTADOS = 8;

    public function __construct(private PDO $db)
    {
    }

    /** @return list<array{codigo: string, etiqueta: string, tipo: string, coincidencia: string}> */
    public function buscar(string $tipoAlerta, string $texto): array
    {
        $tipos = ValidadorAlertaMedica::tiposDeNodo($tipoAlerta);
        $buscado = ArchivosConocimiento::normalizarTexto(ValidadorAlertaMedica::busqueda($texto));
        if ($tipos === [] || mb_strlen($buscado) < ValidadorAlertaMedica::BUSQUEDA_MIN) {
            return [];
        }
        $marcas = implode(', ', array_fill(0, count($tipos), '?'));
        $consulta = $this->db->prepare("SELECT n.codigo, n.etiqueta, n.tipo, s.texto AS sinonimo
            FROM grafo_nodos n
            LEFT JOIN grafo_sinonimos s ON s.id_nodo = n.id_nodo AND s.activo = 1
            WHERE n.activo = 1 AND n.tipo IN ($marcas)
            ORDER BY n.etiqueta");
        $consulta->execute($tipos);
        $mejores = [];
        foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            foreach ([[$fila['etiqueta'], 0], [$fila['sinonimo'], 1]] as [$candidato, $esSinonimo]) {
                if ($candidato === null) {
                    continue;
                }
                $puntaje = self::puntaje(ArchivosConocimiento::normalizarTexto($candidato), $buscado);
                if ($puntaje === null) {
                    continue;
                }
                $puntaje += $esSinonimo;
                $actual = $mejores[$fila['codigo']] ?? null;
                if ($actual === null || $puntaje < $actual['puntaje']) {
                    $mejores[$fila['codigo']] = ['codigo' => $fila['codigo'], 'etiqueta' => $fila['etiqueta'], 'tipo' => $fila['tipo'], 'coincidencia' => $candidato, 'puntaje' => $puntaje];
                }
            }
        }
        usort($mejores, static fn (array $a, array $b): int => [$a['puntaje'], $a['etiqueta']] <=> [$b['puntaje'], $b['etiqueta']]);
        return array_map(static function (array $fila): array {
            unset($fila['puntaje']);
            return $fila;
        }, array_slice($mejores, 0, self::RESULTADOS));
    }

    /** Primero lo exacto, luego lo que empieza igual y al final lo que contiene. */
    private static function puntaje(string $candidato, string $buscado): ?int
    {
        if ($candidato === $buscado) {
            return 0;
        }
        if (str_starts_with($candidato, $buscado)) {
            return 2;
        }
        return str_contains($candidato, $buscado) ? 4 : null;
    }
}
