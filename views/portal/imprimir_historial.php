<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial Clínico - <?php echo htmlspecialchars($mascota['nombre']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/imprimir-historial.css?v=1">
</head>
<body>

    <!-- Botones flotantes -->
    <div class="floating-actions">
        <a href="index.php?action=portal_propietario" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Volver</a>
        <button class="btn" data-ui-accion="imprimir"><i class="fa fa-print"></i> Imprimir / PDF</button>
    </div>

    <!-- Encabezado -->
    <div class="header">
        <div class="logo-wrap">
            <h1>Zooki</h1>
            <p>Clínica Veterinaria & Gestión Inteligente</p>
        </div>
        <div class="report-meta">
            <h2>Ficha Clínica Digital</h2>
            <p>Generado el: <?php echo date('d/m/Y H:i'); ?></p>
        </div>
    </div>

    <!-- Datos de la Mascota -->
    <div class="pet-card">
        <div class="pet-info-item">
            <span>Paciente</span>
            <strong><?php echo htmlspecialchars($mascota['nombre']); ?></strong>
        </div>
        <div class="pet-info-item">
            <span>Especie / Raza</span>
            <strong><?php echo htmlspecialchars($mascota['nombre_especie'] ?? '—'); ?> · <?php echo htmlspecialchars($mascota['nombre_raza'] ?? '—'); ?></strong>
        </div>
        <div class="pet-info-item">
            <span>Sexo</span>
            <strong><?php echo htmlspecialchars($mascota['sexo']); ?></strong>
        </div>
        <div class="pet-info-item">
            <span>Fecha de Nacimiento</span>
            <strong><?php echo $mascota['fecha_nacimiento'] ? date('d/m/Y', strtotime($mascota['fecha_nacimiento'])) : '—'; ?></strong>
        </div>
        <div class="pet-info-item">
            <span>Peso Promedio</span>
            <strong><?php echo htmlspecialchars($mascota['peso']); ?> kg</strong>
        </div>
        <div class="pet-info-item">
            <span>Propietario</span>
            <strong><?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Cliente'); ?></strong>
        </div>
    </div>

    <!-- Historial de Consultas -->
    <h3 class="section-title">Historial de Consultas y Diagnósticos</h3>
    <?php if (empty($consultas)): ?>
        <p class="historial-vacio">No hay consultas médicas registradas para esta mascota.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th class="historial-columna-fecha">Fecha</th>
                    <th class="historial-columna-motivo">Motivo</th>
                    <th class="historial-columna-diagnostico">Diagnóstico y Tratamiento</th>
                    <th class="historial-columna-motivo">Veterinario y clínica</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($consultas as $con): ?>
                    <tr>
                        <td><strong><?php echo date('d/m/Y', strtotime($con['fecha_hora'])); ?></strong></td>
                        <td><?php echo htmlspecialchars($con['motivo_consulta']); ?></td>
                        <td>
                            <strong>Diagnóstico:</strong> <?php echo htmlspecialchars($con['diagnostico']); ?><br>
                            <span class="historial-tratamiento">
                                <strong>Plan:</strong> <?php echo htmlspecialchars($con['plan_tratamiento']); ?>
                            </span>
                        </td>
                        <td>Dr(a). <?php echo htmlspecialchars($con['veterinario']); ?><br><small><?php echo htmlspecialchars($con['clinica_nombre']); ?></small></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Historial de Vacunación -->
    <h3 class="section-title">Registro de Vacunación</h3>
    <?php if (empty($vacunas)): ?>
        <p class="historial-vacio">No se han registrado vacunas aplicadas.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Vacuna</th>
                    <th>Laboratorio y clínica</th>
                    <th>Fecha Aplicación</th>
                    <th>Próxima Aplicación</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vacunas as $v): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($v['nombre_vacuna']); ?></strong></td>
                        <td><?php echo htmlspecialchars($v['laboratorio'] ?: '—'); ?><br><small><?php echo htmlspecialchars($v['clinica_nombre']); ?></small></td>
                        <td><?php echo date('d/m/Y', strtotime($v['fecha_aplicacion'])); ?></td>
                        <td>
                            <?php echo (!empty($v['fecha_proxima_dosis']) && $v['fecha_proxima_dosis'] !== '0000-00-00') ? '<strong>' . date('d/m/Y', strtotime($v['fecha_proxima_dosis'])) . '</strong>' : '—'; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Registro de Desparasitaciones -->
    <h3 class="section-title">Controles Antiparasitarios</h3>
    <?php if (empty($desparasitaciones)): ?>
        <p class="historial-vacio">No se han registrado controles antiparasitarios.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Tipo y clínica</th>
                    <th>Fecha Aplicación</th>
                    <th>Próxima Aplicación</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($desparasitaciones as $d): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($d['producto']); ?></strong></td>
                        <td><?php echo htmlspecialchars(ucfirst((string) $d['tipo']) . ' · ' . $d['periodicidad']); ?><br><small><?php echo htmlspecialchars($d['clinica_nombre']); ?></small></td>
                        <td><?php echo date('d/m/Y', strtotime($d['fecha_aplicacion'])); ?></td>
                        <td>
                            <?php echo ($d['fecha_proxima'] && $d['fecha_proxima'] !== '0000-00-00') ? '<strong>' . date('d/m/Y', strtotime($d['fecha_proxima'])) . '</strong>' : '—'; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <script src="js/imprimir-historial.js?v=1"></script>
    <script src="js/interacciones.js?v=1"></script>
</body>
</html>
