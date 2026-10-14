import { type FormEvent, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Banner, Button, Field, PageHeader, apiError, inputClass } from '../../shared/ui/primitives'
import { Accounts, Customers, Sales } from '../../shared/lib/resources'

export function ExportRealizationPage() {
  const qc = useQueryClient()
  const { data: customers } = useQuery({ queryKey: ['customers'], queryFn: Customers.list })
  const { data: accounts } = useQuery({ queryKey: ['accounts'], queryFn: Accounts.list })
  const assets = (accounts ?? []).filter((a) => a.type === 'asset')

  const [f, setF] = useState<Record<string, string>>({})
  const [msg, setMsg] = useState<{ kind: 'error' | 'success'; text: string } | null>(null)

  const m = useMutation({
    mutationFn: () => Sales.exportReceipt({
      customer_id: Number(f.customer_id),
      fc_amount: Number(f.fc_amount),
      realization_rate: Number(f.realization_rate),
      invoice_rate: Number(f.invoice_rate),
      received_in_account_id: Number(f.account),
    }),
    onSuccess: (r: unknown) => { setMsg({ kind: 'success', text: `Realization ${(r as { receipt_no?: string }).receipt_no ?? ''} posted — exchange gain/loss booked.` }); qc.invalidateQueries({ queryKey: ['dashboard'] }) },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })
  const set = (k: string, v: string) => setF((s) => ({ ...s, [k]: v }))

  return (
    <div>
      <PageHeader title="Export Realization" subtitle="Receive foreign currency; the gain/loss between invoice and realization rate is posted" />
      {msg && <Banner kind={msg.kind}>{msg.text}</Banner>}
      <form onSubmit={(e: FormEvent) => { e.preventDefault(); setMsg(null); m.mutate() }} className="grid max-w-lg gap-4 rounded-xl border border-slate-200 bg-white p-5">
        <Field label="Customer">
          <select className={inputClass} value={f.customer_id ?? ''} onChange={(e) => set('customer_id', e.target.value)} required>
            <option value="">Select…</option>
            {customers?.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </select>
        </Field>
        <div className="grid gap-4 sm:grid-cols-3">
          <Field label="FC Amount"><input className={inputClass} type="number" step="any" value={f.fc_amount ?? ''} onChange={(e) => set('fc_amount', e.target.value)} required /></Field>
          <Field label="Invoice Rate"><input className={inputClass} type="number" step="any" value={f.invoice_rate ?? ''} onChange={(e) => set('invoice_rate', e.target.value)} required /></Field>
          <Field label="Realization Rate"><input className={inputClass} type="number" step="any" value={f.realization_rate ?? ''} onChange={(e) => set('realization_rate', e.target.value)} required /></Field>
        </div>
        <Field label="Received in (Cash/Bank)">
          <select className={inputClass} value={f.account ?? ''} onChange={(e) => set('account', e.target.value)} required>
            <option value="">Select account…</option>
            {assets.map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>)}
          </select>
        </Field>
        <div className="flex justify-end"><Button type="submit" disabled={m.isPending}>{m.isPending ? 'Posting…' : 'Post Realization'}</Button></div>
      </form>
    </div>
  )
}
