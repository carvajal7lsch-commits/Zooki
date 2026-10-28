<?php
// RNF-16: doce pacientes simulados para medir 2/3/4 columnas sin usar una base de trabajo.
function pacientesDirectorioDatos(): array
{
    $nombres = ['Doki', 'Gato', 'Kira', 'Luna', 'Pelusa', 'Toby', 'Max', 'Nala', 'Rocky', 'Mia', 'Simba', 'Coco'];
    $mascotas = [];
    foreach ($nombres as $indice => $nombre) {
        $mascotas[] = [
            'id_mascota' => $indice + 1,
            'id_propietario' => 6,
            'nombre' => $nombre,
            'url_foto' => null,
            'nombre_especie' => $indice % 2 ? 'Felino' : 'Canino',
            'nombre_raza' => $indice % 2 ? 'Bengala' : 'Mestizo',
            'raza_indicada' => null,
            'sexo' => $indice % 2 ? 'Hembra' : 'Macho',
            'fecha_nacimiento' => '2024-12-28',
            'peso' => '9.00',
            'estado' => $indice === 4 ? 0 : 1,
            'propietario_nombre' => 'Fabio Propietario',
            'propietario_documento' => '1000000006',
            'numero_historia_clinica' => $indice < 9 ? 'HC-' . str_pad((string) ($indice + 1), 6, '0', STR_PAD_LEFT) : null,
        ];
    }
    return $mascotas;
}
