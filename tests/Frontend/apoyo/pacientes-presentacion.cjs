const fs = require('node:fs');

/** Mismo orden de hojas del layout; cada archivo conserva su propio contexto de parseo. */
function estilosPacientes() {
    return ['public/css/identidad.css', 'public/css/styles.css', ...['layout', 'bento-kpi', 'users', 'vet-panel', 'utilities-clients'].map(n => `public/css/dashboard-modules/${n}.css`), 'public/css/usuarios.css', ...['base', 'interactive-panel', 'timeline', 'consultas', 'forms-modals'].map(n => `public/css/medical/${n}.css`), 'public/css/pacientes.css'].map(p => `<style>${fs.readFileSync(p, 'utf8').replace(/@import[^;]+;/g, '')}</style>`).join('\n');
}

module.exports = { estilosPacientes };
