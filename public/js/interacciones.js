/** C9: acciones de las vistas en data-*, sin controladores de evento en línea. */
document.addEventListener('click', ejecutarInteraccion);
document.addEventListener('keydown', (evento) => {
    if (evento.defaultPrevented) return;
    if (!['Enter', ' '].includes(evento.key)) return;
    const elemento = evento.target.closest('[data-ui-accion][role="button"]');
    if (!elemento || elemento !== evento.target) return;
    evento.preventDefault();
    ejecutarInteraccion(evento);
});

function ejecutarInteraccion(evento) {
    if (evento.target.closest('[data-ui-detener]')) return;
    const boton = evento.target.closest('[data-ui-accion]');
    if (!boton || boton.disabled) return;
    if (boton.hasAttribute('data-ui-fondo') && evento.target !== boton) return;
    if (evento.target.closest('.btn-cancel-agenda')) return;
    const id = Number(boton.dataset.uiId);
    switch (boton.dataset.uiAccion) {
        case 'cerrar-consulta':
            closeModal('modalConsulta');
            break;
        case 'pestana-consulta':
            switchModalTab({ target: evento.target, currentTarget: boton }, boton.dataset.tab);
            break;
        case 'agregar-tratamiento':
            addTreatmentRow();
            break;
        case 'cerrar-cita':
            closeCitaModal();
            break;
        case 'cambiar-mascota':
            limpiarMascotaSeleccionada(true);
            break;
        case 'cerrar-reprogramacion':
            cerrarModalReprogramar();
            break;
        case 'confirmar-reprogramacion':
            confirmarReprogramacion();
            break;
        case 'detalle-mascota':
            verDetalle(id);
            break;
        case 'detalle-cita-portal':
            mostrarDetalleCita(id);
            break;
        case 'editar-contacto':
            toggleContactEditPortal();
            break;
        case 'editar-password':
            togglePasswordChangePortal();
            break;
        case 'cerrar-drawer':
            cerrarDrawer();
            break;
        case 'acordeon-historia':
            toggleAccordion(boton);
            break;
        case 'cancelar-cita-portal':
            cancelarCitaPortal(id);
            break;
        case 'imprimir':
            window.print();
            break;
        case 'exportar-citas-excel':
            exportarCitas();
            break;
        case 'exportar-citas-pdf':
            exportarCitasPDF();
            break;
        case 'limpiar-citas':
            limpiarFiltros();
            break;
        case 'carrusel-citas':
            scrollKanban(boton.dataset.direccion);
            break;
        case 'detalle-cita-admin':
            verDetalle(id);
            break;
        case 'exportar-auditoria-excel':
            exportarAuditoriaExcel();
            break;
        case 'exportar-auditoria-pdf':
            exportarAuditoriaPDF();
            break;
    }
}
