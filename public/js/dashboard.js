/**
 * Zooki Dashboard v2 — Role-Aware with Charts
 */

// ── Paleta de colores ────────────────────────────────────────────────────
const SPECIES_COLORS = [
  "#5560FF",
  "#F59E0B",
  "#10B981",
  "#06B6D4",
  "#8B5CF6",
  "#EF4444",
];
const STATE_COLORS = {
  pendiente: "#F59E0B",
  confirmada: "#5560FF",
  en_curso: "#0EA5E9",
  completada: "#10B981",
  no_asistio: "#94A3B8",
  sin_cerrar: "#E11D48",
  cerrada_sin_consulta: "#64748B",
  cancelada: "#EF4444",
};
const STATE_LABELS = {
  pendiente: "Pendiente",
  confirmada: "Confirmada",
  en_curso: "En curso",
  completada: "Completada",
  no_asistio: "No asistió",
  sin_cerrar: "Sin cerrar",
  cerrada_sin_consulta: "Cerrada sin consulta",
  cancelada: "Cancelada",
};

if (typeof Chart !== "undefined") {
  Chart.defaults.font.family = "'Inter', sans-serif";
  Chart.defaults.font.size = 12;
  Chart.defaults.plugins.legend.display = false;
  Chart.defaults.plugins.tooltip.padding = 10;
  Chart.defaults.plugins.tooltip.cornerRadius = 8;
  Chart.defaults.plugins.tooltip.backgroundColor = "rgba(26,29,35,0.9)";
}

// ── Loader global ────────────────────────────────────────────────────────
// Antes se mostraba en cada petición y se ocultaba al terminar cada una: una
// pantalla que dispara varias (el calendario, por ejemplo) hacía parpadear el
// velo blanco varias veces y parecía que la página se recargaba. Ahora cuenta
// las peticiones en curso y solo aparece si tardan más de LOADER_ESPERA_MS.
const _loader = document.getElementById("global-loader");
const _origFetch = window.fetch;
const LOADER_ESPERA_MS = 400;
let _peticionesEnCurso = 0;
let _temporizadorLoader = null;

window.fetch = async (...args) => {
  // Trabajo en segundo plano (p. ej. el envío de un correo): no bloquea la
  // pantalla. Se pide con fetch(url, { ..., sinLoader: true }).
  if (args[1] && args[1].sinLoader) {
    return _origFetch(...args);
  }
  _peticionesEnCurso++;
  if (_loader && !_temporizadorLoader) {
    _temporizadorLoader = setTimeout(() => {
      if (_peticionesEnCurso > 0) _loader.style.display = "flex";
    }, LOADER_ESPERA_MS);
  }
  try {
    return await _origFetch(...args);
  } finally {
    _peticionesEnCurso--;
    if (_peticionesEnCurso === 0) {
      clearTimeout(_temporizadorLoader);
      _temporizadorLoader = null;
      if (_loader) _loader.style.display = "none";
    }
  }
};

// TR-02 — Aqui se sobrescribia window.alert para que abriera un SweetAlert.
// Funcionaba, pero solo en las paginas que cargan este archivo: en el portal
// del propietario, que no lo carga, las mismas llamadas seguian siendo alert()
// nativo. Ademas un `alert()` en el codigo dejaba de significar lo que
// significa en cualquier otro proyecto. Ahora los avisos se piden de forma
// explicita con zookiToast/zookiAviso/zookiConfirmar (public/js/avisos.js).

// ── Init ─────────────────────────────────────────────────────────────────
document.addEventListener("DOMContentLoaded", () => {
  loadSystemNotifications();
  loadVetsReprogram();
  initSearch();
  initNotifClose();
  initNotifMarkAll();

  // Fix z-index overlapping with sidebar
  document.querySelectorAll('.modal, .users-modal').forEach(modal => {
    document.body.appendChild(modal);
  });

  // Los paneles del administrador y del veterinario llegan pintados desde el
  // servidor (PanelController); estas cargas solo sirven a recepcion.
  if (typeof ZOOKI_ROLE === "undefined" || ZOOKI_ROLE !== 3) return;

  loadRoleStats();
  loadChartsData();
  loadTimeline();
  startCountdown();
});

// ── KPI Stats ────────────────────────────────────────────────────────────
async function loadRoleStats() {
  try {
    const res = await (
      await fetch("index.php?action=get_role_stats_ajax")
    ).json();
    if (!res.success) return;
    const s = res.stats;
    setCount("stat-citas-hoy", s.citas_hoy ?? null);
    setCount("stat-pacientes", s.pacientes ?? null);
    setCount("stat-clientes", s.clientes ?? null);
    setCount("stat-consultas", s.consultas_mes ?? null);
    setCount("stat-vet-citas-hoy", s.citas_hoy ?? null);
    setCount("stat-vet-consultas", s.consultas_hoy ?? null);
    setCount("stat-vet-pacientes", s.pacientes_atendidos ?? null);
    setCount("stat-recep-citas", s.citas_hoy ?? null);
    setCount("stat-recep-pendientes", s.pendientes ?? null);
    setCount("stat-recep-atendidas", s.atendidas ?? null);
  } catch (e) {
    console.error("loadRoleStats", e);
  }
}

function setCount(id, val) {
  if (val === null) return;
  const el = document.getElementById(id);
  if (!el) return;
  animateCount(el, parseInt(val) || 0);
}

function animateCount(el, target) {
  const dur = 900,
    start = performance.now();
  const run = (now) => {
    const p = Math.min((now - start) / dur, 1);
    const e = 1 - Math.pow(1 - p, 3);
    el.textContent = Math.round(target * e);
    if (p < 1) requestAnimationFrame(run);
  };
  requestAnimationFrame(run);
}

// ── Charts Data ───────────────────────────────────────────────────────────
async function loadChartsData() {
  try {
    const res = await (
      await fetch("index.php?action=get_charts_data_ajax")
    ).json();
    if (!res.success) return;
    const d = res.data;
    if (ZOOKI_ROLE === 1) {
      if (d.citas_mes) renderCitasMes(d.citas_mes);
      if (d.especies) renderEspecies(d.especies);
      // d.dias_semana ya no se renderiza aquí
      if (d.ranking_vets) renderRanking(d.ranking_vets);
    } else if (ZOOKI_ROLE === 2) {
      if (d.mis_citas_estado) renderMisCitas(d.mis_citas_estado);
      if (d.mis_especies) renderMisEspecies(d.mis_especies);
    } else if (ZOOKI_ROLE === 3) {
      if (d.estado_citas_hoy) renderEstadoHoy(d.estado_citas_hoy);
    }
  } catch (e) {
    console.error("loadChartsData", e);
  }
}

// ── Renderers ─────────────────────────────────────────────────────────────

function renderCitasMes(data) {
  const canvas = document.getElementById("chart-citas-mes");
  if (!canvas) return;
  new Chart(canvas, {
    type: "line",
    data: {
      labels: data.map((d) => d.mes),
      datasets: [
        {
          label: "Citas Programadas",
          data: data.map((d) => parseInt(d.total)),
          borderColor: "#0052FF",
          backgroundColor: "rgba(0, 82, 255, 0.15)",
          borderWidth: 3,
          tension: 0.4,
          fill: true,
          pointBackgroundColor: "#fff",
          pointBorderColor: "#0052FF",
          pointBorderWidth: 2,
          pointRadius: 4,
          pointHoverRadius: 6,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: "#1e293b",
          padding: 12,
          titleFont: { size: 13, family: "Inter" },
          bodyFont: { size: 14, family: "Inter", weight: "bold" },
          displayColors: false,
          callbacks: { label: (c) => ` ${c.raw} citas` }
        },
      },
      scales: {
        y: {
          beginAtZero: true,
          grid: { borderDash: [4, 4], color: "#e2e8f0" },
          ticks: { stepSize: 1, font: { size: 11 } },
        },
        x: {
          grid: { display: false },
          ticks: { font: { size: 11 } },
        },
      },
    },
  });
}

function renderEspecies(data) {
  const canvas = document.getElementById("chart-especies");
  if (!canvas) return;
  new Chart(canvas, {
    type: "doughnut",
    data: {
      labels: data.map((d) => d.nombre_especie || "General"),
      datasets: [
        {
          data: data.map((d) => parseInt(d.total)),
          backgroundColor: [
            "#0052FF", "#10B981", "#F59E0B", "#8B5CF6", "#EC4899", "#14B8A6"
          ],
          borderWidth: 0,
          hoverOffset: 6,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: "70%",
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: "#1e293b",
          padding: 12,
          titleFont: { size: 13, family: "Inter" },
          bodyFont: { size: 14, family: "Inter", weight: "bold" },
          callbacks: { label: (c) => ` ${c.label}: ${c.raw}` }
        },
      },
    },
  });
  const leg = document.getElementById("especies-legend");
  if (leg) {
    const colors = ["#0052FF", "#10B981", "#F59E0B", "#8B5CF6", "#EC4899", "#14B8A6"];
    leg.innerHTML = data
      .map(
        (d, i) =>
          `<div style="display:flex; align-items:center; gap:0.4rem; padding: 0.2rem 0.5rem; background:#f8fafc; border-radius:6px; font-weight:600;"><span style="width:10px; height:10px; border-radius:50%; background:${colors[i]}"></span>${d.nombre_especie} <span style="color:#64748b;">(${d.total})</span></div>`,
      )
      .join("");
  }
}

// renderDiasSemana (Eliminado para simplificar el dashboard)

function renderRanking(data) {
  const container = document.getElementById("ranking-vets-list");
  if (!container) return;
  if (!data.length) {
    container.innerHTML =
      '<div class="no-data-placeholder"><i class="fas fa-user-md"></i><p>Sin datos aún</p></div>';
    return;
  }
  const maxC = Math.max(...data.map((d) => parseInt(d.consultas)), 1);
  const medals = ["gold", "silver", "bronze", "", ""];
  container.innerHTML = data
    .map((v, i) => {
      const first = v.nombre_completo.split(" ")[0];
      const pct = Math.round((parseInt(v.consultas) / maxC) * 100);
      return `<div class="ranking-item">
            <span class="rank-num ${medals[i]}">#${i + 1}</span>
            <div class="rank-bar-wrap">
                <div class="rank-name">${first}</div>
                <div class="rank-bar-bg"><div class="rank-bar-fill" data-w="${pct}"></div></div>
            </div>
            <span class="rank-count">${v.consultas}</span>
        </div>`;
    })
    .join("");
  // Animate bars
  requestAnimationFrame(() => {
    container.querySelectorAll(".rank-bar-fill").forEach((el) => {
      el.style.width = el.dataset.w + "%";
    });
  });
}

function renderMisCitas(data) {
  const canvas = document.getElementById("chart-mis-citas");
  if (!canvas) return;
  const estados = ["pendiente", "confirmada", "en_curso", "sin_cerrar", "completada", "no_asistio", "cerrada_sin_consulta", "cancelada"];
  const counts = estados.map((st) => {
    const f = data.find((d) => d.estado === st);
    return f ? parseInt(f.total) : 0;
  });
  if (!counts.reduce((a, b) => a + b, 0)) {
    const wrap = canvas.closest(".chart-wrap-donut");
    if (wrap)
      wrap.innerHTML =
        '<div class="no-data-placeholder"><i class="far fa-calendar"></i><p>Sin citas esta semana</p></div>';
    return;
  }
  new Chart(canvas, {
    type: "doughnut",
    data: {
      labels: estados.map((e) => STATE_LABELS[e]),
      datasets: [
        {
          data: counts,
          backgroundColor: estados.map((e) => STATE_COLORS[e]),
          borderWidth: 0,
          hoverOffset: 6,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: "68%",
      plugins: {
        tooltip: { callbacks: { label: (c) => ` ${c.label}: ${c.raw}` } },
      },
    },
  });
  const leg = document.getElementById("mis-citas-legend");
  if (leg)
    leg.innerHTML = estados
      .filter((_, i) => counts[i] > 0)
      .map(
        (st) =>
          `<div class="legend-item"><span class="legend-dot" style="background:${STATE_COLORS[st]}"></span>${STATE_LABELS[st]} (${counts[estados.indexOf(st)]})</div>`,
      )
      .join("");
}

function renderMisEspecies(data) {
  const canvas = document.getElementById("chart-mis-especies");
  if (!canvas || !data.length) return;
  new Chart(canvas, {
    type: "bar",
    data: {
      labels: data.map((d) => d.nombre_especie),
      datasets: [
        {
          data: data.map((d) => parseInt(d.total)),
          backgroundColor: SPECIES_COLORS.slice(0, data.length),
          borderRadius: 6,
          borderSkipped: false,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        tooltip: { callbacks: { label: (c) => ` ${c.raw} consultas` } },
      },
      scales: {
        x: { grid: { display: false }, ticks: { font: { size: 11 } } },
        y: { display: false },
      },
    },
  });
}

function renderEstadoHoy(data) {
  const canvas = document.getElementById("chart-estado-hoy");
  if (!canvas) return;
  const estados = ["pendiente", "confirmada", "en_curso", "sin_cerrar", "completada", "no_asistio", "cerrada_sin_consulta", "cancelada"];
  const counts = estados.map((st) => {
    const f = data.find((d) => d.estado === st);
    return f ? parseInt(f.total) : 0;
  });
  if (!counts.reduce((a, b) => a + b, 0)) return;
  new Chart(canvas, {
    type: "doughnut",
    data: {
      labels: estados.map((e) => STATE_LABELS[e]),
      datasets: [
        {
          data: counts,
          backgroundColor: estados.map((e) => STATE_COLORS[e]),
          borderWidth: 0,
          hoverOffset: 6,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: "68%",
      plugins: {
        tooltip: { callbacks: { label: (c) => ` ${c.label}: ${c.raw}` } },
      },
    },
  });
  const leg = document.getElementById("estado-hoy-legend");
  if (leg)
    leg.innerHTML = estados
      .filter((_, i) => counts[i] > 0)
      .map(
        (st) =>
          `<div class="legend-item"><span class="legend-dot" style="background:${STATE_COLORS[st]}"></span>${STATE_LABELS[st]} (${counts[estados.indexOf(st)]})</div>`,
      )
      .join("");
}

// ── Timeline (Recepcionista) ──────────────────────────────────────────────
let _timelineData = [];

async function loadTimeline() {
  const container = document.getElementById("timeline-list");
  if (!container) return;
  try {
    const res = await (
      await fetch("index.php?action=get_timeline_ajax")
    ).json();
    if (!res.success) return;
    _timelineData = res.citas;
    renderTimeline(container, _timelineData);
    updateCountdown(_timelineData);
    updatePillFromTimeline(_timelineData);
  } catch (e) {
    console.error("loadTimeline", e);
  }
}

function renderTimeline(container, citas) {
  if (!citas.length) {
    container.innerHTML =
      '<div class="no-data-placeholder"><i class="far fa-calendar-times"></i><p>No hay citas programadas para hoy</p></div>';
    return;
  }
  container.innerHTML = citas
    .map((c) => {
      const hora = c.hora.substring(0, 5);
      const vetFirst = c.veterinario_nombre.split(" ")[0];
      const initial = c.mascota_nombre.charAt(0).toUpperCase();
      return `<div class="schedule-item st-${c.estado}">
            <span class="si-time">${hora}</span>
            <div class="si-avatar">${initial}</div>
            <div class="si-body">
                <div class="si-pet">${c.mascota_nombre} <small style="font-weight:400;color:#94A3B8;">· ${c.nombre_especie || ""}</small></div>
                <div class="si-meta">${c.propietario_nombre} · Dr. ${vetFirst} · ${c.motivo}</div>
            </div>
            <span class="si-badge st-${c.estado}">${STATE_LABELS[c.estado] || c.estado}</span>
        </div>`;
    })
    .join("");
}

function updateCountdown(citas) {
  const cdTime = document.getElementById("cd-time");
  const cdSub = document.getElementById("cd-sub");
  if (!cdTime) return;
  const now = new Date();
  const proxima = citas.find((c) => {
    if (["cancelada", "completada", "no_asistio", "cerrada_sin_consulta"].includes(c.estado)) return false;
    const [h, m] = c.hora.split(":").map(Number);
    const dt = new Date();
    dt.setHours(h, m, 0, 0);
    return dt > now;
  });
  if (!proxima) {
    cdTime.textContent = "—";
    if (cdSub) cdSub.textContent = "Sin citas pendientes";
    return;
  }
  const [h, m] = proxima.hora.split(":").map(Number);
  const dt = new Date();
  dt.setHours(h, m, 0, 0);
  const diffMin = Math.round((dt - now) / 60000);
  if (diffMin <= 0) cdTime.textContent = "¡Ahora!";
  else if (diffMin < 60) cdTime.textContent = `${diffMin} min`;
  else {
    const hh = Math.floor(diffMin / 60);
    cdTime.textContent = `${hh}h ${diffMin % 60}m`;
  }
  if (cdSub)
    cdSub.textContent = `${proxima.mascota_nombre} · ${proxima.propietario_nombre.split(" ")[0]}`;
}

function updatePillFromTimeline(citas) {
  const pill = document.getElementById("pill-percent");
  if (!pill) return;
  const total = citas.filter((c) => c.estado !== "cancelada").length;
  const done = citas.filter((c) => c.estado === "completada").length;
  pill.textContent = total ? `${Math.round((done / total) * 100)}%` : "0%";
}

function startCountdown() {
  setInterval(() => {
    if (_timelineData.length) updateCountdown(_timelineData);
  }, 60000);
}

// ── Agenda Semanal (Admin & Vet) ──────────────────────────────────────────
async function loadAgenda() {
  const container = document.getElementById("agendaTableBody");
  if (!container) return;
  try {
    const citas = await (
      await fetch("index.php?action=listar_citas_ajax")
    ).json();

    if (!citas.length) {
      container.innerHTML =
        '<div class="agenda-empty"><i class="far fa-calendar-times"></i><p>No hay citas esta semana</p></div>';
      return;
    }

    const STATE_COLORS_TL = {
      pendiente:  { bg: "#FEF3C7", color: "#92400E", dot: "#F59E0B" },
      confirmada: { bg: "#EEF2FF", color: "#3730A3", dot: "#6366F1" },
      en_curso:   { bg: "#E0F2FE", color: "#075985", dot: "#0EA5E9" },
      completada: { bg: "#DCFCE7", color: "#166534", dot: "#10B981" },
      no_asistio: { bg: "#F1F5F9", color: "#475569", dot: "#94A3B8" },
      sin_cerrar: { bg: "#FFE4E6", color: "#9F1239", dot: "#E11D48" },
      cerrada_sin_consulta: { bg: "#E2E8F0", color: "#334155", dot: "#64748B" },
      cancelada:  { bg: "#FEE2E2", color: "#991B1B", dot: "#EF4444" },
    };

    container.innerHTML = citas.map((c) => {
      const hora   = c.hora.substring(0, 5);
      const fecha  = new Date(c.fecha + "T00:00:00").toLocaleDateString("es-CO", { weekday: "short", month: "short", day: "numeric" });
      const initial = c.mascota_nombre.charAt(0).toUpperCase();
      const vetFirst = c.veterinario_nombre.split(" ")[0];
      const st = STATE_COLORS_TL[c.estado] || { bg: "#F3F4F6", color: "#374151", dot: "#9CA3AF" };

      const editBtn = c.estado === "pendiente"
        ? `<button class="tl-action-btn" title="Reprogramar"
              onclick="abrirReprogramar(${c.id_cita},'${c.mascota_nombre}','${c.fecha}','${c.hora.substring(0,5)}','${c.doc_veterinario}','${c.motivo}')">
              <i class="fas fa-edit"></i>
           </button>`
        : "";

      return `<div class="tl-item">
        <div class="tl-time-col">
          <span class="tl-time">${hora}</span>
          <span class="tl-date">${fecha}</span>
        </div>
        <div class="tl-dot" style="background:${st.dot}"></div>
        <div class="tl-body">
          <div class="tl-avatar">${initial}</div>
          <div class="tl-info">
            <span class="tl-pet">${c.mascota_nombre}</span>
            <span class="tl-meta">${c.propietario_nombre} &middot; Dr. ${vetFirst}</span>
            <span class="tl-motivo">${c.motivo}</span>
          </div>
          <div class="tl-right">
            <span class="tl-badge" style="background:${st.bg};color:${st.color};">${STATE_LABELS[c.estado] || c.estado}</span>
            ${editBtn}
          </div>
        </div>
      </div>`;
    }).join("");

    const done = citas.filter((c) => c.estado === "completada").length;
    const pill = document.getElementById("pill-percent");
    if (pill)
      pill.textContent = citas.length
        ? `${Math.round((done / citas.length) * 100)}%`
        : "0%";
  } catch (e) {
    console.error("loadAgenda", e);
  }
}

// ── System Notifications ─────────────────────────────────────────────────────
// Título y mensaje llevan nombres escritos por usuarios (mascota, motivo):
// se escapan antes de meterlos en el HTML.
function escNotif(valor) {
  return String(valor ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
}

const NOTIF_ICONOS = {
  NUEVA_CITA: { icon: "fa-calendar-plus", clase: "tipo-nueva" },
  CITA_REPROGRAMADA: { icon: "fa-calendar-alt", clase: "tipo-reprogramada" },
  CITA_CANCELADA: { icon: "fa-calendar-times", clase: "tipo-cancelada" },
};

// "hace 5 min", "hace 3 h", "hace 2 días"; más de una semana, la fecha corta.
function tiempoRelativo(fechaSql) {
  if (!fechaSql) return "";
  const fecha = new Date(String(fechaSql).replace(" ", "T"));
  const segundos = (Date.now() - fecha.getTime()) / 1000;
  if (Number.isNaN(segundos)) return "";
  if (segundos < 60) return "hace un momento";
  const minutos = Math.floor(segundos / 60);
  if (minutos < 60) return `hace ${minutos} min`;
  const horas = Math.floor(minutos / 60);
  if (horas < 24) return `hace ${horas} h`;
  const dias = Math.floor(horas / 24);
  if (dias < 7) return `hace ${dias} día${dias === 1 ? "" : "s"}`;
  return fecha.toLocaleDateString("es-CO", { day: "2-digit", month: "short" });
}

async function loadSystemNotifications() {
  const listMain = document.getElementById("pendingVaccinesList");
  if (!listMain) return;
  try {
    const res = await fetch("index.php?action=get_notificaciones_ajax");
    const data = await res.json();
    
    if (!data.success) return;

    const noLeidas = data.no_leidas;
    const notificaciones = data.notificaciones;

    const badge = document.getElementById("notifBadge");
    const countEl = document.getElementById("notifCount");
    const bell = document.getElementById("notifBell");
    
    if (badge) badge.style.display = noLeidas > 0 ? "block" : "none";
    if (countEl) countEl.textContent = `${noLeidas} ${noLeidas === 1 ? "nueva" : "nuevas"}`;
    if (bell) bell.classList.toggle("ringing", noLeidas > 0);

    // HU-45: el boton de "marcar todas" solo tiene sentido si hay pendientes.
    const markAll = document.getElementById("notifMarkAll");
    if (markAll) markAll.hidden = noLeidas === 0;

    const mainHtml = notificaciones.length
      ? notificaciones
          .map(
            (item) => {
              const noLeida = !Number(item.leida);
              const { icon, clase } = NOTIF_ICONOS[item.tipo] || { icon: "fa-bell", clase: "" };
              const creada = item.fecha_creacion
                ? new Date(item.fecha_creacion.replace(" ", "T")).toLocaleString("es-CO")
                : "";

              return `<div class="notif-item${noLeida ? " is-unread" : ""}" onclick="marcarNotificacionLeida(${Number(item.id)}, '${escNotif(item.enlace || '#')}')">
                <div class="notif-item__icon ${clase}"><i class="fas ${icon}"></i></div>
                <div class="notif-item__text">
                    <span class="notif-item__title">${escNotif(item.titulo)}</span>
                    <div class="notif-item__msg">${escNotif(item.mensaje)}</div>
                    <span class="notif-item__time" title="${escNotif(creada)}">${escNotif(tiempoRelativo(item.fecha_creacion))}</span>
                </div>
                ${noLeida ? '<span class="notif-item__dot" aria-label="No leída"></span>' : ""}
              </div>`;
            }
          )
          .join("")
      : '<div class="notif-empty"><i class="far fa-bell-slash"></i>No tienes notificaciones pendientes</div>';

    if (listMain) listMain.innerHTML = mainHtml;
  } catch (e) {
    console.error("loadSystemNotifications", e);
  }
}

async function marcarNotificacionLeida(id, enlace) {
    try {
        const formData = new FormData();
        formData.append('id', id);
        
        await fetch("index.php?action=marcar_notificacion_leida_ajax", {
            method: 'POST',
            body: formData
        });
        
        if (enlace && enlace !== '#') {
            window.location.href = enlace;
        } else {
            loadSystemNotifications();
        }
    } catch (e) {
        console.error("Error al marcar como leída", e);
    }
}

/**
 * HU-45 — Marca como leídas todas las notificaciones del usuario.
 *
 * El endpoint existía en el enrutador desde el principio, pero ningún archivo
 * del front lo invocaba: el criterio "puedo marcar una o todas como leídas"
 * solo se cumplía a medias.
 */
async function marcarTodasNotificacionesLeidas() {
    const boton = document.getElementById("notifMarkAll");
    if (boton) boton.disabled = true;

    try {
        const res = await fetch("index.php?action=marcar_todas_notificaciones_leidas_ajax", {
            method: "POST",
            body: new FormData(),
        });
        const data = await res.json();

        if (!data.success) {
            throw new Error(data.message || "No se pudieron marcar las notificaciones.");
        }

        await loadSystemNotifications();
    } catch (e) {
        console.error("marcarTodasNotificacionesLeidas", e);
        if (window.Swal) {
            Swal.fire({
                icon: "error",
                title: "No se pudo completar",
                text: "No se pudieron marcar las notificaciones como leídas.",
            });
        }
    } finally {
        if (boton) boton.disabled = false;
    }
}

function initNotifMarkAll() {
    const boton = document.getElementById("notifMarkAll");
    if (!boton) return;
    boton.addEventListener("click", (e) => {
        // El dropdown se cierra al hacer clic fuera; este clic es dentro.
        e.stopPropagation();
        marcarTodasNotificacionesLeidas();
    });
}

// ── Notifications ─────────────────────────────────────────────────────────
function toggleNotifications() {
  document.getElementById("notifDropdown").classList.toggle("active");
}
function initNotifClose() {
  document.addEventListener("click", (e) => {
    const w = document.querySelector(".notifications-wrapper");
    const d = document.getElementById("notifDropdown");
    if (w && d && !w.contains(e.target)) d.classList.remove("active");
  });
}

// ── Search ────────────────────────────────────────────────────────────────
function initSearch() {
  const input = document.getElementById("globalSearch");
  const box = document.getElementById("searchResults");
  if (!input || !box) return;
  let timer;
  input.addEventListener("input", (e) => {
    clearTimeout(timer);
    const val = e.target.value.trim();
    if (val.length < 2) {
      box.style.display = "none";
      return;
    }
    timer = setTimeout(async () => {
      try {
        const res = await (
          await fetch(
            `index.php?action=buscar_global_ajax&query=${encodeURIComponent(val)}`,
          )
        ).json();
        box.innerHTML = res.length
          ? res
              .map(
                (
                  item,
                ) => `<a href="index.php?action=ver_mascota&id=${item.id_mascota}" class="search-result-item">
                        <img src="${item.url_foto ? "uploads/mascotas/" + item.url_foto : "https://ui-avatars.com/api/?name=" + encodeURIComponent(item.nombre)}" class="result-thumb">
                        <div><span class="pet-name">${item.nombre}</span><span class="owner-name">Dueño: ${item.propietario_nombre}</span></div>
                      </a>`,
              )
              .join("")
          : '<div style="padding:1rem;text-align:center;color:#94A3B8;font-size:.85rem;">Sin resultados</div>';
        box.style.display = "block";
      } catch (e) {
        console.error(e);
      }
    }, 300);
  });
  document.addEventListener("click", (e) => {
    if (!input.contains(e.target) && !box.contains(e.target))
      box.style.display = "none";
  });
}

// ── Vets for Reprogram ────────────────────────────────────────────────────
async function loadVetsReprogram() {
  const select = document.getElementById("reprog_veterinario");
  if (!select) return;
  try {
    const res = await (
      await fetch("index.php?action=listar_veterinarios_ajax")
    ).json();
    select.innerHTML = res
      .map(
        (v) => `<option value="${v.documento}">${v.nombre_completo}</option>`,
      )
      .join("");
  } catch (e) {
    console.error(e);
  }
}

// ── Reprogram Modal ───────────────────────────────────────────────────────
function abrirReprogramar(id, mascota, fecha, hora, vet, motivo) {
  document.getElementById("reprog_id_cita").value = id;
  document.getElementById("reprog_mascota").textContent = mascota;
  document.getElementById("reprog_fecha").value = fecha;
  document.getElementById("reprog_hora").value = hora;
  document.getElementById("reprog_veterinario").value = vet;
  document.getElementById("reprog_motivo").value = motivo;
  document.getElementById("modalReprogramarCita").style.display = "flex";
}

async function reprogramarCita(e) {
  e.preventDefault();
  const btn = e.target.querySelector('button[type="submit"]');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
  try {
    const res = await (
      await fetch("index.php?action=reprogramar_cita_ajax", {
        method: "POST",
        body: new FormData(e.target),
      })
    ).json();
    if (res.success) {
      zookiToast(res.message, "success");
      document.getElementById("modalReprogramarCita").style.display = "none";
      loadAgenda();
    } else {
      zookiAviso(res.message);
    }
  } catch (err) {
    zookiAviso("No se pudo reprogramar la cita.");
  } finally {
    btn.disabled = false;
    btn.innerHTML = "Guardar Cambios";
  }
}

// ── Profile & Password Modal ──────────────────────────────────────────────
