/**
 * Panel de inicio del administrador (HU-57).
 *
 * La vista llega pintada desde el servidor; aquí se filtra la lista de citas
 * de hoy y se dibuja la gráfica de asistencia con los datos del atributo
 * data-serie del canvas.
 */
(() => {
    // Colores validados para daltonismo y contraste sobre fondo blanco.
    const COLOR_ATENDIDAS = '#2563EB';
    const COLOR_NO_ASISTIDAS = '#E8710A';

    function iniciarFiltros() {
        const lista = document.getElementById('panelCitasHoy');
        if (!lista) return;
        const vacio = document.getElementById('panelCitasVacio');
        const botones = document.querySelectorAll('.panel-filtro');

        botones.forEach(boton => boton.addEventListener('click', () => {
            const filtro = boton.dataset.filtro;
            botones.forEach(b => {
                const activo = b === boton;
                b.classList.toggle('is-activo', activo);
                b.setAttribute('aria-pressed', String(activo));
            });

            let visibles = 0;
            lista.querySelectorAll('.panel-fila').forEach(fila => {
                const mostrar = filtro === 'todas' || fila.dataset.grupo === filtro;
                fila.hidden = !mostrar;
                if (mostrar) visibles++;
            });
            vacio.hidden = visibles > 0;
        }));
    }

    function iniciarGrafica() {
        const canvas = document.getElementById('panelTendencia');
        if (!canvas || typeof Chart === 'undefined') return;

        let serie;
        try {
            serie = JSON.parse(canvas.dataset.serie || '[]');
        } catch (e) {
            console.error('Serie de tendencia inválida', e);
            return;
        }

        const barra = color => ({
            backgroundColor: color,
            hoverBackgroundColor: color,
            borderColor: '#FFFFFF',
            borderWidth: 2,
            borderRadius: 4,
            borderSkipped: 'bottom',
            maxBarThickness: 28,
        });

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: serie.map(m => m.etiqueta),
                datasets: [
                    { label: 'Atendidas', data: serie.map(m => m.atendidas), ...barra(COLOR_ATENDIDAS) },
                    { label: 'No asistieron', data: serie.map(m => m.no_asistidas), ...barra(COLOR_NO_ASISTIDAS) },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'end',
                        labels: { boxWidth: 10, boxHeight: 10, useBorderRadius: true, borderRadius: 2, color: '#475569', font: { family: "'Inter', sans-serif", size: 12 } },
                    },
                    tooltip: {
                        backgroundColor: '#0F172A',
                        padding: 10,
                        titleFont: { family: "'Inter', sans-serif", weight: '600' },
                        bodyFont: { family: "'Inter', sans-serif" },
                        callbacks: { title: items => `Citas de ${items[0].label}` },
                    },
                },
                scales: {
                    x: { grid: { display: false }, border: { display: false }, ticks: { color: '#475569' } },
                    y: {
                        beginAtZero: true,
                        border: { display: false },
                        grid: { color: '#EEF2F6' },
                        ticks: { color: '#94A3B8', precision: 0 },
                    },
                },
            },
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        iniciarFiltros();
        iniciarGrafica();
    });
})();
