import { NavLink, Outlet } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext.jsx'
import Icon from './Icon.jsx'
import ThemeToggle from './ThemeToggle.jsx'

export default function Layout() {
  const { user, logout } = useAuth()

  return (
    <div className="shell">
      <header className="topbar">
        <div className="topbar__inner">
          <NavLink to="/" className="brand-mark">
            <span className="brand-mark__logo"><Icon name="leaf" size={20} /></span>
            <span className="brand-mark__text">Strain<span>Tracker</span></span>
          </NavLink>

          <nav className="topnav" aria-label="Main">
            <NavLink to="/" end className="topnav__link">
              <Icon name="leaf" /> <span>Strains</span>
            </NavLink>
            {user.isAdmin && (
              <NavLink to="/admin" className="topnav__link">
                <Icon name="settings" /> <span>Admin</span>
              </NavLink>
            )}
          </nav>

          <div className="topbar__actions">
            <ThemeToggle />
            <span className="avatar" title={user.email}>{user.displayName.slice(0, 1).toUpperCase()}</span>
            <button type="button" className="icon-btn" onClick={logout} aria-label="Sign out" title="Sign out">
              <Icon name="logout" />
            </button>
          </div>
        </div>
      </header>

      <main className="page">
        <Outlet />
      </main>

      {/* Phones get a thumb-reachable tab bar instead of the top nav. */}
      <nav className="tabbar" aria-label="Main">
        <NavLink to="/" end className="tabbar__link">
          <Icon name="leaf" size={22} /> <span>Strains</span>
        </NavLink>
        <NavLink to="/strains/new" className="tabbar__link tabbar__link--primary" aria-label="Add strain">
          <Icon name="plus" size={24} />
        </NavLink>
        {user.isAdmin ? (
          <NavLink to="/admin" className="tabbar__link">
            <Icon name="settings" size={22} /> <span>Admin</span>
          </NavLink>
        ) : (
          <button type="button" className="tabbar__link" onClick={logout}>
            <Icon name="logout" size={22} /> <span>Sign out</span>
          </button>
        )}
      </nav>
    </div>
  )
}
