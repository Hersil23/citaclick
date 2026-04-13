const App = (() => {
  const THEME_KEY = 'cc_theme';
  const MODE_KEY = 'cc_mode';
  const DEFAULT_THEME = 'caballeros';
  const DEFAULT_MODE = 'light';

  function init() {
    applyTheme(getTheme());
    applyMode(getMode());

    if (typeof i18n !== 'undefined' && i18n.init) {
      i18n.init();
    }

    Router.init(onRouteChange);
    setupThemeWatcher();
  }

  function getTheme() {
    return localStorage.getItem(THEME_KEY) || DEFAULT_THEME;
  }

  function setTheme(theme) {
    localStorage.setItem(THEME_KEY, theme);
    applyTheme(theme);
  }

  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
  }

  function getMode() {
    return localStorage.getItem(MODE_KEY) || DEFAULT_MODE;
  }

  function setMode(mode) {
    localStorage.setItem(MODE_KEY, mode);
    applyMode(mode);
  }

  function applyMode(mode) {
    document.documentElement.setAttribute('data-mode', mode);
  }

  function toggleMode() {
    const current = getMode();
    setMode(current === 'light' ? 'dark' : 'light');
  }

  function onRouteChange(route) {
    updateSidebarActive(Router.getCurrentPath());
    updateHeaderTitle(route.title);
    loadFooter();

    if (typeof i18n !== 'undefined' && i18n.applyTranslations) {
      i18n.applyTranslations();
    }
  }

  function updateSidebarActive(path) {
    document.querySelectorAll('.sidebar-link').forEach(link => {
      const href = link.getAttribute('href');
      if (href === path) {
        link.classList.add('active');
      } else {
        link.classList.remove('active');
      }
    });
  }

  function updateHeaderTitle(title) {
    const el = document.querySelector('.header-title');
    if (el) {
      el.textContent = title || '';
    }
  }

  function setupThemeWatcher() {
    window.addEventListener('storage', (e) => {
      if (e.key === THEME_KEY && e.newValue) {
        applyTheme(e.newValue);
      }
      if (e.key === MODE_KEY && e.newValue) {
        applyMode(e.newValue);
      }
    });
  }

  return {
    init,
    getTheme,
    setTheme,
    getMode,
    setMode,
    toggleMode,
  };
})();

document.addEventListener('DOMContentLoaded', () => App.init());
