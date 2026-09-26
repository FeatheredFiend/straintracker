import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api } from '../api/client.js'
import Icon from '../components/Icon.jsx'
import { ConfirmModal } from '../components/Modal.jsx'
import { formatPrice, RatingPill, TerpeneChip, ThcMeter } from '../components/Pills.jsx'
import { useToast } from '../components/Toast.jsx'
import { useApi } from '../hooks/useApi.js'
import { RATERS } from '../raters.js'

const dateFormat = new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })

export default function StrainDetailPage() {
  const { id } = useParams()
  const { data: strain, error, loading } = useApi(`/api/strains/${id}`)
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
