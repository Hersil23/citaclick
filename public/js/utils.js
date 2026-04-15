function loadFooter() {
  if (document.querySelector('.site-footer')) return;

  const appMain = document.querySelector('.app-main');
  if (!appMain) return;

  const footer = document.createElement('footer');
  footer.className = 'site-footer';
  footer.textContent = 'Desarrollado por ';

  const link = document.createElement('a');
  link.href = 'https://www.herasi.dev';
  link.target = '_blank';
  link.rel = 'noopener noreferrer';
  link.textContent = '@herasi.dev';
  footer.appendChild(link);

  appMain.appendChild(footer);
}

async function loadComponent(name, selector) {
  const target = document.querySelector(selector);
  if (!target) return;

  try {
    const response = await fetch('components/' + name + '.html');
    if (!response.ok) return;
    const html = await response.text();
    // Safe: loading static HTML from own server, not user-generated
    target.innerHTML = html;
  } catch (err) {
    // Component load failed silently
  }
}

function formatDate(date, locale) {
  const lang = locale || localStorage.getItem('cc_lang') || 'es';
  const d = date instanceof Date ? date : new Date(date);

  if (isNaN(d.getTime())) return '';

  return d.toLocaleDateString(lang === 'es' ? 'es-ES' : 'en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

function formatTime(date) {
  const d = date instanceof Date ? date : new Date(date);
  if (isNaN(d.getTime())) return '';

  return d.toLocaleTimeString([], {
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
  });
}

function formatCurrency(amount, currency, mode) {
  const num = parseFloat(amount);
  if (isNaN(num)) return '';

  const usd = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(num);

  if (mode === 'usd' || !currency || currency === 'USD') {
    return usd;
  }

  const localRate = parseFloat(localStorage.getItem('cc_exchange_rate') || '1');
  const localAmount = num * localRate;
  const localCurrency = currency || 'USD';

  const local = new Intl.NumberFormat('es', {
    style: 'currency',
    currency: localCurrency,
    minimumFractionDigits: 2,
  }).format(localAmount);

  if (mode === 'local') return local;
  if (mode === 'both') return usd + ' / ' + local;

  return usd;
}

function debounce(fn, delay) {
  let timer;
  return function (...args) {
    clearTimeout(timer);
    timer = setTimeout(() => fn.apply(this, args), delay);
  };
}

function generateQR(url, size) {
  var qrSize = size || 200;
  var img = document.createElement('img');
  img.alt = 'QR Code';
  img.width = qrSize;
  img.height = qrSize;
  img.style.display = 'block';
  try {
    if (typeof qrcode === 'undefined') throw 'qrcode lib not loaded';
    var qr = qrcode(0, 'M');
    qr.addData(url);
    qr.make();
    img.src = qr.createDataURL(Math.floor(qrSize / qr.getModuleCount()), 0);
  } catch (e) {
    // Fallback: load library dynamically
    var script = document.createElement('script');
    script.src = '/js/qrcode.min.js';
    script.onload = function() {
      try {
        var qr2 = qrcode(0, 'M');
        qr2.addData(url);
        qr2.make();
        img.src = qr2.createDataURL(Math.floor(qrSize / qr2.getModuleCount()), 0);
      } catch (e2) {
        console.error('QR generation failed:', e2);
      }
    };
    document.head.appendChild(script);
  }
  return img;
}

function downloadFile(blob, filename) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}

async function copyToClipboard(text) {
  try {
    await navigator.clipboard.writeText(text);
    showToast('Copiado al portapapeles', 'success');
  } catch (err) {
    showToast('No se pudo copiar', 'error');
  }
}

function createSvgIcon(pathData, viewBox) {
  const ns = 'http://www.w3.org/2000/svg';
  const svg = document.createElementNS(ns, 'svg');
  svg.setAttribute('viewBox', viewBox || '0 0 20 20');
  svg.setAttribute('fill', 'currentColor');
  const path = document.createElementNS(ns, 'path');
  path.setAttribute('fill-rule', 'evenodd');
  path.setAttribute('clip-rule', 'evenodd');
  path.setAttribute('d', pathData);
  svg.appendChild(path);
  return svg;
}

const TOAST_ICONS = {
  success: 'M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z',
  error: 'M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z',
  warning: 'M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z',
  info: 'M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z',
};

const CLOSE_ICON_PATH = 'M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z';

function showToast(message, type, duration) {
  const ms = duration || 4000;
  let container = document.querySelector('.toast-container');
  if (!container) {
    container = document.createElement('div');
    container.className = 'toast-container';
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  toast.className = 'toast toast-' + (type || 'info');

  const iconEl = document.createElement('span');
  iconEl.className = 'toast-icon';
  iconEl.appendChild(createSvgIcon(TOAST_ICONS[type] || TOAST_ICONS.info));

  const msgEl = document.createElement('span');
  msgEl.className = 'toast-message';
  msgEl.textContent = message;

  const closeEl = document.createElement('button');
  closeEl.className = 'toast-close';
  closeEl.appendChild(createSvgIcon(CLOSE_ICON_PATH));
  closeEl.addEventListener('click', () => removeToast(toast));

  toast.appendChild(iconEl);
  toast.appendChild(msgEl);
  toast.appendChild(closeEl);
  container.appendChild(toast);

  setTimeout(() => removeToast(toast), ms);
}

function removeToast(toast) {
  if (!toast || !toast.parentNode) return;
  toast.classList.add('removing');
  setTimeout(() => {
    if (toast.parentNode) toast.parentNode.removeChild(toast);
  }, 200);
}

function openModal(id) {
  const overlay = document.getElementById(id);
  if (overlay) {
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
}

function closeModal(id) {
  const overlay = document.getElementById(id);
  if (overlay) {
    overlay.classList.remove('active');
    document.body.style.overflow = '';
  }
}

function closeAllModals() {
  document.querySelectorAll('.modal-overlay.active').forEach(overlay => {
    overlay.classList.remove('active');
  });
  document.body.style.overflow = '';
}

function toggleSidebar() {
  const sidebar = document.querySelector('.app-sidebar');
  const overlay = document.querySelector('.sidebar-overlay');

  if (sidebar) {
    sidebar.classList.toggle('open');
  }
  if (overlay) {
    overlay.classList.toggle('active');
  }
}

document.addEventListener('click', (e) => {
  if (e.target.closest('.sidebar-overlay')) {
    toggleSidebar();
  }

  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('active');
    document.body.style.overflow = '';
  }

  const openDropdowns = document.querySelectorAll('.dropdown-menu.active');
  openDropdowns.forEach(menu => {
    if (!menu.parentElement.contains(e.target)) {
      menu.classList.remove('active');
    }
  });
});

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    closeAllModals();

    const sidebar = document.querySelector('.app-sidebar.open');
    if (sidebar) toggleSidebar();

    document.querySelectorAll('.dropdown-menu.active').forEach(menu => {
      menu.classList.remove('active');
    });
  }
});

function toggleDropdown(menuId) {
  const menu = document.getElementById(menuId);
  if (!menu) return;

  document.querySelectorAll('.dropdown-menu.active').forEach(m => {
    if (m.id !== menuId) m.classList.remove('active');
  });

  menu.classList.toggle('active');
}

function slugify(text) {
  return text
    .toString()
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

function getInitials(name) {
  if (!name) return '?';
  return name
    .split(' ')
    .slice(0, 2)
    .map(w => w.charAt(0))
    .join('')
    .toUpperCase();
}
