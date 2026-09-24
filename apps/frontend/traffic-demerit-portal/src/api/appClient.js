const USER_DB_KEY = 'traffic_demerit_portal_user_v1';
const AUTH_TOKEN_KEY = 'traffic_demerit_portal_token_v1';
const REGISTER_PENDING_KEY = 'traffic_demerit_portal_register_pending_v1';
const API_BASE = (import.meta.env.VITE_API_BASE_URL || '/api/integration').replace(/\/$/, '');
const ROOT_API_BASE = API_BASE.replace(/\/integration$/, '');

const defaultUser = {
  id: 'local-dev-user',
  full_name: 'Local Developer',
  email: 'local.dev@barabara.test',
  role: 'admin',
};

const nowIso = () => new Date().toISOString();

const toQueryString = (params) => {
  const qs = new URLSearchParams();
  Object.entries(params || {}).forEach(([k, v]) => {
    if (v === undefined || v === null || v === '') return;
    if (typeof v === 'object') {
      Object.entries(v).forEach(([subK, subV]) => qs.append(`${k}[${subK}]`, String(subV)));
      return;
    }
    qs.append(k, String(v));
  });
  const text = qs.toString();
  return text ? `?${text}` : '';
};

const request = async (path, options = {}) => {
  const token = window.localStorage.getItem(AUTH_TOKEN_KEY);
  const response = await fetch(`${API_BASE}${path}`, {
    method: options.method || 'GET',
    headers: {
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(options.headers || {}),
    },
    body: options.body ? JSON.stringify(options.body) : undefined,
  });

  const text = await response.text();
  let data = null;
  if (text) {
    try {
      data = JSON.parse(text);
    } catch {
      data = { message: text };
    }
  }

  if (!response.ok) {
    const message = data?.message || `Request failed with status ${response.status}`;
    throw new Error(message);
  }

  return data;
};

const authRequest = async (path, options = {}) => {
  const token = window.localStorage.getItem(AUTH_TOKEN_KEY);
  const response = await fetch(`${ROOT_API_BASE}${path}`, {
    method: options.method || 'GET',
    headers: {
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(options.headers || {}),
    },
    body: options.body ? JSON.stringify(options.body) : undefined,
  });

  const text = await response.text();
  let data = null;
  if (text) {
    try {
      data = JSON.parse(text);
    } catch {
      data = { message: text };
    }
  }

  if (!response.ok) {
    const message = data?.message || data?.errors?.email?.[0] || `Request failed with status ${response.status}`;
    throw new Error(message);
  }

  return data;
};

const readUser = () => {
  const raw = window.localStorage.getItem(USER_DB_KEY);
  if (!raw) return defaultUser;

  try {
    const parsed = JSON.parse(raw);
    return { ...defaultUser, ...parsed };
  } catch {
    return defaultUser;
  }
};

const writeUser = (user) => {
  window.localStorage.setItem(USER_DB_KEY, JSON.stringify(user));
};

const writeToken = (token) => {
  if (token) {
    window.localStorage.setItem(AUTH_TOKEN_KEY, token);
  } else {
    window.localStorage.removeItem(AUTH_TOKEN_KEY);
  }
};

const mapRole = (role) => (role === 'motorist' ? 'driver' : role);

const normalizeUser = (user) => ({
  id: String(user.id),
  full_name: user.full_name || user.name || 'User',
  email: user.email,
  role: mapRole(user.role || 'driver'),
});

const makeEntityApi = (entityName) => ({
  async list(sort, limit) {
    const params = toQueryString({ sort, limit });
    return request(`/entities/${entityName}${params}`);
  },

  async filter(query = {}, sort, limit) {
    const params = toQueryString({ query, sort, limit });
    return request(`/entities/${entityName}/filter${params}`);
  },

  async create(payload) {
    return request(`/entities/${entityName}`, {
      method: 'POST',
      body: payload,
    });
  },

  async update(id, patch) {
    return request(`/entities/${entityName}/${id}`, {
      method: 'PATCH',
      body: patch,
    });
  },
});

export const appClient = {
  auth: {
    me: async () => {
      const token = window.localStorage.getItem(AUTH_TOKEN_KEY);
      if (!token) {
        throw new Error('Authentication required');
      }

      const user = await authRequest('/auth/me');
      const normalized = normalizeUser(user);
      writeUser(normalized);
      return normalized;
    },
    logout: async (_returnTo = '/login') => {
      try {
        await authRequest('/auth/logout', { method: 'POST' });
      } catch {
        // Ignore logout errors and clear local auth anyway.
      }
      writeToken(null);
      writeUser(defaultUser);
      if (typeof window !== 'undefined') {
        window.location.href = '/login';
      }
    },
    redirectToLogin: () => {
      if (typeof window !== 'undefined') {
        window.location.href = '/login';
      }
    },
    loginViaEmailPassword: async (email, password) => {
      const payload = await authRequest('/auth/login', {
        method: 'POST',
        body: { email, password },
      });
      writeToken(payload.token);
      const normalized = normalizeUser(payload.user);
      writeUser(normalized);
      return { ok: true, user: normalized };
    },
    loginWithProvider: (_provider, returnTo = '/') => {
      writeUser(defaultUser);
      if (typeof window !== 'undefined') {
        window.location.href = returnTo;
      }
    },
    register: async ({ email, password }) => {
      window.localStorage.setItem(REGISTER_PENDING_KEY, JSON.stringify({ email, password }));
      return { email };
    },
    verifyOtp: async ({ email, otpCode: _otpCode }) => {
      const pendingRaw = window.localStorage.getItem(REGISTER_PENDING_KEY);
      if (!pendingRaw) {
        throw new Error('Registration session expired. Please try again.');
      }

      const pending = JSON.parse(pendingRaw);
      if ((pending.email || '').toLowerCase() !== (email || '').toLowerCase()) {
        throw new Error('Email mismatch. Please try again.');
      }

      const payload = await authRequest('/auth/register', {
        method: 'POST',
        body: {
          email: pending.email,
          password: pending.password,
          name: pending.email.split('@')[0],
          role: 'driver',
        },
      });

      window.localStorage.removeItem(REGISTER_PENDING_KEY);
      writeToken(payload.token);
      const normalized = normalizeUser(payload.user);
      writeUser(normalized);
      return { access_token: payload.token, user: normalized };
    },
    setToken: (token) => writeToken(token),
    resendOtp: async (_email) => ({ ok: true }),
    resetPasswordRequest: async (_email) => ({ ok: true }),
    resetPassword: async (_payload) => ({ ok: true }),
  },
  entities: {
    User: makeEntityApi('User'),
    Role: makeEntityApi('Role'),
    Station: makeEntityApi('Station'),
    OfficerAssignment: makeEntityApi('OfficerAssignment'),
    Vehicle: makeEntityApi('Vehicle'),
    Driver: makeEntityApi('Driver'),
    Violation: makeEntityApi('Violation'),
    DemeritLedger: makeEntityApi('DemeritLedger'),
    Notification: makeEntityApi('Notification'),
    RiskPrediction: makeEntityApi('RiskPrediction'),
    RecidivismScore: makeEntityApi('RecidivismScore'),
    Sanction: makeEntityApi('Sanction'),
    Appeal: makeEntityApi('Appeal'),
    ReviewLog: makeEntityApi('ReviewLog'),
    Payment: makeEntityApi('Payment'),
  },
  payments: {
    initiate: async (violationId, method = 'mpesa') => {
      return authRequest(`/violations/${violationId}/pay`, {
        method: 'POST',
        body: { method },
      });
    },
    verify: async (paymentId, payload = {}) => {
      return authRequest(`/payments/${paymentId}/verify`, {
        method: 'POST',
        body: payload,
      });
    },
  },
  functions: {
    invoke: async (name, payload) => {
      return request(`/functions/${name}`, {
        method: 'POST',
        body: payload || {},
      });
    },
  },
};