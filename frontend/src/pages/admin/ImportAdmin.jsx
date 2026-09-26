import { useState } from 'react'
import { api } from '../../api/client.js'
import Icon from '../../components/Icon.jsx'
import Pagination, { paginate } from '../../components/Pagination.jsx'
import { useToast } from '../../components/Toast.jsx'

const WARNINGS_PER_PAGE = 10

export default function ImportAdmin() {
  const [file, setFile] = useState(null)
  const [report, setReport] = useState(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)
  const [page, setPage] = useState(1)
  const toast = useToast()
  const warnings = paginate(report?.warnings ?? [], page, WARNINGS_PER_PAGE)

  const run = async (dryRun) => {
    const body = new FormData()
    body.append('file', file)
    body.append('dryRun', dryRun ? '1' : '0')
    setBusy(true)
    setError(null)
    try {
      const result = await api.post('/api/admin/import', body)
      setReport(result)
      setPage(1)
      if (!dryRun) toast(`Imported: ${result.created} added, ${result.updated} updated`)
    } catch (err) {
      setError(err.message)
      setReport(null)
    }
    setBusy(false)
  }

  return (
    <section className="panel">
      <header className="panel__header">
        <p className="muted">
          Load strains from a spreadsheet laid out like the original (Brand, Strain, Type, THC %, Terpenes, Genetics, Rating, Price).
          Rows matching an existing brand + strain name are updated, not duplicated. A rating like “Terrible for A Nice for M” is split
          into Anarlia’s and Martyn’s ratings; a single rating applies to both.
        </p>
      </header>

      <label className={`dropzone ${file ? 'has-file' : ''}`}>
        <input
          type="file"
          accept=".xlsx,.csv"
          onChange={(e) => { setFile(e.target.files[0] ?? null); setReport(null); setError(null) }}
        />
        <Icon name="upload" size={28} />
        <strong>{file ? file.name : 'Choose an .xlsx or .csv file'}</strong>
        <span className="muted">{file ? `${(file.size / 1024).toFixed(0)} KB · click to change` : 'or drop it here'}</span>
      </label>

      <div className="form-actions form-actions--start">
        <button type="button" className="btn btn--ghost" disabled={!file || busy} onClick={() => run(true)}>
          Preview
        </button>
        <button type="button" className="btn btn--primary" disabled={!file || busy || !report?.dryRun} onClick={() => run(false)} title={report?.dryRun ? '' : 'Preview first'}>
          {busy ? 'Working…' : 'Import'}
        </button>
      </div>

      {error && <div className="alert alert--danger">{error}</div>}

      {report && (
        <div className="report">
          <h3>{report.dryRun ? 'Preview - nothing saved yet' : 'Import complete'}</h3>
          <div className="stats stats--compact">
            <div className="stat"><span className="stat__value">{report.created}</span><span className="stat__label">{report.dryRun ? 'to add' : 'added'}</span></div>
            <div className="stat"><span className="stat__value">{report.updated}</span><span className="stat__label">{report.dryRun ? 'to update' : 'updated'}</span></div>
            <div className="stat"><span className="stat__value">{report.newBrands.length}</span><span className="stat__label">new brands</span></div>
            <div className="stat"><span className="stat__value">{report.skipped}</span><span className="stat__label">skipped</span></div>
          </div>
          {report.warnings.length > 0 && (
            <>
              <h4>Things tidied up or worth checking</h4>
              <ul className="warnings">
                {warnings.items.map((w) => (
                  <li key={`${w.row}-${w.message}`}><span className="warnings__row">Row {w.row}</span> {w.message}</li>
                ))}
              </ul>
              <Pagination paged={warnings} pageSize={WARNINGS_PER_PAGE} onPageChange={setPage} noun="notes" countOnlyWhenPaged />
            </>
          )}
        </div>
      )}
    </section>
  )
}
