import { useMutation } from '@tanstack/react-query'
import { useState } from 'react'
import { Profile } from '../../shared/lib/resources'
import { Banner, Button, Field, PageHeader, apiError, inputClass } from '../../shared/ui/primitives'

export function ChangePasswordPage() {
  const [current, setCurrent] = useState('')
  const [next, setNext] = useState('')
  const [confirm, setConfirm] = useState('')
  const [msg, setMsg] = useState<string | null>(null)
  const [err, setErr] = useState<string | null>(null)

  const change = useMutation({
    mutationFn: () =>
      Profile.changePassword({ current_password: current, password: next, password_confirmation: confirm }),
    onSuccess: () => {
      setErr(null)
      setMsg('Password updated. Other sessions have been signed out.')
      setCurrent(''); setNext(''); setConfirm('')
    },
    onError: (e) => { setMsg(null); setErr(apiError(e)) },
  })

  const mismatch = next.length > 0 && confirm.length > 0 && next !== confirm

  return (
    <div className="max-w-md">
      <PageHeader title="Change Password" subtitle="Use at least 8 characters. Your current session stays signed in." />
      {msg && <Banner kind="success">{msg}</Banner>}
      {err && <Banner kind="error">{err}</Banner>}

      <form
        className="space-y-4 rounded-xl border border-slate-200 bg-white p-6"
        onSubmit={(e) => { e.preventDefault(); if (!mismatch) change.mutate() }}
      >
        <Field label="Current password">
          <input className={inputClass} type="password" value={current} onChange={(e) => setCurrent(e.target.value)} required autoComplete="current-password" />
        </Field>
        <Field label="New password">
          <input className={inputClass} type="password" value={next} onChange={(e) => setNext(e.target.value)} required minLength={8} autoComplete="new-password" />
        </Field>
        <Field label="Confirm new password">
          <input className={inputClass} type="password" value={confirm} onChange={(e) => setConfirm(e.target.value)} required autoComplete="new-password" />
        </Field>
        {mismatch && <p className="text-sm text-rose-600">Passwords do not match.</p>}
        <Button type="submit" disabled={change.isPending || mismatch}>
          {change.isPending ? 'Updating…' : 'Update Password'}
        </Button>
      </form>
    </div>
  )
}
