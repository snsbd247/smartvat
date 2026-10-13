import { type FormEvent, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Banner, Button, Field, PageHeader, apiError, inputClass } from '../../shared/ui/primitives'
import { Accounts, FundTransfers } from '../../shared/lib/resources'

export function FundTransferPage() {
  const qc = useQueryClient()
  const { data: accounts } = useQuery({ queryKey: ['accounts'], queryFn: Accounts.list })
  const assets = (accounts ?? []).filter((a) => a.type === 'asset')
  const [f, setF] = useState<Record<string, string>>({})
  const [msg, setMsg] = useState<{ kind: 'error' | 'success'; text: string } | null>(null)

  const m = useMutation({
    mutationFn: () => FundTransfers.create({ from_account_id: Number(f.from), to_account_id: Number(f.to), amount: Number(f.amount), narration: f.narration }),
    onSuccess: () => { setMsg({ kind: 'success', text: 'Fund transfer posted (Dr destination / Cr source).' }); setF({}); qc.invalidateQueries({ queryKey: ['dashboard'] }) },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })
  const set = (k: string, v: string) => setF((s) => ({ ...s, [k]: v }))

  return (
    <div>
      <PageHeader title="Fund Transfer" subtitle="Move money between cash/bank accounts" />
      {msg && <Banner kind={msg.kind}>{msg.text}</Banner>}
      <form onSubmit={(e: FormEvent) => { e.preventDefault(); setMsg(null); m.mutate() }} className="grid max-w-lg gap-4 rounded-xl border border-slate-200 bg-white p-5">
        <Field label="From Account">
          <select className={inputClass} value={f.from ?? ''} onChange={(e) => set('from', e.target.value)} required>
            <option value="">Select…</option>
            {assets.map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>)}
          </select>
        </Field>
        <Field label="To Account">
          <select className={inputClass} value={f.to ?? ''} onChange={(e) => set('to', e.target.value)} required>
            <option value="">Select…</option>
            {assets.map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>)}
          </select>
        </Field>
        <Field label="Amount"><input className={inputClass} type="number" step="any" value={f.amount ?? ''} onChange={(e) => set('amount', e.target.value)} required /></Field>
        <Field label="Narration"><input className={inputClass} value={f.narration ?? ''} onChange={(e) => set('narration', e.target.value)} /></Field>
        <div className="flex justify-end"><Button type="submit" disabled={m.isPending}>{m.isPending ? 'Posting…' : 'Transfer'}</Button></div>
      </form>
    </div>
  )
}
