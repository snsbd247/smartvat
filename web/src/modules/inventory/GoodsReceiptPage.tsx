import { type FormEvent, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, Trash2 } from 'lucide-react'
import { Banner, Button, Field, PageHeader, apiError, inputClass } from '../../shared/ui/primitives'
import { Inventory, Products, Suppliers, Warehouses } from '../../shared/lib/resources'

interface Line {
  product_id: string
  qty: string
  unit_cost: string
}

export function GoodsReceiptPage() {
  const qc = useQueryClient()
  const { data: suppliers } = useQuery({ queryKey: ['suppliers'], queryFn: Suppliers.list })
  const { data: warehouses } = useQuery({ queryKey: ['warehouses'], queryFn: Warehouses.list })
  const { data: products } = useQuery({ queryKey: ['products'], queryFn: Products.list })

  const [supplier, setSupplier] = useState('')
  const [warehouse, setWarehouse] = useState('')
  const [lines, setLines] = useState<Line[]>([{ product_id: '', qty: '', unit_cost: '' }])
  const [msg, setMsg] = useState<{ kind: 'error' | 'success'; text: string } | null>(null)

  const mutation = useMutation({
    mutationFn: () =>
      Inventory.goodsReceipt({
        supplier_id: Number(supplier),
        warehouse_id: Number(warehouse),
        lines: lines.map((l) => ({ product_id: Number(l.product_id), qty: Number(l.qty), unit_cost: Number(l.unit_cost) })),
      }),
    onSuccess: (res: unknown) => {
      const grn = (res as { grn_no?: string }).grn_no
      setMsg({ kind: 'success', text: `Goods receipt ${grn ?? ''} posted — stock and GL updated.` })
      setLines([{ product_id: '', qty: '', unit_cost: '' }])
      qc.invalidateQueries({ queryKey: ['stock'] })
      qc.invalidateQueries({ queryKey: ['dashboard'] })
    },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })

  function submit(e: FormEvent) {
    e.preventDefault()
    setMsg(null)
    mutation.mutate()
  }

  const setLine = (i: number, k: keyof Line, v: string) =>
    setLines((s) => s.map((l, idx) => (idx === i ? { ...l, [k]: v } : l)))

  return (
    <div>
      <PageHeader title="Goods Receipt" subtitle="Receive material — posts stock in + Dr Inventory / Cr GRN-clearing" />
      {msg && <Banner kind={msg.kind}>{msg.text}</Banner>}

      <form onSubmit={submit} className="space-y-5 rounded-xl border border-slate-200 bg-white p-5">
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
          <div className="text-sm font-medium text-ink">Lines</div>
          {lines.map((l, i) => (
            <div key={i} className="grid grid-cols-12 gap-2">
              <select
                className={`${inputClass} col-span-6`}
                value={l.product_id}
                onChange={(e) => setLine(i, 'product_id', e.target.value)}
                required
              >
                <option value="">Product…</option>
                {products?.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
              </select>
              <input className={`${inputClass} col-span-2`} placeholder="Qty" type="number" step="any"
                value={l.qty} onChange={(e) => setLine(i, 'qty', e.target.value)} required />
              <input className={`${inputClass} col-span-3`} placeholder="Unit cost" type="number" step="any"
                value={l.unit_cost} onChange={(e) => setLine(i, 'unit_cost', e.target.value)} required />
              <button type="button" className="col-span-1 grid place-items-center rounded-lg border border-slate-300 text-muted hover:bg-slate-50"
                onClick={() => setLines((s) => s.filter((_, idx) => idx !== i))}>
                <Trash2 size={15} />
              </button>
            </div>
          ))}
          <Button variant="ghost" onClick={() => setLines((s) => [...s, { product_id: '', qty: '', unit_cost: '' }])}>
            <Plus size={15} /> Add line
          </Button>
        </div>

        <div className="flex justify-end">
          <Button type="submit" disabled={mutation.isPending}>
            {mutation.isPending ? 'Posting…' : 'Post Goods Receipt'}
          </Button>
        </div>
      </form>
    </div>
  )
}
