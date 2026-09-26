import { useMemo, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import Icon from '../components/Icon.jsx'
import MultiSelect from '../components/MultiSelect.jsx'
import { formatPrice, RatingPill, TerpeneChip, ThcMeter } from '../components/Pills.jsx'
import { useApi } from '../hooks/useApi.js'
import { RATERS } from '../raters.js'

const SORTS = {
  brand: { label: 'Brand A–Z', compare: (a, b) => a.brand.name.localeCompare(b.brand.name) || a.name.localeCompare(b.name) },
  name: { label: 'Strain A–Z', compare: (a, b) => a.name.localeCompare(b.name) },
  rating: { label: 'Best rated', compare: (a, b) => combinedScore(b) - combinedScore(a) || a.name.localeCompare(b.name) },
  thc: { label: 'Strongest THC', compare: (a, b) => (b.thcPercent ?? -1) - (a.thcPercent ?? -1) },
  priceAsc: { label: 'Cheapest', compare: (a, b) => (a.price ?? Infinity) - (b.price ?? Infinity) },
  priceDesc: { label: 'Priciest', compare: (a, b) => (b.price ?? -1) - (a.price ?? -1) },
  updated: { label: 'Recently updated', compare: (a, b) => b.updatedAt.localeCompare(a.updatedAt) },
}

function combinedScore(strain) {
  return RATERS.reduce((sum, r) => sum + (strain[r.key]?.score ?? 0), 0)
}

function average(values) {
  const present = values.filter((v) => v != null)
  return present.length ? present.reduce((a, b) => a + b, 0) / present.length : null
}

export default function StrainsPage() {
  const strains = useApi('/api/strains')
  const lookups = useApi('/api/lookups')
  const [params, setParams] = useSearchParams()
  const [filtersOpen, setFiltersOpen] = useState(false)
  const navigate = useNavigate()

  const q = params.get('q') ?? ''
  const brand = params.get('brand') ?? ''
  const type = params.get('type') ?? ''
  const terps = (params.get('terps') ?? '').split(',').filter(Boolean).map(Number)
  const raterFilters = Object.fromEntries(RATERS.map((r) => [r.key, params.get(r.key) ?? '']))
  const sort = SORTS[params.get('sort')] ? params.get('sort') : 'brand'
  const view = params.get('view') === 'table' ? 'table' : 'cards'

  const update = (changes) => {
    const next = new URLSearchParams(params)
    for (const [key, value] of Object.entries(changes)) {
      if (value === '' || value == null || (Array.isArray(value) && !value.length)) next.delete(key)
      else next.set(key, Array.isArray(value) ? value.join(',') : value)
    }
    setParams(next, { replace: true })
  }

  const activeFilterCount = [brand, type, terps.length ? 'x' : '', ...Object.values(raterFilters)].filter(Boolean).length

  // A personal log is small enough to filter on every render.
  const needle = q.trim().toLowerCase()
  const visible = (strains.data ?? [])
    .filter((s) => {
      if (needle) {
        const haystack = [s.name, s.brand.name, s.genetics, s.type?.name, s.notes, ...s.terpenes.map((t) => t.name)]
          .filter(Boolean).join(' ').toLowerCase()
        if (!haystack.includes(needle)) return false
      }
      if (brand && String(s.brand.id) !== brand) return false
      if (type && String(s.type?.id) !== type) return false
      if (terps.length && !terps.every((id) => s.terpenes.some((t) => t.id === id))) return false
      for (const r of RATERS) {
        if (raterFilters[r.key] && String(s[r.key]?.id) !== raterFilters[r.key]) return false
      }
      return true
    })
    .sort(SORTS[sort].compare)

  const stats = useMemo(() => {
    const all = strains.data ?? []
    const topScore = Math.max(0, ...(lookups.data?.ratings ?? []).map((r) => r.score))
    return {
      total: all.length,
      brands: new Set(all.map((s) => s.brand.id)).size,
      thc: average(all.map((s) => s.thcPercent)),
      price: average(all.map((s) => s.price)),
      bothLoved: topScore ? all.filter((s) => RATERS.every((r) => s[r.key]?.score === topScore)).length : 0,
    }
  }, [strains.data, lookups.data])

  if (strains.error || lookups.error) {
    return <div className="alert alert--danger">Couldn’t load strains. {strains.error?.message || lookups.error?.message}</div>
  }

  return (
    <>
      <section className="hero">
        <div>
          <p className="eyebrow">Your log</p>
          <h1>Strains tried</h1>
        </div>
        <Link to="/strains/new" className="btn btn--primary hide-mobile">
          <Icon name="plus" /> Add strain
        </Link>
      </section>

      <section className="stats">
        <Stat label="Strains" value={stats.total} loading={strains.loading} />
        <Stat label="Brands" value={stats.brands} loading={strains.loading} />
        <Stat label="Avg THC" value={stats.thc == null ? '–' : `${stats.thc.toFixed(1)}%`} loading={strains.loading} />
        <Stat label="Avg price" value={formatPrice(stats.price)} loading={strains.loading} />
        <Stat label="Both loved" value={stats.bothLoved} loading={strains.loading} accent />
      </section>

      <section className="toolbar">
        <label className="search">
          <Icon name="search" />
          <input
            type="search"
            placeholder="Search strain, brand, genetics, terpene…"
            value={q}
            onChange={(e) => update({ q: e.target.value })}
            aria-label="Search strains"
          />
        </label>

        <button
          type="button"
          className={`btn btn--ghost filters-toggle ${filtersOpen ? 'is-on' : ''}`}
          onClick={() => setFiltersOpen((o) => !o)}
          aria-expanded={filtersOpen}
        >
          <Icon name="filter" /> Filters
          {activeFilterCount > 0 && <span className="count-badge">{activeFilterCount}</span>}
        </button>

        <select className="select select--compact" value={sort} onChange={(e) => update({ sort: e.target.value === 'brand' ? '' : e.target.value })} aria-label="Sort by">
          {Object.entries(SORTS).map(([key, s]) => <option key={key} value={key}>{s.label}</option>)}
        </select>

        <div className="segmented" role="group" aria-label="View">
          <button type="button" className={view === 'cards' ? 'is-on' : ''} onClick={() => update({ view: '' })} aria-label="Card view" title="Cards">
            <Icon name="grid" />
          </button>
          <button type="button" className={view === 'table' ? 'is-on' : ''} onClick={() => update({ view: 'table' })} aria-label="Table view" title="Table">
            <Icon name="list" />
          </button>
        </div>
      </section>

      {filtersOpen && lookups.data && (
        <section className="filters">
          <label className="field">
            <span className="field__label">Brand</span>
            <select className="select" value={brand} onChange={(e) => update({ brand: e.target.value })}>
              <option value="">All brands</option>
              {lookups.data.brands.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
            </select>
          </label>
          <label className="field">
            <span className="field__label">Type</span>
            <select className="select" value={type} onChange={(e) => update({ type: e.target.value })}>
              <option value="">All types</option>
              {lookups.data.types.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
            </select>
          </label>
          {RATERS.map((r) => (
            <label className="field" key={r.key}>
              <span className="field__label">{r.label}</span>
              <select className="select" value={raterFilters[r.key]} onChange={(e) => update({ [r.key]: e.target.value })}>
                <option value="">Any</option>
                {lookups.data.ratings.map((rt) => <option key={rt.id} value={rt.id}>{rt.label}</option>)}
              </select>
            </label>
          ))}
          <div className="field filters__terps">
            <span className="field__label">Has all these terpenes</span>
            <MultiSelect options={lookups.data.terpenes} value={terps} onChange={(ids) => update({ terps: ids })} placeholder="Any terpenes" />
          </div>
          {activeFilterCount > 0 && (
            <button type="button" className="btn btn--link" onClick={() => update({ brand: '', type: '', terps: [], ...Object.fromEntries(RATERS.map((r) => [r.key, ''])) })}>
              Clear filters
            </button>
          )}
        </section>
      )}

      {strains.loading ? (
        <div className="card-grid">
          {Array.from({ length: 6 }, (_, i) => <div key={i} className="strain-card skeleton" />)}
        </div>
      ) : visible.length === 0 ? (
        <div className="empty">
          <Icon name="leaf" size={36} />
          <h2>{strains.data.length ? 'Nothing matches' : 'No strains yet'}</h2>
          <p className="muted">
            {strains.data.length ? 'Try a different search or clear the filters.' : 'Add the first one, or import your spreadsheet from Admin → Import.'}
          </p>
        </div>
      ) : view === 'cards' ? (
        <div className="card-grid">
          {visible.map((s) => <StrainCard key={s.id} strain={s} />)}
        </div>
      ) : (
        <StrainTable strains={visible} sort={sort} onSort={(key) => update({ sort: key === 'brand' ? '' : key })} onOpen={(id) => navigate(`/strains/${id}`)} />
      )}

      {!strains.loading && visible.length > 0 && (
        <p className="result-count muted">Showing {visible.length} of {strains.data.length}</p>
      )}
    </>
  )
}

function Stat({ label, value, loading, accent }) {
  return (
    <div className={`stat ${accent ? 'stat--accent' : ''}`}>
      <span className="stat__value">{loading ? <span className="skeleton skeleton--text" /> : value}</span>
      <span className="stat__label">{label}</span>
    </div>
  )
}

function StrainCard({ strain }) {
  return (
    <Link to={`/strains/${strain.id}`} className="strain-card">
      <div className="strain-card__head">
        <div>
          <p className="strain-card__brand">{strain.brand.name}</p>
          <h3 className="strain-card__name">{strain.name}</h3>
        </div>
        <span className="strain-card__price">{formatPrice(strain.price)}</span>
      </div>

      <div className="strain-card__meta">
        {strain.type && <span className="type-pill">{strain.type.name}</span>}
        <ThcMeter value={strain.thcPercent} />
      </div>

      {strain.terpenes.length > 0 && (
        <div className="chip-row">
          {strain.terpenes.map((t) => <TerpeneChip key={t.id} terpene={t} />)}
        </div>
      )}

      {strain.genetics && <p className="strain-card__genetics" title={strain.genetics}>{strain.genetics}</p>}

      <div className="strain-card__ratings">
        {RATERS.map((r) => <RatingPill key={r.key} rater={r.name} rating={strain[r.key]} />)}
      </div>
    </Link>
  )
}

const COLUMNS = [
  { label: 'Brand', sort: 'brand' },
  { label: 'Strain', sort: 'name' },
  { label: 'Type' },
  { label: 'THC', sort: 'thc' },
  { label: 'Terpenes' },
  { label: 'Genetics' },
  ...RATERS.map((r) => ({ label: r.label, sort: 'rating' })),
  { label: 'Price', sort: 'priceAsc' },
]

function StrainTable({ strains, sort, onSort, onOpen }) {
  return (
    <div className="table-wrap">
      <table className="table">
        <thead>
          <tr>
            {COLUMNS.map((c) => (
              <th key={c.label} aria-sort={c.sort === sort ? 'descending' : undefined}>
                {c.sort ? (
                  <button type="button" className={`th-sort ${c.sort === sort ? 'is-on' : ''}`} onClick={() => onSort(c.sort)}>
                    {c.label}
                  </button>
                ) : c.label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {strains.map((s) => (
            <tr key={s.id} onClick={() => onOpen(s.id)} className="is-clickable">
              <td className="muted">{s.brand.name}</td>
              <td><Link to={`/strains/${s.id}`} className="table__name" onClick={(e) => e.stopPropagation()}>{s.name}</Link></td>
              <td>{s.type?.name ?? '–'}</td>
              <td className="num">{s.thcPercent != null ? `${s.thcPercent}%` : '–'}</td>
              <td>
                <div className="chip-row chip-row--tight">
                  {s.terpenes.map((t) => <TerpeneChip key={t.id} terpene={t} />)}
                </div>
              </td>
              <td className="table__genetics">{s.genetics ?? '–'}</td>
              {RATERS.map((r) => <td key={r.key}><RatingPill rating={s[r.key]} /></td>)}
              <td className="num">{formatPrice(s.price)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
