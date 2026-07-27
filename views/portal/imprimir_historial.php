<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial Clínico - <?php echo htmlspecialchars($mascota['nombre']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            color: #1e293b;
            background: #ffffff;
            margin: 0;
            padding: 2rem;
            box-sizing: border-box;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 1.5rem;
            margin-bottom: 2rem;
        }

        .logo-wrap h1 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 800;
            color: #5560ff;
        }

        .logo-wrap p {
            margin: 4px 0 0;
            font-size: 0.8rem;
            color: #64748b;
        }

        .report-meta {
            text-align: right;
            font-size: 0.82rem;
            color: #64748b;
        }

        .report-meta h2 {
            margin: 0 0 4px;
            font-size: 1.2rem;
            font-weight: 800;
            color: #0f172a;
        }

        .pet-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 1.25rem;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .pet-info-item {
            font-size: 0.85rem;
        }

        .pet-info-item span {
            display: block;
            font-weight: 700;
            font-size: 0.72rem;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .pet-info-item strong {
            color: #0f172a;
            font-size: 0.95rem;
        }

        .section-title {
            font-size: 1.05rem;
            font-weight: 800;
            color: #0f172a;
            border-bottom: 2px solid #5560ff;
            padding-bottom: 6px;
            margin: 2.5rem 0 1rem;
            text-transform: uppercase;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.5rem;
            font-size: 0.82rem;
        }

        th {
            background: #f1f5f9;
            color: #475569;
            text-align: left;
            font-weight: 700;
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
        }

        td {
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            color: #334155;
            vertical-align: top;
            line-height: 1.4;
        }

        tr:nth-child(even) td {
            background: #f8fafc;
        }

        .floating-actions {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            display: flex;
            gap: 0.5rem;
            z-index: 1000;
        }

        .btn {
            background: #5560ff;
            color: #ffffff;
            border: none;
            padding: 0.75rem 1.25rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(85, 96, 255, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            text-decoration: none;
        }

        .btn-secondary {
            background: #64748b;
            box-shadow: 0 4px 12px rgba(100, 116, 139, 0.3);
        }

        /* Ocultar elementos al imprimir */
        @media print {
            body {
                padding: 0;
            }
            .floating-actions {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <!-- Botones flotantes -->
    <div class="floating-actions">
        <a href="index.php?action=portal_propietario" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Volver</a>
        <button class="btn" onclick="window.print()"><i class="fa fa-print"></i> Imprimir / PDF</button>
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
        <p style="font-size:0.85rem; color:#64748b; font-style:italic;">No hay consultas médicas registradas para esta mascota.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th style="width: 15%;">Fecha</th>
                    <th style="width: 25%;">Motivo</th>
                    <th style="width: 35%;">Diagnóstico y Tratamiento</th>
                    <th style="width: 25%;">Veterinario</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($consultas as $con): ?>
                    <tr>
                        <td><strong><?php echo date('d/m/Y', strtotime($con['fecha_hora'])); ?></strong></td>
                        <td><?php echo htmlspecialchars($con['motivo_consulta']); ?></td>
                        <td>
                            <strong>Diagnóstico:</strong> <?php echo htmlspecialchars($con['diagnostico']); ?><br>
                            <span style="font-size: 0.75rem; color: #475569; display: block; margin-top: 4px;">
                                <strong>Plan:</strong> <?php echo htmlspecialchars($con['plan_tratamiento']); ?>
                            </span>
                        </td>
                        <td>Dr(a). <?php echo htmlspecialchars($con['veterinario']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Historial de Vacunación -->
    <h3 class="section-title">Registro de Vacunación</h3>
    <?php if (empty($vacunas)): ?>
        <p style="font-size:0.85rem; color:#64748b; font-style:italic;">No se han registrado vacunas aplicadas.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Vacuna</th>
                    <th>Dosis</th>
                    <th>Fecha Aplicación</th>
                    <th>Próxima Aplicación</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vacunas as $v): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($v['nombre_vacuna']); ?></strong></td>
                        <td><?php echo htmlspecialchars($v['dosis']); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($v['fecha_aplicacion'])); ?></td>
                        <td>
                            <?php echo ($v['fecha_proxima'] && $v['fecha_proxima'] !== '0000-00-00') ? '<strong>' . date('d/m/Y', strtotime($v['fecha_proxima'])) . '</strong>' : '—'; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Registro de Desparasitaciones -->
    <h3 class="section-title">Controles Antiparasitarios</h3>
    <?php if (empty($desparasitaciones)): ?>
        <p style="font-size:0.85rem; color:#64748b; font-style:italic;">No se han registrado controles antiparasitarios.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Dosis</th>
                    <th>Fecha Aplicación</th>
                    <th>Próxima Aplicación</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($desparasitaciones as $d): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($d['producto']); ?></strong></td>
                        <td><?php echo htmlspecialchars($d['dosis']); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($d['fecha_aplicacion'])); ?></td>
                        <td>
                            <?php echo ($d['fecha_proxima'] && $d['fecha_proxima'] !== '0000-00-00') ? '<strong>' . date('d/m/Y', strtotime($d['fecha_proxima'])) . '</strong>' : '—'; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <script>
        // Lanzar diálogo de impresión automáticamente al abrir en limpio
        window.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                window.print();
            }, 800);
        });
    </script>
</body>
</html>
