class TimePicker {
    constructor(inputElement, options = {}) {
        this.input = inputElement;
        this.options = {
            minuteInterval: options.minuteInterval || 15,
            onTimeChange: options.onTimeChange || null,
            period: options.period || null,  // 'morning' | 'afternoon' | null
            ...options
        };

        this.value = this.input.value || '07:00';
        this.isOpen = false;
        this.activeField = 'hour';
        this._id = 'tp_' + Math.random().toString(36).substr(2, 9);

        this.init();
    }

    init() {
        this.input.style.display = 'none';

        this.display = document.createElement('div');
        this.display.className = 'time-picker-display';
        this.display.setAttribute('tabindex', '0');
        this.display.textContent = this.formatDisplay(this.value);
        this.display.addEventListener('click', () => this.open());
        this.display.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this.open(); }
        });

        this.input.parentNode.insertBefore(this.display, this.input.nextSibling);
        this.createPicker();
    }

    formatDisplay(time) {
        if (!time) return '--:-- AM';
        const [hours, minutes] = time.split(':');
        const hour = parseInt(hours);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const hour12 = hour === 0 ? 12 : (hour > 12 ? hour - 12 : hour);
        return `${hour12.toString().padStart(2, '0')}:${minutes} ${ampm}`;
    }

    createPicker() {
        this.picker = document.createElement('div');
        this.picker.className = 'tp-overlay';
        this.picker.id = this._id;
        const period = this.options.period;
        const ampmHTML = period === 'morning'
            ? `<div class="tp-ampm-wrap tp-ampm-fixed"><button class="tp-ampm-btn tp-ampm-active tp-morning-badge" type="button" disabled>AM</button></div>`
            : period === 'afternoon'
            ? `<div class="tp-ampm-wrap tp-ampm-fixed"><button class="tp-ampm-btn tp-pm tp-ampm-active" type="button" disabled>PM</button></div>`
            : `<div class="tp-ampm-wrap">
                    <button class="tp-ampm-btn tp-am" type="button">AM</button>
                    <button class="tp-ampm-btn tp-pm" type="button">PM</button>
               </div>`;

        this.picker.innerHTML = `
            <div class="tp-card" role="dialog" aria-modal="true" aria-label="Seleccionar hora">
                <div class="tp-title">INGRESAR HORA</div>
                <div class="tp-body">
                    <div class="tp-fields-row">
                        <div class="tp-field-wrap">
                            <input class="tp-field tp-hour" type="text" inputmode="numeric" maxlength="2" placeholder="--" aria-label="Hora">
                            <div class="tp-field-label">Hora</div>
                        </div>
                        <div class="tp-colon">:</div>
                        <div class="tp-field-wrap">
                            <input class="tp-field tp-minute" type="text" inputmode="numeric" maxlength="2" placeholder="--" aria-label="Minuto">
                            <div class="tp-field-label">Minuto</div>
                        </div>
                        ${ampmHTML}
                    </div>
                </div>
                <div class="tp-footer">
                    <span class="tp-clock-icon" title="Ingresa la hora manualmente">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </span>
                    <div class="tp-actions">
                        <button class="tp-btn-cancel" type="button">Cancelar</button>
                        <button class="tp-btn-ok" type="button">OK</button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(this.picker);
        this.parseValue();
        this.bindEvents();
    }

    bindEvents() {
        const hourInput  = this.picker.querySelector('.tp-hour');
        const minInput   = this.picker.querySelector('.tp-minute');
        const amBtn      = this.picker.querySelector('.tp-am');
        const pmBtn      = this.picker.querySelector('.tp-pm');
        const cancelBtn  = this.picker.querySelector('.tp-btn-cancel');
        const okBtn      = this.picker.querySelector('.tp-btn-ok');

        // Focus styling
        hourInput.addEventListener('focus', () => this.setActive('hour'));
        minInput.addEventListener('focus',  () => this.setActive('minute'));

        // Set placeholder hint based on period
        if (this.options.period === 'morning') {
            hourInput.placeholder = '6-12';
        } else if (this.options.period === 'afternoon') {
            hourInput.placeholder = '1-12';
        }

        // Typing: hour
        hourInput.addEventListener('input', () => {
            let val = hourInput.value.replace(/\D/g, '');
            if (val.length > 2) val = val.slice(-2);
            hourInput.value = val;
            const num = parseInt(val);
            if (!isNaN(num) && val.length > 0) {
                if (this.options.period === 'morning') {
                    // Morning: 6-12 (12 = noon, treated as end-of-morning)
                    if (val.length === 2) {
                        if (num < 6)  { hourInput.value = '06'; }
                        if (num > 12) { hourInput.value = '12'; }
                    }
                    // Auto-update badge and lock minutes when hour=12
                    this._updateMorningBadge(hourInput.value, minInput);
                } else if (this.options.period === 'afternoon') {
                    // Afternoon PM: 12 PM (noon) to 9 PM → hours 12 or 1-9
                    if (val.length === 2) {
                        if (num === 10 || num === 11) { hourInput.value = '09'; }
                        if (num > 12)                 { hourInput.value = '12'; }
                        if (num < 1)                  { hourInput.value = '01'; }
                    }
                    // Lock minutes to 00 when hour=12 (noon = start of afternoon)
                    this._lockMinutesIfNoon(hourInput.value, minInput);
                } else {
                    if (num > 12) { hourInput.value = '12'; }
                    if (num < 1 && val.length === 2) { hourInput.value = '01'; }
                }
            }
            if (hourInput.value.length === 2) minInput.focus();
        });

        // Typing: minute
        minInput.addEventListener('input', () => {
            let val = minInput.value.replace(/\D/g, '');
            const interval = this.options.minuteInterval;
            if (val.length > 2) val = val.slice(-2);
            minInput.value = val;
            const num = parseInt(val);
            if (!isNaN(num) && val.length === 2) {
                // Snap to nearest interval
                const snapped = Math.round(num / interval) * interval;
                const clamped = Math.min(snapped, 60 - interval);
                minInput.value = clamped.toString().padStart(2, '0');
            }
        });

        // AM / PM buttons (only when not locked by period)
        if (amBtn && !this.options.period) {
            amBtn.addEventListener('click', () => {
                this.ampm = 'AM';
                this.refreshAmPm();
            });
        }
        if (pmBtn && !this.options.period) {
            pmBtn.addEventListener('click', () => {
                this.ampm = 'PM';
                this.refreshAmPm();
            });
        }

        // Footer actions
        cancelBtn.addEventListener('click', () => this.close());
        okBtn.addEventListener('click', () => this.confirm());

        // Close on backdrop click
        this.picker.addEventListener('click', (e) => {
            if (e.target === this.picker) this.close();
        });

        // Keyboard: Escape to close, Enter to confirm
        this.picker.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') this.close();
            if (e.key === 'Enter') this.confirm();
        });
    }

    setActive(field) {
        this.activeField = field;
        const hourInput = this.picker.querySelector('.tp-hour');
        const minInput  = this.picker.querySelector('.tp-minute');
        hourInput.classList.toggle('tp-field-active', field === 'hour');
        minInput.classList.toggle('tp-field-active', field === 'minute');
    }

    parseValue() {
        // If period is locked, force ampm
        const forcedAmpm = this.options.period === 'morning' ? 'AM'
                         : this.options.period === 'afternoon' ? 'PM'
                         : null;

        if (this.value) {
            const [hours, minutes] = this.value.split(':');
            const hour = parseInt(hours);
            this.hour   = hour === 0 ? 12 : (hour > 12 ? hour - 12 : hour);
            this.minute = parseInt(minutes);
            this.ampm   = forcedAmpm || (hour >= 12 ? 'PM' : 'AM');
            // Clamp to valid range for locked periods
            if (this.options.period === 'morning' && (this.hour < 6 || this.hour > 12)) {
                this.hour = 6;
            }
            // hour=12 in morning or afternoon means noon exactly — force minute=0
            if (this.hour === 12 && (this.options.period === 'morning' || this.options.period === 'afternoon')) {
                this.minute = 0;
            }
            if (this.options.period === 'morning' && this.hour === 12) {
                this.ampm = 'PM';
            }
            if (this.options.period === 'afternoon' && (this.hour === 10 || this.hour === 11)) {
                this.hour = 9;
            }
        } else {
            this.hour   = this.options.period === 'afternoon' ? 2 : 7;
            this.minute = 0;
            this.ampm   = forcedAmpm || 'AM';
        }
        this.syncInputs();
    }

    syncInputs() {
        const hourInput = this.picker.querySelector('.tp-hour');
        const minInput  = this.picker.querySelector('.tp-minute');
        if (hourInput) hourInput.value = this.hour.toString().padStart(2, '0');
        if (minInput)  minInput.value  = this.minute.toString().padStart(2, '0');
        this.refreshAmPm();
        if (this.options.period === 'morning') {
            this._updateMorningBadge(hourInput ? hourInput.value : '', minInput);
        } else if (this.options.period === 'afternoon') {
            this._lockMinutesIfNoon(hourInput ? hourInput.value : '', minInput);
        }
    }

    _lockMinutesIfNoon(hourVal, minInput) {
        const num = parseInt(hourVal);
        if (isNaN(num) || !minInput) return;
        if (num === 12) {
            minInput.value = '00';
            minInput.disabled = true;
            minInput.style.opacity = '0.5';
        } else {
            minInput.disabled = false;
            minInput.style.opacity = '1';
        }
    }


    refreshAmPm() {
        const amBtn = this.picker.querySelector('.tp-am');
        const pmBtn = this.picker.querySelector('.tp-pm');
        if (!amBtn || !pmBtn) return;
        amBtn.classList.toggle('tp-ampm-active', this.ampm === 'AM');
        pmBtn.classList.toggle('tp-ampm-active', this.ampm === 'PM');
    }

    to24Hour() {
        const hourInput = this.picker.querySelector('.tp-hour');
        const minInput  = this.picker.querySelector('.tp-minute');
        let h = parseInt(hourInput.value) || this.hour;
        let m = parseInt(minInput.value);
        if (isNaN(m)) m = this.minute;
        if (this.options.period === 'morning') {
            // In morning context: 6-11 = AM, 12 = noon (12:00), never midnight
            if (h === 12) h = 12; // noon
            // else h stays as-is (6-11 are already correct in 24h)
        } else if (this.ampm === 'PM' && h !== 12) {
            h += 12;
        } else if (this.ampm === 'AM' && h === 12) {
            h = 0;
        }
        return `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}`;
    }

    open() {
        this.parseValue();
        this.picker.classList.add('tp-open');
        this.isOpen = true;
        // Auto-focus hour field
        setTimeout(() => {
            const hourInput = this.picker.querySelector('.tp-hour');
            if (hourInput) { hourInput.focus(); hourInput.select(); }
        }, 50);
    }

    close() {
        this.picker.classList.remove('tp-open');
        this.isOpen = false;
    }

    confirm() {
        this.value = this.to24Hour();
        this.input.value = this.value;
        this.display.textContent = this.formatDisplay(this.value);
        this.close();
        if (this.options.onTimeChange) this.options.onTimeChange(this.value);
        this.input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    destroy() {
        if (this.picker) this.picker.remove();
        if (this.display) this.display.remove();
        this.input.style.display = '';
    }
}

// ─────────────────────────────────────────────────────────────
//  BlockPicker – single modal for start + end time of a block
// ─────────────────────────────────────────────────────────────
class BlockPicker {
    constructor(startInput, endInput, options = {}) {
        this.startInput = startInput;
        this.endInput   = endInput;
        this.options    = {
            minuteInterval: options.minuteInterval || 15,
            onTimeChange:   options.onTimeChange   || null,
            period:         options.period         || null,
            compactDisplay: options.compactDisplay || false,
        };
        this._id = 'bp_' + Math.random().toString(36).substr(2, 9);
        this.init();
    }

    // ── helpers ──────────────────────────────────────────────
    _to12(time24) {
        if (!time24) return { h: 8, m: 0, ampm: 'AM' };
        const [hs, ms] = time24.split(':');
        const h24 = parseInt(hs), m = parseInt(ms) || 0;
        const ampm = h24 >= 12 ? 'PM' : 'AM';
        const h12  = h24 === 0 ? 12 : (h24 > 12 ? h24 - 12 : h24);
        return { h: h12, m, ampm };
    }

    _to24(h, m, ampm) {
        let hh = parseInt(h);
        if (ampm === 'PM' && hh !== 12) hh += 12;
        if (ampm === 'AM' && hh === 12) hh = 0;
        return `${hh.toString().padStart(2,'0')}:${m.toString().padStart(2,'0')}`;
    }

    _fmt(time24) {
        if (!time24) return '--:--';
        const { h, m, ampm } = this._to12(time24);
        return `${h.toString().padStart(2,'0')}:${m.toString().padStart(2,'0')} ${ampm}`;
    }

    _periodAmpm() { return this.options.period === 'afternoon' ? 'PM' : 'AM'; }
    _periodTitle() {
        return this.options.period === 'morning' ? 'MAÑANA'
             : this.options.period === 'afternoon' ? 'TARDE' : 'HORARIO';
    }
    _defaultStart() { return this.options.period === 'afternoon' ? '14:00' : '08:00'; }
    _defaultEnd()   { return this.options.period === 'afternoon' ? '18:00' : '12:00'; }

    _clampHour(num) {
        if (this.options.period === 'morning') {
            if (num < 6)  return 6;
            if (num > 12) return 12;
        } else if (this.options.period === 'afternoon') {
            if (num < 1)                  return 1;
            if (num === 10 || num === 11) return 9;
            if (num > 12)                 return 12;
        } else {
            if (num < 1)  return 1;
            if (num > 12) return 12;
        }
        return num;
    }

    _snapMinute(num) {
        const i = this.options.minuteInterval;
        return Math.min(Math.round(num / i) * i, 60 - i);
    }

    // ── init ─────────────────────────────────────────────────
    init() {
        this.startInput.hidden = true;
        this.endInput.hidden = true;

        // Hide the dash separator between the two inputs
        let sep = this.startInput.nextSibling;
        while (sep && sep.nodeType === 3) sep = sep.nextSibling;
        this.separator = sep?.tagName === 'SPAN' ? sep : null;
        if (this.separator) this.separator.hidden = true;

        // Create single display trigger button
        this.display = document.createElement('button');
        this.display.className = 'block-picker-display';
        this.display.type = 'button';
        this._refreshDisplay();
        this.display.addEventListener('click', () => this.open());
        this.endInput.parentNode.insertBefore(this.display, this.endInput.nextSibling);

        this._createPicker();
    }

    _refreshDisplay() {
        if (this.options.compactDisplay) {
            this.display.textContent = this.options.compactDisplay === 'rango'
                ? this.startInput.value + '–' + this.endInput.value
                : 'de ' + this.startInput.value + ' a ' + this.endInput.value;
            return;
        }
        const s = this._fmt(this.startInput.value || this._defaultStart());
        const e = this._fmt(this.endInput.value   || this._defaultEnd());
        this.display.innerHTML =
            `<span class="bpd-from">de ${s}</span>` +
            `<span class="bpd-arrow">a</span>` +
            `<span class="bpd-to">${e}</span>`;
    }

    _ampmBadgeHTML(cls) {
        const label = this._periodAmpm();
        const colorCls = label === 'PM' ? 'tp-pm' : 'tp-am';
        return `<div class="tp-ampm-wrap tp-ampm-fixed">
                    <button class="tp-ampm-btn ${colorCls} tp-ampm-active ${cls}" type="button" disabled>${label}</button>
                </div>`;
    }

    _createPicker() {
        this.picker = document.createElement('div');
        this.picker.className = 'tp-overlay';
        this.picker.id = this._id;
        this.picker.innerHTML = `
            <div class="tp-card bp-card" role="dialog" aria-modal="true" aria-label="Seleccionar horario">
                <div class="tp-title">${this._periodTitle()}</div>
                <div class="bp-body">
                    <div class="bp-row">
                        <span class="bp-label">Desde</span>
                        <div class="bp-fields">
                            <div class="tp-field-wrap">
                                <input class="tp-field bp-hour-start" type="text" inputmode="numeric" maxlength="2" placeholder="--" aria-label="Hora inicio">
                                <div class="tp-field-label">Hora</div>
                            </div>
                            <div class="tp-colon">:</div>
                            <div class="tp-field-wrap">
                                <input class="tp-field bp-min-start" type="text" inputmode="numeric" maxlength="2" placeholder="--" aria-label="Minuto inicio">
                                <div class="tp-field-label">Min</div>
                            </div>
                            ${this._ampmBadgeHTML('bp-ampm-start')}
                        </div>
                    </div>
                    <div class="bp-divider"></div>
                    <div class="bp-row">
                        <span class="bp-label">Hasta</span>
                        <div class="bp-fields">
                            <div class="tp-field-wrap">
                                <input class="tp-field bp-hour-end" type="text" inputmode="numeric" maxlength="2" placeholder="--" aria-label="Hora fin">
                                <div class="tp-field-label">Hora</div>
                            </div>
                            <div class="tp-colon">:</div>
                            <div class="tp-field-wrap">
                                <input class="tp-field bp-min-end" type="text" inputmode="numeric" maxlength="2" placeholder="--" aria-label="Minuto fin">
                                <div class="tp-field-label">Min</div>
                            </div>
                            ${this._ampmBadgeHTML('bp-ampm-end')}
                        </div>
                    </div>
                </div>
                <div class="bp-error" hidden></div>
                <div class="tp-footer">
                    <span class="tp-clock-icon" title="Ingresa la hora manualmente">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </span>
                    <div class="tp-actions">
                        <button class="tp-btn-cancel" type="button">Cancelar</button>
                        <button class="tp-btn-ok" type="button">OK</button>
                    </div>
                </div>
            </div>`;
        document.body.appendChild(this.picker);
        this._bindEvents();
    }

    // ── sync picker fields from hidden inputs ─────────────────
    _syncFields() {
        const sv = this._to12(this.startInput.value || this._defaultStart());
        const ev = this._to12(this.endInput.value   || this._defaultEnd());

        const hS = this.picker.querySelector('.bp-hour-start');
        const mS = this.picker.querySelector('.bp-min-start');
        const hE = this.picker.querySelector('.bp-hour-end');
        const mE = this.picker.querySelector('.bp-min-end');
        const bS = this.picker.querySelector('.bp-ampm-start');
        const bE = this.picker.querySelector('.bp-ampm-end');

        if (hS) hS.value = sv.h.toString().padStart(2,'0');
        if (mS) mS.value = sv.m.toString().padStart(2,'0');
        if (hE) hE.value = ev.h.toString().padStart(2,'0');
        if (mE) mE.value = ev.m.toString().padStart(2,'0');

        // Update end badge for morning h=12 (noon → PM)
        if (this.options.period === 'morning' && bE) {
            this._applyMorningBadge(hE ? hE.value : '', bE);
        }

        this._applyNoonLock(hS ? hS.value : '', mS, bS);
        this._applyNoonLock(hE ? hE.value : '', mE, bE);
    }

    _applyNoonLock(hourVal, minEl, badgeEl) {
        const num = parseInt(hourVal);
        if (isNaN(num) || !minEl) return;
        if (num === 12 && this.options.period === 'morning') {
            minEl.value    = '00';
            minEl.disabled = true;

            if (badgeEl && this.options.period === 'morning') {
                badgeEl.textContent = 'PM';
                badgeEl.classList.replace('tp-am', 'tp-pm');
            }
        } else {
            minEl.disabled = false;

            if (badgeEl && this.options.period === 'morning') {
                badgeEl.textContent = 'AM';
                badgeEl.classList.replace('tp-pm', 'tp-am');
            }
        }
    }

    _applyMorningBadge(hourVal, badgeEl) {
        if (!badgeEl || this.options.period !== 'morning') return;
        const num = parseInt(hourVal);
        if (num === 12) {
            badgeEl.textContent = 'PM';
            badgeEl.classList.replace('tp-am', 'tp-pm');
        } else {
            badgeEl.textContent = 'AM';
            badgeEl.classList.replace('tp-pm', 'tp-am');
        }
    }

    // ── events ────────────────────────────────────────────────
    _bindHourRow(hEl, mEl, badgeEl) {
        hEl.placeholder = this.options.period === 'morning' ? '6-12' : '1-12';

        hEl.addEventListener('focus', () => {
            hEl.classList.add('tp-field-active');
            mEl.classList.remove('tp-field-active');
            setTimeout(() => hEl.select(), 0);
        });
        mEl.addEventListener('focus', () => {
            mEl.classList.add('tp-field-active');
            hEl.classList.remove('tp-field-active');
            setTimeout(() => mEl.select(), 0);
        });

        hEl.addEventListener('input', () => {
            let val = hEl.value.replace(/\D/g, '').slice(0, 2);
            hEl.value = val;
            const num = parseInt(val);
            if (!isNaN(num) && val.length === 2) {
                hEl.value = this._clampHour(num).toString().padStart(2,'0');
            }
            this._applyNoonLock(hEl.value, mEl, badgeEl);
            if (hEl.value.length === 2) mEl.focus();
        });

        mEl.addEventListener('input', () => {
            let val = mEl.value.replace(/\D/g, '').slice(0, 2);
            mEl.value = val;
            const num = parseInt(val);
            if (!isNaN(num) && val.length === 2) {
                mEl.value = this._snapMinute(num).toString().padStart(2,'0');
            }
        });
    }

    _bindEvents() {
        const hS = this.picker.querySelector('.bp-hour-start');
        const mS = this.picker.querySelector('.bp-min-start');
        const hE = this.picker.querySelector('.bp-hour-end');
        const mE = this.picker.querySelector('.bp-min-end');
        const bS = this.picker.querySelector('.bp-ampm-start');
        const bE = this.picker.querySelector('.bp-ampm-end');

        this._bindHourRow(hS, mS, bS);
        this._bindHourRow(hE, mE, bE);

        this.picker.querySelector('.tp-btn-cancel').addEventListener('click', () => this.close());
        this.picker.querySelector('.tp-btn-ok').addEventListener('click', () => this.confirm());
        this.picker.addEventListener('click', e => { if (e.target === this.picker) this.close(); });
        this.picker.addEventListener('keydown', e => {
            if (e.key === 'Escape' || e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation();
                if (e.key === 'Escape') this.close();
                else this.confirm();
            }
        });
    }

    // ── open / close / confirm ────────────────────────────────
    open() {
        if (this.startInput.disabled || this.endInput.disabled || this.display.disabled) return;
        (this.startInput.closest('dialog[open]') || document.body).append(this.picker);
        this._syncFields();
        this.picker.classList.add('tp-open');
        setTimeout(() => {
            const hS = this.picker.querySelector('.bp-hour-start');
            if (hS) { hS.focus(); hS.select(); }
        }, 50);
    }

    close() { this.picker.classList.remove('tp-open'); }

    confirm() {
        const hS = this.picker.querySelector('.bp-hour-start');
        const mS = this.picker.querySelector('.bp-min-start');
        const hE = this.picker.querySelector('.bp-hour-end');
        const mE = this.picker.querySelector('.bp-min-end');
        const bS = this.picker.querySelector('.bp-ampm-start');
        const bE = this.picker.querySelector('.bp-ampm-end');
        const errEl = this.picker.querySelector('.bp-error');

        const ampmS = bS ? bS.textContent.trim() : this._periodAmpm();
        const ampmE = bE ? bE.textContent.trim() : this._periodAmpm();

        const start24 = this._to24(hS.value, mS.value, ampmS);
        const end24   = this._to24(hE.value, mE.value, ampmE);

        // El selector compartido no confirma entradas vacías ni turnos fuera de su periodo.
        const horaValida = valor => /^([01]\d|2[0-3]):[0-5]\d$/.test(valor);
        const fueraPeriodo = this.options.period === 'morning' ? start24 < '06:00' || end24 > '12:00' : this.options.period === 'afternoon' ? start24 < '12:00' || end24 > '21:00' : false;
        if (!horaValida(start24) || !horaValida(end24) || end24 <= start24 || fueraPeriodo) {
            if (errEl) {
                errEl.textContent = 'La hora fin debe ser mayor a la hora inicio';
                errEl.hidden = false;
            }
            return; // Don't close
        }
        if (errEl) errEl.hidden = true;

        this.startInput.value = start24;
        this.endInput.value   = end24;

        this._refreshDisplay();
        this.close();
        if (this.options.onTimeChange) this.options.onTimeChange();
        this.startInput.dispatchEvent(new Event('change', { bubbles: true }));
        this.endInput.dispatchEvent(new Event('change', { bubbles: true }));
    }

    destroy() {
        if (this.picker)  this.picker.remove();
        if (this.display) this.display.remove();
        this.startInput.hidden = false;
        this.endInput.hidden = false;
        if (this.separator) this.separator.hidden = false;
    }
}
