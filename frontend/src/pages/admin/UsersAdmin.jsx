import { useState } from 'react'
import { api } from '../../api/client.js'
import { useAuth } from '../../auth/AuthContext.jsx'
import Field from '../../components/Field.jsx'
import Icon from '../../components/Icon.jsx'
import Modal, { ConfirmModal } from '../../components/Modal.jsx'
import Pagination, { paginate } from '../../components/Pagination.jsx'
import { useToast } from '../../components/Toast.jsx'
import { useApi } from '../../hooks/useApi.js'

const BLANK = { email: '', displayName: '', password: '', isAdmin: false }
const PAGE_SIZES = [10, 25, 50]

export default function UsersAdmin() {
  const { user: me } = useAuth()
  const { data: users, error, loading, setData } = useApi('/api/admin/users')
  const [editing, setEditing] = useState(null)
  const [deleting, setDeleting] = useState(null)
  const [busy, setBusy] = useState(false)
  const [page, setPage] = useState(1)
  const [pageSize, setPageSize] = useState(PAGE_SIZES[0])
  const toast = useToast()

  if (error) return <div className="alert alert--danger">{error.message}</div>

  const paged = paginate(users ?? [], page, pageSize)

  const remove = async () => {
    setBusy(true)
    try {
      await api.delete(`/api/admin/users/${deleting.id}`)
      setData((all) => all.filter((u) => u.id !== deleting.id))
      toast(`${deleting.displayName} removed`)
    } catch (err) {
      toast(err.message, 'danger')
    }
    setBusy(false)
    setDeleting(null)
  }

  return (
    <section className="panel">
      <header className="panel__header">
        <p className="muted">Everyone who can sign in. Admins also get this section.</p>
        <div className="panel__tools">
          <button type="button" className="btn btn--primary" onClick={() => setEditing(BLANK)}>
            <Icon name="plus" /> Add user
          </button>
        </div>
      </header>

      {loading ? (
        <div className="skeleton" style={{ height: 160 }} />
      ) : (
        <ul className="lookup-list">
          {paged.items.map((u) => (
            <li key={u.id} className="lookup-list__item">
              <div className="lookup-list__main">
                <span className="lookup-row">
                  <span className="avatar avatar--small">{u.displayName.slice(0, 1).toUpperCase()}</span>
                  <span>
                    <strong>{u.displayName}</strong>
                    <span className="muted block">{u.email}</span>
                  </span>
                </span>
              </div>
              <span className={`uses ${u.isAdmin ? 'uses--accent' : ''}`}>{u.isAdmin ? 'Admin' : 'User'}</span>
              <div className="lookup-list__actions">
                <button type="button" className="icon-btn" onClick={() => setEditing({ ...u, password: '' })} aria-label={`Edit ${u.displayName}`}>
                  <Icon name="edit" size={16} />
                </button>
                <button
                  type="button"
                  className="icon-btn icon-btn--danger"
                  onClick={() => setDeleting(u)}
                  disabled={u.id === me.id}
                  title={u.id === me.id ? "You can't delete yourself" : 'Delete'}
                  aria-label={`Delete ${u.displayName}`}
                >
                  <Icon name="trash" size={16} />
                </button>
              </div>
            </li>
          ))}
        </ul>
      )}

      <Pagination
        paged={paged}
        pageSize={pageSize}
        pageSizes={PAGE_SIZES}
        onPageChange={setPage}
        onPageSizeChange={(n) => { setPageSize(n); setPage(1) }}
        noun="users"
      />

      {editing && (
        <UserForm
          user={editing}
          onClose={() => setEditing(null)}
          onSaved={(saved) => {
            setData((all) => (editing.id ? all.map((u) => (u.id === saved.id ? saved : u)) : [...all, saved]))
            setEditing(null)
            toast(`${saved.displayName} saved`)
          }}
        />
      )}

      {deleting && (
        <ConfirmModal
          title="Remove user?"
          message={`${deleting.displayName} (${deleting.email}) won’t be able to sign in any more. Their strain ratings stay.`}
          confirmLabel="Remove"
          onConfirm={remove}
          onClose={() => setDeleting(null)}
          busy={busy}
        />
      )}
    </section>
  )
}

function UserForm({ user, onClose, onSaved }) {
  const [values, setValues] = useState(user)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)
  const set = (key) => (e) => setValues((v) => ({ ...v, [key]: e.target.type === 'checkbox' ? e.target.checked : e.target.value }))

  const submit = async (e) => {
    e.preventDefault()
    setSaving(true)
    try {
      const body = { email: values.email, displayName: values.displayName, password: values.password, isAdmin: values.isAdmin }
      onSaved(user.id ? await api.put(`/api/admin/users/${user.id}`, body) : await api.post('/api/admin/users', body))
    } catch (err) {
      setErrors(Object.keys(err.fields).length ? err.fields : { _: err.message })
      setSaving(false)
    }
  }

  return (
    <Modal
      title={user.id ? 'Edit user' : 'New user'}
      onClose={onClose}
      footer={
        <>
          <button type="button" className="btn btn--ghost" onClick={onClose}>Cancel</button>
          <button type="submit" form="user-form" className="btn btn--primary" disabled={saving}>{saving ? 'Saving…' : 'Save'}</button>
        </>
      }
    >
      <form id="user-form" className="stack" onSubmit={submit} noValidate>
        {errors._ && <div className="alert alert--danger">{errors._}</div>}
        <Field label="Name" error={errors.displayName}>
          {(fid) => <input id={fid} className="input" value={values.displayName} onChange={set('displayName')} autoFocus />}
        </Field>
        <Field label="Email" error={errors.email}>
          {(fid) => <input id={fid} className="input" type="email" autoComplete="off" value={values.email} onChange={set('email')} />}
        </Field>
        <Field label="Password" error={errors.password} hint={user.id ? 'Leave blank to keep the current password.' : 'At least 10 characters.'}>
          {(fid) => <input id={fid} className="input" type="password" autoComplete="new-password" value={values.password} onChange={set('password')} />}
        </Field>
        <label className="check">
          <input type="checkbox" checked={values.isAdmin} onChange={set('isAdmin')} />
          <span>Admin - can manage lists, users and imports</span>
        </label>
        {errors.isAdmin && <p className="field__error">{errors.isAdmin}</p>}
      </form>
    </Modal>
  )
}
