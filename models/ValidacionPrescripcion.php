<?php
require_once __DIR__ . '/GrafoConocimiento.php';
require_once __DIR__ . '/PacienteDiagnostico.php';
require_once __DIR__ . '/MedicacionVigente.php';
require_once __DIR__ . '/../helpers/MotorPrescripcion.php';
require_once __DIR__ . '/../helpers/PrescripcionPublica.php';
require_once __DIR__ . '/../helpers/ValidadorPrescripcion.php';
require_once __DIR__ . '/../helpers/PrescripcionRechazada.php';

/** Coordinación interna de lectura, evaluación y decisión; no expone medicación protegida. */
final class ValidacionPrescripcion
{
    public function __construct(private PDO $db)
    {
    }

    public function evaluar(int $idMascota, array $filas, DateTimeImmutable $fecha): array
    {
        $medicacion = (new MedicacionVigente($this->db))->leer($idMascota, $fecha);
        $grafo = (new GrafoConocimiento($this->db))->instantanea();
        $contexto = (new PacienteDiagnostico($this->db))->contexto($idMascota, $grafo, $fecha, []);
        $limpias = [];
        foreach ($filas as $indice => $fila) {
            if (!is_array($fila)) {
                ValidadorPrescripcion::error($indice, 'farmaco', 'La fila debe ser un objeto.');
            }
            $limpias[] = ValidadorPrescripcion::fila($fila, $indice);
        }
        foreach ($limpias as $indice => $fila) {
            if ($fila['farmaco'] !== null && ($grafo->nodos[$fila['farmaco']]['tipo'] ?? null) !== 'farmaco') {
                ValidadorPrescripcion::error($indice, 'farmaco', 'Elige un fármaco activo del catálogo o deja el campo sin identificar.');
            }
        }
        $salida = [];
        foreach ($limpias as $indice => $fila) {
            $otras = [];
            foreach ($limpias as $otroIndice => $otra) {
                if ($otroIndice !== $indice) {
                    $otras[] = ['farmaco' => $otra['farmaco'], 'visible' => true, 'vigencia' => 'vigente', 'origen' => 'fila_nueva', 'fila' => $otroIndice];
                }
            }
            $interna = MotorPrescripcion::evaluar($fila['farmaco'], $contexto, $medicacion['alergias'], $medicacion['vigentes'], $otras, $grafo, $fecha);
            $publica = PrescripcionPublica::desde($interna, $grafo);
            // La clave nunca sale de la sesión: un hash público permitiría probar fármacos protegidos.
            $_SESSION['clave_huellas_prescripcion'] ??= bin2hex(random_bytes(32));
            $firmado = ['mascota' => $idMascota, 'usuario' => Contexto::idUsuario(), 'clinica' => Contexto::clinicaActiva(), 'fila' => $indice, 'farmacos' => array_column($limpias, 'farmaco'), 'contexto' => $contexto, 'fecha' => $fecha->format('Y-m-d'), 'evaluacion' => $interna];
            $publica['huella'] = hash_hmac('sha256', json_encode($firmado, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $_SESSION['clave_huellas_prescripcion']);
            $salida[] = ['interna' => $interna, 'publica' => $publica, 'entrada' => $fila];
        }
        return $salida;
    }

    public static function exigirDecisiones(array $evaluaciones): void
    {
        foreach ($evaluaciones as $indice => $evaluacion) {
            $interna = $evaluacion['interna'];
            $entrada = $evaluacion['entrada'];
            $publica = $evaluacion['publica'];
            $mensaje = null;
            if ($interna['bloqueada']) {
                $mensaje = 'Prescripción bloqueada. Elige otro fármaco; un bloqueo no se puede confirmar.';
            } elseif ($interna['requiere_decision'] && ($entrada['decision'] !== 'confirmar' || !hash_equals($publica['huella'], $entrada['huella']))) {
                $mensaje = 'Revisa las advertencias actuales y confirma esta fila con su huella vigente.';
            } elseif ($interna['requiere_conciliacion'] && $entrada['conciliacion'] === '') {
                $mensaje = 'Documenta la conciliación con el propietario antes de confirmar.';
            }
            if ($mensaje !== null) {
                throw new PrescripcionRechazada($indice, $publica, $interna, $mensaje);
            }
        }
    }

    public function auditarDecision(int $idConsulta, int $indice, array $evaluacion): void
    {
        $entrada = $evaluacion['entrada'];
        if ($entrada['decision'] !== 'confirmar' && $entrada['conciliacion'] === '') {
            return;
        }
        $datos = ['fila' => $indice, 'decision' => $entrada['decision'], 'conciliacion' => $entrada['conciliacion'], 'version' => $evaluacion['interna']['version'], 'evaluacion' => $evaluacion['interna']];
        if (!(new Auditoria($this->db))->log(Contexto::idUsuario(), 'OTHER', 'consultas', $idConsulta, null, $datos, 'Decisión de prescripción (RE-2.9.3)')) {
            throw new RuntimeException('No se pudo auditar la decisión de prescripción.');
        }
    }
}
