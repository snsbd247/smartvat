import { type FormEvent, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, Trash2 } from 'lucide-react'
import { Banner, Button, Field, PageHeader, apiError, inputClass } from '../../shared/ui/primitives'
import { Customers, Products, Sales } from '../../shared/lib/resources'

interface Line { product_id: string; qty: string; unit_price: string }

export function ExportInvoicePage() {
  const qc = useQueryClient()
  const { data: customers } = useQuery({ queryKey: ['customers'], queryFn: Customers.list })
  const { data: products } = useQuery({ queryKey: ['products'], queryFn: Products.list })
  const [customer, setCustomer] = useState('')
  const [currency, setCurrency] = useState('USD')
  const [rate, setRate] = useState('')
  const [lines, setLines] = useState<Line[]>([{ product_id: '', qty: '', unit_price: '' }])
  const [msg, setMsg] = useState<{ kind: 'error' | 'success'; text: string } | null>(null)
  const fcTotal = lines.reduce((s, l) => s + Number(l.qty || 0) * Number(l.unit_price || 0), 0)

  const m = useMutation({
    mutationFn: () => Sales.invoice({
      customer_id: Number(customer),
      kind: 'export',
      currency,
      exchange_rate: Number(rate),
      lines: lines.map((l) => ({ product_id: Number(l.product_id), qty: Number(l.qty), unit_price: Number(l.unit_price), line_total: Number(l.qty) * Number(l.unit_price) })),
    }),
    onSuccess: (r: unknown) => {
      setMsg({ kind: 'success', text: `Export invoice ${(r as { inv_no?: string }).inv_no ?? ''} posted (AR in base currency at ${rate}).` })
      setLines([{ product_id: '', qty: '', unit_price: '' }])
      qc.invalidateQueries({ queryKey: ['dashboard'] })
    },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })
  const set = (i: number, k: keyof Line, v: string) => setLines((s) => s.map((l, idx) => (idx === i ? { ...l, [k]: v } : l)))

  return (
    <div>
      <PageHeader title="Export Sales Invoice" subtitle="Foreign currency → AR/revenue booked in base currency at the rate" />
      {msg && <Banner kind={msg.kind}>{msg.text}</Banner>}
      <form onSubmit={(e: FormEvent) => { e.preventDefault(); setMsg(null); m.mutate() }} className="space-y-5 rounded-xl border border-slate-200 bg-white p-5">
        <div className="grid gap-4 sm:grid-cols-3">
          <Field label="Customer">
            <select className={inputClass} value={customer} onChange={(e) => setCustomer(e.target.value)} required>
              <option value="">Select…</option>
              {customers?.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
            </select>
          </Field>
          <Field label="Currency"><input className={inputClass} value={currency} onChange={(e) => setCurrency(e.target.value)} required /></Field>
          <Field label="Exchange Rate"><input className={inputClass} type="number" step="any" value={rate} onChange={(e) => setRate(e.target.value)} required /></Field>
        </div>
        <div className="space-y-2">
          {lines.map((l, i) => (
            <div key={i} className="grid grid-cols-12 items-center gap-2">
              <select className={`${inputClass} col-span-6`} value={l.product_id} onChange={(e) => set(i, 'product_id', e.target.value)} required>
                <option value="">Product…</option>
                {products?.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
              </select>
              <input className={`${inputClass} col-span-2`} placeholder="Qty" type="number" step="any" value={l.qty} onChange={(e) => set(i, 'qty', e.target.value)} required />
              <input className={`${inputClass} col-span-3`} placeholder="FC price" type="number" step="any" value={l.unit_price} onChange={(e) => set(i, 'unit_price', e.target.value)} required />
              <button type="button" className="col-span-1 grid place-items-center rounded-lg border border-slate-300 text-muted hover:bg-slate-50" onClick={() => setLines((s) => s.filter((_, idx) => idx !== i))}><Trash2 size={15} /></button>
            </div>
          ))}
          <Button variant="ghost" onClick={() => setLines((s) => [...s, { product_id: '', qty: '', unit_price: '' }])}><Plus size={15} /> Add line</Button>
        </div>
        <div className="flex items-center justify-between border-t border-slate-100 pt-4">
          <span className="text-sm text-muted">FC Total</span>
          <span className="text-lg font-semibold text-ink">{currency} {fcTotal.toLocaleString()} ≈ ৳ {(fcTotal * Number(rate || 0)).toLocaleString()}</span>
        </div>
        <div className="flex justify-end"><Button type="submit" disabled={m.isPending}>{m.isPending ? 'Posting…' : 'Post Export Invoice'}</Button></div>
      </form>
    </div>
  )
}
