<?php

/** M0-S: réplica portable de las columnas aprobadas; sin tocar bases externas. */
final class EsquemaSuscripcion
{
    public static function completar(PDO $db): void
    {
        $db->exec('ALTER TABLE planes ADD COLUMN precio_anual INTEGER DEFAULT 899900');
        $db->exec('UPDATE planes SET precio_mensual=89990 WHERE id_plan=2');
        foreach (['suscripcion_gracia_dias' => 3, 'aviso_vencimiento_1_dias' => 7, 'aviso_vencimiento_2_dias' => 1] as $campo => $defecto) {
            $db->exec("ALTER TABLE clinicas ADD COLUMN $campo INTEGER NOT NULL DEFAULT $defecto");
        }
        $columnas = ['gestion_cobro' => "TEXT NOT NULL DEFAULT 'legado'", 'periodo_inicio' => 'TEXT', 'periodo_fin' => 'TEXT',
            'estado_cobro' => "TEXT NOT NULL DEFAULT 'sin_vigencia'", 'version_periodo' => 'INTEGER NOT NULL DEFAULT 0',
            'cobro_automatico' => 'INTEGER NOT NULL DEFAULT 0', 'id_fuente_activa' => 'INTEGER', 'autorizado_por' => 'INTEGER',
            'autorizado_en' => 'TEXT', 'retirado_en' => 'TEXT', 'motivo_automatico_apagado' => 'TEXT', 'cambio_ciclo_siguiente' => 'TEXT'];
        foreach ($columnas as $campo => $tipo) {
            $db->exec("ALTER TABLE suscripciones ADD COLUMN $campo $tipo");
        }
        $db->exec(file_get_contents(__DIR__ . '/suscripcion.sqlite.sql'));
    }
}
