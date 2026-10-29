// RE-2.5.2/RE-5.5.1/RE-5.9.2: historial sin depender del idioma ni de UTC.
function fechaHistorial(fecha) {
    const partes = String(fecha || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (!partes) return 'Sin fecha';
    return `${partes[3]}/${partes[2]}/${partes[1]}`;
}
