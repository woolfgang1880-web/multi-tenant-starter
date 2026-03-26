import { createContext, useCallback, useContext, useState } from 'react'

const ToastContext = createContext(null)

let toastId = 0

export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([])

  const showToast = useCallback((message, type = 'success') => {
    const id = ++toastId
    setToasts((t) => [...t, { id, message, type }])
    window.setTimeout(() => {
      setToasts((t) => t.filter((x) => x.id !== id))
    }, 4200)
  }, [])

  const dismissToast = useCallback((id) => {
    setToasts((t) => t.filter((x) => x.id !== id))
  }, [])

  return (
    <ToastContext.Provider value={{ showToast, dismissToast }}>
      {children}
      <div className="dash-toast-host" aria-live="polite">
        {toasts.map((t) => (
          <div
            key={t.id}
            className={`dash-toast dash-toast--${t.type}`}
            role="status"
          >
            <span className="dash-toast__msg">{t.message}</span>
            <button
              type="button"
              className="dash-toast__close"
              onClick={() => dismissToast(t.id)}
              aria-label="Cerrar"
            >
              ×
            </button>
          </div>
        ))}
      </div>
    </ToastContext.Provider>
  )
}

export function useToast() {
  const ctx = useContext(ToastContext)
  if (!ctx) {
    return { showToast: () => {}, dismissToast: () => {} }
  }
  return ctx
}
