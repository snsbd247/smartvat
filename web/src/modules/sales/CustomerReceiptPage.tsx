import { type FormEvent, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Banner, Button, Field, PageHeader, apiError, inputClass } from '../../shared/ui/primitives'
import { Accounts, Customers, Sales } from '../../shared/lib/resources'

export function CustomerReceiptPage() {
  const qc = useQueryClient()
  const { data: customers } = useQuery({ queryKey: ['customers'], queryFn: Customers.list })
  const { data: accounts } = useQuery({ queryKey: ['accounts'], queryFn: Accounts.list })
  const assets = (accounts ?? []).filter((a) => a.type === 'asset')

  const [customer, setCustomer] = useState('')
  const [amount, setAmount] = useState('')
  const [account, setAccount] = useState('')
  const [msg, setMsg] = useState<{ kind: 'error' | 'success'; text: string } | null>(null)

  const m = useMutation({
    mutationFn: () => Sales.receipt({ customer_id: Number(customer), amount: Number(amount), received_in_account_id: Number(account) }),
    onSuccess: (r: unknown) => {
      setMsg({ kind: 'success', text: `Receipt ${(r as { receipt_no?: string }).receipt_no ?? ''} posted — Dr Cash·Bank / Cr AR.` })
      setAmount('')
      qc.invalidateQueries({ queryKey: ['dashboard'] })
    },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })

  return (
    <div>
      <PageHeader title="Customer Receipt" subtitle="Receive from a customer — Dr Cash·Bank / Cr Accounts Receivable" />
      {msg && <Banner kind={msg.kind}>{msg.text}</Banner>}
      <form onSubmit={(e: FormEvent) => { e.preventDefault(); setMsg(null); m.mutate() }} className="grid max-w-lg gap-4 rounded-xl border border-slate-200 bg-white p-5">
        <Field label="Customer">
          <select className={inputClass} value={customer} onChange={(e) => setCustomer(e.target.value)} required>
            <option value="">Select customer…</option>
            {customers?.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </select>
        </Field>
        <Field label="Amount">
          <input className={inputClass} type="number" step="any" value={amount} onChange={(e) => setAmount(e.target.value)} required />
        </Field>
        <Field label="Received in (Cash/Bank)">
          <select className={inputClass} value={account} onChange={(e) => setAccount(e.target.value)} required>
            <option value="">Select account…</option>
            {assets.map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>)}
          </select>
        </Field>
        <div className="flex justify-end"><Button type="submit" disabled={m.isPending}>{m.isPending ? 'Posting…' : 'Post Receipt'}</Button></div>
      </form>
    </div>
  )
}
