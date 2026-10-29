<?php
// RE-2.12.5: conjunto real sobre SQLite efímera; nunca lee configuración local.
require __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../Support/ConocimientoPrueba.php';
require_once __DIR__ . '/../../../models/GrafoConocimiento.php';
session_set_save_handler(static fn () => true, static fn () => true, static fn () => '', static fn () => true, static fn () => true, static fn () => 0);
session_start();
Contexto::activar(Contexto::dePlataforma(), 1);
$_SESSION['usuario_nombre'] = 'Plataforma Zooki';
if (($argv[1] ?? '') !== 'datos') {
    require __DIR__ . '/../../../views/plataforma/grafo.php';
    exit;
}
$db = ConocimientoPrueba::sqlite();
$carpeta = __DIR__ . '/../../../database/conocimiento';
$nodos = json_decode(file_get_contents($carpeta . '/nodos.json'), true);
foreach ($nodos['nodos'] ?? $nodos as $nodo) {
    if (!is_array($nodo) || empty($nodo['taxonomia'])) {
        continue;
    }
    $catalogo = $nodo['taxonomia'];
    if (!empty($catalogo['especie'])) {
        $db->prepare('INSERT OR IGNORE INTO especies (nombre_especie) VALUES (?)')->execute([$catalogo['especie']]);
        if (!empty($catalogo['raza'])) {
            $db->prepare('INSERT INTO razas (id_especie, nombre_raza) SELECT id_especie, ? FROM especies WHERE nombre_especie = ? AND NOT EXISTS (SELECT 1 FROM razas r WHERE r.id_especie = especies.id_especie AND r.nombre_raza = ?)')->execute([$catalogo['raza'], $catalogo['especie'], $catalogo['raza']]);
        }
    }
}
(new CargadorConocimiento($db))->cargar($carpeta);
echo json_encode(['success' => true, 'datos' => (new GrafoConocimiento($db))->mapa()], JSON_UNESCAPED_UNICODE);
