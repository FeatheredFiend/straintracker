import { useId } from 'react'

/**
 * Label + control + error message. `children` is a render function that
 * receives the generated id, so the label is always wired to its control.
 */
export default function Field({ label, error, hint, className = '', children }) {
  const id = useId()
  return (
    <div className={`field ${error ? 'has-error' : ''} ${className}`}>
      <label className="field__label" htmlFor={id}>{label}</label>
      {children(id)}
      {error ? <p className="field__error">{error}</p> : hint ? <p className="field__hint">{hint}</p> : null}
    </div>
  )
}
