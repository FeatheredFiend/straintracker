import { useState } from 'react'
import { api } from '../../api/client.js'
import Field from '../../components/Field.jsx'
import Icon from '../../components/Icon.jsx'
import Modal, { ConfirmModal } from '../../components/Modal.jsx'
import { RatingPill, TerpeneChip } from '../../components/Pills.jsx'
import { useToast } from '../../components/Toast.jsx'
import { useApi } from '../../hooks/useApi.js'

// One screen serves all four lookup tables; this is what differs.
const CONFIG = {
  brands: {
    singular: 'brand',
    intro: 'Who made it. Strains pick their brand from this list.',
    fields: [{ key: 'name', label: 'Name' }],
    display: (row) => <strong>{row.name}</strong>,
    blank: { name: '' },
  },
  terpenes: {
    singular: 'terpene',
    intro: 'The options in the strain form’s terpene picker. The colour tints the chip.',
    fields: [
      { key: 'name', label: 'Name' },
      { key: 'aroma', label: 'Aroma', hint: 'Shown as a hint in the picker, e.g. “Citrus, lemon”' },
      { key: 'colour', label: 'Colour', type: 'color' },
    ],
    display: (row) => (
      <span className="lookup-row">
        <TerpeneChip terpene={row} />
        {row.aroma && <span className="muted">{row.aroma}</span>}
      </span>
    ),
    blank: { name: '', aroma: '', colour: '#10b981' },
  },
  types: {
    singular: 'type',
    intro: 'Indica ↔ Sativa spectrum. Position sets the order in dropdowns (lowest first).',
    fields: [
      { key: 'name', label: 'Name' },
      { key: 'position', label: 'Position', type: 'number' },
    ],
    display: (row) => <span className="lookup-row"><span className="type-pill">{row.name}</span><span className="muted">position {row.position}</span></span>,
    blank: { name: '', position: 0 },
    sort: (a, b) => a.position - b.position,
  },
  ratings: {
    singular: 'rating',
    intro: 'The scale both Anarlia and Martyn rate on. Higher score = better; it drives “Best rated” sorting.',
    fields: [
      { key: 'label', label: 'Label' },
      { key: 'score', label: 'Score', type: 'number' },
      { key: 'colour', label: 'Colour', type: 'color' },
    ],
    display: (row) => <span className="lookup-row"><RatingPill rating={row} /><span className="muted">score {row.score}</span></span>,
    blank: { label: '', score: 0, colour: '#10b981' },
  },
}

const nameOf = (row) => row.name ?? row.label

export default function LookupAdmin({ lookup }) {
  const config = CONFIG[lookup]
  const { data: rows, error, loading, setData } = useApi(`/api/admin/${lookup}`)
  const [editing, setEditing] = useState(null) // row being edited, or config.blank for new
  const [deleting, setDeleting] = useState(null)
  const [busy, setBusy] = useState(false)
  const [filter, setFilter] = useState('')
  const toast = useToast()

  if (error) return <div className="alert alert--danger">{error.message}</div>

  const visible = (rows ?? [])
    .filter((r) => nameOf(r).toLowerCase().includes(filter.trim().toLowerCase()))
    .sort(config.sort ?? (() => 0))

  const remove = async () => {
    setBusy(true)
    try {
      await api.delete(`/api/admin/${lookup}/${deleting.id}`)
      setData((all) => all.filter((r) => r.id !== deleting.id))
      toast(`${nameOf(deleting)} deleted`)
    } catch (err) {
      toast(err.message, 'danger')
    }
    setBusy(false)
    setDeleting(null)
  }

  return (
    <section className="panel">
      <header className="panel__header">
        <p className="muted">{config.intro}</p>
        <div className="panel__tools">
          <label className="search search--small">
            <Icon name="search" size={16} />
            <input type="search" placeholder={`Find a ${config.singular}…`} value={filter} onChange={(e) => setFilter(e.target.value)} aria-label={`Find a ${config.singular}`} />
          </label>
          <button type="button" className="btn btn--primary" onClick={() => setEditing(config.blank)}>
            <Icon name="plus" /> Add {config.singular}
          </button>
        </div>
      </header>

      {loading ? (
        <div className="skeleton" style={{ height: 240 }} />
      ) : visible.length === 0 ? (
        <p className="empty muted">Nothing here yet.</p>
      ) : (
        <ul className="lookup-list">
          {visible.map((row) => (
            <li key={row.id} className="lookup-list__item">
              <div className="lookup-list__main">{config.display(row)}</div>
              <span className="uses">{row.uses} strain{row.uses === 1 ? '' : 's'}</span>
              <div className="lookup-list__actions">
                <button type="button" className="icon-btn" onClick={() => setEditing(row)} aria-label={`Edit ${nameOf(row)}`}>
                  <Icon name="edit" size={16} />
                </button>
                <button
                  type="button"
                  className="icon-btn icon-btn--danger"
                  onClick={() => setDeleting(row)}
                  disabled={row.uses > 0}
                  title={row.uses > 0 ? 'In use - reassign those strains first' : 'Delete'}
                  aria-label={`Delete ${nameOf(row)}`}
                >
                  <Icon name="trash" size={16} />
                </button>
              </div>
            </li>
          ))}
        </ul>
      )}

      {editing && (
        <LookupForm
          lookup={lookup}
          config={config}
          row={editing}
          onClose={() => setEditing(null)}
          onSaved={(saved) => {
            setData((all) => (editing.id ? all.map((r) => (r.id === saved.id ? saved : r)) : [...all, saved]))
            setEditing(null)
            toast(`${nameOf(saved)} saved`)
          }}
        />
      )}

      {deleting && (
        <ConfirmModal
          title={`Delete ${config.singular}?`}
          message={`“${nameOf(deleting)}” will be removed from the list.`}
          onConfirm={remove}
          onClose={() => setDeleting(null)}
          busy={busy}
        />
      )}
    </section>
  )
}

function LookupForm({ lookup, config, row, onClose, onSaved }) {
  const [values, setValues] = useState(() => Object.fromEntries(config.fields.map((f) => [f.key, row[f.key] ?? config.blank[f.key]])))
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const submit = async (e) => {
    e.preventDefault()
    setSaving(true)
    try {
      const saved = row.id ? await api.put(`/api/admin/${lookup}/${row.id}`, values) : await api.post(`/api/admin/${lookup}`, values)
      onSaved(saved)
    } catch (err) {
      setErrors(Object.keys(err.fields).length ? err.fields : { _: err.message })
      setSaving(false)
    }
  }

  return (
    <Modal
      title={row.id ? `Edit ${config.singular}` : `New ${config.singular}`}
      onClose={onClose}
      footer={
        <>
          <button type="button" className="btn btn--ghost" onClick={onClose}>Cancel</button>
          <button type="submit" form="lookup-form" className="btn btn--primary" disabled={saving}>{saving ? 'Saving…' : 'Save'}</button>
        </>
      }
    >
      <form id="lookup-form" className="stack" onSubmit={submit} noValidate>
        {errors._ && <div className="alert alert--danger">{errors._}</div>}
        {config.fields.map((f, i) => (
          <Field key={f.key} label={f.label} error={errors[f.key]} hint={f.hint}>
            {(fid) =>
              f.type === 'color' ? (
                <div className="colour-input">
                  <input id={fid} type="color" value={values[f.key]} onChange={(e) => setValues((v) => ({ ...v, [f.key]: e.target.value }))} />
                  <code>{values[f.key]}</code>
                </div>
              ) : (
                <input
                  id={fid}
                  className="input"
                  type={f.type ?? 'text'}
                  value={values[f.key] ?? ''}
                  autoFocus={i === 0}
                  onChange={(e) => setValues((v) => ({ ...v, [f.key]: e.target.value }))}
                />
              )
            }
          </Field>
        ))}
      </form>
    </Modal>
  )
}
