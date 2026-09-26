// Batch dates travel as plain "YYYY-MM-DD" strings (what <input type="date">
// uses). Parse them as local dates so they never shift by a timezone.

const longDate = new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })

export function formatDate(ymd) {
  if (!ymd) return '–'
  const [y, m, d] = ymd.split('-').map(Number)
  return longDate.format(new Date(y, m - 1, d))
}

export function todayIso() {
  const now = new Date()
  const pad = (n) => String(n).padStart(2, '0')
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`
}
