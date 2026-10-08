<?php
/**
 * HU-T.19 (RN-G19) — Versión vigente de la política de tratamiento de datos.
 *
 * Es el único lugar donde vive la versión: la página de la política, el
 * registro, el alta presencial y la re-aceptación al iniciar sesión la leen
 * de aquí. Al publicar una política nueva se cambian VERSION y VIGENCIA, y
 * cada titular la vuelve a aceptar en su siguiente inicio de sesión
 * (RE-T.19.3).
 */
final class PoliticaDatos
{
    /** Cabe en consentimientos_datos.version_politica (VARCHAR(20)). */
    public const VERSION = '2026-08-31';

    /** Fecha de entrada en vigencia que muestra la política (Decreto 1074 de 2015). */
    public const VIGENCIA = '31 de agosto de 2026';

    /** Medios del MER (consentimientos_datos.medio). */
    public const MEDIO_FORMULARIO = 'formulario';
    public const MEDIO_GOOGLE = 'google';
    public const MEDIO_ALTA_PERSONAL = 'alta_personal';
}
