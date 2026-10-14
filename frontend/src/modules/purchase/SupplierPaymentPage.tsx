import { type FormEvent, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Banner, Button, Field, PageHeader, apiError, inputClass } from '../../shared/ui/primitives'
import { Accounts, PurchaseApi, Suppliers } from '../../shared/lib/resources'

export function SupplierPaymentPage() {
  const qc = useQueryClient()
  const { data: suppliers } = useQuery({ queryKey: ['suppliers'], queryFn: Suppliers.list })
  const { data: accounts } = useQuery({ queryKey: ['accounts'], queryFn: Accounts.list })
  const assets = (accounts ?? []).filter((a) => a.type === 'asset')

  const [supplier, setSupplier] = useState('')
  const [amount, setAmount] = useState('')
  const [account, setAccount] = useState('')
  const [msg, setMsg] = useState<{ kind: 'error' | 'success'; text: string } | null>(null)

  const m = useMutation({
    mutationFn: () => PurchaseApi.payment({ supplier_id: Number(supplier), amount: Number(amount), paid_from_account_id: Number(account) }),
    onSuccess: (r: unknown) => {
      setMsg({ kind: 'success', text: `Payment ${(r as { payment_no?: string }).payment_no ?? ''} posted — Dr AP / Cr ${account}.` })
      setAmount('')
      qc.invalidateQueries({ queryKey: ['dashboard'] })
    },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })

  return (
    <div>
      <PageHeader title="Supplier Payment" subtitle="Pay a supplier — Dr Accounts Payable / Cr Cash·Bank" />
      {msg && <Banner kind={msg.kind}>{msg.text}</Banner>}
      <form onSubmit={(e: FormEvent) => { e.preventDefault(); setMsg(null); m.mutate() }} className="grid max-w-lg gap-4 rounded-xl border border-slate-200 bg-white p-5">
        <Field label="Supplier">
          <select className={inputClass} value={supplier} onChange={(e) => setSupplier(e.target.value)} required>
            <option value="">Select supplier…</option>
            {suppliers?.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
          </select>
        </Field>
        <Field label="Amount">
          <input className={inputClass} type="number" step="any" value={amount} onChange={(e) => setAmount(e.target.value)} required />
        </Field>
        <Field label="Paid from (Cash/Bank)">
          <select className={inputClass} value={account} onChange={(e) => setAccount(e.target.value)} required>
            <option value="">Select account…</option>
            {assets.map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>)}
          </select>
        </Field>
        <div className="flex justify-end"><Button type="submit" disabled={m.isPending}>{m.isPending ? 'Posting…' : 'Post Payment'}</Button></div>
      </form>
    </div>
  )
}
