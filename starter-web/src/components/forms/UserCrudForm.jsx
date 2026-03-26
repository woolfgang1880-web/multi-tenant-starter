import { useEffect, useMemo, useState } from 'react'
import { mapApiError } from '../../utils/apiError.js'
import Button from '../ui/Button.jsx'
import { Field, TextInput } from '../ui/Field.jsx'

function initialByMode(mode, initialValues) {
  const base = {
    usuario: initialValues?.usuario ?? '',
    codigo_cliente: initialValues?.codigo_cliente ?? '',
  }
  if (mode === 'create') {
    return { ...base, password: '' }
  }
  return base
}

function validate(mode, values) {
  const errors = {}
  const usuario = values.usuario?.trim() ?? ''
  if (!usuario) errors.usuario = 'Usuario es requerido.'
  else if (usuario.length < 2) errors.usuario = 'Usuario debe tener al menos 2 caracteres.'

  if (mode === 'create') {
    const password = values.password ?? ''
    if (!password) errors.password = 'Contrasena es requerida.'
    else if (password.length < 8) errors.password = 'Contrasena debe tener al menos 8 caracteres.'
  }

  return errors
}

function validationFieldErrorsFromApi(err) {
  const out = {}
  const api = err?.body?.data?.errors
  if (!api || typeof api !== 'object') return out

  for (const [field, msgs] of Object.entries(api)) {
    if (Array.isArray(msgs) && msgs[0]) out[field] = String(msgs[0])
  }
  return out
}

export default function UserCrudForm({
  mode = 'create',
  initialValues = {},
  onSubmit,
  onSuccess,
  onError,
  submitLabel,
  submittingLabel,
}) {
  const [values, setValues] = useState(() => initialByMode(mode, initialValues))
  const [status, setStatus] = useState('idle')
  const [fieldErrors, setFieldErrors] = useState({})
  const [formError, setFormError] = useState(null)
  const [successMessage, setSuccessMessage] = useState(null)

  useEffect(() => {
    setValues(initialByMode(mode, initialValues))
    setStatus('idle')
    setFieldErrors({})
    setFormError(null)
    setSuccessMessage(null)
  }, [mode, initialValues?.usuario, initialValues?.codigo_cliente])

  const isSubmitting = status === 'submitting'
  const labels = useMemo(() => ({
    submit: submitLabel || (mode === 'create' ? 'Crear usuario' : 'Guardar cambios'),
    submitting: submittingLabel || (mode === 'create' ? 'Creando...' : 'Guardando...'),
  }), [mode, submitLabel, submittingLabel])

  function setField(field, value) {
    setValues((v) => ({ ...v, [field]: value }))
    setFieldErrors((e) => ({ ...e, [field]: undefined }))
    setFormError(null)
    if (status === 'success') setStatus('idle')
  }

  async function handleSubmit(e) {
    e.preventDefault()
    if (isSubmitting) return

    const nextErrors = validate(mode, values)
    if (Object.keys(nextErrors).length > 0) {
      setFieldErrors(nextErrors)
      setFormError(null)
      setStatus('error')
      return
    }

    setStatus('submitting')
    setFieldErrors({})
    setFormError(null)
    setSuccessMessage(null)

    try {
      const payload = {
        usuario: values.usuario.trim(),
        codigo_cliente: values.codigo_cliente?.trim() || null,
      }
      if (mode === 'create') {
        payload.password = values.password
        payload.password_confirmation = values.password
      }

      const result = await onSubmit(payload)
      setStatus('success')
      setSuccessMessage(mode === 'create' ? 'Usuario creado correctamente.' : 'Usuario actualizado.')
      if (mode === 'create') {
        setValues(initialByMode(mode, {}))
      }
      onSuccess?.(result)
    } catch (err) {
      const mappedError = mapApiError(err, 'No se pudo enviar el formulario.')
      const apiFieldErrors = validationFieldErrorsFromApi(err)
      if (Object.keys(apiFieldErrors).length > 0) {
        setFieldErrors(apiFieldErrors)
      }
      setFormError(mappedError)
      onError?.(mappedError, err)
      setStatus('error')
    }
  }

  return (
    <form className="dash-form dash-form--grid" onSubmit={handleSubmit}>
      <Field label="Usuario" error={fieldErrors.usuario}>
        <TextInput
          value={values.usuario}
          onChange={(e) => setField('usuario', e.target.value)}
          required
          minLength={2}
          disabled={isSubmitting}
          placeholder="usuario"
        />
      </Field>

      <Field label="Codigo cliente (opcional)" error={fieldErrors.codigo_cliente}>
        <TextInput
          value={values.codigo_cliente}
          onChange={(e) => setField('codigo_cliente', e.target.value)}
          disabled={isSubmitting}
          placeholder="CLI-001"
        />
      </Field>

      {mode === 'create' && (
        <Field label="Contrasena inicial" error={fieldErrors.password}>
          <TextInput
            type="password"
            minLength={8}
            value={values.password}
            onChange={(e) => setField('password', e.target.value)}
            required
            disabled={isSubmitting}
            placeholder="Minimo 8 caracteres"
          />
        </Field>
      )}

      <div className="dash-form__actions">
        {formError && <p className="dash-field-error" role="alert">{formError}</p>}
        {successMessage && <p className="dash-muted" role="status">{successMessage}</p>}
        <Button type="submit" variant="primary" loading={isSubmitting}>
          {isSubmitting ? labels.submitting : labels.submit}
        </Button>
      </div>
    </form>
  )
}

