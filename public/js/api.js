// Shared fetch wrapper. All writes send the CSRF token from the #boot block.
export const BOOT = JSON.parse(document.getElementById('boot').textContent);

export const url = (path) => `${BOOT.base}/${path}`;

export class ApiError extends Error {
  constructor(status, message, errors = {}) {
    super(message);
    this.status = status;
    this.errors = errors; // field name -> message, from a 422 response
  }
}

async function request(method, endpoint, body) {
  const headers = { Accept: 'application/json' };
  if (method !== 'GET') headers['X-CSRF-Token'] = BOOT.csrf;
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  let res;
  try {
    res = await fetch(endpoint, { method, headers, credentials: 'same-origin', body: body === undefined ? undefined : JSON.stringify(body) });
  } catch {
    throw new ApiError(0, 'Could not reach the server. Check your connection and try again.');
  }
  if (res.status === 401) {
    window.location.href = url('login');
    throw new ApiError(401, 'Please sign in again.');
  }
  let data = {};
  try {
    data = await res.json();
  } catch {
    // Non-JSON error page: fall through to the generic message.
  }
  if (!res.ok) throw new ApiError(res.status, data.error || 'The request failed.', data.errors || {});
  return data;
}

const endpoint = (resource) => url(`api/${resource}.php`);

export const api = {
  list: (resource) => request('GET', endpoint(resource)),
  create: (resource, body) => request('POST', endpoint(resource), body),
  update: (resource, id, body) => request('PUT', `${endpoint(resource)}?id=${encodeURIComponent(id)}`, body),
  remove: (resource, id) => request('DELETE', `${endpoint(resource)}?id=${encodeURIComponent(id)}`),
  report: (params) => request('GET', `${endpoint('reports')}?${new URLSearchParams(params)}`),
  reportCsvUrl: (params) => `${endpoint('reports')}?${new URLSearchParams({ ...params, format: 'csv' })}`,
};
