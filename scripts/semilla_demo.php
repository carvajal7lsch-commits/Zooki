<?php
/** Consola de demostración2.3.0; nunca es una ruta web ni se ejecuta al importar sus pruebas. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../helpers/ZonaHoraria.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../helpers/EntornoLocal.php';
require_once __DIR__ . '/demo/SemillaDemo.php';

final class ComandoSemillaDemo
{
    public static function opciones(array $argumentos): array
    {
        $opciones = ['modo' => 'simular', 'entorno' => null, 'dia' => (new DateTimeImmutable('today', new DateTimeZone('America/Bogota')))->format('Y-m-d'), 'clave' => null];
        $vistos = [];
        $modos = [];
        foreach (array_slice($argumentos, 1) as $argumento) {
            [$nombre, $valor] = array_pad(explode('=', $argumento, 2), 2, null);
            if (isset($vistos[$nombre])) {
                throw new InvalidArgumentException('Argumento repetido: ' . $nombre);
            }
            $vistos[$nombre] = true;
            if (in_array($nombre, ['--si', '--escena', '--borrar'], true) && $valor === null) {
                $modos[] = substr($nombre, 2);
            } elseif (in_array($nombre, ['--entorno', '--dia', '--clave'], true) && $valor !== null && $valor !== '') {
                $opciones[substr($nombre, 2)] = $valor;
            } else {
                throw new InvalidArgumentException('Argumento no admitido: ' . $nombre);
            }
        }
        if (!in_array($opciones['entorno'], ['local', 'pruebas', 'produccion'], true)) {
            throw new InvalidArgumentException('Indica --entorno=local|pruebas|produccion.');
        }
        if (count($modos) > 1) {
            throw new InvalidArgumentException('Elige solo un modo: --si, --escena o --borrar.');
        }
        $opciones['modo'] = $modos[0] ?? 'simular';
        if ($opciones['modo'] !== 'si' && (isset($vistos['--dia']) || isset($vistos['--clave']))) {
            throw new InvalidArgumentException('--dia y --clave solo se admiten con --si.');
        }
        SemillaDemo::fecha($opciones['dia']);
        return $opciones;
    }

    public static function confirmar(string $entorno, string $escrito): void
    {
        if ($entorno !== 'local' && trim($escrito) !== $entorno) {
            throw new RuntimeException('La confirmación no coincide con el entorno. No se ejecutó la semilla.');
        }
    }

    public static function ejecutar(array $argumentos): int
    {
        ZonaHoraria::aplicar();
        try {
            $opciones = self::opciones($argumentos);
            if ($opciones['entorno'] !== 'local') {
                fwrite(STDOUT, 'Escribe ' . $opciones['entorno'] . ' para confirmar el entorno: ');
                self::confirmar($opciones['entorno'], (string) fgets(STDIN));
            }
            // Únicamente la conexión existente de la aplicación; no se abre ni se analiza .env aquí.
            // En la verificación se inyectan solo las variables de la base temporal predeterminada.
            $configuracion = Database::enPruebas() ? Database::configuracionPruebas() : null;
            ob_start();
            try {
                $db = (new Database($configuracion))->getConnection();
            } finally {
                $errorConexion = trim((string) ob_get_clean());
            }
            if (!$db instanceof PDO) {
                throw new RuntimeException('No se pudo conectar a la base: ' . $errorConexion);
            }
            $conexion = (string) $db->getAttribute(PDO::ATTR_CONNECTION_STATUS);
            if ($opciones['entorno'] === 'local' && ($motivo = EntornoLocal::motivoNoLocal($conexion)) !== null) {
                throw new RuntimeException('No se acepta --entorno=local: ' . $motivo . '. Usa el entorno real con su confirmación escrita.');
            }
            fwrite(STDOUT, 'Base de destino: ' . $db->query('SELECT DATABASE()')->fetchColumn() . ' (' . $conexion . ')' . PHP_EOL);
            $semilla = new SemillaDemo($db);
            if ($opciones['modo'] === 'simular') {
                $revision = $semilla->revisar();
                fwrite(STDOUT, 'Simulación sin escritura: 5 clínicas, ' . $revision['cuentas'] . ' cuentas y ' . $revision['mascotas'] . ' mascotas; 6 meses de historia y próximas citas.' . PHP_EOL);
                fwrite(STDOUT, 'Para crear usa --si [--dia=AAAA-MM-DD] [--clave=CLAVE]; --escena prepara solo el día actual y --borrar retira solo la demo.' . PHP_EOL);
                return 0;
            }
            $resultado = match ($opciones['modo']) {
                'si' => $semilla->crear($opciones['dia'], $opciones['clave']),
                'escena' => $semilla->escena(),
                'borrar' => $semilla->borrar(),
            };
            if (isset($resultado['cuentas'])) {
                fwrite(STDOUT, PHP_EOL . 'Rol | Clínica | Correo | Clave' . PHP_EOL);
                foreach ($resultado['cuentas'] as $cuenta) {
                    fwrite(STDOUT, implode(' | ', $cuenta) . PHP_EOL);
                }
            }
            fwrite(STDOUT, PHP_EOL . 'Resultado: ' . json_encode($resultado['conteos'] ?? $resultado, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL);
            foreach ($resultado['niveles'] ?? [] as $nombre => $nivel) {
                fwrite(STDOUT, $nombre . ': ' . $nivel['nivel'] . ', revisión=' . ($nivel['revision'] ? 'sí' : 'no') . PHP_EOL);
            }
            return 0;
        } catch (Throwable $error) {
            fwrite(STDERR, '[semilla demo] ' . $error->getMessage() . PHP_EOL);
            return 1;
        }
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(ComandoSemillaDemo::ejecutar($argv));
}
