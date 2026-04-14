const Calendar = (() => {
  let container = null;
  let currentDate = new Date();
  let currentView = 'month';
  let appointments = [];
  let callbacks = {};

  const HOURS_START = 7;
  const HOURS_END = 22;

  function init(containerId, opts) {
    container = document.getElementById(containerId);
    if (!container) return;

    callbacks = opts || {};

    document.querySelectorAll('.cal-view-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.cal-view-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentView = btn.dataset.view;
        render();
      });
    });

    const prevBtn = document.getElementById('cal-prev');
    const nextBtn = document.getElementById('cal-next');
    const todayBtn = document.getElementById('cal-today');

    if (prevBtn) prevBtn.addEventListener('click', () => navigate(-1));
    if (nextBtn) nextBtn.addEventListener('click', () => navigate(1));
    if (todayBtn) todayBtn.addEventListener('click', () => {
      currentDate = new Date();
      render();
    });

    loadAppointments();
  }

  async function loadAppointments() {
    const range = getDateRange();
    const res = await Api.get(
      '/appointments?date_from=' + range.from + '&date_to=' + range.to
    );

    if (res.success && Array.isArray(res.data)) {
      appointments = res.data;
    } else {
      appointments = [];
    }

    render();
  }

  function navigate(direction) {
    if (currentView === 'month') {
      currentDate.setMonth(currentDate.getMonth() + direction);
    } else if (currentView === 'week') {
      currentDate.setDate(currentDate.getDate() + (direction * 7));
    } else {
      currentDate.setDate(currentDate.getDate() + direction);
    }
    loadAppointments();
  }

  function getDateRange() {
    const y = currentDate.getFullYear();
    const m = currentDate.getMonth();

    if (currentView === 'month') {
      const first = new Date(y, m, 1);
      const last = new Date(y, m + 1, 0);
      const startDay = first.getDay() === 0 ? 6 : first.getDay() - 1;
      first.setDate(first.getDate() - startDay);
      last.setDate(last.getDate() + (6 - (last.getDay() === 0 ? 6 : last.getDay() - 1)));
      return { from: fmtISO(first), to: fmtISO(last) };
    }

    if (currentView === 'week') {
      const dayOfWeek = currentDate.getDay() === 0 ? 6 : currentDate.getDay() - 1;
      const monday = new Date(currentDate);
      monday.setDate(currentDate.getDate() - dayOfWeek);
      const sunday = new Date(monday);
      sunday.setDate(monday.getDate() + 6);
      return { from: fmtISO(monday), to: fmtISO(sunday) };
    }

    return { from: fmtISO(currentDate), to: fmtISO(currentDate) };
  }

  function render() {
    if (!container) return;
    updateLabel();

    switch (currentView) {
      case 'month': renderMonth(); break;
      case 'week': renderWeek(); break;
      case 'day': renderDay(); break;
    }
  }

  function updateLabel() {
    const label = document.getElementById('cal-label');
    if (!label) return;

    const lang = (typeof i18n !== 'undefined') ? i18n.getLocale() : 'es';
    const locale = lang === 'es' ? 'es-ES' : 'en-US';

    if (currentView === 'day') {
      label.textContent = currentDate.toLocaleDateString(locale, {
        weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
      });
    } else {
      label.textContent = currentDate.toLocaleDateString(locale, {
        month: 'long', year: 'numeric'
      });
    }
  }

  function renderMonth() {
    const y = currentDate.getFullYear();
    const m = currentDate.getMonth();
    const today = new Date();
    const todayStr = fmtISO(today);

    const first = new Date(y, m, 1);
    const startDay = first.getDay() === 0 ? 6 : first.getDay() - 1;
    first.setDate(first.getDate() - startDay);

    const lang = (typeof i18n !== 'undefined') ? i18n.getLocale() : 'es';
    const dayNames = lang === 'es'
      ? ['Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab', 'Dom']
      : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    container.textContent = '';

    const grid = el('div', 'cal-month-grid');

    dayNames.forEach(d => {
      const header = el('div', 'cal-day-header');
      header.textContent = d;
      grid.appendChild(header);
    });

    for (let i = 0; i < 42; i++) {
      const date = new Date(first);
      date.setDate(first.getDate() + i);
      const dateStr = fmtISO(date);
      const isOther = date.getMonth() !== m;
      const isToday = dateStr === todayStr;

      const cell = el('div', 'cal-day' + (isOther ? ' other-month' : '') + (isToday ? ' today' : ''));
      cell.dataset.date = dateStr;

      const numSpan = el('span', 'cal-day-number');
      numSpan.textContent = date.getDate();
      cell.appendChild(numSpan);

      const dayAppts = appointments.filter(a => (a.appointment_date || a.date || '').substring(0, 10) === dateStr);
      dayAppts.slice(0, 3).forEach(a => {
        const evt = el('div', 'cal-event cal-event-' + (a.status || 'pending'));
        evt.dataset.id = a.id;
        evt.textContent = fmtTimeShort(a.start_time) + ' ' + (a.client_name || '');
        evt.addEventListener('click', (e) => {
          e.stopPropagation();
          if (callbacks.onEventClick) callbacks.onEventClick(a.id);
        });
        cell.appendChild(evt);
      });

      if (dayAppts.length > 3) {
        const more = el('div', 'cal-event');
        more.style.cssText = 'color:var(--color-text-muted);background:transparent;font-size:0.625rem;';
        more.textContent = '+' + (dayAppts.length - 3) + ' mas';
        cell.appendChild(more);
      }

      cell.addEventListener('click', () => {
        if (callbacks.onSlotClick) callbacks.onSlotClick(dateStr);
      });

      grid.appendChild(cell);
    }

    container.appendChild(grid);
  }

  function renderWeek() {
    const dayOfWeek = currentDate.getDay() === 0 ? 6 : currentDate.getDay() - 1;
    const monday = new Date(currentDate);
    monday.setDate(currentDate.getDate() - dayOfWeek);

    const lang = (typeof i18n !== 'undefined') ? i18n.getLocale() : 'es';
    const todayStr = fmtISO(new Date());

    container.textContent = '';

    const grid = el('div', 'cal-week-grid');
    grid.appendChild(el('div', 'cal-day-header'));

    for (let d = 0; d < 7; d++) {
      const date = new Date(monday);
      date.setDate(monday.getDate() + d);
      const dayName = date.toLocaleDateString(lang === 'es' ? 'es-ES' : 'en-US', { weekday: 'short' });
      const isToday = fmtISO(date) === todayStr;
      const header = el('div', 'cal-day-header');
      if (isToday) header.style.cssText = 'color:var(--color-highlight);font-weight:700;';
      header.textContent = dayName + ' ' + date.getDate();
      grid.appendChild(header);
    }

    for (let h = HOURS_START; h < HOURS_END; h++) {
      const timeCell = el('div', 'cal-week-time');
      timeCell.textContent = fmtHour(h);
      grid.appendChild(timeCell);

      for (let d = 0; d < 7; d++) {
        const date = new Date(monday);
        date.setDate(monday.getDate() + d);
        const dateStr = fmtISO(date);

        const cell = el('div', 'cal-week-cell');
        cell.dataset.date = dateStr;
        cell.dataset.hour = h;
        cell.style.position = 'relative';
        cell.addEventListener('click', () => {
          if (callbacks.onSlotClick) callbacks.onSlotClick(dateStr, h + ':00');
        });
        grid.appendChild(cell);

        const dayAppts = appointments.filter(a =>
          (a.appointment_date || a.date || '').substring(0, 10) === dateStr &&
          parseInt((a.start_time || '00').split(':')[0], 10) === h
        );

        dayAppts.forEach(a => {
          const startMin = parseInt((a.start_time || '00:00').split(':')[1], 10);
          const duration = parseInt(a.duration || 30, 10);

          const evt = el('div', 'cal-timeline-event cal-event-' + (a.status || 'pending'));
          evt.style.top = ((startMin / 60) * 48) + 'px';
          evt.style.height = Math.max((duration / 60) * 48, 20) + 'px';
          evt.dataset.id = a.id;
          evt.textContent = fmtTimeShort(a.start_time) + ' ' + (a.client_name || '');
          evt.addEventListener('click', (e) => {
            e.stopPropagation();
            if (callbacks.onEventClick) callbacks.onEventClick(a.id);
          });
          cell.appendChild(evt);
        });
      }
    }

    container.appendChild(grid);
  }

  function renderDay() {
    const dateStr = fmtISO(currentDate);
    const dayAppts = appointments.filter(a => (a.appointment_date || a.date || '').substring(0, 10) === dateStr);

    container.textContent = '';

    const timeline = el('div', 'cal-day-timeline');

    for (let h = HOURS_START; h < HOURS_END; h++) {
      const row = el('div', 'cal-timeline-hour');

      const label = el('div', 'cal-timeline-label');
      label.textContent = fmtHour(h);
      row.appendChild(label);

      const slot = el('div', 'cal-timeline-slot');
      slot.dataset.date = dateStr;
      slot.dataset.hour = h;
      slot.addEventListener('click', () => {
        if (callbacks.onSlotClick) callbacks.onSlotClick(dateStr, h + ':00');
      });

      const hourAppts = dayAppts.filter(a =>
        parseInt((a.start_time || '00').split(':')[0], 10) === h
      );

      hourAppts.forEach(a => {
        const startMin = parseInt((a.start_time || '00:00').split(':')[1], 10);
        const duration = parseInt(a.duration || 30, 10);

        const evt = el('div', 'cal-timeline-event cal-event-' + (a.status || 'pending'));
        evt.style.top = ((startMin / 60) * 60) + 'px';
        evt.style.height = Math.max((duration / 60) * 60, 24) + 'px';
        evt.dataset.id = a.id;

        const timeStrong = document.createElement('strong');
        timeStrong.textContent = fmtTimeShort(a.start_time);
        evt.appendChild(timeStrong);
        evt.appendChild(document.createTextNode(' ' + (a.client_name || '') + ' - ' + (a.service_name || '')));

        evt.addEventListener('click', (e) => {
          e.stopPropagation();
          if (callbacks.onEventClick) callbacks.onEventClick(a.id);
        });
        slot.appendChild(evt);
      });

      row.appendChild(slot);
      timeline.appendChild(row);
    }

    container.appendChild(timeline);
  }

  function el(tag, className) {
    const e = document.createElement(tag);
    if (className) e.className = className;
    return e;
  }

  function fmtISO(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + d;
  }

  function fmtHour(h) {
    const suffix = h >= 12 ? 'PM' : 'AM';
    const hour12 = h > 12 ? h - 12 : (h === 0 ? 12 : h);
    return hour12 + ' ' + suffix;
  }

  function fmtTimeShort(time) {
    if (!time) return '';
    const [h, m] = time.split(':');
    const hour = parseInt(h, 10);
    const suffix = hour >= 12 ? 'p' : 'a';
    const hour12 = hour > 12 ? hour - 12 : (hour === 0 ? 12 : hour);
    return hour12 + ':' + m + suffix;
  }

  function setView(view) {
    currentView = view;
    document.querySelectorAll('.cal-view-btn').forEach(b => {
      b.classList.toggle('active', b.dataset.view === view);
    });
    loadAppointments();
  }

  function refresh() {
    loadAppointments();
  }

  return { init, setView, refresh, navigate };
})();
