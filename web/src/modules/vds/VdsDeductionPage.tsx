import { type FormEvent, useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { Banner, Button, Field, PageHeader, apiError, inputClass } from '../../shared/ui/primitives'
import { Suppliers, Vds } from '../../shared/lib/resources'

export function VdsDeductionPage() {
  const { data: suppliers } = useQuery({ queryKey: ['suppliers'], queryFn: Suppliers.list })
  const [supplier, setSupplier] = useState('')
  const [category, setCategory] = useState('')
  const [amount, setAmount] = useState('')
  const [msg, setMsg] = useState<{ kind: 'error' | 'success'; text: string } | null>(null)

  const m = useMutation({
    mutationFn: () => Vds.deduct({ supplier_id: Number(supplier), service_category: category, base_amount: Number(amount) }),
    onSuccess: (r: unknown) => {
      const d = r as { deduction_no?: string; vds_amount?: string }
      setMsg({ kind: 'success', text: `Deduction ${d.deduction_no ?? ''} posted — VDS ৳ ${Number(d.vds_amount ?? 0).toLocaleString()} (Dr AP / Cr VDS payable).` })
      setAmount('')
    },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })

  return (
    <div>
      <PageHeader title="VDS Deduction" subtitle="Withhold VDS on a service — requires a verified rule for the category" />
      {msg && <Banner kind={msg.kind}>{msg.text}</Banner>}
      <form onSubmit={(e: FormEvent) => { e.preventDefault(); setMsg(null); m.mutate() }} className="grid max-w-lg gap-4 rounded-xl border border-slate-200 bg-white p-5">
        <Field label="Supplier">
          <select className={inputClass} value={supplier} onChange={(e) => setSupplier(e.target.value)} required>
            <option value="">Select supplier…</option>
            {suppliers?.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
          </select>
        </Field>
        <Field label="Service Category">
          <input className={inputClass} value={category} onChange={(e) => setCategory(e.target.value)} required placeholder="e.g. consultancy" />
        </Field>
        <Field label="Base Amount">
          <input className={inputClass} type="number" step="any" value={amount} onChange={(e) => setAmount(e.target.value)} required />
        </Field>
        <div className="flex justify-end"><Button type="submit" disabled={m.isPending}>{m.isPending ? 'Posting…' : 'Deduct VDS'}</Button></div>
      </form>
    </div>
  )
}
