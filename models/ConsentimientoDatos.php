<?php
require_once __DIR__ . '/../helpers/PoliticaDatos.php';

/**
 * HU-T.19 — Prueba de la aceptación de la política (RE-T.19.2): quién, qué
 * versión, por qué medio, cuándo y desde qué IP.
 */
final class ConsentimientoDatos
{
    public function __construct(private PDO $conn)
    {
    }

    public function registrar(int $idUsuario, string $medio, string $ip, string $version = PoliticaDatos::VERSION): void
    {
        $medios = [PoliticaDatos::MEDIO_FORMULARIO, PoliticaDatos::MEDIO_GOOGLE, PoliticaDatos::MEDIO_ALTA_PERSONAL];
        if (!in_array($medio, $medios, true)) {
            throw new InvalidArgumentException('Medio de aceptación no válido.');
        }
        $stmt = $this->conn->prepare(
            'INSERT INTO consentimientos_datos (id_usuario, version_politica, medio, ip_address) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$idUsuario, $version, $medio, mb_substr($ip, 0, 45)]);
    }

    /** RE-T.19.3: true si la persona ya aceptó la versión vigente. */
    public function aceptoVigente(int $idUsuario): bool
    {
        $stmt = $this->conn->prepare(
            'SELECT 1 FROM consentimientos_datos WHERE id_usuario = ? AND version_politica = ? LIMIT 1'
        );
        $stmt->execute([$idUsuario, PoliticaDatos::VERSION]);
        return (bool) $stmt->fetchColumn();
    }
}
