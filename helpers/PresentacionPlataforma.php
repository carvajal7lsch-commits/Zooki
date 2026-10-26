<?php
/**
 * M0.2 — Cómo se muestran los datos del panel del super-administrador
 * (HU-0.3). Solo da formato a metadatos de la clínica y a cantidades del uso
 * del plan; nunca recibe datos clínicos (RN-004, RN-G13).
 */
final class PresentacionPlataforma
{
    public const ESTADOS = [
        'activa' => 'Activa',
        'pendiente_verificacion' => 'Pendiente de verificación',
        'suspendida' => 'Suspendida',
        'baja' => 'Dada de baja',
    ];

    private const RECURSOS = ['personal' => 'Personal', 'mascotas' => 'Mascotas', 'citas' => 'Citas del mes'];

    public static function situacion(array $vigencia): string
    {
        if ($vigencia['legado_sin_fecha']) {
            return 'Legado sin fecha';
        }
        return ['al_dia' => 'Al día', 'gracia' => 'En gracia', 'congelada' => 'Congelada', 'sin_vigencia' => 'Sin periodo de pago'][$vigencia['situacion']] ?? 'Sin periodo de pago';
    }

    private const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    /** Desde este porcentaje la barra avisa que el cupo se está acabando. */
    private const UMBRAL_ALTO = 80;

    /** El NIT se guarda con el DV pegado (ValidadorNit::normalizar); se muestra con su guion. */
    public static function nit(?string $nit): string
    {
        $nit = (string) $nit;
        if (!preg_match('/^\d{2,}$/D', $nit)) {
            return $nit;
        }
        return substr($nit, 0, -1) . '-' . substr($nit, -1);
    }

    public static function estado(string $estado): string
    {
        return self::ESTADOS[$estado] ?? $estado;
    }

    private const ICONOS_ESTADO = [
        'activa' => 'bi-check-circle',
        'pendiente_verificacion' => 'bi-hourglass-split',
        'suspendida' => 'bi-pause-circle',
        'baja' => 'bi-x-circle',
    ];

    /** Clase de la etiqueta del estado: la activa va en azul (identidad de Zooki), las demás en gris. */
    public static function claseEstado(string $estado): string
    {
        return 'plataforma-estado--' . (array_key_exists($estado, self::ESTADOS) ? str_replace('_verificacion', '', $estado) : 'baja');
    }

    /** El icono distingue el estado sin depender del color. */
    public static function iconoEstado(string $estado): string
    {
        return self::ICONOS_ESTADO[$estado] ?? 'bi-x-circle';
    }

    /** «11 oct 2026»: la fecha de registro sin la hora cruda. */
    public static function fecha(?string $fecha): string
    {
        $marca = strtotime((string) $fecha);
        if ($marca === false) {
            return '—';
        }
        return date('j', $marca) . ' ' . self::MESES[(int) date('n', $marca) - 1] . ' ' . date('Y', $marca);
    }

    /** Plan y estado de la suscripción en palabras: el gratuito no tiene pago que mostrar. */
    public static function plan(?string $plan, ?string $suscripcion): string
    {
        if ($plan === null || $plan === '') {
            return 'Sin plan hasta verificar';
        }
        $suscripciones = ['activa' => 'suscripción activa', 'suspendida' => 'suscripción suspendida', 'cancelada' => 'suscripción cancelada'];
        if ($suscripcion === null || $suscripcion === '') {
            return $plan . ' · sin pago';
        }
        return $plan . ' · ' . ($suscripciones[$suscripcion] ?? $suscripcion);
    }

    /** La línea de la suscripción en la tarjeta; el plan va en su propia etiqueta. */
    public static function suscripcion(?string $plan, ?string $suscripcion): string
    {
        if ($plan === null || $plan === '') {
            return 'Plan al verificar el correo';
        }
        $suscripciones = ['activa' => 'Suscripción activa', 'suspendida' => 'Suscripción suspendida', 'cancelada' => 'Suscripción cancelada'];
        if ($suscripcion === null || $suscripcion === '') {
            return 'Sin pago';
        }
        return $suscripciones[$suscripcion] ?? $suscripcion;
    }

    public static function administrador(array $clinica): string
    {
        if (!empty($clinica['administrador'])) {
            return (string) $clinica['administrador'];
        }
        if (!empty($clinica['administrador_pendiente'])) {
            return $clinica['administrador_pendiente'] . ' (por confirmar)';
        }
        return 'Sin administrador activo';
    }

    /**
     * Una fila por recurso: nombre, texto, porcentaje y nivel de la barra.
     * Sin límite (plan Profesional) no hay barra que llenar.
     */
    public static function uso(array $usoPlan): array
    {
        $filas = [];
        foreach ($usoPlan as $recurso => $cupo) {
            $uso = (int) $cupo['uso'];
            $limite = $cupo['limite'] === null ? null : (int) $cupo['limite'];
            $porcentaje = $limite === null || $limite <= 0 ? 0 : min(100, (int) round($uso * 100 / $limite));
            $nivel = 'libre';
            if ($limite !== null) {
                $nivel = $porcentaje >= 100 ? 'lleno' : ($porcentaje >= self::UMBRAL_ALTO ? 'alto' : 'normal');
            }
            $filas[] = [
                'nombre' => self::RECURSOS[$recurso] ?? ucfirst((string) $recurso),
                'uso' => $uso,
                'limite' => $limite,
                'texto' => $limite === null ? $uso . ' · sin límite' : $uso . ' / ' . $limite,
                'nivel' => $nivel,
            ];
        }
        return $filas;
    }

    /** M0.2.1: los topes de un plan en una línea, para su tarjeta en el modal. */
    public static function topesPlan(array $plan): string
    {
        $topes = [
            'personal' => $plan['limite_personal'] ?? null,
            'mascotas' => $plan['limite_mascotas'] ?? null,
            'citas al mes' => $plan['limite_citas_mes'] ?? null,
        ];
        if (count(array_filter($topes, static fn ($tope): bool => $tope !== null)) === 0) {
            return 'Sin límites de personal, mascotas ni citas';
        }
        $partes = [];
        foreach ($topes as $nombre => $tope) {
            $partes[] = ($tope === null ? 'sin límite de' : (int) $tope) . ' ' . $nombre;
        }
        return ucfirst(implode(' · ', $partes));
    }

    /** Dos letras para el distintivo de la tarjeta. */
    public static function iniciales(string $nombre): string
    {
        $palabras = preg_split('/\s+/u', trim($nombre), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
        $letras = array_map(static fn (string $palabra): string => mb_substr($palabra, 0, 1), array_slice($palabras, 0, 2));
        return mb_strtoupper(implode('', $letras));
    }

    /** Texto en minúsculas con nombre y NIT (con y sin guion) para el buscador del navegador. */
    public static function buscable(array $clinica): string
    {
        return mb_strtolower($clinica['nombre'] . ' ' . $clinica['nit'] . ' ' . self::nit($clinica['nit']));
    }

    /** Contadores de las pestañas Soporte (casos abiertos) y Seguridad (IP bloqueadas). */
    public static function contadores(array $casos, array $ips): array
    {
        return [
            'casos' => count(array_filter($casos, static fn (array $caso): bool => $caso['estado'] === 'abierto')),
            'ips' => count($ips),
        ];
    }
}
