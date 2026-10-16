<?php
// Arnés visual C8.1: vistas reales, datos sintéticos, sin conectar una base ni leer .env.
require_once __DIR__ . '/../../../helpers/Contexto.php';
require_once __DIR__ . '/../../../helpers/ValidadorMascota.php';
// Sesión solo en memoria: el arnés nunca toca las sesiones del XAMPP del usuario.
session_set_save_handler(static fn() => true, static fn() => true, static fn($id) => '', static fn($id, $datos) => true, static fn($id) => true, static fn($maximo) => 0);
session_start();
Contexto::iniciarIdentidad(2, 'Beto Norte', false, 'password');
Contexto::activar(Contexto::deClinica(1, 'Clínica Norte', Roles::VETERINARIO), 1);
$_GET['action'] = $argv[1] ?? 'vet_atencion';
$cita = ['id_cita' => 5, 'estado' => 'en_curso', 'fecha' => '2026-10-16', 'hora' => '08:00:00', 'motivo' => 'Revisión por vómitos'];
$mascota = ['id_mascota' => 7, 'nombre' => 'Luna', 'nombre_especie' => 'Canino', 'nombre_raza' => 'Mestizo', 'sexo' => 'Hembra', 'peso' => '9.00', 'numero_historia_clinica' => 'HC-000001', 'url_foto' => null, 'fecha_nacimiento' => '2022-01-01', 'raza_indicada' => null, 'propietario_nombre' => 'Fabio Propietario'];
$consultaCita = null;
$esDiaDeLaCita = true;
$resumenClinico = ['ultimo_peso' => '9.00', 'alertas' => [], 'ultima_consulta' => ['fecha' => '2026-10-01', 'diagnostico' => 'Control digestivo']];
$propietario = ['nombre_completo' => 'Fabio Propietario', 'telefono' => '3001122333'];
$edadMascota = '4 años';
$fotoMascota = 'img/default-pet.svg';
$tipoCitaNombre = 'Consulta general';
$consultas = [];
$vacunas = [];
$desparasitaciones = [];
$tiposCita = [];
$especies = [];
$razas = [];
$colores = [];
$mascotas = [];
$propietarios = [];
$content_view = __DIR__ . '/../../../views/vet/' . ($_GET['action'] === 'vet_atencion' ? 'atencion.php' : ($_GET['action'] === 'vet_pacientes' ? 'pacientes.php' : 'consultas.php'));
require __DIR__ . '/../../../views/vet/layout.php';
