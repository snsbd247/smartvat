import { type FormEvent, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { KeyRound } from 'lucide-react'
import { Profile } from '../../shared/lib/resources'
import { apiError } from '../../shared/ui/primitives'

export function ResetPasswordPage() {
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const token = params.get('token') ?? ''
  const email = params.get('email') ?? ''

  const [password, setPassword] = useState('')
  const [confirm, setConfirm] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [done, setDone] = useState(false)

  const mismatch = password.length > 0 && confirm.length > 0 && password !== confirm

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    if (mismatch) return
    setError(null); setBusy(true)
    try {
      await Profile.resetPassword({ token, email, password, password_confirmation: confirm })
      setDone(true)
      setTimeout(() => navigate('/login', { replace: true }), 2000)
    } catch (err) {
      setError(apiError(err))
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="grid min-h-screen place-items-center bg-sidebar p-4">
      <div className="w-full max-w-sm rounded-2xl bg-white p-8 shadow-xl">
        <h1 className="mb-1 text-lg font-semibold text-ink">Choose a new password</h1>
        <p className="mb-6 text-sm text-muted">{email ? `For ${email}` : 'Set a new password for your account.'}</p>

        {done ? (
          <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-3 text-sm text-emerald-700">
            Password reset. Redirecting to sign in…
          </div>
        ) : !token || !email ? (
          <div className="rounded-lg border border-rose-200 bg-rose-50 px-3 py-3 text-sm text-rose-700">
            This reset link is invalid or incomplete. Request a new one.
          </div>
        ) : (
          <form onSubmit={onSubmit}>
            {error && <div className="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">{error}</div>}
            <label className="mb-1 block text-sm font-medium text-ink">New password</label>
            <input type="password" autoComplete="new-password" value={password} onChange={(e) => setPassword(e.target.value)} required minLength={8}
              className="mb-4 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
            <label className="mb-1 block text-sm font-medium text-ink">Confirm password</label>
            <input type="password" autoComplete="new-password" value={confirm} onChange={(e) => setConfirm(e.target.value)} required
              className="mb-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
            {mismatch && <p className="mb-3 text-sm text-rose-600">Passwords do not match.</p>}
            <button type="submit" disabled={busy || mismatch}
              className="mt-2 flex w-full items-center justify-center gap-2 rounded-lg bg-brand py-2.5 text-sm font-semibold text-white transition-colors hover:bg-brand-dark disabled:opacity-60">
              <KeyRound size={16} /> {busy ? 'Resetting…' : 'Reset password'}
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
