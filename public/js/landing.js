/**
 * ZOOKI · LANDING PAGE
 * Comportamiento de la pagina publica. Cada modulo se inicializa por
 * separado y falla en silencio si su marcado no esta presente, de modo
 * que el archivo pueda reutilizarse en las paginas legales.
 */
(function () {
    'use strict';

    /** Respeta la preferencia del sistema de reducir animaciones. */
    var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ------------------------------------------------------------
       Menu lateral (drawer) para moviles
       ------------------------------------------------------------ */
    function initDrawer() {
        var toggle = document.getElementById('navToggle');
        var drawer = document.getElementById('navDrawer');
        var overlay = document.getElementById('navOverlay');
        var closeBtn = document.getElementById('navClose');

        if (!toggle || !drawer || !overlay) return;

        function openDrawer() {
            drawer.classList.add('is-open');
            overlay.classList.add('is-open');
            document.body.classList.add('nav-open');
            toggle.setAttribute('aria-expanded', 'true');
            drawer.setAttribute('aria-hidden', 'false');
        }

        function closeDrawer() {
            drawer.classList.remove('is-open');
            overlay.classList.remove('is-open');
            document.body.classList.remove('nav-open');
            toggle.setAttribute('aria-expanded', 'false');
            drawer.setAttribute('aria-hidden', 'true');
        }

        toggle.addEventListener('click', openDrawer);
        overlay.addEventListener('click', closeDrawer);
        if (closeBtn) closeBtn.addEventListener('click', closeDrawer);

        // Al elegir un destino el menu debe cerrarse solo.
        drawer.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', closeDrawer);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && drawer.classList.contains('is-open')) {
                closeDrawer();
                toggle.focus();
            }
        });

        // Si el usuario vuelve a escritorio, el estado movil se descarta.
        window.matchMedia('(min-width: 993px)').addEventListener('change', function (event) {
            if (event.matches) closeDrawer();
        });
    }

    /* ------------------------------------------------------------
       Barra superior: sombra al hacer scroll
       ------------------------------------------------------------ */
    function initHeaderState() {
        var header = document.getElementById('landingHeader');
        if (!header) return;

        var ticking = false;

        function update() {
            header.classList.toggle('is-scrolled', window.scrollY > 24);
            ticking = false;
        }

        window.addEventListener('scroll', function () {
            if (!ticking) {
                window.requestAnimationFrame(update);
                ticking = true;
            }
        }, { passive: true });

        update();
    }

    /* ------------------------------------------------------------
       Resaltado del enlace de la seccion visible
       ------------------------------------------------------------ */
    function initScrollSpy() {
        var links = document.querySelectorAll('.lp-nav__link[href^="#"]');
        if (!links.length || !('IntersectionObserver' in window)) return;

        var map = {};
        var sections = [];

        links.forEach(function (link) {
            var id = link.getAttribute('href').slice(1);
            var section = document.getElementById(id);
            if (section) {
                map[id] = link;
                sections.push(section);
            }
        });

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                links.forEach(function (link) { link.classList.remove('is-active'); });
                var active = map[entry.target.id];
                if (active) active.classList.add('is-active');
            });
        }, { rootMargin: '-45% 0px -50% 0px', threshold: 0 });

        sections.forEach(function (section) { observer.observe(section); });
    }

    /* ------------------------------------------------------------
       Aparicion progresiva de bloques al entrar en pantalla
       ------------------------------------------------------------ */
    function initReveal() {
        var items = document.querySelectorAll('.lp-reveal');
        if (!items.length) return;

        // Sin soporte o con movimiento reducido, se muestra todo de una vez.
        if (prefersReducedMotion || !('IntersectionObserver' in window)) {
            items.forEach(function (item) { item.classList.add('is-visible'); });
            return;
        }

        var observer = new IntersectionObserver(function (entries, obs) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                obs.unobserve(entry.target);
            });
        }, { rootMargin: '0px 0px -12% 0px', threshold: 0.12 });

        items.forEach(function (item) { observer.observe(item); });
    }

    /* ------------------------------------------------------------
       Pestanas de roles
       ------------------------------------------------------------ */
    function initRoleTabs() {
        var tabs = document.querySelectorAll('.lp-roles__tab');
        var panels = document.querySelectorAll('.lp-roles__panel');
        if (!tabs.length || !panels.length) return;

        function activate(targetId) {
            tabs.forEach(function (tab) {
                var isActive = tab.dataset.role === targetId;
                tab.classList.toggle('is-active', isActive);
                tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
                tab.setAttribute('tabindex', isActive ? '0' : '-1');
            });

            panels.forEach(function (panel) {
                panel.classList.toggle('is-active', panel.id === 'rol-' + targetId);
            });
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                activate(tab.dataset.role);
            });
        });

        // Navegacion con flechas segun el patron ARIA de pestanas.
        var tabList = document.querySelector('.lp-roles__tabs');
        if (tabList) {
            tabList.addEventListener('keydown', function (event) {
                if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') return;

                var list = Array.prototype.slice.call(tabs);
                var current = list.indexOf(document.activeElement);
                if (current === -1) return;

                var step = event.key === 'ArrowRight' ? 1 : -1;
                var next = list[(current + step + list.length) % list.length];

                event.preventDefault();
                next.focus();
                activate(next.dataset.role);
            });
        }
    }

    /* ------------------------------------------------------------
       Acordeon de preguntas frecuentes
       ------------------------------------------------------------ */
    function initFaq() {
        var items = document.querySelectorAll('.lp-faq__item');
        if (!items.length) return;

        items.forEach(function (item) {
            var question = item.querySelector('.lp-faq__question');
            var answer = item.querySelector('.lp-faq__answer');
            if (!question || !answer) return;

            question.addEventListener('click', function () {
                var willOpen = !item.classList.contains('is-open');

                // Solo una respuesta abierta a la vez.
                items.forEach(function (other) {
                    var otherAnswer = other.querySelector('.lp-faq__answer');
                    var otherQuestion = other.querySelector('.lp-faq__question');
                    other.classList.remove('is-open');
                    if (otherAnswer) otherAnswer.style.maxHeight = null;
                    if (otherQuestion) otherQuestion.setAttribute('aria-expanded', 'false');
                });

                if (willOpen) {
                    item.classList.add('is-open');
                    answer.style.maxHeight = answer.scrollHeight + 'px';
                    question.setAttribute('aria-expanded', 'true');
                }
            });
        });

        // Recalcula la altura abierta cuando cambia el ancho del texto.
        window.addEventListener('resize', function () {
            var open = document.querySelector('.lp-faq__item.is-open .lp-faq__answer');
            if (open) open.style.maxHeight = open.scrollHeight + 'px';
        });
    }

    /* ------------------------------------------------------------
       Arranque
       ------------------------------------------------------------ */
    document.addEventListener('DOMContentLoaded', function () {
        initDrawer();
        initHeaderState();
        initScrollSpy();
        initReveal();
        initRoleTabs();
        initFaq();
    });
})();
