/** B1: cada intención conserva su clave ante un reintento de red. */
(function (raiz) {
    function formatearHora(hora, am = 'AM', pm = 'PM') {
        if (!hora) return '';
        const [h, m = '00', s = '00'] = String(hora).split(':');
        const horas = Number(h);
        const segundos = s.padStart(2, '0');
        const reloj = `${horas % 12 || 12}:${m.padStart(2, '0')}${segundos === '00' ? '' : ':' + segundos}`;
        return `${reloj} ${horas < 12 ? am : pm}`;
    }

    function crearComando(azar = () => raiz.crypto.getRandomValues(new Uint8Array(32))) {
        let huella = null;
        let clave = null;
        return {
            reiniciar() {
                huella = null;
                clave = null;
            },
            clave(datos) {
                const entradas = Array.from(datos.entries()).filter(([campo]) => !['csrf_token', 'clave_operacion', 'ignore_warning'].includes(campo));
                entradas.sort(([a], [b]) => a.localeCompare(b));
                const actual = JSON.stringify(entradas);
                if (actual !== huella) {
                    huella = actual;
                    clave = Array.from(azar(), n => n.toString(16).padStart(2, '0')).join('');
                }
                return clave;
            }
        };
    }
    /** H-3: excluir el instante actual solo en su fecha, sin alterar la disponibilidad del servidor. */
    function horariosReprogramacion(horas, fecha, cita) {
        return (Array.isArray(horas) ? horas : []).filter(hora => !cita || fecha !== cita.fecha || String(hora).slice(0, 5) !== String(cita.hora).slice(0, 5));
    }
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { crearComando, formatearHora, horariosReprogramacion };
    } else {
        raiz.zookiCrearComandoAgenda = crearComando;
        raiz.zookiHoraAgenda = formatearHora;
        raiz.zookiHorariosReprogramacion = horariosReprogramacion;
    }
})(globalThis);
