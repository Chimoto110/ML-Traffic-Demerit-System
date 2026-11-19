const AUTH_TOKEN_KEY = 'traffic_demerit_portal_token_v1';

// API origin + base path.
//  - VITE_API_URL: absolute origin of the Laravel server (http://localhost:8000)
//  - VITE_API_BASE_URL: entity/integration base (/api/integration)
// Auth and root-level endpoints live under ROOT_API_BASE (/api).
// When VITE_API_URL is empty, relative URLs are used and the Vite dev
// proxy forwards /api to Laravel (see vite.config.js).
const ORIGIN = (import.meta.env.VITE_API_URL || '').replace(/\/$/, '');
const API_BASE = (import.meta.env.VITE_API_BASE_URL || '/api/integration').replace(/\/$/, '');
const ROOT_API_BASE = API_BASE.replace(/\/integration$/, '');
const jsonHeaders = { Accept: 'application/json', 'Content-Type': 'application/json' };

const toQueryString = (params) => {
  const qs = new URLSearchParams();
  Object.entries(params || {}).forEach(([k, v]) => {
    if (v === undefined || v === null || v === '') return;
    if (typeof v === 'object') {
      Object.entries(v).forEach(([subK, subV]) => {
        if (subV === undefined || subV === null || subV === '') return;
        qs.append(`${k}[${subK}]`, String(subV));
      });
      return;
    }
    qs.append(k, String(v));
  });
  const text = qs.toString();
  return text ? `?${text}` : '';
};

/** Extracts a human-readable error from a response (JSON or HTML). */
const errorFrom = async (response, fallback) => {
  const text = await response.text().catch(() => '');
  let data = null;

  if (text) {
    try {
      data = JSON.parse(text);
    } catch {
      // HTML error page (Laravel 404/500 without Accept: json) - never
      // surface raw markup to the UI.
      const match = text.match(/<title>([^<]+)<\/title>/i);
      data = { message: match ? match[1].trim() : fallback };
    }
  }

  const message =
    data?.errors?.email?.[0] ||
    (data?.errors && Object.values(data.errors).flat()[0]) ||
    data?.message ||
    fallback;

  const error = new Error(message);
  error.status = response.status;
  error.errors = data?.errors || null;
  return error;
};

const request = async (path, options = {}) => {
  const token = window.localStorage.getItem(AUTH_TOKEN_KEY);
  // base: 'root' targets ROOT_API_BASE (/api — CRUD/charts/system/payments);
  // default targets API_BASE (/api/integration — entities/functions).
  const { base, ...fetchOptions } = options;
  const url = `${ORIGIN}${base === 'root' ? ROOT_API_BASE : API_BASE}${path}`;

  let response;
  try {
    response = await fetch(url, {
      method: fetchOptions.method || 'GET',
      headers: {
        ...jsonHeaders,
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...(fetchOptions.headers || {}),
      },
      body: fetchOptions.body ? JSON.stringify(fetchOptions.body) : undefined,
    });
  } catch (networkError) {
    const error = new Error(
      `Cannot reach the API at ${ORIGIN || ''}${ROOT_API_BASE}. Is the Laravel server running? (${networkError.message})`
    );
    error.network = true;
    throw error;
  }

  if (response.status === 401) {
    // Token expired or revoked - drop it so the next render routes to login.
    window.localStorage.removeItem(AUTH_TOKEN_KEY);
  }

  if (!response.ok) {
    throw await errorFrom(response, `Request failed with status ${response.status}`);
  }

  const text = await response.text();
  if (!text) return null;

  try {
    return JSON.parse(text);
  } catch {
    throw new Error('The API returned an unreadable (non-JSON) response.');
  }
};

const authRequest = async (path, options = {}) => {
  const token = window.localStorage.getItem(AUTH_TOKEN_KEY);
  let response;

  try {
    response = await fetch(`${ORIGIN}${ROOT_API_BASE}${path}`, {
      method: options.method || 'GET',
      headers: {
        ...jsonHeaders,
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...(options.headers || {}),
      },
      body: options.body ? JSON.stringify(options.body) : undefined,
    });
  } catch (networkError) {
    const error = new Error(`Cannot reach the API. Is the Laravel server running? (${networkError.message})`);
    error.network = true;
    throw error;
  }

  if (!response.ok) {
    throw await errorFrom(response, `Request failed with status ${response.status}`);
  }

  const text = await response.text();
  if (!text) return null;

  try {
    return JSON.parse(text);
  } catch {
    throw new Error('The API returned an unreadable (non-JSON) response.');
  }
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
  id: String(user.id ?? ''),
  full_name: user.full_name || user.name || 'User',
  name: user.name || user.full_name || 'User',
  email: user.email || '',
  role: mapRole(user.role || 'driver'),
  is_active: user.is_active !== false,
});

const makeEntityApi = (entityName) => ({
  /** Paginated list: { data, meta } - preferred for tables. */
  async listPaged(params = {}) {
    return request(`/integration/entities/${entityName}${toQueryString({ ...params })}`);
  },

  async list(sort, limit) {
    const payload = await request(`/entities/${entityName}${toQueryString({ sort, limit })}`);
    // Legacy endpoints return a bare array; the paged envelope wraps it.
    return Array.isArray(payload) ? payload : payload?.data || [];
  },

  async filter(query = {}, sort, limit) {
    const payload = await request(`/entities/${entityName}/filter${toQueryString({ query, sort, limit })}`);
    return Array.isArray(payload) ? payload : payload?.data || [];
  },

  async get(id) {
    return request(`/entities/${entityName}/${id}`);
  },

  async create(payload) {
    return request(`/entities/${entityName}`, { method: 'POST', body: payload });
  },

  async update(id, patch) {
    return request(`/entities/${entityName}/${id}`, { method: 'PATCH', body: patch });
  },
});

/**
 * Paginated CRUD API: /api/{resource}?page&per_page&q&sort&filter[...]
 * Returns { data, meta } envelopes from Laravel.
 */
const makeCrudApi = (resource) => ({
  async list(params = {}) {
    return request(`/${resource}${toQueryString(params)}`, { base: 'root' });
  },
  async get(id) {
    return request(`/${resource}/${id}`, { base: 'root' });
  },
  async create(payload) {
    return request(`/${resource}`, { method: 'POST', body: payload, base: 'root' });
  },
  async update(id, payload) {
    return request(`/${resource}/${id}`, { method: 'PUT', body: payload, base: 'root' });
  },
  async remove(id) {
    return request(`/${resource}/${id}`, { method: 'DELETE', base: 'root' });
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
      return normalizeUser(user);
    },

    logout: async (_returnTo = '/login') => {
      try {
        await authRequest('/auth/logout', { method: 'POST' });
      } catch {
        // Ignore logout errors and clear local auth anyway.
      }
      writeToken(null);
      if (typeof window !== 'undefined') {
        window.location.href = '/';
      }
    },

    redirectToLogin: (portal = '') => {
      if (typeof window !== 'undefined') {
        window.location.href = portal ? `/login/${portal}` : '/';
      }
    },

    /**
     * Portal-scoped login: POST /api/auth/{driver|officer|admin}/login.
     * A role mismatch returns the generic "Invalid credentials for this
     * portal"; 5 failures lock the identity for 15 minutes.
     */
    loginPortal: async (portal, email, password) => {
      const payload = await authRequest(`/auth/${portal}/login`, {
        method: 'POST',
        body: { email, password },
      });

      if (!payload?.token) {
        throw new Error('The API did not return a token.');
      }

      writeToken(payload.token);
      const normalized = normalizeUser(payload.user);

      return { ok: true, user: normalized, redirect: payload.redirect || `/${portal}` };
    },

    /** Legacy single login - kept for compatibility. */
    loginViaEmailPassword: async (email, password) => {
      const payload = await authRequest('/auth/login', {
        method: 'POST',
        body: { email, password },
      });

      if (!payload?.token) {
        throw new Error('The API did not return a token.');
      }

      writeToken(payload.token);
      const normalized = normalizeUser(payload.user);

      return { ok: true, user: normalized, redirect: payload.redirect || `/${normalized.role}` };
    },

    /** Real self-registration - drivers only (backend enforces this). */
    register: async ({ name, email, password }) => {
      const payload = await authRequest('/auth/register', {
        method: 'POST',
        body: { name, email, password, role: 'driver' },
      });

      if (!payload?.token) {
        throw new Error('The API did not return a token.');
      }

      writeToken(payload.token);

      return { access_token: payload.token, user: normalizeUser(payload.user) };
    },

    setToken: (token) => writeToken(token),
    getToken: () => window.localStorage.getItem(AUTH_TOKEN_KEY),
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

  /** REST CRUD with pagination/search/sort/filters ({data, meta}). */
  crud: {
    users: makeCrudApi('users'),
    drivers: makeCrudApi('drivers'),
    vehicles: makeCrudApi('vehicles'),
    stations: makeCrudApi('stations'),
    assignments: makeCrudApi('assignments'),
    violations: makeCrudApi('violations'),
    appeals: makeCrudApi('appeals'),
    payments: {
      ...makeCrudApi('payments'),
      void: (id, reason) =>
        request(`/payments/${id}/void`, { method: 'POST', body: { reason }, base: 'root' }),
    },
  },

  /** MySQL-backed chart series - never hardcoded, works with ML down. */
  charts: {
    admin: () => request('/charts/admin', { base: 'root' }),
    mlInsights: () => request('/charts/ml-insights', { base: 'root' }),
    driver: () => request('/charts/driver', { base: 'root' }),
    officer: () => request('/charts/officer', { base: 'root' }),
  },

  /** GET /api/system/status - DB name, row counts, ML up/down. */
  system: {
    status: () => request('/system/status', { base: 'root' }),
  },

  payments: {
    initiate: async (violationId, method = 'mpesa', phone = '') => {
      return request(`/violations/${violationId}/pay`, {
        method: 'POST',
        body: { method, phone },
        base: 'root',
      });
    },
    verify: async (paymentId, payload = {}) => {
      return request(`/payments/${paymentId}/verify`, { method: 'POST', body: payload, base: 'root' });
    },
    status: async (paymentId) => {
      return request(`/payments/${paymentId}/status`, { base: 'root' });
    },
  },

  functions: {
    invoke: async (name, payload) => {
      return request(`/functions/${name}`, { method: 'POST', body: payload || {} });
    },
  },
};
