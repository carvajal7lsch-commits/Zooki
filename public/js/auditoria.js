/** HU-T.8: gráficas y exportaciones de la auditoría filtrada por clínica. */
document.addEventListener('DOMContentLoaded', function() {
    const total = Number(document.getElementById('kpi-eventos-spark').dataset.valor);
    document.querySelectorAll('[data-auditoria-spark]').forEach((grafica) => {
        dibujarIndicador(grafica.id, Number(grafica.dataset.valor), Math.max(1, total), grafica.dataset.color);
    });
});



// Script para exportar tabla a Excel
async function exportarAuditoriaExcel() {
    try {
        Swal.fire({ title: 'Generando Reporte...', text: 'Preparando Excel de auditoría.', allowOutsideClick: false, didOpen: () => {
            Swal.showLoading();
        }});

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
        headerRow.eachCell(cell => {
            Object.assign(cell, headerStyle);
        });
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
            
            newRow.eachCell(cell => {
                cell.alignment = { vertical: 'middle' };
            });
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
        Swal.fire({ title: 'Generando PDF...', allowOutsideClick: false, didOpen: () => {
            Swal.showLoading();
        }});

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
