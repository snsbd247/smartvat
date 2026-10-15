import { type FormEvent, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, Trash2 } from 'lucide-react'
import { Inventory, Products, Warehouses } from '../../shared/lib/resources'
import { Banner, Button, Field, PageHeader, Table, apiError, inputClass } from '../../shared/ui/primitives'

interface Line { product_id: string; counted_qty: string }
interface Variance { product: string; system_qty: string; counted_qty: string; variance: string }

export function PhysicalCountPage() {
  const qc = useQueryClient()
  const { data: products } = useQuery({ queryKey: ['products'], queryFn: Products.list })
  const { data: warehouses } = useQuery({ queryKey: ['warehouses'], queryFn: Warehouses.list })

  const [warehouse, setWarehouse] = useState('')
  const [lines, setLines] = useState<Line[]>([{ product_id: '', counted_qty: '' }])
  const [result, setResult] = useState<Variance[] | null>(null)
  const [msg, setMsg] = useState<{ kind: 'error' | 'success'; text: string } | null>(null)

  const pName = (id: number) => products?.find((p) => p.id === id)?.name ?? `#${id}`

  const submit = useMutation({
    mutationFn: () => Inventory.physicalCount({
      warehouse_id: Number(warehouse),
      lines: lines.filter((l) => l.product_id && l.counted_qty).map((l) => ({ product_id: Number(l.product_id), counted_qty: Number(l.counted_qty) })),
    }),
    onSuccess: (res: { count_no: string; lines: { product_id: number; system_qty: string; counted_qty: string; variance: string }[] }) => {
      setMsg({ kind: 'success', text: `Count ${res.count_no} posted — stock adjusted to the counted quantities.` })
      setResult(res.lines.map((l) => ({ product: pName(l.product_id), system_qty: l.system_qty, counted_qty: l.counted_qty, variance: l.variance })))
      setLines([{ product_id: '', counted_qty: '' }])
      qc.invalidateQueries({ queryKey: ['stock'] }); qc.invalidateQueries({ queryKey: ['dashboard'] })
    },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })

  const setLine = (i: number, k: keyof Line, v: string) => setLines((s) => s.map((l, idx) => (idx === i ? { ...l, [k]: v } : l)))
  function onSubmit(e: FormEvent) { e.preventDefault(); setMsg(null); setResult(null); submit.mutate() }

  return (
    <div>
      <PageHeader title="Physical Stock Count" subtitle="Enter counted quantities; the system records the variance and adjusts stock to match." />
      {msg && <Banner kind={msg.kind}>{msg.text}</Banner>}

      <form onSubmit={onSubmit} className="mb-6 space-y-5 rounded-xl border border-slate-200 bg-white p-5">
        <Field label="Warehouse">
          <select className={`${inputClass} sm:max-w-sm`} value={warehouse} onChange={(e) => setWarehouse(e.target.value)} required>
            <option value="">Select warehouse…</option>
            {warehouses?.map((w) => <option key={w.id} value={w.id}>{w.name}</option>)}
          </select>
        </Field>

        <div className="space-y-2">
          <div className="text-sm font-medium text-ink">Counted items</div>
          {lines.map((l, i) => (
            <div key={i} className="grid grid-cols-12 items-center gap-2">
              <select className={`${inputClass} col-span-7`} value={l.product_id} onChange={(e) => setLine(i, 'product_id', e.target.value)} required>
                <option value="">Product…</option>
                {products?.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
              </select>
              <input className={`${inputClass} col-span-4`} placeholder="Counted qty" type="number" step="any" min="0" value={l.counted_qty} onChange={(e) => setLine(i, 'counted_qty', e.target.value)} required />
              <button type="button" className="col-span-1 grid place-items-center rounded-lg border border-slate-300 text-muted hover:bg-slate-50" onClick={() => setLines((s) => s.filter((_, idx) => idx !== i))}>
                <Trash2 size={15} />
              </button>
            </div>
          ))}
          <Button variant="ghost" onClick={() => setLines((s) => [...s, { product_id: '', counted_qty: '' }])}><Plus size={15} /> Add item</Button>
        </div>

        <div className="flex justify-end">
          <Button type="submit" disabled={submit.isPending}>{submit.isPending ? 'Posting…' : 'Post Count & Adjust'}</Button>
        </div>
      </form>

      {result && (
        <>
          <h3 className="mb-2 text-sm font-semibold text-ink">Variance summary</h3>
          <Table head={['Product', 'System qty', 'Counted', 'Variance']}>
            {result.map((r, i) => (
              <tr key={i} className="text-ink">
                <td className="px-4 py-2.5">{r.product}</td>
                <td className="px-4 py-2.5">{Number(r.system_qty)}</td>
                <td className="px-4 py-2.5">{Number(r.counted_qty)}</td>
                <td className={`px-4 py-2.5 font-medium ${Number(r.variance) < 0 ? 'text-rose-600' : Number(r.variance) > 0 ? 'text-emerald-600' : 'text-muted'}`}>
                  {Number(r.variance) > 0 ? '+' : ''}{Number(r.variance)}
                </td>
              </tr>
            ))}
          </Table>
        </>
      )}
    </div>
  )
}
