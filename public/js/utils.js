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
  var canvas = document.createElement('canvas');
  canvas.width = qrSize;
  canvas.height = qrSize;
  canvas.style.imageRendering = 'pixelated';
  try {
    var modules = _qrEncode(url);
    var ctx = canvas.getContext('2d');
    var cellSize = qrSize / modules.length;
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, qrSize, qrSize);
    ctx.fillStyle = '#000000';
    for (var r = 0; r < modules.length; r++) {
      for (var c = 0; c < modules.length; c++) {
        if (modules[r][c]) {
          ctx.fillRect(Math.round(c * cellSize), Math.round(r * cellSize), Math.ceil(cellSize), Math.ceil(cellSize));
        }
      }
    }
  } catch (e) {
    var ctx2 = canvas.getContext('2d');
    ctx2.fillStyle = '#f0f0f0';
    ctx2.fillRect(0, 0, qrSize, qrSize);
    ctx2.fillStyle = '#999';
    ctx2.font = '12px sans-serif';
    ctx2.textAlign = 'center';
    ctx2.fillText('QR Error', qrSize / 2, qrSize / 2);
  }
  var img = document.createElement('img');
  img.src = canvas.toDataURL('image/png');
  img.alt = 'QR Code';
  img.width = qrSize;
  img.height = qrSize;
  return img;
}

// Minimal QR encoder (Mode Byte, ECC-L, versions 1-10)
function _qrEncode(text) {
  var data = [];
  for (var i = 0; i < text.length; i++) {
    var code = text.charCodeAt(i);
    if (code < 128) data.push(code);
    else if (code < 2048) { data.push(192 | (code >> 6)); data.push(128 | (code & 63)); }
    else { data.push(224 | (code >> 12)); data.push(128 | ((code >> 6) & 63)); data.push(128 | (code & 63)); }
  }
  // Version capacities for ECC-L byte mode
  var caps = [17,32,53,78,106,134,154,192,230,271];
  var ver = 1;
  for (var v = 0; v < caps.length; v++) { if (data.length <= caps[v]) { ver = v + 1; break; } }
  var sz = ver * 4 + 17;
  // Data codewords and EC codewords per version (ECC-L)
  var dcws = [19,34,55,80,108,136,156,194,232,274];
  var ecws = [7,10,15,20,26,18,20,24,30,18];
  var totalDc = dcws[ver - 1];
  var totalEc = ecws[ver - 1];
  // Build data bits: mode(4) + count(8 or 16) + data + terminator + padding
  var bits = [];
  function pushBits(val, len) { for (var b = len - 1; b >= 0; b--) bits.push((val >> b) & 1); }
  pushBits(4, 4); // byte mode
  pushBits(data.length, ver <= 9 ? 8 : 16);
  for (var i = 0; i < data.length; i++) pushBits(data[i], 8);
  var totalBits = totalDc * 8;
  pushBits(0, Math.min(4, totalBits - bits.length));
  while (bits.length % 8 !== 0) bits.push(0);
  var pads = [236, 17];
  for (var p = 0; bits.length < totalBits; p++) pushBits(pads[p % 2], 8);
  // Convert bits to bytes
  var dcBytes = [];
  for (var i = 0; i < bits.length; i += 8) {
    var b = 0; for (var j = 0; j < 8; j++) b = (b << 1) | (bits[i + j] || 0);
    dcBytes.push(b);
  }
  // Reed-Solomon EC
  var ecBytes = _rsEncode(dcBytes, totalEc);
  var allBytes = dcBytes.concat(ecBytes);
  // Create module grid
  var grid = []; var reserved = [];
  for (var r = 0; r < sz; r++) { grid[r] = []; reserved[r] = []; for (var c = 0; c < sz; c++) { grid[r][c] = false; reserved[r][c] = false; } }
  // Finder patterns
  function setFinder(row, col) {
    for (var r = -1; r <= 7; r++) for (var c = -1; c <= 7; c++) {
      var rr = row + r, cc = col + c;
      if (rr < 0 || rr >= sz || cc < 0 || cc >= sz) continue;
      var on = (r >= 0 && r <= 6 && (c === 0 || c === 6)) || (c >= 0 && c <= 6 && (r === 0 || r === 6)) || (r >= 2 && r <= 4 && c >= 2 && c <= 4);
      grid[rr][cc] = on; reserved[rr][cc] = true;
    }
  }
  setFinder(0, 0); setFinder(0, sz - 7); setFinder(sz - 7, 0);
  // Timing patterns
  for (var i = 8; i < sz - 8; i++) { grid[6][i] = i % 2 === 0; reserved[6][i] = true; grid[i][6] = i % 2 === 0; reserved[i][6] = true; }
  // Dark module + reserved format areas
  grid[sz - 8][8] = true; reserved[sz - 8][8] = true;
  for (var i = 0; i < 9; i++) { reserved[8][i] = true; reserved[i][8] = true; reserved[8][sz - 1 - i] = true; if (i < 8) reserved[sz - 1 - i][8] = true; }
  // Alignment patterns (ver >= 2)
  if (ver >= 2) {
    var aligns = [6, [6,18],[6,22],[6,26],[6,30],[6,34],[6,22,38],[6,24,42],[6,26,46],[6,28,50]];
    var pos = ver === 1 ? [] : aligns[ver - 1];
    if (typeof pos === 'number') pos = [pos];
    for (var i = 0; i < pos.length; i++) for (var j = 0; j < pos.length; j++) {
      var r = pos[i], c = pos[j];
      if (reserved[r][c]) continue;
      for (var dr = -2; dr <= 2; dr++) for (var dc = -2; dc <= 2; dc++) {
        grid[r + dr][c + dc] = Math.abs(dr) === 2 || Math.abs(dc) === 2 || (dr === 0 && dc === 0);
        reserved[r + dr][c + dc] = true;
      }
    }
  }
  // Version info (ver >= 7) - skip for simplicity, max ver 10
  // Place data bits
  var bitIdx = 0;
  var allBits = [];
  for (var i = 0; i < allBytes.length; i++) for (var b = 7; b >= 0; b--) allBits.push((allBytes[i] >> b) & 1);
  for (var col = sz - 1; col >= 1; col -= 2) {
    if (col === 6) col = 5;
    for (var cnt = 0; cnt < sz; cnt++) {
      var row = ((Math.floor((sz - 1 - col) / 2)) % 2 === 0) ? sz - 1 - cnt : cnt;
      for (var dc = 0; dc <= 1; dc++) {
        var cc = col - dc;
        if (reserved[row][cc]) continue;
        grid[row][cc] = bitIdx < allBits.length ? !!allBits[bitIdx] : false;
        bitIdx++;
      }
    }
  }
  // Apply mask 0 (checkerboard) and format info
  for (var r = 0; r < sz; r++) for (var c = 0; c < sz; c++) {
    if (!reserved[r][c]) { if ((r + c) % 2 === 0) grid[r][c] = !grid[r][c]; }
  }
  // Format bits for mask 0, ECC-L: pre-computed
  var fmtBits = [1,1,1,0,1,1,1,1,1,0,0,0,1,0,0];
  var fmtPos = [[8,0],[8,1],[8,2],[8,3],[8,4],[8,5],[8,7],[8,8],[7,8],[5,8],[4,8],[3,8],[2,8],[1,8],[0,8]];
  var fmtPos2 = [[sz-1,8],[sz-2,8],[sz-3,8],[sz-4,8],[sz-5,8],[sz-6,8],[sz-7,8],[8,sz-8],[8,sz-7],[8,sz-6],[8,sz-5],[8,sz-4],[8,sz-3],[8,sz-2],[8,sz-1]];
  for (var i = 0; i < 15; i++) {
    grid[fmtPos[i][0]][fmtPos[i][1]] = !!fmtBits[i];
    grid[fmtPos2[i][0]][fmtPos2[i][1]] = !!fmtBits[i];
  }
  return grid;
}

// Reed-Solomon encoding over GF(256)
function _rsEncode(data, ecLen) {
  var gfExp = new Array(256), gfLog = new Array(256);
  var x = 1;
  for (var i = 0; i < 256; i++) { gfExp[i] = x; gfLog[x] = i; x <<= 1; if (x >= 256) x ^= 0x11d; }
  gfLog[0] = 255;
  // Generator polynomial
  var gen = [1];
  for (var i = 0; i < ecLen; i++) {
    var ng = new Array(gen.length + 1);
    for (var j = 0; j < ng.length; j++) ng[j] = 0;
    for (var j = 0; j < gen.length; j++) {
      ng[j] ^= gen[j];
      ng[j + 1] ^= gfExp[(gfLog[gen[j]] + i) % 255];
    }
    gen = ng;
  }
  var msg = data.slice();
  for (var i = 0; i < ecLen; i++) msg.push(0);
  for (var i = 0; i < data.length; i++) {
    var coef = msg[i];
    if (coef !== 0) {
      for (var j = 0; j < gen.length; j++) {
        msg[i + j] ^= gfExp[(gfLog[gen[j]] + gfLog[coef]) % 255];
      }
    }
  }
  return msg.slice(data.length);
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
