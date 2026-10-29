/* RNF-16, R B: el mismo botón de envío sale del cuerpo desplazable solo en celular.
 * Al volver a tableta se repone en su lugar, sin cambiar listeners ni formularios. */
document.addEventListener('DOMContentLoaded', () => {
    const celular = window.matchMedia('(max-width: 767px)');
    const pies = [];
    document.querySelectorAll('.pacientes-hoja.drawer form').forEach(formulario => {
        const guardar = formulario.querySelector('[type="submit"]');
        if (!guardar) return;
        const marca = document.createComment('Lugar del botón en escritorio');
        guardar.before(marca);
        const pie = document.createElement('div');
        pie.className = 'users-modal__footer';
        const cancelar = document.createElement('button');
        cancelar.type = 'button';
        cancelar.className = 'btn-modal-secondary';
        cancelar.textContent = 'Cancelar';
        cancelar.addEventListener('click', () => closeDrawer(formulario.closest('.drawer').id));
        pie.append(cancelar);
        pies.push({ formulario, guardar, marca, pie });
    });
    function adaptar() {
        pies.forEach(({ formulario, guardar, marca, pie }) => {
            if (celular.matches) {
                pie.append(guardar);
                formulario.append(pie);
            } else {
                marca.after(guardar);
                pie.remove();
            }
        });
    }
    celular.addEventListener('change', adaptar);
    adaptar();
});
