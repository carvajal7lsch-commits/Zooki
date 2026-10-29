/** HU-T.8 / RE-T.8.2–4: filtros existentes, detalle de lectura y paginación compacta del lote. */
(() => {
    'use strict';
    function iniciar() {
        const raiz = document.querySelector('[data-auditoria]');
        if (!raiz) return;
        const filas = Array.from(raiz.querySelectorAll('[data-evento]'));
        const form = document.getElementById('auditoriaFiltros');
        const filtros = document.getElementById('auditoriaFiltrosDialogo');
        const detalle = document.getElementById('auditoriaDetalle');
        const movil = window.matchMedia('(max-width: 767px)');
        const selectores = document.querySelector('[data-selectores]');
        const exportar = document.querySelector('[data-exportar]');
        const anterior = raiz.querySelector('[data-auditoria-anterior]');
        const siguiente = raiz.querySelector('[data-auditoria-siguiente]');
        const hayAnterior = anterior.getAttribute('aria-disabled') !== 'true';
        const haySiguiente = siguiente.getAttribute('aria-disabled') !== 'true';
        const desde = form.elements.namedItem('fecha_desde');
        const hasta = form.elements.namedItem('fecha_hasta');
        let inicio = 0;
        let cantidad = 10;
        let valoresAlAbrir = null;
        let buscando;

        function paginar() {
            const espacio = raiz.querySelector('.auditoria-listado').clientHeight;
            const cabecera = raiz.querySelector('thead').getBoundingClientRect().height;
            cantidad = movil.matches ? 6 : Math.max(1, Math.min(10, Math.floor((espacio - cabecera) / 46)));
            inicio = Math.min(inicio, Math.max(0, filas.length - 1));
            filas.forEach((fila, indice) => { fila.hidden = indice < inicio || indice >= inicio + cantidad; });
            const base = Number(raiz.dataset.offset);
            const primero = filas.length ? base + inicio + 1 : 0;
            const ultimo = base + Math.min(inicio + cantidad, filas.length);
            raiz.querySelector('[data-auditoria-contador]').textContent = filas.length ? `${primero}–${ultimo} de ${raiz.dataset.total} eventos` : `0 de ${raiz.dataset.total} eventos`;
            anterior.setAttribute('aria-disabled', String(inicio === 0 && !hayAnterior));
            siguiente.setAttribute('aria-disabled', String(inicio + cantidad >= filas.length && !haySiguiente));
        }
        function situar() {
            document.querySelector(movil.matches ? '[data-filtros-movil]' : '[data-filtros-escritorio]').append(selectores);
            document.querySelector(movil.matches ? '[data-exportar-movil]' : '[data-exportar-escritorio]').append(exportar);
            paginar();
        }
        for (const [boton, direccion] of [[anterior, -1], [siguiente, 1]]) {
            boton.addEventListener('click', evento => {
                if (boton.getAttribute('aria-disabled') === 'true') {
                    evento.preventDefault();
                    return;
                }
                if ((direccion < 0 && inicio > 0) || (direccion > 0 && inicio + cantidad < filas.length)) {
                    evento.preventDefault();
                    inicio = Math.max(0, inicio + cantidad * direccion);
                    paginar();
                }
            });
        }
        function validarFechas() {
            const mensaje = desde.value && hasta.value && desde.value > hasta.value ? 'Hasta debe ser igual o posterior a Desde.' : '';
            hasta.setCustomValidity(mensaje);
            filtros.querySelector('[data-error-fechas]').textContent = mensaje;
            return !mensaje;
        }
        function fechaLocal(fecha) {
            return `${fecha.getFullYear()}-${String(fecha.getMonth() + 1).padStart(2, '0')}-${String(fecha.getDate()).padStart(2, '0')}`;
        }
        function guardarFiltros() {
            valoresAlAbrir = Array.from(form.elements).filter(campo => campo.name).map(campo => [campo.name, campo.value]);
        }
        function abrirFiltros() {
            guardarFiltros();
            globalThis.zookiAbrirDialogo('auditoriaFiltrosDialogo');
        }
        filtros.addEventListener('close', () => {
            if (!valoresAlAbrir) return;
            for (const [nombre, valor] of valoresAlAbrir) form.elements.namedItem(nombre).value = valor;
            valoresAlAbrir = null;
            validarFechas();
        });
        raiz.querySelector('[data-abrir-dialogo="auditoriaFiltrosDialogo"]').addEventListener('click', guardarFiltros);
        raiz.querySelectorAll('[data-periodo]').forEach(boton => {
            boton.addEventListener('click', () => {
                if (boton.dataset.periodo === 'rango') {
                    abrirFiltros();
                    return;
                }
                const hoy = new Date();
                const primero = new Date(hoy);
                primero.setDate(hoy.getDate() - (boton.dataset.periodo === 'hoy' ? 0 : Number(boton.dataset.periodo) - 1));
                desde.value = fechaLocal(primero);
                hasta.value = fechaLocal(hoy);
                form.requestSubmit();
            });
        });
        function marcarPeriodo() {
            const hoy = new Date();
            let periodo = 'rango';
            if (hasta.value === fechaLocal(hoy)) {
                for (const dias of [1, 7, 30]) {
                    const primero = new Date(hoy);
                    primero.setDate(hoy.getDate() - dias + 1);
                    if (desde.value === fechaLocal(primero)) periodo = dias === 1 ? 'hoy' : String(dias);
                }
            }
            raiz.querySelectorAll('[data-periodo]').forEach(boton => boton.setAttribute('aria-pressed', String(boton.dataset.periodo === periodo)));
        }
        raiz.querySelectorAll('[data-accion-rapida]').forEach(boton => {
            boton.addEventListener('click', () => {
                const campo = form.elements.namedItem('accion');
                if (!Array.from(campo.options).some(opcion => opcion.value === boton.dataset.accionRapida)) campo.add(new Option(boton.textContent.trim(), boton.dataset.accionRapida));
                campo.value = boton.dataset.accionRapida;
                form.requestSubmit();
            });
        });
        selectores.addEventListener('change', () => {
            if (!movil.matches) form.requestSubmit();
        });
        form.elements.namedItem('usuario').addEventListener('input', () => {
            clearTimeout(buscando);
            buscando = setTimeout(() => form.requestSubmit(), 500);
        });
        [desde, hasta].forEach(campo => campo.addEventListener('input', validarFechas));
        form.addEventListener('submit', evento => {
            clearTimeout(buscando);
            if (!validarFechas()) {
                evento.preventDefault();
                return;
            }
            valoresAlAbrir = null;
        });
        const nombresCampos = { duracion_minutos: 'Duración (min)', margen_minutos: 'Margen (min)', permite_pausa: 'Se puede pausar', activo: 'Activo', nombre: 'Nombre', telefono: 'Teléfono', email_contacto: 'Correo de contacto' };
        function aplanar(datos, ruta = '', salida = {}) {
            for (const [clave, valor] of Object.entries(datos)) {
                const campo = ruta ? `${ruta} · ${clave}` : clave;
                if (valor !== null && typeof valor === 'object' && Object.keys(valor).length) aplanar(valor, campo, salida);
                else salida[campo] = valor;
            }
            return salida;
        }
        function mostrarValor(valor) {
            if (valor === undefined || valor === null) return '—';
            if (typeof valor === 'boolean') return valor ? 'Sí' : 'No';
            if (typeof valor === 'object') return JSON.stringify(valor);
            return String(valor);
        }
        function abrirDetalle(fila) {
            const datos = JSON.parse(fila.dataset.evento);
            for (const clave of ['persona', 'fecha', 'modulo', 'ip', 'descripcion']) detalle.querySelector(`[data-detalle-${clave}]`).textContent = datos[clave];
            const accion = detalle.querySelector('[data-detalle-accion]');
            accion.textContent = datos.etiqueta;
            accion.dataset.accion = datos.accion;
            detalle.querySelector('[data-detalle-titulo]').textContent = datos.modulo + (datos.registro !== null ? ` · registro ${datos.registro}` : '');
            const antes = aplanar(datos.antes);
            const despues = aplanar(datos.despues);
            const cuerpo = detalle.querySelector('[data-detalle-cambios]');
            cuerpo.replaceChildren();
            for (const clave of new Set([...Object.keys(antes), ...Object.keys(despues)])) {
                if (JSON.stringify(antes[clave]) === JSON.stringify(despues[clave])) continue;
                const registro = document.createElement('tr');
                for (const valor of [nombresCampos[clave] || clave.replaceAll('_', ' '), mostrarValor(antes[clave]), mostrarValor(despues[clave])]) {
                    const celda = document.createElement('td');
                    celda.textContent = valor;
                    registro.append(celda);
                }
                cuerpo.append(registro);
            }
            detalle.querySelector('.auditoria-cambios table').hidden = !cuerpo.children.length;
            detalle.querySelector('[data-detalle-sin-cambios]').hidden = !!cuerpo.children.length;
            globalThis.zookiAbrirDialogo('auditoriaDetalle');
        }
        raiz.addEventListener('click', evento => {
            const fila = evento.target.closest('[data-evento]');
            if (fila) abrirDetalle(fila);
        });
        raiz.addEventListener('keydown', evento => {
            const fila = evento.target.closest('[data-evento]');
            if (!fila || !['Enter', ' '].includes(evento.key)) return;
            evento.preventDefault();
            abrirDetalle(fila);
        });
        movil.addEventListener('change', situar);
        window.addEventListener('resize', paginar);
        marcarPeriodo();
        situar();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
    else iniciar();
})();

// Exporta el lote completo del servidor, también las filas ocultas por la presentación móvil.
function datosExportacionAuditoria() {
    return Array.from(document.querySelectorAll('[data-auditoria] [data-evento]'), fila => {
        const dato = JSON.parse(fila.dataset.evento);
        const descripcion = dato.descripcion + (dato.registro !== null ? ` · ID: ${dato.registro}` : '');
        return [dato.fecha, dato.persona, dato.accion, dato.tabla, descripcion, dato.ip];
    });
}
async function exportarAuditoriaExcel() {
    try {
        if (!window.ExcelJS) throw new Error('Excel no disponible');
        const libro = new ExcelJS.Workbook();
        libro.creator = 'Zooki';
        const hoja = libro.addWorksheet('Auditoría');
        hoja.addRow(['FECHA/HORA', 'USUARIO', 'ACCIÓN', 'TABLA', 'DESCRIPCIÓN', 'IP']);
        hoja.addRows(datosExportacionAuditoria());
        hoja.columns = [{ width: 20 }, { width: 25 }, { width: 20 }, { width: 25 }, { width: 70 }, { width: 22 }];
        hoja.getRow(1).font = { bold: true };
        const contenido = await libro.xlsx.writeBuffer();
        const url = URL.createObjectURL(new Blob([contenido], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }));
        const enlace = document.createElement('a');
        enlace.href = url;
        enlace.download = `Zooki_Auditoria_${new Date().toISOString().slice(0, 10)}.xlsx`;
        enlace.click();
        URL.revokeObjectURL(url);
        document.querySelector('[data-exportar]').open = false;
        globalThis.zookiToast?.('Excel exportado.');
    } catch (error) {
        globalThis.zookiToast?.('No se pudo generar el Excel. Intenta de nuevo.', 'error');
    }
}
function exportarAuditoriaPDF() {
    try {
        if (!window.jspdf) throw new Error('PDF no disponible');
        const { jsPDF } = window.jspdf;
        const documento = new jsPDF('landscape');
        documento.setFontSize(16);
        documento.text('Zooki · Auditoría', 14, 18);
        documento.autoTable({ head: [['Fecha/hora', 'Persona', 'Acción', 'Tabla', 'Descripción', 'IP']], body: datosExportacionAuditoria(), startY: 25, styles: { fontSize: 8 }, headStyles: { fillColor: [0, 82, 255] } });
        documento.save(`Zooki_Auditoria_${new Date().toISOString().slice(0, 10)}.pdf`);
        document.querySelector('[data-exportar]').open = false;
        globalThis.zookiToast?.('PDF exportado.');
    } catch (error) {
        globalThis.zookiToast?.('No se pudo generar el PDF. Intenta de nuevo.', 'error');
    }
}
