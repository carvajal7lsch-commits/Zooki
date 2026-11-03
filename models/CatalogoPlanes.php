<?php

/** RN-008: excepción pública del catálogo global; nunca devuelve datos de clínicas. */
final class CatalogoPlanes
{
    public static function profesional(PDO $db): array
    {
        return $db->query("SELECT * FROM planes WHERE nombre='Profesional' AND estado=1")->fetch(PDO::FETCH_ASSOC)
            ?: throw new RuntimeException('Falta el plan profesional activo.');
    }
}
