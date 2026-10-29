/* HU-0.3 / HU-2.12: menús de la cabecera compartida, también con ratón y teclado. */
(function () {
    'use strict';

    const menus = [...document.querySelectorAll('.plataforma-menu-cuenta')];
    if (menus.length === 0) return;

    document.addEventListener('click', evento => {
        for (const menu of menus) {
            if (!menu.open) continue;
            if (!menu.contains(evento.target) || evento.target.closest?.('a')) {
                menu.open = false;
            }
        }
    }, true);

    document.addEventListener('keydown', evento => {
        if (evento.key !== 'Escape') return;
        const abiertos = menus.filter(menu => menu.open);
        if (abiertos.length === 0) return;
        for (const menu of abiertos) {
            menu.open = false;
        }
        abiertos[0].querySelector('summary').focus();
        // Escape cierra el menú superior sin cerrar también una ficha o un modal del fondo.
        evento.preventDefault();
        evento.stopPropagation();
    }, true);
}());
