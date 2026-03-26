import { useEffect, useRef } from 'react'

export default function ConfirmDialog({
  open,
  title,
  message,
  confirmLabel = 'Confirmar',
  cancelLabel = 'Cancelar',
  danger,
  loading,
  onConfirm,
  onClose,
}) {
  const cancelRef = useRef(null)

  useEffect(() => {
    if (!open) return undefined
    const prev = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    cancelRef.current?.focus()
    return () => {
      document.body.style.overflow = prev
    }
  }, [open])

  useEffect(() => {
    if (!open) return undefined
    function onKey(e) {
      if (e.key === 'Escape') onClose()
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [open, onClose])

  if (!open) return null

  return (
    <div className="dash-dialog-root" role="presentation">
      <button
        type="button"
        className="dash-dialog-backdrop"
        aria-label="Cerrar diálogo"
        onClick={onClose}
      />
      <div
        className="dash-dialog"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="dash-dialog-title"
        aria-describedby="dash-dialog-desc"
      >
        <h2 id="dash-dialog-title" className="dash-dialog__title">
          {title}
        </h2>
        <p id="dash-dialog-desc" className="dash-dialog__message">
          {message}
        </p>
        <div className="dash-dialog__actions">
          <button
            ref={cancelRef}
            type="button"
            className="dash-btn dash-btn--ghost"
            onClick={onClose}
            disabled={loading}
          >
            {cancelLabel}
          </button>
          <button
            type="button"
            className={`dash-btn ${danger ? 'dash-btn--danger-solid' : 'dash-btn--primary'}`}
            onClick={onConfirm}
            disabled={loading}
          >
            {loading ? 'Procesando…' : confirmLabel}
          </button>
        </div>
      </div>
    </div>
  )
}
