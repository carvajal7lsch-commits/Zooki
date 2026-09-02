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
  completada: "#10B981",
  cancelada: "#EF4444",
};
const STATE_LABELS = {
  pendiente: "Pendiente",
  confirmada: "Confirmada",
  completada: "Completada",
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
const _loader = document.getElementById("global-loader");
const _origFetch = window.fetch;
window.fetch = async (...args) => {
  if (_loader) _loader.style.display = "flex";
  try {
    return await _origFetch(...args);
  } finally {
    if (_loader) _loader.style.display = "none";
  }
};

window.alert = (msg, type = "info") => {
  Swal.fire({
    title: "Zooki",
    text: msg,
    icon: type,
    confirmButtonColor: "#5560FF",
    showClass: { popup: "animate__animated animate__fadeInUp animate__faster" },
  });
};

// ── Init ─────────────────────────────────────────────────────────────────
document.addEventListener("DOMContentLoaded", () => {
  loadSystemNotifications();
  loadVetsReprogram();
  initSearch();
  initNotifClose();

  // Fix z-index overlapping with sidebar
  document.querySelectorAll('.modal, .users-modal').forEach(modal => {
    document.body.appendChild(modal);
  });

  if (typeof ZOOKI_ROLE === "undefined") return;

  loadRoleStats();
  loadChartsData();

  if (ZOOKI_ROLE === 1 || ZOOKI_ROLE === 2) {
    loadAgenda();
  }
  if (ZOOKI_ROLE === 3) {
    loadTimeline();
    startCountdown();
  }
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
  const estados = ["pendiente", "confirmada", "completada", "cancelada"];
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
  const estados = ["pendiente", "confirmada", "completada", "cancelada"];
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
    if (c.estado === "cancelada" || c.estado === "completada") return false;
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
      completada: { bg: "#DCFCE7", color: "#166534", dot: "#10B981" },
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
    if (countEl) countEl.textContent = `${noLeidas} nuevas`;
    if (bell) bell.classList.toggle("ringing", noLeidas > 0);

    const mainHtml = notificaciones.length
      ? notificaciones
          .map(
            (item) => {
              const bg = item.leida ? "#f8fafc" : "#eff6ff";
              const border = item.leida ? "none" : "1px solid #bfdbfe";
              let icon = "fa-bell";
              let iconColor = "#64748b";
              
              if (item.tipo === 'NUEVA_CITA') { icon = 'fa-calendar-plus'; iconColor = '#0052FF'; }
              if (item.tipo === 'CITA_CANCELADA') { icon = 'fa-calendar-times'; iconColor = '#ef4444'; }
              
              return `<div class="vaccine-alert-item" style="margin-bottom:.5rem;background:${bg};border:${border};padding:.75rem;cursor:pointer;border-radius:8px;" onclick="marcarNotificacionLeida(${item.id}, '${item.enlace || '#'}')">
                <div class="v-icon" style="width:30px;height:30px;font-size:.8rem;color:${iconColor};"><i class="fas ${icon}"></i></div>
                <div class="v-details">
                    <strong style="font-size:.85rem;color:#1e293b;">${item.titulo}</strong>
                    <div style="font-size:.75rem;color:#475569;margin-top:2px;">${item.mensaje}</div>
                    <div class="v-meta" style="font-size:.7rem;margin-top:4px;"><span>${item.fecha_creacion}</span></div>
                </div>
                ${!item.leida ? '<div style="width:8px;height:8px;background:#0052FF;border-radius:50%;margin-left:auto;"></div>' : ''}
              </div>`;
            }
          )
          .join("")
      : '<div style="text-align:center;padding:2rem;color:#94A3B8;font-size:.9rem;">Sin notificaciones ✨</div>';

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
      alert(res.message, "success");
      document.getElementById("modalReprogramarCita").style.display = "none";
      loadAgenda();
    } else {
      alert(res.message, "error");
    }
  } catch (err) {
    alert("Error al reprogramar la cita.", "error");
  } finally {
    btn.disabled = false;
    btn.innerHTML = "Guardar Cambios";
  }
}

// ── Profile & Password Modal ──────────────────────────────────────────────
function toggleProfileMenu() {
    const dropdown = document.getElementById('profileDropdown');
    if(dropdown) {
        dropdown.style.display = dropdown.style.display === 'none' || dropdown.style.display === '' ? 'block' : 'none';
    }
}

document.addEventListener('click', function(event) {
    const headerUserWrapper = document.querySelector('.header-user-wrapper');
    const dropdown = document.getElementById('profileDropdown');
    if (headerUserWrapper && !headerUserWrapper.contains(event.target)) {
        if (dropdown) dropdown.style.display = 'none';
    }
});

function abrirPersonalizarPerfil(e) {
    e.preventDefault();
    document.getElementById('profileDropdown').style.display = 'none';
    
    Swal.fire({
        html: `
            <div class="pwd-modal-container">
                <div class="pwd-modal-header">
                    <h3><i class="fas fa-lock" style="margin-right: 8px;"></i> Cambiar Contraseña</h3>
                    <p>Protege tu cuenta con una nueva contraseña segura.</p>
                </div>
                <div class="pwd-modal-body">
                    <div class="pwd-input-group">
                        <label>Nueva Contraseña</label>
                        <input type="password" id="swal-new-pwd" class="pwd-input" placeholder="Mínimo 8 caracteres, con mayúscula, minúscula y número">
                        <small id="swal-pwd-feedback" style="display:block;margin-top:6px;font-size:0.8rem;min-height:1.1em;" role="status" aria-live="polite"></small>
                    </div>
                    <div class="pwd-input-group">
                        <label>Confirmar Contraseña</label>
                        <input type="password" id="swal-conf-pwd" class="pwd-input" placeholder="Repite la contraseña">
                    </div>
                    <div class="pwd-actions">
                        <button type="button" onclick="Swal.close()" class="btn-pwd-cancel">Cancelar</button>
                        <button type="button" onclick="document.getElementById('hidden-confirm-btn').click()" class="btn-pwd-confirm">Actualizar Contraseña</button>
                    </div>
                </div>
            </div>
            <button id="hidden-confirm-btn" style="display:none;"></button>
        `,
        showConfirmButton: false,
        showCancelButton: false,
        showCloseButton: true,
        padding: '0',
        customClass: {
            popup: 'premium-modal',
            htmlContainer: 'premium-html-container',
            closeButton: 'premium-close-btn'
        },
        width: '500px',
        didOpen: () => {
            // Aviso mientras se escribe: antes el usuario solo se enteraba de
            // que su contrasena no servia al pulsar "Actualizar".
            const campoNuevo = document.getElementById('swal-new-pwd');
            const aviso = document.getElementById('swal-pwd-feedback');
            if (campoNuevo && aviso) {
                campoNuevo.addEventListener('input', function () {
                    if (this.value === '') {
                        aviso.textContent = '';
                        return;
                    }
                    const motivo = window.motivoPasswordInvalida(this.value);
                    aviso.textContent = motivo === null ? 'Contraseña válida' : motivo;
                    aviso.style.color = motivo === null ? '#047857' : '#DC2626';
                });
            }

            document.getElementById('hidden-confirm-btn').addEventListener('click', () => {
                const pwd1 = document.getElementById('swal-new-pwd').value;
                const pwd2 = document.getElementById('swal-conf-pwd').value;
                if (!pwd1 || !pwd2) {
                    Swal.showValidationMessage('Ambos campos son requeridos');
                    return;
                }
                if (pwd1 !== pwd2) {
                    Swal.showValidationMessage('Las contraseñas no coinciden');
                    return;
                }
                const motivoPwd = window.motivoPasswordInvalida(pwd1);
                if (motivoPwd !== null) {
                    Swal.showValidationMessage(motivoPwd);
                    return;
                }
                Swal.resetValidationMessage();
                
                Swal.fire({
                    title: 'Actualizando...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                const formData = new FormData();
                formData.append('nueva_password', pwd1);

                fetch('index.php?action=actualizar_password_ajax', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: formData
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        Swal.fire('¡Actualizada!', 'Tu contraseña ha sido cambiada correctamente.', 'success');
                    } else {
                        Swal.fire('Error', res.message || 'Error al actualizar', 'error');
                    }
                })
                .catch(err => {
                    Swal.fire('Error', 'Hubo un error de conexión.', 'error');
                });
            });
        }
    });
}
