<?php
require_once __DIR__ . '/Roles.php';

/**
 * Contexto activo de la sesión (HU-T.17, RN-G01, RN-G18).
 *
 * Una persona tiene una sola identidad (usuarios.id_usuario) y puede entrar en
 * varios papeles: personal en cada clínica donde trabaja (usuario_clinica),
 * su portal de propietario (propietario_clinica) o la plataforma, si es
 * super-administrador. Los permisos de cada petición son los del contexto
 * activo, nunca la suma de todos.
 *
 * Un contexto es un arreglo con: clave (lo que envía el selector), tipo,
 * id_clinica (solo en los de clínica), id_rol, clinica y rol (para mostrar).
 */
final class Contexto
{
    public const CLINICA = 'clinica';
    public const PROPIETARIO = 'propietario';
    public const PLATAFORMA = 'plataforma';

    public static function deClinica(int $idClinica, string $nombreClinica, int $idRol): array
    {
        return [
            'clave' => self::CLINICA . ':' . $idClinica . ':' . $idRol,
            'tipo' => self::CLINICA,
            'id_clinica' => $idClinica,
            'id_rol' => $idRol,
            'clinica' => $nombreClinica,
            'rol' => Roles::nombre($idRol),
        ];
    }

    /** El portal es uno solo aunque la persona esté vinculada a varias clínicas. */
    public static function dePropietario(): array
    {
        return [
            'clave' => self::PROPIETARIO,
            'tipo' => self::PROPIETARIO,
            'id_clinica' => null,
            'id_rol' => Roles::PROPIETARIO,
            'clinica' => null,
            'rol' => Roles::nombre(Roles::PROPIETARIO),
        ];
    }

    public static function dePlataforma(): array
    {
        return [
            'clave' => self::PLATAFORMA,
            'tipo' => self::PLATAFORMA,
            'id_clinica' => null,
            'id_rol' => Roles::SUPER_ADMIN,
            'clinica' => null,
            'rol' => Roles::nombre(Roles::SUPER_ADMIN),
        ];
    }

    /** @param array<int, array> $disponibles */
    public static function buscar(array $disponibles, string $clave): ?array
    {
        foreach ($disponibles as $contexto) {
            if ($contexto['clave'] === $clave) {
                return $contexto;
            }
        }
        return null;
    }

    /**
     * Abre la identidad en la sesión, sin contexto todavía. Se llama después
     * de autenticar y de session_regenerate_id().
     */
    public static function iniciarIdentidad(int $idUsuario, string $nombre, bool $debeCambiarPassword, string $metodo): void
    {
        $_SESSION['id_usuario'] = $idUsuario;
        $_SESSION['usuario_nombre'] = $nombre;
        $_SESSION['debe_cambiar_password'] = $debeCambiarPassword ? 1 : 0;
        $_SESSION['login_method'] = $metodo;
        self::salir();
    }

    /**
     * Fija el contexto activo. usuario_id_rol se mantiene como espejo del rol
     * del contexto para el código de C2–C9 que aún lo lee: así ese código
     * actúa con el rol del contexto (RN-G18) y, si no lo reconoce, deniega.
     */
    public static function activar(array $contexto, int $totalDisponibles): void
    {
        $_SESSION['contexto'] = $contexto;
        $_SESSION['contextos_total'] = $totalDisponibles;
        $_SESSION['usuario_id_rol'] = $contexto['id_rol'];
    }

    /** Quita el contexto activo; la identidad sigue abierta. */
    public static function salir(): void
    {
        unset($_SESSION['contexto'], $_SESSION['usuario_id_rol']);
    }

    public static function actual(): ?array
    {
        $contexto = $_SESSION['contexto'] ?? null;
        return is_array($contexto) && isset($contexto['clave'], $contexto['id_rol']) ? $contexto : null;
    }

    public static function idUsuario(): ?int
    {
        $id = $_SESSION['id_usuario'] ?? null;
        return is_int($id) || ctype_digit((string) $id) ? (int) $id : null;
    }

    /** Clínica del contexto activo, o null si no es un contexto de clínica. */
    public static function clinicaActiva(): ?int
    {
        $c = self::actual();
        return $c !== null && $c['tipo'] === self::CLINICA ? (int) $c['id_clinica'] : null;
    }

    public static function rolActivo(): ?int
    {
        $c = self::actual();
        return $c !== null ? (int) $c['id_rol'] : null;
    }

    /** True si la persona tiene más de un contexto y puede cambiar. */
    public static function puedeCambiar(): bool
    {
        return (int) ($_SESSION['contextos_total'] ?? 0) > 1;
    }

    /** Acción de inicio de cada contexto (RE-T.1.4). */
    public static function destino(array $contexto): string
    {
        if ($contexto['tipo'] === self::PLATAFORMA) {
            return 'plataforma_inicio';
        }
        if ($contexto['tipo'] === self::PROPIETARIO) {
            return 'portal_propietario';
        }
        return (int) $contexto['id_rol'] === Roles::ADMIN ? 'admin_panel' : 'vet_area';
    }

    /** «Clínica Norte · Administrador», «Mi portal de propietario» o «Plataforma Zooki». */
    public static function etiqueta(array $contexto): string
    {
        if ($contexto['tipo'] === self::PLATAFORMA) {
            return 'Plataforma Zooki · Super-administrador';
        }
        if ($contexto['tipo'] === self::PROPIETARIO) {
            return 'Mi portal de propietario';
        }
        return $contexto['clinica'] . ' · ' . $contexto['rol'];
    }
}
