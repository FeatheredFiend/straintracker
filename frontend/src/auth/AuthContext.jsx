import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import { api, SESSION_EXPIRED } from '../api/client.js'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  // undefined = still checking the session, null = signed out
  const [user, setUser] = useState(undefined)

  useEffect(() => {
    api.get('/api/me').then((data) => setUser(data.user)).catch(() => setUser(null))

    const onExpired = () => setUser(null)
    window.addEventListener(SESSION_EXPIRED, onExpired)
    return () => window.removeEventListener(SESSION_EXPIRED, onExpired)
  }, [])

  const login = useCallback(async (email, password) => {
    const data = await api.post('/api/login', { email, password })
    setUser(data.user)
  }, [])

  const logout = useCallback(async () => {
    await api.post('/api/logout').catch(() => {})
    setUser(null)
  }, [])

  const value = useMemo(() => ({ user, login, logout }), [user, login, logout])

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  return useContext(AuthContext)
}
