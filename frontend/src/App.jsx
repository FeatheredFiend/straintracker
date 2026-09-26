import { Navigate, Route, Routes } from 'react-router-dom'
import { useAuth } from './auth/AuthContext.jsx'
import Layout from './components/Layout.jsx'
import AdminLayout from './pages/admin/AdminLayout.jsx'
import ImportAdmin from './pages/admin/ImportAdmin.jsx'
import LookupAdmin from './pages/admin/LookupAdmin.jsx'
import UsersAdmin from './pages/admin/UsersAdmin.jsx'
import LoginPage from './pages/LoginPage.jsx'
import StrainDetailPage from './pages/StrainDetailPage.jsx'
import StrainFormPage from './pages/StrainFormPage.jsx'
import StrainsPage from './pages/StrainsPage.jsx'

export default function App() {
  const { user } = useAuth()

  if (user === undefined) {
    return (
      <div className="splash">
        <span className="spinner" aria-label="Loading" />
      </div>
    )
  }
  if (user === null) return <LoginPage />

  return (
    <Routes>
      <Route element={<Layout />}>
        <Route index element={<StrainsPage />} />
        <Route path="strains/new" element={<StrainFormPage key="new" />} />
        <Route path="strains/:id" element={<StrainDetailPage />} />
        <Route path="strains/:id/edit" element={<StrainFormPage />} />
        {user.isAdmin && (
          <Route path="admin" element={<AdminLayout />}>
            <Route index element={<Navigate to="brands" replace />} />
            <Route path="brands" element={<LookupAdmin key="brands" lookup="brands" />} />
            <Route path="terpenes" element={<LookupAdmin key="terpenes" lookup="terpenes" />} />
            <Route path="types" element={<LookupAdmin key="types" lookup="types" />} />
            <Route path="ratings" element={<LookupAdmin key="ratings" lookup="ratings" />} />
            <Route path="users" element={<UsersAdmin />} />
            <Route path="import" element={<ImportAdmin />} />
          </Route>
        )}
        <Route path="*" element={<Navigate to="/" replace />} />
      </Route>
    </Routes>
  )
}
