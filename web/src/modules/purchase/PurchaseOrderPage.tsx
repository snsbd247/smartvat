import { type FormEvent, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, Trash2 } from 'lucide-react'
import { Banner, Button, Field, PageHeader, apiError, inputClass } from '../../shared/ui/primitives'
import { PurchaseApi, Products, Suppliers } from '../../shared/lib/resources'

interface Line {
  product_id: string
  qty: string
  unit_price: string
}

export function PurchaseOrderPage({ title = 'Purchase Order', subtitle = 'Create a draft PO (approve from the Approval Center)' }: { title?: string; subtitle?: string }) {
  const qc = useQueryClient()
  const { data: suppliers } = useQuery({ queryKey: ['suppliers'], queryFn: Suppliers.list })
  const { data: products } = useQuery({ queryKey: ['products'], queryFn: Products.list })
  const [supplier, setSupplier] = useState('')
  const [lines, setLines] = useState<Line[]>([{ product_id: '', qty: '', unit_price: '' }])
  const [msg, setMsg] = useState<{ kind: 'error' | 'success'; text: string } | null>(null)
  const total = lines.reduce((s, l) => s + Number(l.qty || 0) * Number(l.unit_price || 0), 0)

  const m = useMutation({
    mutationFn: () =>
      PurchaseApi.order({
        supplier_id: Number(supplier),
        lines: lines.map((l) => ({ product_id: Number(l.product_id), qty: Number(l.qty), unit_price: Number(l.unit_price) })),
      }),
    onSuccess: (r: unknown) => {
      setMsg({ kind: 'success', text: `Purchase order ${(r as { po_no?: string }).po_no ?? ''} created (draft).` })
      setLines([{ product_id: '', qty: '', unit_price: '' }])
      qc.invalidateQueries({ queryKey: ['dashboard'] })
    },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })
  const set = (i: number, k: keyof Line, v: string) =>
    setLines((s) => s.map((l, idx) => (idx === i ? { ...l, [k]: v } : l)))

  return (
    <div>
      <PageHeader title={title} subtitle={subtitle} />
      {msg && <Banner kind={msg.kind}>{msg.text}</Banner>}
      <form
        onSubmit={(e: FormEvent) => {
          e.preventDefault()
          setMsg(null)
          m.mutate()
        }}
        className="space-y-5 rounded-xl border border-slate-200 bg-white p-5"
      >
        <Field label="Supplier">
          <select className={`${inputClass} sm:max-w-sm`} value={supplier} onChange={(e) => setSupplier(e.target.value)} required>
            <option value="">Select supplier…</option>
            {suppliers?.map((s) => (
              <option key={s.id} value={s.id}>
                {s.name}
              </option>
            ))}
          </select>
        </Field>
        <div className="space-y-2">
          {lines.map((l, i) => (
            <div key={i} className="grid grid-cols-12 gap-2">
              <select className={`${inputClass} col-span-6`} value={l.product_id} onChange={(e) => set(i, 'product_id', e.target.value)} required>
                <option value="">Product…</option>
                {products?.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.name}
                  </option>
                ))}
              </select>
              <input className={`${inputClass} col-span-2`} placeholder="Qty" type="number" step="any" value={l.qty} onChange={(e) => set(i, 'qty', e.target.value)} required />
              <input className={`${inputClass} col-span-3`} placeholder="Unit price" type="number" step="any" value={l.unit_price} onChange={(e) => set(i, 'unit_price', e.target.value)} required />
              <button type="button" className="col-span-1 grid place-items-center rounded-lg border border-slate-300 text-muted hover:bg-slate-50" onClick={() => setLines((s) => s.filter((_, idx) => idx !== i))}>
                <Trash2 size={15} />
              </button>
            </div>
          ))}
          <Button variant="ghost" onClick={() => setLines((s) => [...s, { product_id: '', qty: '', unit_price: '' }])}>
            <Plus size={15} /> Add line
          </Button>
        </div>
        <div className="flex items-center justify-between border-t border-slate-100 pt-4">
          <span className="text-sm text-muted">Total</span>
          <span className="text-lg font-semibold text-ink">৳ {total.toLocaleString()}</span>
        </div>
        <div className="flex justify-end">
          <Button type="submit" disabled={m.isPending}>
            {m.isPending ? 'Saving…' : 'Create PO'}
          </Button>
        </div>
      </form>
    </div>
  )
}
