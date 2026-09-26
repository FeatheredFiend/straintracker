import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api } from '../api/client.js'
import Icon from '../components/Icon.jsx'
import { ConfirmModal } from '../components/Modal.jsx'
import { formatPrice, RatingPill, TerpeneChip, ThcMeter } from '../components/Pills.jsx'
import { useToast } from '../components/Toast.jsx'
import { formatDate, todayIso } from '../dates.js'
import { useApi } from '../hooks/useApi.js'
import { RATERS } from '../raters.js'

const dateFormat = new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })

export default function StrainDetailPage() {
  const { id } = useParams()
  const { data: strain, error, loading, setData } = useApi(`/api/strains/${id}`)
  const [confirming, setConfirming] = useState(false)
  const [busy, setBusy] = useState(false)
  const navigate = useNavigate()
  const toast = useToast()

  if (error) return <div className="alert alert--danger">{error.status === 404 ? 'That strain doesn’t exist any more.' : error.message}</div>
  if (loading) return <div className="detail skeleton" style={{ height: 420 }} />

  const remove = async () => {
    setBusy(true)
    try {
      await api.delete(`/api/strains/${strain.id}`)
      toast(`${strain.name} deleted`)
      navigate('/')
    } catch (err) {
      toast(err.message, 'danger')
      setBusy(false)
    }
  }

  return (
    <>
      <button type="button" className="back-link" onClick={() => navigate(-1)}>
        <Icon name="arrowLeft" /> Back
      </button>

      <article className="detail">
        <header className="detail__header">
          <div>
            <p className="eyebrow">{strain.brand.name}</p>
            <h1>{strain.name}</h1>
            <div className="detail__tags">
              {strain.type && <span className="type-pill">{strain.type.name}</span>}
            </div>
          </div>
          <div className="detail__actions">
            <Link to={`/strains/${strain.id}/edit`} className="btn btn--primary"><Icon name="edit" /> Edit</Link>
            <button type="button" className="btn btn--ghost btn--danger-text" onClick={() => setConfirming(true)}>
              <Icon name="trash" /> Delete
            </button>
          </div>
        </header>

        <div className="detail__ratings">
          {RATERS.map((r) => (
            <div key={r.key} className="rating-card" style={{ '--pill': strain[r.key]?.colour }}>
              <span className="rating-card__who">{r.label}</span>
              <RatingPill rating={strain[r.key]} />
            </div>
          ))}
        </div>

        <dl className="facts">
          <div><dt>THC</dt><dd><ThcMeter value={strain.thcPercent} /></dd></div>
          <div><dt>Price</dt><dd className="facts__big">{formatPrice(strain.price)}</dd></div>
          <div className="facts__wide"><dt>Genetics</dt><dd>{strain.genetics ?? <span className="muted">Unknown</span>}</dd></div>
          <div className="facts__wide">
            <dt>Terpenes</dt>
            <dd>
              {strain.terpenes.length ? (
                <ul className="terp-list">
                  {strain.terpenes.map((t) => (
                    <li key={t.id}>
                      <TerpeneChip terpene={t} />
                      {t.aroma && <span className="muted">{t.aroma}</span>}
                    </li>
                  ))}
                </ul>
              ) : <span className="muted">Unknown</span>}
            </dd>
          </div>
          {strain.notes && <div className="facts__wide"><dt>Notes</dt><dd className="notes">{strain.notes}</dd></div>}
        </dl>

        <BatchSection strain={strain} onChange={setData} />

        <p className="detail__footer muted">
          Added {dateFormat.format(new Date(strain.createdAt))} · Updated {dateFormat.format(new Date(strain.updatedAt))}
        </p>
      </article>

      {confirming && (
        <ConfirmModal
          title="Delete strain?"
          message={`${strain.name} by ${strain.brand.name} will be removed from the log for good.`}
          onConfirm={remove}
          onClose={() => setConfirming(false)}
          busy={busy}
        />
      )}
    </>
  )
}

/** The strain's batch list, with a quick add so a repeat purchase is one step. */
function BatchSection({ strain, onChange }) {
  const [adding, setAdding] = useState(false)
  const [draft, setDraft] = useState({ batchNumber: '', date: todayIso() })
  const [errors, setErrors] = useState({})
  const [busy, setBusy] = useState(false)
  const [removing, setRemoving] = useState(null)
  const toast = useToast()

  const add = async (e) => {
    e.preventDefault()
    setBusy(true)
    try {
      onChange(await api.post(`/api/strains/${strain.id}/batches`, draft))
      toast(`Batch ${draft.batchNumber.trim()} added`)
      setDraft({ batchNumber: '', date: todayIso() })
      setErrors({})
      setAdding(false)
    } catch (err) {
      setErrors(Object.keys(err.fields).length ? err.fields : { batchNumber: err.message })
    }
    setBusy(false)
  }

  const remove = async () => {
    setBusy(true)
    try {
      onChange(await api.delete(`/api/strains/${strain.id}/batches/${removing.id}`))
      toast(`Batch ${removing.batchNumber} removed`)
    } catch (err) {
      toast(err.message, 'danger')
    }
    setBusy(false)
    setRemoving(null)
  }

  return (
    <section className="batches">
      <header className="batches__header">
        <h2>Batches <span className="count-pill">{strain.batches.length}</span></h2>
        {!adding && (
          <button type="button" className="btn btn--ghost btn--small" onClick={() => setAdding(true)}>
            <Icon name="plus" size={16} /> Add batch
          </button>
        )}
      </header>

      {adding && (
        <form className="batch-add" onSubmit={add} noValidate>
          <label className={`batch-row__field ${errors.date ? 'has-error' : ''}`}>
            <span className="batch-row__label">Date</span>
            <input className="input" type="date" value={draft.date} onChange={(e) => setDraft((d) => ({ ...d, date: e.target.value }))} />
          </label>
          <label className={`batch-row__field batch-row__field--grow ${errors.batchNumber ? 'has-error' : ''}`}>
            <span className="batch-row__label">Batch number</span>
            <input
              className="input"
              value={draft.batchNumber}
              onChange={(e) => setDraft((d) => ({ ...d, batchNumber: e.target.value }))}
              placeholder="e.g. 4C-2609-17"
              autoCapitalize="characters"
              spellCheck={false}
              autoFocus
            />
          </label>
          <div className="batch-add__actions">
            <button type="button" className="btn btn--ghost" onClick={() => { setAdding(false); setErrors({}) }}>Cancel</button>
            <button type="submit" className="btn btn--primary" disabled={busy}>{busy ? 'Adding…' : 'Add'}</button>
          </div>
          {(errors.batchNumber || errors.date) && <p className="field__error batch-row__error">{errors.batchNumber || errors.date}</p>}
        </form>
      )}

      {strain.batches.length === 0 ? (
        !adding && <p className="muted">No batches recorded yet.</p>
      ) : (
        <ul className="batch-list">
          {strain.batches.map((b, i) => (
            <li key={b.id} className="batch-list__item">
              <div className="batch-list__info">
                <span className="batch-list__date">{formatDate(b.date)}</span>
                <code className="batch-list__number">{b.batchNumber}</code>
                {i === 0 && <span className="batch-list__latest">Latest</span>}
              </div>
              <button type="button" className="icon-btn icon-btn--danger" onClick={() => setRemoving(b)} aria-label={`Remove batch ${b.batchNumber}`}>
                <Icon name="trash" size={16} />
              </button>
            </li>
          ))}
        </ul>
      )}

      {removing && (
        <ConfirmModal
          title="Remove batch?"
          message={`Batch ${removing.batchNumber} (${formatDate(removing.date)}) will be removed from ${strain.name}.`}
          confirmLabel="Remove"
          onConfirm={remove}
          onClose={() => setRemoving(null)}
          busy={busy}
        />
      )}
    </section>
  )
}
