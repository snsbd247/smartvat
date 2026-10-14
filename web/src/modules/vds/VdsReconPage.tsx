import { type FormEvent, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Banner, Button, Field, PageHeader, apiError, inputClass } from '../../shared/ui/primitives'
import { Accounts, Vds } from '../../shared/lib/resources'

const money = (v: string | undefined) => '৳ ' + Number(v ?? 0).toLocaleString(undefined, { maximumFractionDigits: 2 })

export function VdsReconPage({ title = 'VDS Reconciliation & Deposit', subtitle = 'Deducted vs deposited on the VDS-payable account' }: { title?: string; subtitle?: string }) {
  const qc = useQueryClient()
  const { data: recon } = useQuery({ queryKey: ['vds-recon'], queryFn: Vds.reconciliation })
  const { data: accounts } = useQuery({ queryKey: ['accounts'], queryFn: Accounts.list })
  const assets = (accounts ?? []).filter((a) => a.type === 'asset')

  const [amount, setAmount] = useState('')
  const [account, setAccount] = useState('')
  const [msg, setMsg] = useState<{ kind: 'error' | 'success'; text: string } | null>(null)

  const m = useMutation({
    mutationFn: () => Vds.deposit({ amount: Number(amount), paid_from_account_id: Number(account) }),
    onSuccess: () => { setMsg({ kind: 'success', text: 'Treasury deposit posted — Dr VDS payable / Cr Cash·Bank.' }); setAmount(''); qc.invalidateQueries({ queryKey: ['vds-recon'] }) },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })

  return (
    <div>
      <PageHeader title={title} subtitle={subtitle} />
      {msg && <Banner kind={msg.kind}>{msg.text}</Banner>}

      <div className="mb-5 grid gap-4 sm:grid-cols-3">
        <Stat label="Deducted" value={money(recon?.deducted)} />
        <Stat label="Deposited" value={money(recon?.deposited)} />
        <Stat label="Payable (outstanding)" value={money(recon?.payable)} accent />
      </div>

      <form onSubmit={(e: FormEvent) => { e.preventDefault(); setMsg(null); m.mutate() }} className="grid max-w-lg gap-4 rounded-xl border border-slate-200 bg-white p-5">
        <div className="text-sm font-medium text-ink">New Treasury Deposit</div>
        <Field label="Amount">
          <input className={inputClass} type="number" step="any" value={amount} onChange={(e) => setAmount(e.target.value)} required />
        </Field>
        <Field label="Paid from (Cash/Bank)">
          <select className={inputClass} value={account} onChange={(e) => setAccount(e.target.value)} required>
            <option value="">Select account…</option>
            {assets.map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>)}
          </select>
        </Field>
        <div className="flex justify-end"><Button type="submit" disabled={m.isPending}>{m.isPending ? 'Posting…' : 'Deposit'}</Button></div>
      </form>
    </div>
  )
}

function Stat({ label, value, accent }: { label: string; value: string; accent?: boolean }) {
  return (
    <div className="rounded-xl border border-slate-200 bg-white p-4">
      <div className="text-sm text-muted">{label}</div>
      <div className={`mt-1 text-xl font-bold ${accent ? 'text-amber-600' : 'text-ink'}`}>{value}</div>
    </div>
  )
}
