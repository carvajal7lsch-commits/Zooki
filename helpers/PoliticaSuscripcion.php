<?php

/** Ajuste 2 de M0-S §9: políticas fijas de plataforma, no parámetros por clínica. */
final class PoliticaSuscripcion
{
    public const HORA_AVISO = '07:00:00'; // RN-017.
    public const REINTENTOS_HORAS = [0, 24, 48]; // RN-016, reservados para S3.
    public const COTIZACION_MINUTOS = 15; // RN-014, reservado para S2.
    public const RECLAMACION_SEGUNDOS = 300; // RN-017: una entrega incierta no se repite.
    public const ZONA = 'America/Bogota';
}
