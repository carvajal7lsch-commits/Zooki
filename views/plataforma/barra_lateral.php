<?php
/**
 * Barra del panel de plataforma (M0.2), compartida por sus pantallas.
 * Espera $seccionPlataforma: 'clinicas' o 'grafo' (HU-2.12).
 * Rediseño (2026-10-26): usa la barra común, con nombres en el celular.
 */
$seccionPlataforma = $seccionPlataforma ?? 'clinicas';
$barraInicio = 'index.php?action=plataforma_inicio';
$barraItems = [
    ['href' => 'index.php?action=plataforma_inicio', 'icono' => 'fas fa-building', 'titulo' => 'Plataforma', 'corto' => 'Plataforma', 'activo' => $seccionPlataforma === 'clinicas', 'principal' => true],
    ['href' => 'index.php?action=plataforma_grafo', 'icono' => 'fas fa-project-diagram', 'titulo' => 'Conocimiento clínico', 'corto' => 'Conocimiento', 'activo' => $seccionPlataforma === 'grafo', 'principal' => true],
];
include __DIR__ . '/../partials/barra_navegacion.php';
