<?php
require_once __DIR__ . '/../../helpers/PoliticaPassword.php';
require_once __DIR__ . '/../../helpers/PoliticaDatos.php';
require_once __DIR__ . '/../../models/ConsentimientoDatos.php';
require_once __DIR__ . '/../../helpers/ValidadorNit.php';

/**
 * D2.1 — Apoyo de scripts/dev/datos_prueba.php, separado para probarlo sin la
 * base del usuario: la clave común opcional (--clave) y la política aceptada
 * de los usuarios de prueba.
 */
final class DatosPrueba
{
    /** E2: comprobar todos los conflictos antes de regenerar claves o modificar clínicas. */
    public static function revisarClinicas(PDO $db, array $esperadas, bool $bloquear = false): array
    {
        $bloqueo = $bloquear && $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $filas = $db->query('SELECT id_clinica, nombre, nit FROM clinicas' . $bloqueo)->fetchAll(PDO::FETCH_ASSOC);
        $propias = [];
        $ajenas = [];
        foreach ($filas as $fila) {
            $clavePropia = null;
            foreach ($esperadas as $clave => $esperada) {
                if (ValidadorNit::normalizar($fila['nit']) !== ValidadorNit::normalizar($esperada['nit'])) {
                    continue;
                }
                if ($fila['nombre'] !== $esperada['nombre'] || isset($propias[$clave])) {
                    throw new InvalidArgumentException('El NIT de prueba ' . $esperada['nit'] . ' ya pertenece a otra clínica local. No se cambió ningún dato.');
                }
                $clavePropia = $clave;
                $propias[$clave] = (int) $fila['id_clinica'];
                break;
            }
            if ($clavePropia === null) {
                $ajenas[] = $fila['nit'];
            }
        }
        return ['propias' => $propias, 'ajenas' => $ajenas];
    }

    /**
     * F0 (decisión del usuario, 2026-10-11): las clínicas de prueba quedan en
     * el plan Profesional con una suscripción activa y al día, para que sus
     * tres personas no choquen con el tope del gratuito (HU-0.4). Solo para
     * estas clínicas de scripts/dev; nunca para la base de producción.
     * Repetible: devuelve true si creó la suscripción ahora.
     */
    public static function planProfesional(PDO $db, int $idClinica): bool
    {
        $plan = (int) $db->query("SELECT id_plan FROM planes WHERE nombre = 'Profesional'")->fetchColumn();
        if ($plan === 0) {
            throw new RuntimeException('Falta el plan Profesional en la semilla.');
        }
        $db->prepare('UPDATE clinicas SET id_plan = ? WHERE id_clinica = ?')->execute([$plan, $idClinica]);
        $ultima = $db->prepare('SELECT id_plan, estado, al_dia FROM suscripciones WHERE id_clinica = ? ORDER BY id_suscripcion DESC LIMIT 1');
        $ultima->execute([$idClinica]);
        $fila = $ultima->fetch(PDO::FETCH_ASSOC);
        if ($fila && (int) $fila['id_plan'] === $plan && $fila['estado'] === 'activa' && (int) $fila['al_dia'] === 1) {
            return false;
        }
        // La que estaba activa (la del gratuito) se cierra: queda una sola vigente.
        $db->prepare("UPDATE suscripciones SET estado = 'cancelada', fecha_fin = ? WHERE id_clinica = ? AND estado = 'activa'")
            ->execute([date('Y-m-d'), $idClinica]);
        $db->prepare("INSERT INTO suscripciones (id_clinica, id_plan, estado, ciclo, al_dia, fecha_inicio) VALUES (?, ?, 'activa', 'mensual', 1, ?)")
            ->execute([$idClinica, $plan, date('Y-m-d')]);
        return true;
    }

    /** Valor de --clave=<clave>, o null si no se pasó. */
    public static function claveDeArgumentos(array $argumentos): ?string
    {
        foreach ($argumentos as $argumento) {
            if (str_starts_with((string) $argumento, '--clave=')) {
                return substr((string) $argumento, strlen('--clave='));
            }
        }
        return null;
    }

    /**
     * Motivo por el que la clave común no sirve, o null si cumple la
     * política (RN-G10) para todas las personas: la política también la
     * compara con el documento, el nombre y el correo de cada una.
     *
     * @param array<int, array> $personas filas [nombre, documento, correo, ...]
     */
    public static function motivoClaveInvalida(string $clave, array $personas): ?string
    {
        foreach ($personas as [$nombre, $documento, $correo]) {
            $motivo = PoliticaPassword::validar($clave, [(string) $documento, (string) $nombre, (string) $correo]);
            if ($motivo !== null) {
                return "no cumple la política para {$nombre}: " . rtrim($motivo, ".");
            }
        }
        return null;
    }

    /**
     * RE-T.19.3: la persona de prueba ya aceptó la versión vigente, para no
     * pedírsela en cada prueba manual. Devuelve true si la registró ahora.
     */
    public static function aceptarPolitica(PDO $db, int $idUsuario): bool
    {
        $consentimientos = new ConsentimientoDatos($db);
        if ($consentimientos->aceptoVigente($idUsuario)) {
            return false;
        }
        $consentimientos->registrar($idUsuario, PoliticaDatos::MEDIO_FORMULARIO, '127.0.0.1');
        return true;
    }
}
