import { useEffect, useState } from 'react'
import { consumeAuthNotice } from '../api/client.js'
import LoginForm from '../components/LoginForm.jsx'
import { InlineAlert } from '../components/ui/feedback.jsx'

export default function LoginPage() {
  const [notice, setNotice] = useState(null)

  useEffect(() => {
    const msg = consumeAuthNotice()
    if (msg) setNotice(msg)
  }, [])

  return (
    <div className="login-page">
      {notice && (
        <InlineAlert kind="error">{notice}</InlineAlert>
      )}
      <LoginForm />
    </div>
  )
}
