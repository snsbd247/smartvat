import { type FormEvent, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, Trash2 } from 'lucide-react'
import { Banner, Button, Field, PageHeader, apiError, inputClass } from '../../shared/ui/primitives'
import { PurchaseApi, Products, Suppliers, Warehouses } from '../../shared/lib/resources'

interface Line {
  product_id: string
  qty: string
}

export function PurchaseReturnPage() {
  const qc = useQueryClient()
  const { data: suppliers } = useQuery({ queryKey: ['suppliers'], queryFn: Suppliers.list })
  const { data: products } = useQuery({ queryKey: ['products'], queryFn: Products.list })
  const { data: warehouses } = useQuery({ queryKey: ['warehouses'], queryFn: Warehouses.list })
  const [supplier, setSupplier] = useState('')
  const [warehouse, setWarehouse] = useState('')
  const [lines, setLines] = useState<Line[]>([{ product_id: '', qty: '' }])
  const [msg, setMsg] = useState<{ kind: 'error' | 'success'; text: string } | null>(null)

  const m = useMutation({
    mutationFn: () =>
      PurchaseApi.returnGoods({
        supplier_id: Number(supplier),
        warehouse_id: Number(warehouse),
        lines: lines.map((l) => ({ product_id: Number(l.product_id), qty: Number(l.qty) })),
      }),
    onSuccess: (r: unknown) => {
      setMsg({ kind: 'success', text: `Purchase return ${(r as { return_no?: string }).return_no ?? ''} posted — stock out + Dr AP / Cr Inventory.` })
      setLines([{ product_id: '', qty: '' }])
      qc.invalidateQueries({ queryKey: ['stock'] })
      qc.invalidateQueries({ queryKey: ['dashboard'] })
    },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })
  const set = (i: number, k: keyof Line, v: string) => setLines((s) => s.map((l, idx) => (idx === i ? { ...l, [k]: v } : l)))

  return (
    <div>
      <PageHeader title="Purchase Return" subtitle="Return goods to a supplier at average cost" />
      {msg && <Banner kind={msg.kind}>{msg.text}</Banner>}
      <form onSubmit={(e: FormEvent) => { e.preventDefault(); setMsg(null); m.mutate() }} className="space-y-5 rounded-xl border border-slate-200 bg-white p-5">
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Supplier">
            <select className={inputClass} value={supplier} onChange={(e) => setSupplier(e.target.value)} required>
              <option value="">Select supplier…</option>
              {suppliers?.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
            </select>
          </Field>
          <Field label="Warehouse">
            <select className={inputClass} value={warehouse} onChange={(e) => setWarehouse(e.target.value)} required>
              <option value="">Select warehouse…</option>
              {warehouses?.map((w) => <option key={w.id} value={w.id}>{w.name}</option>)}
            </select>
          </Field>
        </div>
        <div className="space-y-2">
          {lines.map((l, i) => (
            <div key={i} className="grid grid-cols-12 gap-2">
              <select className={`${inputClass} col-span-8`} value={l.product_id} onChange={(e) => set(i, 'product_id', e.target.value)} required>
                <option value="">Product…</option>
                {products?.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
              </select>
              <input className={`${inputClass} col-span-3`} placeholder="Qty" type="number" step="any" value={l.qty} onChange={(e) => set(i, 'qty', e.target.value)} required />
              <button type="button" className="col-span-1 grid place-items-center rounded-lg border border-slate-300 text-muted hover:bg-slate-50" onClick={() => setLines((s) => s.filter((_, idx) => idx !== i))}><Trash2 size={15} /></button>
            </div>
          ))}
          <Button variant="ghost" onClick={() => setLines((s) => [...s, { product_id: '', qty: '' }])}><Plus size={15} /> Add line</Button>
        </div>
        <div className="flex justify-end"><Button type="submit" disabled={m.isPending}>{m.isPending ? 'Posting…' : 'Post Return'}</Button></div>
      </form>
    </div>
  )
}
