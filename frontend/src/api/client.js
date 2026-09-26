// Thin fetch wrapper for the same-origin Symfony API. The session cookie
// does the authentication, so there are no tokens to manage here.

export class ApiError extends Error {
  constructor(status, body) {
    super(body?.error || `Request failed (${status})`)
    this.status = status
    this.fields = body?.fields || {}
  }
}

// AuthContext listens for this to drop back to the login screen when the
// session expires mid-use.
export const SESSION_EXPIRED = 'straintracker:session-expired'

async function request(method, url, body) {
  const options = { method, credentials: 'same-origin', headers: { Accept: 'application/json' } }
  if (body instanceof FormData) {
    options.body = body
  } else if (body) {
    options.headers['Content-Type'] = 'application/json'
    options.body = JSON.stringify(body)
  }
  const response = await fetch(url, options)

  if (response.status === 204) return null
  const data = await response.json().catch(() => null)

  if (!response.ok) {
    if (response.status === 401 && url !== '/api/login') {
      window.dispatchEvent(new Event(SESSION_EXPIRED))
    }
    throw new ApiError(response.status, data)
  }
  return data
}

export const api = {
  get: (url) => request('GET', url),
  post: (url, body) => request('POST', url, body),
  put: (url, body) => request('PUT', url, body),
  delete: (url) => request('DELETE', url),
}
