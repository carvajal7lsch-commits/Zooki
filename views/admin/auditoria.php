<?php
/**
 * HU-T.8 — Auditoría de la clínica activa. AuditoriaController entrega $logs,
 * $total, $page, $acciones, $tablas y $stats_hoy, ya filtrados por clínica.
 */
$accionColors = [
    'LOGIN' => '#10B981', 'LOGIN_FAIL' => '#EF4444', 'LOGOUT' => '#64748B',
    'INSERT' => '#0C66E4', 'UPDATE' => '#F59E0B', 'DELETE' => '#EF4444',
    'VIEW' => '#8B5CF6', 'OTHER' => '#94A3B8'
];

$accionIcons = [
    'LOGIN' => 'fa-sign-in-alt', 'LOGIN_FAIL' => 'fa-user-lock', 'LOGOUT' => 'fa-sign-out-alt',
    'INSERT' => 'fa-plus-circle', 'UPDATE' => 'fa-edit', 'DELETE' => 'fa-trash-alt',
    'VIEW' => 'fa-eye', 'OTHER' => 'fa-cog'
];

// Estadísticas de hoy para los KPIs de seguridad
if (!isset($stats_hoy) || !is_array($stats_hoy)) {
    $stats_hoy = [];
}
$alertas_criticas = ($stats_hoy['DELETE'] ?? 0) + ($stats_hoy['LOGIN_FAIL'] ?? 0);
$intentos_fallidos = $stats_hoy['LOGIN_FAIL'] ?? 0;
$total_acciones = array_sum($stats_hoy);

$perPage = 50; // O el valor que tenga en el controlador
$totalPages = isset($total) ? ceil($total / $perPage) : 1;

if (!isset($acciones) || !is_array($acciones)) $acciones = [];
if (!isset($tablas) || !is_array($tablas)) $tablas = [];
if (!isset($logs) || !is_array($logs)) $logs = [];
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.3.0/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.1/jspdf.plugin.autotable.min.js"></script>

<div class="animate__animated animate__fadeIn">
    <!-- Header de Seguridad -->
    <div class="header-container-white" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem;">
        <div class="head-title-desc" style="flex: 1; min-width: 250px;">
            <h1 class="users-page-title" style="margin-bottom: 0.2rem; font-size: 1.5rem; font-weight: 800; color: #1e293b;"><i class="fas fa-shield-alt" style="color: #0052FF;"></i> Centro de Seguridad</h1>
            <p class="users-module-desc" style="margin: 0; color: #64748b; font-size: 0.9rem;">Monitorización de actividad y cumplimiento</p>
        </div>
        
        <!-- Mini KPIs Compactos Integrados -->
        <div class="mini-kpis-container" style="margin: 0; flex: 2; justify-content: center;">
            <div class="mini-kpi">
                <svg id="kpi-eventos-spark" class="kpi-sparkline" viewBox="0 0 100 30" preserveAspectRatio="none"></svg>
                <div class="mini-kpi-content">
                    <span class="mini-kpi-value" id="kpi-eventos"><?= number_format($total_acciones) ?></span>
                    <span class="mini-kpi-label">Total Eventos</span>
                </div>
            </div>
            <div class="kpi-divider"></div>
            <div class="mini-kpi">
                <svg id="kpi-criticas-spark" class="kpi-sparkline" viewBox="0 0 100 30" preserveAspectRatio="none"></svg>
                <div class="mini-kpi-content">
                    <span class="mini-kpi-value" id="kpi-criticas"><?= number_format($alertas_criticas) ?></span>
                    <span class="mini-kpi-label">Alertas Críticas</span>
                </div>
            </div>
            <div class="kpi-divider"></div>
            <div class="mini-kpi">
                <svg id="kpi-fallidos-spark" class="kpi-sparkline" viewBox="0 0 100 30" preserveAspectRatio="none"></svg>
                <div class="mini-kpi-content">
                    <span class="mini-kpi-value" id="kpi-fallidos"><?= number_format($intentos_fallidos) ?></span>
                    <span class="mini-kpi-label">Logins Fallidos</span>
                </div>
            </div>
        </div>

        <div class="header-actions" style="margin-left: auto; display: flex; gap: 0.5rem;">
            <button class="btn-secondary" onclick="exportarAuditoriaExcel()">
                <i class="fas fa-file-excel" style="color: #0052FF;"></i> Excel
            </button>
            <button class="btn-secondary" onclick="exportarAuditoriaPDF()">
                <i class="fas fa-file-pdf" style="color: #0052FF;"></i> PDF
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div style="background: white; padding: 1.5rem; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 15px rgba(0,0,0,0.02); margin-bottom: 2rem;">
        <form method="GET" action="index.php" style="display:flex; gap:1rem; flex-wrap:wrap; align-items:flex-end;">
            <input type="hidden" name="action" value="admin_auditoria">
            
            <div style="display: flex; flex-direction: column; gap: 0.3rem;">
                <label style="font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase;">Persona</label>
                <input type="text" name="usuario" value="<?=htmlspecialchars($_GET['usuario']??'')?>" placeholder="Nombre, documento o correo" style="padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 8px; outline: none;">
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 0.3rem;">
                <label style="font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase;">Tipo Acción</label>
                <select name="accion" style="padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 8px; outline: none; min-width: 130px;">
                    <option value="">Todas</option>
                    <?php foreach($acciones as $a): ?>
                        <option value="<?=$a?>" <?=($_GET['accion']??'')===$a?'selected':''?>><?=$a?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 0.3rem;">
                <label style="font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase;">Tabla Afectada</label>
                <select name="tabla" style="padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 8px; outline: none; min-width: 130px;">
                    <option value="">Todas</option>
                    <?php foreach($tablas as $t): ?>
                        <option value="<?=$t?>" <?=($_GET['tabla']??'')===$t?'selected':''?>><?=$t?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 0.3rem;">
                <label style="font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase;">Desde</label>
                <input type="date" name="fecha_desde" value="<?=htmlspecialchars($_GET['fecha_desde']??'')?>" style="padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 8px; outline: none;">
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 0.3rem;">
                <label style="font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase;">Hasta</label>
                <input type="date" name="fecha_hasta" value="<?=htmlspecialchars($_GET['fecha_hasta']??'')?>" style="padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 8px; outline: none;">
            </div>
            
            <button type="submit" style="background: #0C66E4; color: white; border: none; padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: 700; cursor: pointer;"><i class="fas fa-search"></i> Buscar</button>
            <a href="index.php?action=admin_auditoria" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: 700; text-decoration: none; display: flex; align-items: center;"><i class="fas fa-eraser"></i> Limpiar</a>
        </form>
    </div>

    <!-- Tabla Estilizada -->
    <div style="background: white; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 15px rgba(0,0,0,0.02); overflow: hidden;">
        <div class="table-responsive" style="margin: 0; padding: 0; max-height: calc(100vh - 350px); overflow-y: auto;">
            <table class="data-table" id="tablaAuditoria" style="margin: 0; width: 100%; border-collapse: collapse;">
                <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; z-index: 10;">
                    <tr>
                        <th style="padding: 1rem; color: #475569; font-weight: 800; font-size: 0.8rem; text-transform: uppercase;">Fecha/Hora</th>
                        <th style="padding: 1rem; color: #475569; font-weight: 800; font-size: 0.8rem; text-transform: uppercase;">Usuario</th>
                        <th style="padding: 1rem; color: #475569; font-weight: 800; font-size: 0.8rem; text-transform: uppercase;">Acción</th>
                        <th style="padding: 1rem; color: #475569; font-weight: 800; font-size: 0.8rem; text-transform: uppercase;">Tabla</th>
                        <th style="padding: 1rem; color: #475569; font-weight: 800; font-size: 0.8rem; text-transform: uppercase;">Descripción</th>
                        <th style="padding: 1rem; color: #475569; font-weight: 800; font-size: 0.8rem; text-transform: uppercase;">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($logs)): ?>
                        <tr><td colspan="6" style="text-align:center; padding: 3rem; color:#94A3B8;"><i class="fas fa-search" style="font-size: 2rem; margin-bottom: 1rem;"></i><br>No hay registros de auditoría para estos filtros.</td></tr>
                    <?php else: ?>
                        <?php foreach($logs as $log): 
                            $color = $accionColors[$log['accion']] ?? '#94A3B8';
                            $icon = $accionIcons[$log['accion']] ?? 'fa-circle';
                        ?>
                            <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s;">
                                <td style="padding: 1rem; font-size: 0.85rem; color: #475569; white-space: nowrap;">
                                    <i class="far fa-clock" style="margin-right: 0.3rem; color: #94a3b8;"></i>
                                    <?= date('d/m/Y H:i', strtotime($log['fecha_hora'])) ?>
                                </td>
                                <td style="padding: 1rem; font-size: 0.85rem; font-weight: 600; color: #1e293b;">
                                    <?= htmlspecialchars($log['usuario_nombre'] ?? 'Sistema') ?>
                                </td>
                                <td style="padding: 1rem;">
                                    <span style="font-size: 0.75rem; font-weight: 800; color: <?= $color ?>; background: <?= $color ?>15; padding: 0.3rem 0.6rem; border-radius: 20px; display: inline-flex; align-items: center; gap: 0.3rem;">
                                        <i class="fas <?= $icon ?>"></i> <?= $log['accion'] ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem; font-size: 0.85rem; color: #64748b; font-weight: 600; text-transform: capitalize;">
                                    <?= htmlspecialchars($log['tabla_afectada'] ?? '—') ?>
                                </td>
                                <td style="padding: 1rem; font-size: 0.85rem; color: #334155;">
                                    <?= htmlspecialchars($log['descripcion'] ?? 'Sin descripción') ?>
                                    <?php if(!empty($log['registro_id'])): ?>
                                        <br><small style="color: #94a3b8;">ID: <?= htmlspecialchars($log['registro_id']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 1rem; font-size: 0.75rem; color: #94a3b8; font-family: monospace;">
                                    <?= htmlspecialchars($log['ip_address'] ?? '—') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Paginación -->
    <?php if($totalPages > 1): ?>
        <div style="display:flex; justify-content:center; gap:0.5rem; margin-top:2rem;">
            <?php for($i=1; $i<=$totalPages; $i++): ?>
                <a href="index.php?action=admin_auditoria&page=<?=$i?>&usuario=<?=urlencode($_GET['usuario']??'')?>&accion=<?=urlencode($_GET['accion']??'')?>&tabla=<?=urlencode($_GET['tabla']??'')?>&fecha_desde=<?=urlencode($_GET['fecha_desde']??'')?>&fecha_hasta=<?=urlencode($_GET['fecha_hasta']??'')?>"
                   style="padding: 0.5rem 0.85rem; border-radius: 8px; font-size: 0.9rem; font-weight: 700; text-decoration: none; transition: all 0.2s;
                          <?= $page==$i ? 'background:#0C66E4; color:white; box-shadow: 0 4px 10px rgba(12, 102, 228, 0.3);' : 'background:white; color:#64748b; border: 1px solid #cbd5e1;' ?>">
                    <?=$i?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>

    <p style="text-align:center; font-size:0.85rem; font-weight: 600; color:#94A3B8; margin-top:1.5rem;">
        Mostrando <?= count($logs) ?> de <?= $total ?> registros de seguridad
    </p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    drawSparkline('kpi-eventos-spark', <?= $total_acciones ?>, <?= max(1, $total_acciones) ?>, '#0052FF');
    drawSparkline('kpi-criticas-spark', <?= $alertas_criticas ?>, <?= max(1, $total_acciones) ?>, '#EF4444');
    drawSparkline('kpi-fallidos-spark', <?= $intentos_fallidos ?>, <?= max(1, $total_acciones) ?>, '#F59E0B');
});

function drawSparkline(elementId, value, max, color) {
    const container = document.getElementById(elementId);
    if(!container) return;

    if (max === 0) max = 1;
    const normalizedHeight = 28 - ((value / max) * 15);
    
    let pathData;
    if (value === 0) {
        pathData = `M0,28 L25,28 L50,28 L75,28 L100,28`;
    } else {
        const heightDiff = 28 - normalizedHeight;
        const p1 = 28 - Math.random() * (heightDiff * 0.5);
        const p2 = 28 - Math.random() * (heightDiff * 1.2);
        const p3 = 28 - Math.random() * (heightDiff * 0.8);
        pathData = `M0,28 L25,${p1} L50,${p2} L75,${p3} L100,${normalizedHeight}`;
    }
    
    const fillPath = `${pathData} L100,30 L0,30 Z`;
    
    container.innerHTML = `
        <defs>
            <linearGradient id="grad-${elementId}" x1="0%" y1="0%" x2="0%" y2="100%">
                <stop offset="0%" style="stop-color:${color};stop-opacity:0.15" />
                <stop offset="100%" style="stop-color:${color};stop-opacity:0" />
            </linearGradient>
        </defs>
        <path d="${pathData}" fill="none" stroke="${color}" stroke-width="2" vector-effect="non-scaling-stroke"></path>
        <path d="${fillPath}" fill="url(#grad-${elementId})" stroke="none"></path>
        <circle cx="100" cy="${normalizedHeight}" r="2.5" fill="${color}" />
    `;
}

// Script para exportar tabla a Excel
async function exportarAuditoriaExcel() {
    try {
        Swal.fire({ title: 'Generando Reporte...', text: 'Preparando Excel de auditoría.', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); }});

        const workbook = new ExcelJS.Workbook();
        workbook.creator = 'Zooki Security System';
        const worksheet = workbook.addWorksheet('Auditoría');

        // Estilos
        const headerStyle = { font: { bold: true, color: { argb: 'FFFFFFFF' } }, fill: { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF0F172A' } }, alignment: { vertical: 'middle', horizontal: 'center' } };

        // Cabecera Principal
        worksheet.mergeCells('A1:F1');
        const titleCell = worksheet.getCell('A1');
        titleCell.value = 'ZOOKI - REPORTE DE AUDITORÍA Y SEGURIDAD';
        titleCell.font = { name: 'Arial', family: 4, size: 14, bold: true, color: { argb: 'FFFFFFFF' } };
        titleCell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF0C66E4' } };
        titleCell.alignment = { vertical: 'middle', horizontal: 'center' };
        worksheet.getRow(1).height = 30;

        worksheet.mergeCells('A2:F2');
        worksheet.getCell('A2').value = 'Generado el: ' + new Date().toLocaleString();
        worksheet.getCell('A2').font = { italic: true, color: { argb: 'FF64748B' } };
        worksheet.getCell('A2').alignment = { horizontal: 'right' };

        // Cabeceras Tabla
        const headers = ['FECHA/HORA', 'USUARIO DOC', 'ACCIÓN', 'TABLA AFECTADA', 'DESCRIPCIÓN', 'IP'];
        const headerRow = worksheet.addRow(headers);
        headerRow.eachCell(cell => { Object.assign(cell, headerStyle); });
        worksheet.getRow(3).height = 25;

        // Datos
        const table = document.getElementById('tablaAuditoria');
        const rows = table.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            if(row.cells.length === 1) return; // Fila vacía (No hay registros)
            
            const rowData = [
                row.cells[0].innerText.trim(),
                row.cells[1].innerText.trim(),
                row.cells[2].innerText.trim(),
                row.cells[3].innerText.trim(),
                row.cells[4].innerText.trim().replace(/\n/g, ' - '),
                row.cells[5].innerText.trim()
            ];
            
            const newRow = worksheet.addRow(rowData);
            
            // Colores de acciones
            const accionCell = newRow.getCell(3);
            accionCell.font = { bold: true };
            const accion = rowData[2];
            if (accion === 'LOGIN') accionCell.font.color = { argb: 'FF10B981' };
            else if (accion === 'LOGIN_FAIL' || accion === 'DELETE') accionCell.font.color = { argb: 'FFEF4444' };
            else if (accion === 'UPDATE') accionCell.font.color = { argb: 'FFF59E0B' };
            else if (accion === 'INSERT') accionCell.font.color = { argb: 'FF0C66E4' };
            
            newRow.eachCell(cell => { cell.alignment = { vertical: 'middle' }; });
        });

        // Anchos
        worksheet.columns = [
            { width: 18 }, { width: 15 }, { width: 15 }, { width: 20 }, { width: 50 }, { width: 15 }
        ];

        // Descarga
        const buffer = await workbook.xlsx.writeBuffer();
        const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `Zooki_Auditoria_${new Date().toISOString().split('T')[0]}.xlsx`;
        a.click();
        window.URL.revokeObjectURL(url);

        Swal.close();
        const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
        Toast.fire({ icon: 'success', title: 'Excel Exportado' });
    } catch (e) {
        console.error(e);
        Swal.fire('Error', 'No se pudo exportar el archivo Excel', 'error');
    }
}

// Script para exportar tabla a PDF
function exportarAuditoriaPDF() {
    try {
        Swal.fire({ title: 'Generando PDF...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); }});

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('landscape');

        // Título
        doc.setFillColor(12, 102, 228);
        doc.rect(0, 0, doc.internal.pageSize.width, 25, 'F');
        doc.setTextColor(255, 255, 255);
        doc.setFontSize(16);
        doc.setFont("helvetica", "bold");
        doc.text('ZOOKI - REPORTE DE AUDITORÍA Y SEGURIDAD', 14, 16);
        
        doc.setFontSize(9);
        doc.setFont("helvetica", "normal");
        doc.text(`Generado: ${new Date().toLocaleString()}`, doc.internal.pageSize.width - 14, 16, { align: 'right' });

        const table = document.getElementById('tablaAuditoria');
        const rows = Array.from(table.querySelectorAll('tbody tr')).filter(r => r.cells.length > 1);
        
        const data = rows.map(r => [
            r.cells[0].innerText.trim(),
            r.cells[1].innerText.trim(),
            r.cells[2].innerText.trim(),
            r.cells[3].innerText.trim(),
            r.cells[4].innerText.trim().replace(/\n/g, ' '),
            r.cells[5].innerText.trim()
        ]);

        doc.autoTable({
            head: [['Fecha/Hora', 'Usuario', 'Acción', 'Tabla', 'Descripción', 'IP']],
            body: data,
            startY: 30,
            theme: 'grid',
            headStyles: { fillColor: [15, 23, 42], textColor: 255, fontSize: 9, fontStyle: 'bold' },
            bodyStyles: { fontSize: 8 },
            didParseCell: function(data) {
                if (data.section === 'body' && data.column.index === 2) {
                    const accion = data.cell.raw;
                    if (accion === 'LOGIN') data.cell.styles.textColor = [16, 185, 129];
                    else if (accion === 'LOGIN_FAIL' || accion === 'DELETE') data.cell.styles.textColor = [239, 68, 68];
                    else if (accion === 'UPDATE') data.cell.styles.textColor = [245, 158, 11];
                    else if (accion === 'INSERT') data.cell.styles.textColor = [12, 102, 228];
                    data.cell.styles.fontStyle = 'bold';
                }
            }
        });

        doc.save(`Zooki_Auditoria_${new Date().toISOString().split('T')[0]}.pdf`);
        Swal.close();
    } catch (e) {
        console.error(e);
        Swal.fire('Error', 'No se pudo exportar el archivo PDF', 'error');
    }
}
</script>

<style>
/* Mini KPIs Compactos */
.mini-kpis-container {
    display: flex;
    gap: 1.5rem;
    align-items: center;
}

.mini-kpi {
    display: flex;
    flex-direction: column;
    align-items: center;
    background: transparent;
    border: none;
    padding: 0.2rem 0;
    position: relative;
    min-width: 85px;
    overflow: hidden;
}

.kpi-sparkline {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 45%;
    z-index: 0;
    pointer-events: none;
}

.mini-kpi-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    z-index: 1;
    text-shadow: 0 0 6px #f8fafc, 0 0 3px #ffffff;
}

.mini-kpi-value {
    font-size: 1.4rem;
    font-weight: 800;
    line-height: 1;
    color: #0052FF;
}

.mini-kpi-label {
    font-size: 0.65rem;
    color: #334155;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 0.3rem;
}

.kpi-divider {
    width: 1px;
    height: 30px;
    background: #e2e8f0;
}

.btn-secondary {
    background: white;
    color: #334155;
    border: 1px solid #e2e8f0;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s;
    font-size: 0.85rem;
}

.btn-secondary:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    color: #1e293b;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}
</style>
