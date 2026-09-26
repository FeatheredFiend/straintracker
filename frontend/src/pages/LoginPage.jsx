import { useState } from 'react'
import { useAuth } from '../auth/AuthContext.jsx'
import Icon from '../components/Icon.jsx'

export default function LoginPage() {
  const { login } = useAuth()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)

  const submit = async (e) => {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      await login(email, password)
    } catch (err) {
      setError(err.status === 429 ? 'Too many attempts - try again in a few minutes.' : 'That email and password don’t match.')
      setBusy(false)
    }
  }

  return (
    <div className="login">
      <div className="login__glow" aria-hidden="true" />
      <form className="login__card" onSubmit={submit}>
        <div className="login__logo"><Icon name="leaf" size={28} /></div>
        <h1>Strain Tracker</h1>
        <p className="muted">Sign in to see what you’ve tried.</p>

        {error && <div className="alert alert--danger">{error}</div>}

        <label className="field">
          <span className="field__label">Email</span>
          <input className="input" type="email" autoComplete="username" required value={email} onChange={(e) => setEmail(e.target.value)} autoFocus />
        </label>
        <label className="field">
          <span className="field__label">Password</span>
          <input className="input" type="password" autoComplete="current-password" required value={password} onChange={(e) => setPassword(e.target.value)} />
        </label>

        <button type="submit" className="btn btn--primary btn--block" disabled={busy}>
          {busy ? 'Signing in…' : 'Sign in'}
        </button>
      </form>
    </div>
  )
}
