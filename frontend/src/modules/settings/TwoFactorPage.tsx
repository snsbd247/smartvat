import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { ShieldCheck, ShieldOff } from 'lucide-react'
import { Profile } from '../../shared/lib/resources'
import { Banner, Button, Field, PageHeader, apiError, inputClass } from '../../shared/ui/primitives'

export function TwoFactorPage() {
  const qc = useQueryClient()
  const { data: me } = useQuery({ queryKey: ['me'], queryFn: Profile.me })
  const [setup, setSetup] = useState<{ secret: string; otpauth_uri: string } | null>(null)
  const [code, setCode] = useState('')
  const [password, setPassword] = useState('')
  const [msg, setMsg] = useState<{ kind: 'error' | 'success'; text: string } | null>(null)

  const enabled = me?.two_factor_enabled

  const begin = useMutation({
    mutationFn: () => Profile.enable2fa(),
    onSuccess: (d) => { setSetup(d); setMsg(null) },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })
  const confirm = useMutation({
    mutationFn: () => Profile.confirm2fa(code),
    onSuccess: () => { setMsg({ kind: 'success', text: 'Two-factor authentication is now enabled.' }); setSetup(null); setCode(''); qc.invalidateQueries({ queryKey: ['me'] }) },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })
  const disable = useMutation({
    mutationFn: () => Profile.disable2fa(password),
    onSuccess: () => { setMsg({ kind: 'success', text: 'Two-factor authentication disabled.' }); setPassword(''); qc.invalidateQueries({ queryKey: ['me'] }) },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })

  return (
    <div className="max-w-xl">
      <PageHeader title="Two-Factor Authentication" subtitle="Add a time-based one-time code (TOTP) from an authenticator app to your sign-in." />
      {msg && <Banner kind={msg.kind}>{msg.text}</Banner>}

      <div className="rounded-xl border border-slate-200 bg-white p-6">
        <div className="mb-4 flex items-center gap-3">
          <span className={`grid h-10 w-10 place-items-center rounded-lg ${enabled ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-muted'}`}>
            {enabled ? <ShieldCheck size={20} /> : <ShieldOff size={20} />}
          </span>
          <div>
            <div className="font-semibold text-ink">{enabled ? 'Enabled' : 'Not enabled'}</div>
            <div className="text-sm text-muted">{enabled ? 'A code is required at every sign-in.' : 'Your account is protected by password only.'}</div>
          </div>
        </div>

        {!enabled && !setup && (
          <Button onClick={() => begin.mutate()} disabled={begin.isPending}>{begin.isPending ? 'Preparing…' : 'Enable two-factor'}</Button>
        )}

        {!enabled && setup && (
          <div className="space-y-4">
            <div className="rounded-lg bg-workspace p-4 text-sm">
              <p className="mb-2 text-ink">1. In your authenticator app (Google Authenticator, Authy…), add an account using this key:</p>
              <code className="block break-all rounded bg-white px-3 py-2 font-mono text-xs text-ink">{setup.secret}</code>
              <p className="mt-2 text-xs text-muted">Or use this setup URI: <span className="break-all">{setup.otpauth_uri}</span></p>
            </div>
            <Field label="2. Enter the 6-digit code to confirm">
              <input className={inputClass} inputMode="numeric" maxLength={6} value={code} onChange={(e) => setCode(e.target.value)} placeholder="000000" />
            </Field>
            <div className="flex gap-2">
              <Button variant="ghost" onClick={() => setSetup(null)}>Cancel</Button>
              <Button onClick={() => confirm.mutate()} disabled={confirm.isPending || code.length !== 6}>{confirm.isPending ? 'Confirming…' : 'Confirm & enable'}</Button>
            </div>
          </div>
        )}

        {enabled && (
          <div className="space-y-3">
            <Field label="Enter your password to disable two-factor">
              <input className={inputClass} type="password" value={password} onChange={(e) => setPassword(e.target.value)} autoComplete="current-password" />
            </Field>
            <Button variant="danger" onClick={() => disable.mutate()} disabled={disable.isPending || !password}>{disable.isPending ? 'Disabling…' : 'Disable two-factor'}</Button>
          </div>
        )}
      </div>
    </div>
  )
}
