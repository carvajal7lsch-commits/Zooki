// RE-2.12.6: el CI verifica presentación e interacción sin necesitar una GPU.
function instalarMapaFalso() {
    const controles = {};
    const valores = {};
    let escena;
    escena = new Proxy({}, {
        get: (_, clave) => {
            if (clave === 'controls') return () => controles;
            if (clave === 'd3Force') return () => ({ strength: () => {} });
            if (clave === 'graph2ScreenCoords') return () => ({ x: 80, y: 80 });
            return (...args) => {
                if (!args.length) return valores[clave];
                valores[clave] = args[0];
                if (clave === 'graphData') {
                    args[0].nodes.forEach((nodo, indice) => {
                        nodo.x = indice + 1;
                        nodo.y = indice + 2;
                        nodo.z = indice + 3;
                    });
                }
                return escena;
            };
        },
    });
    window.ForceGraph3D = () => () => escena;
    window.mapaPrueba = valores;
    HTMLCanvasElement.prototype.getContext = () => ({});
}

module.exports = { instalarMapaFalso };
