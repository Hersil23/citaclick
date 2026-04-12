const Auth = (() => {
  const TOKEN_KEY = 'cc_token';
  const USER_KEY = 'cc_user';

  async function login(email, password) {
    const result = await Api.post('/auth/login', { email, password });
    if (result.success && result.data) {
      saveSession(result.data.token, result.data.user);
    }
    return result;
  }

  async function loginWithGoogle() {
    const clientId = window.CC_GOOGLE_CLIENT_ID || '';
    if (!clientId) {
      showToast(i18n.t('errors.server'), 'error');
      return { success: false };
    }

    return new Promise((resolve) => {
      const width = 500;
      const height = 600;
      const left = (screen.width - width) / 2;
      const top = (screen.height - height) / 2;
      const authUrl = 'https://accounts.google.com/o/oauth2/v2/auth' +
        '?client_id=' + encodeURIComponent(clientId) +
        '&redirect_uri=' + encodeURIComponent(window.location.origin + '/auth/google/callback') +
        '&response_type=code' +
        '&scope=openid%20email%20profile' +
        '&prompt=select_account';

      const popup = window.open(authUrl, 'google-auth',
        'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top);

      window.addEventListener('message', async function handler(e) {
        if (e.origin !== window.location.origin) return;
        window.removeEventListener('message', handler);

        if (e.data && e.data.code) {
          const result = await Api.post('/auth/google', { code: e.data.code });
          if (result.success && result.data) {
            saveSession(result.data.token, result.data.user);
            Router.navigate('/dashboard');
          }
          resolve(result);
        } else {
          resolve({ success: false, message: 'Google auth cancelled' });
        }
      });

      const timer = setInterval(() => {
        if (popup && popup.closed) {
          clearInterval(timer);
          resolve({ success: false, message: 'Google auth cancelled' });
        }
      }, 500);
    });
  }

  async function loginWithPhone(phone) {
    return Api.post('/auth/phone', { phone });
  }

  async function verifyOtp(phone, code) {
    const result = await Api.post('/auth/phone/verify', { phone, code });
    if (result.success && result.data) {
      saveSession(result.data.token, result.data.user);
    }
    return result;
  }

  async function register(data) {
    const result = await Api.post('/auth/register', data);
    if (result.success && result.data) {
      saveSession(result.data.token, result.data.user);

      if (data.theme) {
        App.setTheme(data.theme);
      }
    }
    return result;
  }

  function logout() {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
    Api.delete('/auth/logout').catch(() => {});
    Router.navigate('/login');
  }

  function isAuthenticated() {
    const token = getToken();
    if (!token) return false;

    try {
      const payload = decodeTokenPayload(token);
      if (!payload || !payload.exp) return false;
      return payload.exp * 1000 > Date.now();
    } catch (e) {
      return false;
    }
  }

  function getUser() {
    const stored = localStorage.getItem(USER_KEY);
    if (!stored) return null;

    try {
      return JSON.parse(stored);
    } catch (e) {
      return null;
    }
  }

  function getToken() {
    return localStorage.getItem(TOKEN_KEY);
  }

  async function refreshToken() {
    const result = await Api.post('/auth/refresh');
    if (result.success && result.data) {
      localStorage.setItem(TOKEN_KEY, result.data.token);
      if (result.data.user) {
        localStorage.setItem(USER_KEY, JSON.stringify(result.data.user));
      }
    }
    return result;
  }

  function saveSession(token, user) {
    localStorage.setItem(TOKEN_KEY, token);
    if (user) {
      localStorage.setItem(USER_KEY, JSON.stringify(user));
    }
  }

  function decodeTokenPayload(token) {
    try {
      const parts = token.split('.');
      if (parts.length !== 3) return null;
      const payload = parts[1].replace(/-/g, '+').replace(/_/g, '/');
      return JSON.parse(atob(payload));
    } catch (e) {
      return null;
    }
  }

  function scheduleRefresh() {
    const token = getToken();
    if (!token) return;

    try {
      const payload = decodeTokenPayload(token);
      if (!payload || !payload.exp) return;

      const expiresIn = (payload.exp * 1000) - Date.now();
      const refreshAt = expiresIn - (5 * 60 * 1000);

      if (refreshAt > 0) {
        setTimeout(() => {
          if (isAuthenticated()) {
            refreshToken().then(() => scheduleRefresh());
          }
        }, refreshAt);
      }
    } catch (e) {
      // Token decode failed
    }
  }

  if (isAuthenticated()) {
    scheduleRefresh();
  }

  return {
    login,
    loginWithGoogle,
    loginWithPhone,
    verifyOtp,
    register,
    logout,
    isAuthenticated,
    getUser,
    getToken,
    refreshToken,
  };
})();
