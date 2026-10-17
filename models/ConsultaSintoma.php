<?php
require_once __DIR__ . '/ModeloHistoria.php';
require_once __DIR__ . '/../helpers/CampoInvalido.php';
require_once __DIR__ . '/../helpers/ValidadorClinico.php';

/**
 * M2 C5a (RN-210, A.1): síntomas del catálogo marcados en una consulta.
 * Se guardan por id de nodo, nunca extraídos de la anamnesis, y se ven con la
 * misma regla que su consulta (RN-113).
 */
class ConsultaSintoma extends ModeloHistoria
{
    /**
     * Forma de la entrada: una lista de códigos de texto, sin repetir y con
     * tope. Si el campo no llegó, no hay síntomas (siguen siendo opcionales).
     *
     * @return list<string>
     */
    public static function codigosDeEntrada($crudo): array
    {
        if ($crudo === null || $crudo === '' || $crudo === []) {
            return [];
        }
        if (!is_array($crudo) || !array_is_list($crudo)) {
            throw self::invalido();
        }
        $codigos = [];
        foreach ($crudo as $codigo) {
            if (!is_string($codigo) || trim($codigo) === '' || mb_strlen($codigo) > 100) {
                throw self::invalido();
            }
            $codigos[trim($codigo)] = true;
        }
        if (count($codigos) > ValidadorClinico::SINTOMAS_MAX) {
            $mensaje = 'Marca hasta ' . ValidadorClinico::SINTOMAS_MAX . ' síntomas.';
            throw new CampoInvalido('sintomas', $mensaje, $mensaje);
        }
        return array_keys($codigos);
    }

    /**
     * Cada código debe ser un síntoma activo del conocimiento global. Lo llama
     * Consulta::escribir dentro de su transacción, antes de escribir nada.
     *
     * @param list<string> $codigos
     * @return list<int> id_nodo en el mismo orden
     */
    public function nodosActivos(array $codigos): array
    {
        if ($codigos === []) {
            return [];
        }
        $marcas = implode(',', array_fill(0, count($codigos), '?'));
        $consulta = $this->conn->prepare("SELECT codigo, id_nodo FROM grafo_nodos
            WHERE codigo IN ($marcas) AND tipo = 'sintoma' AND activo = 1");
        $consulta->execute($codigos);
        $porCodigo = $consulta->fetchAll(PDO::FETCH_KEY_PAIR);

        $ids = [];
        foreach ($codigos as $codigo) {
            if (!isset($porCodigo[$codigo])) {
                throw self::invalido();
            }
            $ids[] = (int) $porCodigo[$codigo];
        }
        return $ids;
    }

    /** @param list<int> $idsNodo */
    public function insertarEnConsulta(int $idConsulta, array $idsNodo): void
    {
        if ($idsNodo === []) {
            return;
        }
        $this->exigirConsultaPropia($idConsulta, 'Síntomas sobre una consulta de otra clínica (RN-112)');
        $insertar = $this->conn->prepare('INSERT INTO consulta_sintomas (id_consulta, id_nodo) VALUES (?, ?)');
        foreach ($idsNodo as $idNodo) {
            $insertar->execute([$idConsulta, $idNodo]);
        }
    }

    /**
     * Síntomas de las consultas pedidas que la clínica activa puede ver,
     * agrupados por consulta. La etiqueta sale aunque el nodo se haya retirado
     * después: es lo que se registró.
     */
    public function deConsultas(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        [$visible, $parametros] = $this->consultaVisible('c');
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT cs.id_consulta, n.codigo, n.etiqueta
            FROM consulta_sintomas cs
            JOIN consultas c ON c.id_consulta = cs.id_consulta
            JOIN grafo_nodos n ON n.id_nodo = cs.id_nodo
            WHERE cs.id_consulta IN ($marcas) AND $visible
            ORDER BY cs.id_consulta, n.etiqueta, n.codigo";
        $consulta = $this->conn->prepare($sql);
        $consulta->execute([...array_map('intval', $ids), ...$parametros]);

        $porConsulta = [];
        foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $porConsulta[(int) $fila['id_consulta']][] = ['codigo' => $fila['codigo'], 'etiqueta' => $fila['etiqueta']];
        }
        return $porConsulta;
    }

    private static function invalido(): CampoInvalido
    {
        $aviso = ValidadorClinico::AVISOS_CONSULTA['sintomas'];
        return new CampoInvalido('sintomas', $aviso, 'Síntoma no válido o retirado del catálogo. ' . $aviso);
    }
}
