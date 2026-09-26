import { NavLink, Outlet } from 'react-router-dom'
import Icon from '../../components/Icon.jsx'

const TABS = [
  { to: 'brands', label: 'Brands', icon: 'tag' },
  { to: 'terpenes', label: 'Terpenes', icon: 'flask' },
  { to: 'types', label: 'Types', icon: 'shapes' },
  { to: 'ratings', label: 'Ratings', icon: 'star' },
  { to: 'users', label: 'Users', icon: 'users' },
  { to: 'import', label: 'Import', icon: 'upload' },
]

export default function AdminLayout() {
  return (
    <>
      <section className="hero">
        <div>
          <p className="eyebrow">Admin</p>
          <h1>Manage lists</h1>
        </div>
      </section>

      <nav className="tabs" aria-label="Admin sections">
        {TABS.map((t) => (
          <NavLink key={t.to} to={t.to} className="tabs__tab">
            <Icon name={t.icon} size={16} /> {t.label}
          </NavLink>
        ))}
      </nav>

      <Outlet />
    </>
  )
}
