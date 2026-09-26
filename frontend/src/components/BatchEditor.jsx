import { todayIso } from '../dates.js'
import Icon from './Icon.jsx'

let nextKey = 0
/** A new, unsaved batch row. `key` is only for React; the API ignores it. */
export function newBatch() {
  return { key: `new-${nextKey++}`, batchNumber: '', date: todayIso() }
}

/**
 * The editable batch list in the strain form. Errors arrive from the API
 * keyed by row position, e.g. "batches.1.date".
 */
export default function BatchEditor({ batches, onChange, errors }) {
  const update = (index, field, value) => onChange(batches.map((b, i) => (i === index ? { ...b, [field]: value } : b)))
  const remove = (index) => onChange(batches.filter((_, i) => i !== index))

  return (
    <fieldset className="batch-editor">
      <legend className="field__label">Batches</legend>

      {batches.length === 0 ? (
        <p className="batch-editor__empty muted">No batches recorded.</p>
      ) : (
        <ul className="batch-editor__rows">
          {batches.map((batch, i) => {
            const numberError = errors[`batches.${i}.batchNumber`]
            const dateError = errors[`batches.${i}.date`]
            return (
              <li key={batch.id ?? batch.key} className="batch-row">
                <label className={`batch-row__field ${dateError ? 'has-error' : ''}`}>
                  <span className="batch-row__label">Date</span>
                  <input className="input" type="date" value={batch.date} onChange={(e) => update(i, 'date', e.target.value)} />
                </label>
                <label className={`batch-row__field batch-row__field--grow ${numberError ? 'has-error' : ''}`}>
                  <span className="batch-row__label">Batch number</span>
                  <input
                    className="input"
                    value={batch.batchNumber}
                    onChange={(e) => update(i, 'batchNumber', e.target.value)}
                    placeholder="e.g. 4C-2609-17"
                    autoCapitalize="characters"
                    spellCheck={false}
                  />
                </label>
                <button type="button" className="icon-btn icon-btn--danger batch-row__remove" onClick={() => remove(i)} aria-label={`Remove batch ${batch.batchNumber || i + 1}`}>
                  <Icon name="trash" size={16} />
                </button>
                {(dateError || numberError) && <p className="field__error batch-row__error">{numberError || dateError}</p>}
              </li>
            )
          })}
        </ul>
      )}

      {errors.batches && <p className="field__error">{errors.batches}</p>}

      <button type="button" className="btn btn--ghost btn--small" onClick={() => onChange([newBatch(), ...batches])}>
        <Icon name="plus" size={16} /> Add batch
      </button>
    </fieldset>
  )
}
