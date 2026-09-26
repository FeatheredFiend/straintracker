import Icon from './Icon.jsx'

/**
 * Client-side paging for an already-filtered list. Clamps `page` so a
 * stale page number (e.g. after deleting the last row) still lands on a
 * real page.
 */
export function paginate(items, page, pageSize) {
  const total = items.length
  const totalPages = Math.max(1, Math.ceil(total / pageSize))
  const current = Math.min(Math.max(1, page), totalPages)
  const start = (current - 1) * pageSize
  return {
    items: items.slice(start, start + pageSize),
    page: current,
    totalPages,
    total,
    from: total ? start + 1 : 0,
    to: Math.min(start + pageSize, total),
  }
}

/** 1 … 4 5 6 7 8 … 12 - first, last, and a window of five around the current page. */
function pageNumbers(page, totalPages) {
  const start = Math.max(1, Math.min(page - 2, totalPages - 4))
  const window = Array.from({ length: Math.min(5, totalPages) }, (_, i) => start + i)
  const pages = [...new Set([1, ...window, totalPages])].sort((a, b) => a - b)
  return pages.flatMap((p, i) => (i > 0 && p - pages[i - 1] > 1 ? ['gap-' + p, p] : [p]))
}

/**
 * The "1–24 of 78" count always shows (it doubles as the result count for
 * searches) unless `countOnlyWhenPaged` - for lists that already show
 * their size elsewhere.
 */
export default function Pagination({ paged, pageSize, onPageChange, onPageSizeChange, pageSizes, noun = 'items', countOnlyWhenPaged = false }) {
  const { page, totalPages, total, from, to } = paged
  const showSizes = onPageSizeChange && pageSizes && total > pageSizes[0]
  if (total === 0 || (countOnlyWhenPaged && totalPages <= 1 && !showSizes)) return null

  return (
    <nav className="pagination" aria-label={`${noun} pages`}>
      <p className="pagination__summary">
        {from}–{to} of {total} {noun}
      </p>

      {totalPages > 1 && (
        <div className="pagination__pages">
          <button type="button" className="pagination__btn" onClick={() => onPageChange(page - 1)} disabled={page === 1} aria-label="Previous page">
            <Icon name="chevronDown" size={16} className="pagination__prev" />
          </button>
          {pageNumbers(page, totalPages).map((p) =>
            typeof p === 'string' ? (
              <span key={p} className="pagination__gap">…</span>
            ) : (
              <button
                key={p}
                type="button"
                className={`pagination__btn pagination__num ${p === page ? 'is-current' : ''}`}
                onClick={() => onPageChange(p)}
                aria-current={p === page ? 'page' : undefined}
                aria-label={`Page ${p}`}
              >
                {p}
              </button>
            ),
          )}
          <span className="pagination__compact">Page {page} of {totalPages}</span>
          <button type="button" className="pagination__btn" onClick={() => onPageChange(page + 1)} disabled={page === totalPages} aria-label="Next page">
            <Icon name="chevronDown" size={16} className="pagination__next" />
          </button>
        </div>
      )}

      {showSizes && (
        <label className="pagination__size">
          <span>Per page</span>
          <select className="select select--compact" value={pageSize} onChange={(e) => onPageSizeChange(Number(e.target.value))}>
            {pageSizes.map((n) => <option key={n} value={n}>{n}</option>)}
          </select>
        </label>
      )}
    </nav>
  )
}
