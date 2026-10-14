import { type FormEvent, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, Trash2 } from 'lucide-react'
import { Banner, Button, Field, PageHeader, apiError, inputClass } from '../../shared/ui/primitives'
import { Inventory, Products, Warehouses } from '../../shared/lib/resources'

interface Line { product_id: string; qty: string; unit_cost: string }

export function StockAdjustmentPage({ title = 'Stock Adjustment', subtitle = 'Positive or negative quantity adjustment (use + / − qty)' }: { title?: string; subtitle?: string }) {
  const qc = useQueryClient()
  const { data: products } = useQuery({ queryKey: ['products'], queryFn: Products.list })
  const { data: warehouses } = useQuery({ queryKey: ['warehouses'], queryFn: Warehouses.list })
  const [warehouse, setWarehouse] = useState('')
  const [lines, setLines] = useState<Line[]>([{ product_id: '', qty: '', unit_cost: '' }])
  const [msg, setMsg] = useState<{ kind: 'error' | 'success'; text: string } | null>(null)

  const m = useMutation({
    mutationFn: () => Inventory.adjustment({
      warehouse_id: Number(warehouse),
      lines: lines.map((l) => ({ product_id: Number(l.product_id), qty: Number(l.qty), ...(l.unit_cost ? { unit_cost: Number(l.unit_cost) } : {}) })),
    }),
    onSuccess: (r: unknown) => {
      setMsg({ kind: 'success', text: `Adjustment ${(r as { txn_no?: string }).txn_no ?? ''} posted.` })
      setLines([{ product_id: '', qty: '', unit_cost: '' }]); qc.invalidateQueries({ queryKey: ['stock'] }); qc.invalidateQueries({ queryKey: ['dashboard'] })
    },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })
  const set = (i: number, k: keyof Line, v: string) => setLines((s) => s.map((l, idx) => (idx === i ? { ...l, [k]: v } : l)))

  return (
    <div>
      <PageHeader title={title} subtitle={subtitle} />
      {msg && <Banner kind={msg.kind}>{msg.text}</Banner>}
      <form onSubmit={(e: FormEvent) => { e.preventDefault(); setMsg(null); m.mutate() }} className="space-y-5 rounded-xl border border-slate-200 bg-white p-5">
        <Field label="Warehouse">
          <select className={`${inputClass} sm:max-w-sm`} value={warehouse} onChange={(e) => setWarehouse(e.target.value)} required>
            <option value="">Select warehouse…</option>
            {warehouses?.map((w) => <option key={w.id} value={w.id}>{w.name}</option>)}
          </select>
        </Field>
        <div className="space-y-2">
          <div className="grid grid-cols-12 gap-2 text-xs text-muted">
            <div className="col-span-6">Product</div><div className="col-span-3">Qty (+/−)</div><div className="col-span-3">Unit cost (for increase)</div>
          </div>
          {lines.map((l, i) => (
            <div key={i} className="grid grid-cols-12 gap-2">
              <select className={`${inputClass} col-span-6`} value={l.product_id} onChange={(e) => set(i, 'product_id', e.target.value)} required>
                <option value="">Product…</option>
                {products?.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
              </select>
              <input className={`${inputClass} col-span-3`} placeholder="+/- Qty" type="number" step="any" value={l.qty} onChange={(e) => set(i, 'qty', e.target.value)} required />
              <input className={`${inputClass} col-span-2`} placeholder="Cost" type="number" step="any" value={l.unit_cost} onChange={(e) => set(i, 'unit_cost', e.target.value)} />
              <button type="button" className="col-span-1 grid place-items-center rounded-lg border border-slate-300 text-muted hover:bg-slate-50" onClick={() => setLines((s) => s.filter((_, idx) => idx !== i))}><Trash2 size={15} /></button>
            </div>
          ))}
          <Button variant="ghost" onClick={() => setLines((s) => [...s, { product_id: '', qty: '', unit_cost: '' }])}><Plus size={15} /> Add line</Button>
        </div>
        <div className="flex justify-end"><Button type="submit" disabled={m.isPending}>{m.isPending ? 'Posting…' : 'Post Adjustment'}</Button></div>
      </form>
    </div>
  )
}
