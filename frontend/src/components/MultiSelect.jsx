import { useEffect, useId, useMemo, useRef, useState } from 'react'
import Icon from './Icon.jsx'
import { TerpeneChip } from './Pills.jsx'

/**
 * Searchable multi-select "box": chosen options show as removable chips,
 * typing filters the list, and it's fully keyboard-driven (arrows, Enter
 * to toggle, Backspace to remove the last chip, Escape to close).
 *
 * options: [{ id, name, colour?, aroma? }], value: array of ids
 */
export default function MultiSelect({ options, value, onChange, placeholder = 'Choose…', invalid, id }) {
  const [open, setOpen] = useState(false)
  const [query, setQuery] = useState('')
  const [active, setActive] = useState(0)
  const rootRef = useRef(null)
  const inputRef = useRef(null)
  const listId = useId()

  const selected = useMemo(() => options.filter((o) => value.includes(o.id)), [options, value])
  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase()
    return q ? options.filter((o) => o.name.toLowerCase().includes(q)) : options
  }, [options, query])

  useEffect(() => {
    if (!open) return
    const onDocClick = (e) => {
      if (!rootRef.current?.contains(e.target)) setOpen(false)
    }
    document.addEventListener('mousedown', onDocClick)
    return () => document.removeEventListener('mousedown', onDocClick)
  }, [open])

  const toggle = (optionId) => {
    onChange(value.includes(optionId) ? value.filter((v) => v !== optionId) : [...value, optionId])
    setQuery('')
    inputRef.current?.focus()
  }

  const onKeyDown = (e) => {
    if (e.key === 'ArrowDown') {
      e.preventDefault()
      setOpen(true)
      setActive((i) => Math.min(i + 1, filtered.length - 1))
    } else if (e.key === 'ArrowUp') {
      e.preventDefault()
      setActive((i) => Math.max(i - 1, 0))
    } else if (e.key === 'Enter') {
      if (open && filtered[active]) {
        e.preventDefault()
        toggle(filtered[active].id)
      }
    } else if (e.key === 'Escape') {
      setOpen(false)
    } else if (e.key === 'Backspace' && query === '' && value.length) {
      onChange(value.slice(0, -1))
    }
  }

  return (
    <div className={`multiselect ${open ? 'is-open' : ''} ${invalid ? 'is-invalid' : ''}`} ref={rootRef}>
      <div className="multiselect__box" onClick={() => { setOpen(true); inputRef.current?.focus() }}>
        {selected.map((o) => (
          <TerpeneChip key={o.id} terpene={o} onRemove={(e) => { e?.stopPropagation?.(); toggle(o.id) }} />
        ))}
        <input
          id={id}
          ref={inputRef}
          className="multiselect__input"
          value={query}
          placeholder={selected.length ? '' : placeholder}
          onChange={(e) => { setQuery(e.target.value); setOpen(true); setActive(0) }}
          onFocus={() => setOpen(true)}
          onKeyDown={onKeyDown}
          role="combobox"
          aria-expanded={open}
          aria-controls={listId}
          aria-autocomplete="list"
        />
        <Icon name="chevronDown" className="multiselect__chevron" />
      </div>

      {open && (
        <ul className="multiselect__list" id={listId} role="listbox" aria-multiselectable="true">
          {filtered.length === 0 && <li className="multiselect__empty">No matches</li>}
          {filtered.map((o, i) => {
            const isSelected = value.includes(o.id)
            return (
              <li
                key={o.id}
                role="option"
                aria-selected={isSelected}
                className={`multiselect__option ${i === active ? 'is-active' : ''} ${isSelected ? 'is-selected' : ''}`}
                onMouseEnter={() => setActive(i)}
                onMouseDown={(e) => { e.preventDefault(); toggle(o.id) }}
              >
                <span className="multiselect__check">{isSelected && <Icon name="check" size={14} />}</span>
                <span className="multiselect__dot" style={{ background: o.colour || 'var(--accent)' }} />
                <span className="multiselect__name">{o.name}</span>
                {o.aroma && <span className="multiselect__hint">{o.aroma}</span>}
              </li>
            )
          })}
        </ul>
      )}
    </div>
  )
}
