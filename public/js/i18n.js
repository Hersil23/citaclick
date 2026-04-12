const i18n = (() => {
  const LANG_KEY = 'cc_lang';
  const SUPPORTED = ['es', 'en'];
  let currentLocale = 'es';
  let translations = {};

  function init() {
    const saved = localStorage.getItem(LANG_KEY);
    if (saved && SUPPORTED.includes(saved)) {
      currentLocale = saved;
    } else {
      const browser = (navigator.language || 'es').slice(0, 2).toLowerCase();
      currentLocale = SUPPORTED.includes(browser) ? browser : 'es';
    }
    return loadLocale(currentLocale);
  }

  async function loadLocale(lang) {
    try {
      const response = await fetch('/locales/' + lang + '.json');
      if (!response.ok) throw new Error('Locale not found');
      translations = await response.json();
      currentLocale = lang;
      localStorage.setItem(LANG_KEY, lang);
      applyTranslations();
    } catch (err) {
      if (lang !== 'es') {
        return loadLocale('es');
      }
    }
  }

  function setLocale(lang) {
    if (!SUPPORTED.includes(lang)) return;
    if (lang === currentLocale) return;
    return loadLocale(lang);
  }

  function getLocale() {
    return currentLocale;
  }

  function t(key, vars) {
    const keys = key.split('.');
    let value = translations;

    for (let i = 0; i < keys.length; i++) {
      if (value === undefined || value === null) return key;
      value = value[keys[i]];
    }

    if (typeof value !== 'string') return key;

    if (vars && typeof vars === 'object') {
      return value.replace(/\{(\w+)\}/g, (match, name) => {
        return vars[name] !== undefined ? vars[name] : match;
      });
    }

    return value;
  }

  function applyTranslations() {
    document.querySelectorAll('[data-i18n]').forEach(el => {
      const key = el.getAttribute('data-i18n');
      const translated = t(key);
      if (translated !== key) {
        el.textContent = translated;
      }
    });

    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
      const key = el.getAttribute('data-i18n-placeholder');
      const translated = t(key);
      if (translated !== key) {
        el.placeholder = translated;
      }
    });

    document.querySelectorAll('[data-i18n-title]').forEach(el => {
      const key = el.getAttribute('data-i18n-title');
      const translated = t(key);
      if (translated !== key) {
        el.title = translated;
      }
    });

    document.querySelectorAll('[data-i18n-aria]').forEach(el => {
      const key = el.getAttribute('data-i18n-aria');
      const translated = t(key);
      if (translated !== key) {
        el.setAttribute('aria-label', translated);
      }
    });
  }

  return { init, setLocale, getLocale, t, applyTranslations };
})();
