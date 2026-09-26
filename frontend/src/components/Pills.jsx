// Small presentational pieces shared by the strain list, detail and admin.

export function RatingPill({ rating, rater }) {
  if (!rating) {
    return (
      <span className="rating-pill rating-pill--empty">
        {rater && <span className="rating-pill__who">{rater}</span>}
        Not rated
      </span>
    )
  }
  return (
    <span className="rating-pill" style={{ '--pill': rating.colour }}>
      {rater && <span className="rating-pill__who">{rater}</span>}
      {rating.label}
    </span>
  )
}

export function TerpeneChip({ terpene, onRemove }) {
  return (
    <span className="terp-chip" style={{ '--terp': terpene.colour }} title={terpene.aroma || undefined}>
      <span className="terp-chip__dot" />
      {terpene.name}
      {onRemove && (
        <button type="button" className="terp-chip__remove" onClick={onRemove} aria-label={`Remove ${terpene.name}`}>
          ×
        </button>
      )}
    </span>
  )
}

export function ThcMeter({ value }) {
  if (value == null) return <span className="muted">–</span>
  // 35% is about the ceiling for flower, so scale the bar to that.
  const width = Math.min(100, (value / 35) * 100)
  return (
    <span className="thc">
      <span className="thc__bar">
        <span className="thc__fill" style={{ width: `${width}%` }} />
      </span>
      <span className="thc__value">{value}%</span>
    </span>
  )
}

const money = new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP' })

export function formatPrice(price) {
  return price == null ? '–' : money.format(price)
}
