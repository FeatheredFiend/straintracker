import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { api } from '../api/client.js'
import { useAuth } from '../auth/AuthContext.jsx'
import Field from '../components/Field.jsx'
import Icon from '../components/Icon.jsx'
import MultiSelect from '../components/MultiSelect.jsx'
import { useToast } from '../components/Toast.jsx'
import { useApi } from '../hooks/useApi.js'
import { RATERS } from '../raters.js'

const EMPTY = {
  name: '',
  brandId: '',
  typeId: '',
  thcPercent: '',
  price: '',
  genetics: '',
  notes: '',
  terpeneIds: [],
  aRatingId: '',
  mRatingId: '',
}

function toForm(strain) {
  return {
    name: strain.name,
    brandId: String(strain.brand.id),
    typeId: strain.type ? String(strain.type.id) : '',
    thcPercent: strain.thcPercent ?? '',
    price: strain.price ?? '',
    genetics: strain.genetics ?? '',
    notes: strain.notes ?? '',
    terpeneIds: strain.terpenes.map((t) => t.id),
    ...Object.fromEntries(RATERS.map((r) => [r.idKey, strain[r.key] ? String(strain[r.key].id) : ''])),
  }
}

export default function StrainFormPage() {
  const { id } = useParams()
  const isEdit = Boolean(id)
  const lookups = useApi('/api/lookups')
  const { user } = useAuth()
  const [form, setForm] = useState(isEdit ? null : EMPTY)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)
  const navigate = useNavigate()
  const toast = useToast()

  useEffect(() => {
    if (!isEdit) return
    api.get(`/api/strains/${id}`).then((s) => setForm(toForm(s))).catch((err) => toast(err.message, 'danger'))
  }, [id, isEdit, toast])

  const set = (key) => (e) => setForm((f) => ({ ...f, [key]: e?.target ? e.target.value : e }))

  const submit = async (e) => {
    e.preventDefault()
    setSaving(true)
    setErrors({})
    try {
      const saved = isEdit ? await api.put(`/api/strains/${id}`, form) : await api.post('/api/strains', form)
      toast(isEdit ? 'Changes saved' : `${saved.name} added`)
      navigate(`/strains/${saved.id}`, { replace: true })
    } catch (err) {
      setErrors(err.fields || {})
      toast(err.message, 'danger')
      setSaving(false)
    }
  }

  if (!form || !lookups.data) return <div className="form-card skeleton" style={{ height: 520 }} />
  const { brands, types, terpenes, ratings } = lookups.data

  return (
    <>
      <button type="button" className="back-link" onClick={() => navigate(-1)}>
        <Icon name="arrowLeft" /> Back
      </button>

      <form className="form-card" onSubmit={submit} noValidate>
        <h1>{isEdit ? 'Edit strain' : 'Add a strain'}</h1>

        <div className="form-grid">
          <Field label="Strain name" error={errors.name}>
            {(fid) => <input id={fid} className="input" value={form.name} onChange={set('name')} required autoFocus={!isEdit} />}
          </Field>

          <Field
            label="Brand"
            error={errors.brandId}
            hint={user.isAdmin ? 'Missing one? Add it under Admin → Brands.' : 'Missing one? Ask an admin to add it.'}
          >
            {(fid) => (
              <select id={fid} className="select" value={form.brandId} onChange={set('brandId')} required>
                <option value="">Choose a brand…</option>
                {brands.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
              </select>
            )}
          </Field>

          <Field label="Type" error={errors.typeId}>
            {(fid) => (
              <select id={fid} className="select" value={form.typeId} onChange={set('typeId')}>
                <option value="">Not sure</option>
                {types.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
              </select>
            )}
          </Field>

          <div className="form-grid__pair">
            <Field label="THC %" error={errors.thcPercent}>
              {(fid) => <input id={fid} className="input" type="number" inputMode="decimal" min="0" max="100" step="0.1" value={form.thcPercent} onChange={set('thcPercent')} />}
            </Field>
            <Field label="Price (£)" error={errors.price}>
              {(fid) => <input id={fid} className="input" type="number" inputMode="decimal" min="0" step="0.01" value={form.price} onChange={set('price')} />}
            </Field>
          </div>

          <Field label="Terpenes" error={errors.terpeneIds} className="form-grid__full">
            {(fid) => (
              <MultiSelect id={fid} options={terpenes} value={form.terpeneIds} onChange={set('terpeneIds')} placeholder="Search terpenes…" invalid={Boolean(errors.terpeneIds)} />
            )}
          </Field>

          <Field label="Genetics" error={errors.genetics} hint="Parent strains, e.g. Gelato #25 & South Florida OG" className="form-grid__full">
            {(fid) => <input id={fid} className="input" value={form.genetics} onChange={set('genetics')} />}
          </Field>

          {RATERS.map((r) => (
            <fieldset key={r.key} className={`rating-picker ${errors[r.idKey] ? 'has-error' : ''}`}>
              <legend className="field__label">{r.label}</legend>
              <div className="rating-picker__options">
                {ratings.map((rt) => (
                  <label key={rt.id} className="rating-option" style={{ '--pill': rt.colour }}>
                    <input type="radio" name={r.idKey} value={rt.id} checked={form[r.idKey] === String(rt.id)} onChange={set(r.idKey)} />
                    <span>{rt.label}</span>
                  </label>
                ))}
                <label className="rating-option rating-option--none">
                  <input type="radio" name={r.idKey} value="" checked={form[r.idKey] === ''} onChange={set(r.idKey)} />
                  <span>Not rated</span>
                </label>
              </div>
              {errors[r.idKey] && <p className="field__error">{errors[r.idKey]}</p>}
            </fieldset>
          ))}

          <Field label="Notes" error={errors.notes} className="form-grid__full">
            {(fid) => <textarea id={fid} className="input textarea" rows={4} value={form.notes} onChange={set('notes')} placeholder="Taste, effects, batch, where you got it…" />}
          </Field>
        </div>

        <div className="form-actions">
          <button type="button" className="btn btn--ghost" onClick={() => navigate(-1)}>Cancel</button>
          <button type="submit" className="btn btn--primary" disabled={saving}>
            {saving ? 'Saving…' : isEdit ? 'Save changes' : 'Add strain'}
          </button>
        </div>
      </form>
    </>
  )
}
