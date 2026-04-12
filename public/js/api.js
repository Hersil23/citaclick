const Api = (() => {
  const BASE_URL = window.location.hostname === 'localhost'
    ? 'http://localhost/citaclick/api'
    : 'https://citaclick.net/api';

  async function request(method, endpoint, data = null) {
    const url = BASE_URL + endpoint;
    const options = {
      method,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    };

    const token = localStorage.getItem('cc_token');
    if (token) {
      options.headers['Authorization'] = 'Bearer ' + token;
    }

    if (data && method !== 'GET') {
      options.body = JSON.stringify(data);
    }

    try {
      const response = await fetch(url, options);
      const json = await response.json();

      if (response.status === 401) {
        localStorage.removeItem('cc_token');
        localStorage.removeItem('cc_user');
        Router.navigate('/login');
        return { success: false, message: 'Sesion expirada', data: null };
      }

      if (!response.ok) {
        const errorMsg = json.message || 'Error en la solicitud';
        if (typeof showToast === 'function') {
          showToast(errorMsg, 'error');
        }
        return { success: false, message: errorMsg, errors: json.errors || {}, data: null };
      }

      return json;
    } catch (err) {
      const networkMsg = 'Error de conexion. Verifica tu red.';
      if (typeof showToast === 'function') {
        showToast(networkMsg, 'error');
      }
      return { success: false, message: networkMsg, data: null };
    }
  }

  function get(endpoint) {
    return request('GET', endpoint);
  }

  function post(endpoint, data) {
    return request('POST', endpoint, data);
  }

  function put(endpoint, data) {
    return request('PUT', endpoint, data);
  }

  function patch(endpoint, data) {
    return request('PATCH', endpoint, data);
  }

  function del(endpoint) {
    return request('DELETE', endpoint);
  }

  async function upload(endpoint, formData) {
    const url = BASE_URL + endpoint;
    const options = {
      method: 'POST',
      headers: {},
    };

    const token = localStorage.getItem('cc_token');
    if (token) {
      options.headers['Authorization'] = 'Bearer ' + token;
    }

    options.body = formData;

    try {
      const response = await fetch(url, options);
      const json = await response.json();

      if (response.status === 401) {
        localStorage.removeItem('cc_token');
        localStorage.removeItem('cc_user');
        Router.navigate('/login');
        return { success: false, message: 'Sesion expirada', data: null };
      }

      if (!response.ok) {
        const errorMsg = json.message || 'Error al subir archivo';
        if (typeof showToast === 'function') {
          showToast(errorMsg, 'error');
        }
        return { success: false, message: errorMsg, data: null };
      }

      return json;
    } catch (err) {
      const networkMsg = 'Error de conexion. Verifica tu red.';
      if (typeof showToast === 'function') {
        showToast(networkMsg, 'error');
      }
      return { success: false, message: networkMsg, data: null };
    }
  }

  return { get, post, put, patch, delete: del, upload };
})();
