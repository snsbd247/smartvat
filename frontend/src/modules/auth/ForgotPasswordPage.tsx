import { type FormEvent, useState } from 'react'
import { Link } from 'react-router-dom'
import { Mail } from 'lucide-react'
import { Profile } from '../../shared/lib/resources'
import { apiError } from '../../shared/ui/primitives'

export function ForgotPasswordPage() {
  const [email, setEmail] = useState('')
  const [busy, setBusy] = useState(false)
  const [done, setDone] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null); setBusy(true)
    try {
      await Profile.forgotPassword(email)
      setDone(true)
    } catch (err) {
      setError(apiError(err))
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="grid min-h-screen place-items-center bg-sidebar p-4">
      <div className="w-full max-w-sm rounded-2xl bg-white p-8 shadow-xl">
        <h1 className="mb-1 text-lg font-semibold text-ink">Reset your password</h1>
        <p className="mb-6 text-sm text-muted">Enter your email and we'll send you a reset link.</p>

        {done ? (
          <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-3 text-sm text-emerald-700">
            If that email is registered, a reset link is on its way. Check your inbox.
          </div>
        ) : (
          <form onSubmit={onSubmit}>
            {error && <div className="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">{error}</div>}
            <label className="mb-1 block text-sm font-medium text-ink">Email</label>
            <input
              type="email" autoComplete="email" value={email} onChange={(e) => setEmail(e.target.value)} required
              className="mb-6 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20"
            />
            <button type="submit" disabled={busy}
              className="flex w-full items-center justify-center gap-2 rounded-lg bg-brand py-2.5 text-sm font-semibold text-white transition-colors hover:bg-brand-dark disabled:opacity-60">
              <Mail size={16} /> {busy ? 'Sending…' : 'Send reset link'}
            </button>
          </form>
        )}

        <div className="mt-4 text-center">
          <Link to="/login" className="text-sm font-medium text-brand hover:underline">Back to sign in</Link>
        </div>
      </div>
    </div>
  )
}
