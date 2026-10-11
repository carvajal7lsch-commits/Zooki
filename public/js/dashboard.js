/**
 * Zooki — comportamiento común del layout del personal (administrador y
 * veterinario): loader global, notificaciones internas y su menú.
 *
 * C7: las estadísticas, gráficas, línea de tiempo, agenda y el modal de
 * reprogramar pertenecían a pantallas que ya no existen; se
 * retiraron. Los paneles llegan pintados desde el servidor (PanelController).
 */

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
    document.getElementById("notifBell")?.addEventListener("click", toggleNotifications);
  loadSystemNotifications();
  initNotifClose();
  initNotifMarkAll();

  // Fix z-index overlapping with sidebar
  document.querySelectorAll('.modal, .users-modal').forEach(modal => {
    document.body.appendChild(modal);
  });
});

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
    
    if (badge) badge.hidden = noLeidas <= 0;
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
