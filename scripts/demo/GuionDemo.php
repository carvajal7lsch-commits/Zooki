<?php
require_once __DIR__ . '/../../helpers/Roles.php';
require_once __DIR__ . '/../../helpers/ValidadorNit.php';

/** Datos ficticios del plan semilla-demo; sin credenciales persistidas ni IDs de negocio prefijados. */
final class GuionDemo
{
    public const PREFIJO_CORREO = 'zooki.vet+demo-';
    public const CLINICAS = [
        'sanroque' => ['Centro Veterinario San Roque', '999230001', 'Carrera 11 # 14-28, Centro, Florencia, Caquetá'],
        'huellitas' => ['Veterinaria Huellitas', '999230002', 'Calle 18 # 7-36, Juan XXIII, Florencia, Caquetá'],
        'bosque' => ['Clínica Veterinaria El Bosque', '999230003', 'Carrera 8 # 22-15, Los Alpes, Florencia, Caquetá'],
        'petcare' => ['PetCare Caquetá', '999230004', 'Calle 9 # 16-42, Siete de Agosto, Florencia, Caquetá'],
        'estrella' => ['Centro Animal La Estrella', '999230005', 'Carrera 4 # 5-21, La Estrella, Florencia, Caquetá'],
    ];
    public const DIAGNOSTICOS_PERRO = ['gastritis_aguda', 'giardiasis', 'sarna_sarcoptica', 'osteoartritis', 'dermatitis_atopica_canina', 'enfermedad_periodontal'];
    public const DIAGNOSTICOS_GATO = ['gastritis_aguda', 'giardiasis', 'dermatofitosis', 'complejo_respiratorio_felino'];
    public const CODIGOS = [
        'raza.collie', 'raza.criollo_canino', 'raza.criollo_felino', 'raza.golden', 'raza.gran_danes', 'raza.bulldog_frances', 'raza.labrador', 'raza.pastor_aleman', 'raza.shih_tzu',
        'farmaco.ivermectina', 'farmaco.selamectina', 'farmaco.meloxicam', 'farmaco.prednisolona', 'farmaco.oclacitinib', 'farmaco.amoxicilina_clavulanato', 'farmaco.doxiciclina', 'condicion.epilepsia',
        'sintoma.picazon', 'sintoma.costras_piel', 'sintoma.perdida_pelo', 'sintoma.lame_patas', 'sintoma.intentos_orinar_minimo', 'sintoma.dolor_orinar', 'sintoma.sed_aumentada', 'sintoma.secrecion_genital', 'sintoma.decaimiento', 'sintoma.vomito', 'sintoma.diarrea', 'sintoma.otro_no_se', 'sintoma.control_preventivo',
    ];

    public static function correo(string $alias): string
    {
        return self::PREFIJO_CORREO . $alias . '@gmail.com';
    }

    public static function nit(string $clave): string
    {
        $numero = self::CLINICAS[$clave][1];
        return $numero . '-' . ValidadorNit::digito($numero);
    }

    public static function personas(): array
    {
        $personas = [];
        $personal = [
            ['sanroque-admin', 'Natalia Ortega', 'sanroque', Roles::ADMIN],
            ['sanroque-vet-1', 'Valentina Rojas', 'sanroque', Roles::VETERINARIO],
            ['sanroque-vet-2', 'Camilo Suárez', 'sanroque', Roles::VETERINARIO],
            ['sanroque-vet-3', 'Andrés Bermúdez', 'sanroque', Roles::VETERINARIO],
            ['huellitas-admin', 'Daniela López', 'huellitas', Roles::ADMIN],
            ['huellitas-vet-1', 'Mateo Cabrera', 'huellitas', Roles::VETERINARIO],
            ['bosque-admin', 'Jorge Castillo', 'bosque', Roles::ADMIN],
            ['petcare-admin', 'Paula Moreno', 'petcare', Roles::ADMIN],
            ['estrella-admin', 'Martha Arias', 'estrella', Roles::ADMIN],
        ];
        foreach ($personal as $i => [$alias, $nombre, $clinica, $rol]) {
            $personas[$alias] = self::persona($alias, $nombre, $clinica, $rol, $i + 1);
        }
        $nombres = ['Laura Mora', 'Carlos Medina', 'Ana Torres', 'Luis Ríos', 'Diana Pérez', 'Pedro Gómez', 'María Ruiz', 'Juan Castro', 'Sofía Vargas', 'Julián Herrera', 'Lucía Ramírez', 'Sergio Martínez', 'Carmen Silva', 'Felipe Muñoz', 'Elena Cárdenas', 'Diego Ortiz', 'Patricia Delgado', 'Óscar Romero', 'Adriana Sánchez', 'Miguel Peña', 'Claudia Molina', 'Ricardo Álvarez', 'Sandra Quintero', 'Héctor Rincón', 'Beatriz Salazar', 'Daniel Acosta', 'Viviana León', 'Manuel Pardo', 'Rosa Sandoval', 'Esteban Marín', 'Andrea Valencia', 'Gustavo Vera'];
        foreach ($nombres as $i => $nombre) {
            $alias = $i === 0 ? 'portal' : ($i < 25 ? 'prop-' . ($i + 1) : 'huellitas-prop-' . ($i - 24));
            $personas[$alias] = self::persona($alias, $nombre, $i < 25 ? 'sanroque' : 'huellitas', Roles::PROPIETARIO, 100 + $i);
        }
        return $personas;
    }

    private static function persona(string $alias, string $nombre, string $clinica, int $rol, int $numero): array
    {
        return ['alias' => $alias, 'nombre' => $nombre, 'clinica' => $clinica, 'rol' => $rol, 'documento' => (string) (1902300000 + $numero), 'email' => self::correo($alias), 'telefono' => '310' . str_pad((string) (2300000 + $numero), 7, '0', STR_PAD_LEFT)];
    }

    /** Meses cumplidos, peso y propietario; los seis pacientes guion se conservan literalmente. */
    public static function mascotas(): array
    {
        $mascotas = [
            ['Rocco', 'Canino', 'Collie', 'Macho', 48, 28, 1, 'prop-2'],
            ['Max', 'Canino', 'Criollo (Mestizo)', 'Macho', 108, 30, 1, 'prop-3'],
            ['Mishi', 'Felino', 'Criollo', 'Hembra', 60, 4.2, 1, 'prop-4'],
            ['Simón', 'Felino', 'Criollo', 'Macho', 36, 4.8, 1, 'portal'],
            ['Luna', 'Canino', 'Golden Retriever', 'Hembra', 72, 27, 0, 'prop-5'],
            ['Kira', 'Canino', 'Criollo (Mestizo)', 'Hembra', 3, 4, 0, 'prop-6'],
            ['Nala', 'Felino', 'Criollo', 'Hembra', 36, 4.1, 1, 'portal'],
        ];
        $nombres = ['Toby', 'Bruno', 'Mila', 'Rocky', 'Pelusa', 'Coco', 'Nina', 'Apolo', 'Lola', 'Zeus', 'Kiwi', 'Tango', 'Mía', 'Thor', 'Lulú', 'Dante', 'Mango', 'Lía', 'Benji', 'Alma', 'Cosmo', 'Arya', 'Milo', 'Noa', 'Otto', 'Chispa', 'Nico', 'Uma'];
        $razas = [['Canino', 'Gran danés', 55], ['Canino', 'Bulldog Francés', 12], ['Canino', 'Labrador Retriever', 29], ['Canino', 'Pastor Alemán', 34], ['Canino', 'Poodle (Caniche)', 8], ['Canino', 'Shih Tzu', 6], ['Canino', 'Criollo (Mestizo)', 18], ['Felino', 'Criollo', 4.5]];
        foreach ($nombres as $i => $nombre) {
            [$especie, $raza, $peso] = $i < 6 ? $razas[$i] : ($i % 3 === 0 ? ['Felino', 'Criollo', 4.5] : ['Canino', 'Criollo (Mestizo)', 18]);
            $mascotas[] = [$nombre, $especie, $raza, $i % 2 === 0 ? 'Macho' : 'Hembra', 24 + $i * 2, $peso, 1, 'prop-' . (2 + $i % 24)];
        }
        foreach (['Bimba', 'Lucas', 'Cleopatra', 'Paco', 'Greta', 'Tomás', 'Sasha'] as $i => $nombre) {
            $mascotas[] = [$nombre, $i % 2 === 0 ? 'Canino' : 'Felino', $i % 2 === 0 ? 'Criollo (Mestizo)' : 'Criollo', 'Hembra', 30 + $i, $i % 2 === 0 ? 15 : 4, 1, 'huellitas-prop-' . ($i + 1)];
        }
        return $mascotas;
    }

    public static function casos(): array
    {
        return [
            'Simón' => [['intentos_orinar_minimo', 'dolor_orinar'], 'rojo', []],
            'Luna' => [['sed_aumentada', 'secrecion_genital', 'decaimiento'], 'naranja', ['dias_desde_celo' => 35]],
            'Kira' => [['vomito', 'diarrea'], 'naranja', []],
            'Nala' => [['otro_no_se'], 'amarillo', ['otro_sintoma' => 'Camina de lado y el propietario no reconoce ese cambio entre los síntomas del catálogo.']],
            'Toby' => [['control_preventivo'], 'verde', []],
        ];
    }

    public static function episodios(array $sintomas): array
    {
        return array_map(static fn (string $codigo): array => ['codigo' => 'sintoma.' . $codigo, 'tiempo' => $codigo === 'control_preventivo' ? 'no_aplica' : 'conocido', 'minutos' => $codigo === 'control_preventivo' ? null : 120], $sintomas);
    }
}
