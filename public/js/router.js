const Router = (() => {
  const ROUTES = {
    '/': { page: 'pages/login.html', auth: false, title: 'Iniciar Sesion' },
    '/login': { page: 'pages/login.html', auth: false, title: 'Iniciar Sesion' },
    '/register': { page: 'pages/register.html', auth: false, title: 'Registro' },
    '/dashboard': { page: 'pages/dashboard.html', auth: true, title: 'Dashboard' },
    '/appointments': { page: 'pages/appointments.html', auth: true, title: 'Citas' },
    '/clients': { page: 'pages/clients.html', auth: true, title: 'Clientes' },
    '/services': { page: 'pages/services.html', auth: true, title: 'Servicios' },
    '/providers': { page: 'pages/providers.html', auth: true, title: 'Prestadores' },
    '/settings': { page: 'pages/settings.html', auth: true, title: 'Configuracion' },
    '/reports': { page: 'pages/reports.html', auth: true, title: 'Reportes' },
    '/admin': { page: 'pages/admin/dashboard.html', auth: true, role: 'superadmin', title: 'Admin' },
  };

  const PUBLIC_ROUTES = ['/login', '/register', '/'];

  let currentPath = null;
  let onRouteChange = null;

  function init(callback) {
    onRouteChange = callback;
    window.addEventListener('popstate', () => resolve());
    document.addEventListener('click', handleLinkClick);
    resolve();
  }

  function handleLinkClick(e) {
    const link = e.target.closest('a[data-link]');
    if (!link) return;
    e.preventDefault();
    const href = link.getAttribute('href');
    if (href && href !== currentPath) {
      navigate(href);
    }
  }

  function navigate(path) {
    window.history.pushState(null, '', path);
    resolve();
  }

  function resolve() {
    let path = window.location.pathname;

    if (path.startsWith('/negocio/')) {
      loadCatalog(path);
      return;
    }

    const route = ROUTES[path];

    if (!route) {
      load404();
      return;
    }

    if (route.auth && !Auth.isAuthenticated()) {
      navigate('/login');
      return;
    }

    if (!route.auth && Auth.isAuthenticated() && PUBLIC_ROUTES.includes(path)) {
      navigate('/dashboard');
      return;
    }

    if (route.role) {
      const user = Auth.getUser();
      if (!user || user.role !== route.role) {
        navigate('/dashboard');
        return;
      }
    }

    currentPath = path;
    loadPage(route);
  }

  async function loadPage(route) {
    const view = document.getElementById('app-view');
    if (!view) return;

    try {
      const response = await fetch(route.page);
      if (!response.ok) throw new Error('Page not found');
      const html = await response.text();
      // Safe: loading static HTML pages from our own server, never user-generated content
      view.innerHTML = html; // trusted source: own server pages
      window.scrollTo(0, 0);
      document.title = route.title + ' \u2014 CitaClick';

      if (typeof i18n !== 'undefined' && i18n.applyTranslations) {
        i18n.applyTranslations();
      }

      if (onRouteChange) {
        onRouteChange(route);
      }
    } catch (err) {
      load404();
    }
  }

  async function loadCatalog(path) {
    const slug = path.replace('/negocio/', '').replace(/\/$/, '');
    const view = document.getElementById('app-view');
    if (!view) return;

    try {
      const response = await fetch('pages/catalog.html');
      if (!response.ok) throw new Error('Catalog page not found');
      const html = await response.text();
      view.innerHTML = html; // trusted source: own server pages
      document.title = 'CitaClick';
      window.scrollTo(0, 0);

      if (typeof CatalogPage !== 'undefined' && CatalogPage.init) {
        CatalogPage.init(slug);
      }
    } catch (err) {
      load404();
    }
  }

  function load404() {
    const view = document.getElementById('app-view');
    if (!view) return;

    view.textContent = '';

    const container = document.createElement('div');
    container.style.cssText = 'display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:60dvh;text-align:center;padding:2rem;';

    const heading = document.createElement('h1');
    heading.textContent = '404';
    heading.style.cssText = 'font-size:var(--text-4xl);color:var(--color-highlight);margin-bottom:var(--space-4);';

    const message = document.createElement('p');
    message.textContent = 'Pagina no encontrada';
    message.style.cssText = 'font-size:var(--text-lg);color:var(--color-text-secondary);margin-bottom:var(--space-6);';

    const link = document.createElement('a');
    link.href = '/dashboard';
    link.setAttribute('data-link', '');
    link.className = 'btn btn-primary';
    link.textContent = 'Volver al inicio';

    container.appendChild(heading);
    container.appendChild(message);
    container.appendChild(link);
    view.appendChild(container);

    document.title = '404 \u2014 CitaClick';
  }

  function getCurrentPath() {
    return currentPath;
  }

  return { init, navigate, getCurrentPath };
})();
