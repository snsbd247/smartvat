import { type FormEvent, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, Trash2 } from 'lucide-react'
import { Banner, Button, Field, PageHeader, Table, apiError, inputClass } from '../../shared/ui/primitives'
import { Import, Products, Suppliers, Warehouses } from '../../shared/lib/resources'

interface Line { product_id: string; qty: string; fc_unit_price: string; weight: string }
interface Charge { type: string; fc_amount: string; allocation_basis: string; recoverable: boolean }
type Preview = Awaited<ReturnType<typeof Import.preview>>

const BASIS = ['value', 'quantity', 'weight', 'volume']
const money = (v: string | number) => '৳ ' + Number(v).toLocaleString(undefined, { maximumFractionDigits: 2 })

export function ImportShipmentPage({ title = 'Import Purchase', subtitle = 'Foreign-currency shipment with landed-cost allocation' }: { title?: string; subtitle?: string }) {
  const qc = useQueryClient()
  const { data: suppliers } = useQuery({ queryKey: ['suppliers'], queryFn: Suppliers.list })
  const { data: warehouses } = useQuery({ queryKey: ['warehouses'], queryFn: Warehouses.list })
  const { data: products } = useQuery({ queryKey: ['products'], queryFn: Products.list })

  const [head, setHead] = useState<Record<string, string>>({ currency: 'USD', exchange_rate: '' })
  const [lines, setLines] = useState<Line[]>([{ product_id: '', qty: '', fc_unit_price: '', weight: '' }])
  const [charges, setCharges] = useState<Charge[]>([{ type: 'freight', fc_amount: '', allocation_basis: 'value', recoverable: false }])
  const [shipmentId, setShipmentId] = useState<number | null>(null)
  const [preview, setPreview] = useState<Preview | null>(null)
  const [msg, setMsg] = useState<{ kind: 'error' | 'success'; text: string } | null>(null)

  const create = useMutation({
    mutationFn: () => Import.create({
      supplier_id: Number(head.supplier_id),
      warehouse_id: Number(head.warehouse_id),
      currency: head.currency,
      exchange_rate: Number(head.exchange_rate),
      lines: lines.map((l) => ({ product_id: Number(l.product_id), qty: Number(l.qty), fc_unit_price: Number(l.fc_unit_price), weight: Number(l.weight || 0) })),
      charges: charges.map((c) => ({ type: c.type, fc_amount: Number(c.fc_amount), allocation_basis: c.allocation_basis, recoverable: c.recoverable })),
    }),
    onSuccess: async (r: unknown) => {
      const id = (r as { id: number }).id
      setShipmentId(id)
      setPreview(await Import.preview(id))
      setMsg({ kind: 'success', text: 'Shipment created. Review the landed cost, then receive.' })
    },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })
  const receive = useMutation({
    mutationFn: () => Import.receive(shipmentId!),
    onSuccess: () => {
      setMsg({ kind: 'success', text: 'Shipment received at landed cost — stock + GL updated.' })
      setShipmentId(null); setPreview(null)
      setLines([{ product_id: '', qty: '', fc_unit_price: '', weight: '' }])
      qc.invalidateQueries({ queryKey: ['stock'] }); qc.invalidateQueries({ queryKey: ['dashboard'] })
    },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })

  const setLine = (i: number, k: keyof Line, v: string) => setLines((s) => s.map((l, idx) => (idx === i ? { ...l, [k]: v } : l)))
  const setCharge = (i: number, k: keyof Charge, v: string | boolean) => setCharges((s) => s.map((c, idx) => (idx === i ? { ...c, [k]: v } : c)))

  return (
    <div>
      <PageHeader title={title} subtitle={subtitle} />
      {msg && <Banner kind={msg.kind}>{msg.text}</Banner>}

      {!shipmentId ? (
        <form onSubmit={(e: FormEvent) => { e.preventDefault(); setMsg(null); create.mutate() }} className="space-y-5 rounded-xl border border-slate-200 bg-white p-5">
          <div className="grid gap-4 sm:grid-cols-4">
            <Field label="Supplier">
              <select className={inputClass} value={head.supplier_id ?? ''} onChange={(e) => setHead((s) => ({ ...s, supplier_id: e.target.value }))} required>
                <option value="">Select…</option>
                {suppliers?.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
              </select>
            </Field>
            <Field label="Warehouse">
              <select className={inputClass} value={head.warehouse_id ?? ''} onChange={(e) => setHead((s) => ({ ...s, warehouse_id: e.target.value }))} required>
                <option value="">Select…</option>
                {warehouses?.map((w) => <option key={w.id} value={w.id}>{w.name}</option>)}
              </select>
            </Field>
            <Field label="Currency"><input className={inputClass} value={head.currency ?? ''} onChange={(e) => setHead((s) => ({ ...s, currency: e.target.value }))} required /></Field>
            <Field label="Exchange Rate"><input className={inputClass} type="number" step="any" value={head.exchange_rate ?? ''} onChange={(e) => setHead((s) => ({ ...s, exchange_rate: e.target.value }))} required /></Field>
          </div>

          <div className="space-y-2">
            <div className="text-sm font-medium text-ink">Lines (foreign-currency unit price)</div>
            {lines.map((l, i) => (
              <div key={i} className="grid grid-cols-12 gap-2">
                <select className={`${inputClass} col-span-5`} value={l.product_id} onChange={(e) => setLine(i, 'product_id', e.target.value)} required>
                  <option value="">Product…</option>
                  {products?.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                </select>
                <input className={`${inputClass} col-span-2`} placeholder="Qty" type="number" step="any" value={l.qty} onChange={(e) => setLine(i, 'qty', e.target.value)} required />
                <input className={`${inputClass} col-span-2`} placeholder="FC price" type="number" step="any" value={l.fc_unit_price} onChange={(e) => setLine(i, 'fc_unit_price', e.target.value)} required />
                <input className={`${inputClass} col-span-2`} placeholder="Weight" type="number" step="any" value={l.weight} onChange={(e) => setLine(i, 'weight', e.target.value)} />
                <button type="button" className="col-span-1 grid place-items-center rounded-lg border border-slate-300 text-muted hover:bg-slate-50" onClick={() => setLines((s) => s.filter((_, idx) => idx !== i))}><Trash2 size={15} /></button>
              </div>
            ))}
            <Button variant="ghost" onClick={() => setLines((s) => [...s, { product_id: '', qty: '', fc_unit_price: '', weight: '' }])}><Plus size={15} /> Add line</Button>
          </div>

          <div className="space-y-2">
            <div className="text-sm font-medium text-ink">Charges (freight, customs, etc.)</div>
            {charges.map((c, i) => (
              <div key={i} className="grid grid-cols-12 items-center gap-2">
                <input className={`${inputClass} col-span-3`} placeholder="Type" value={c.type} onChange={(e) => setCharge(i, 'type', e.target.value)} />
                <input className={`${inputClass} col-span-3`} placeholder="FC amount" type="number" step="any" value={c.fc_amount} onChange={(e) => setCharge(i, 'fc_amount', e.target.value)} required />
                <select className={`${inputClass} col-span-3`} value={c.allocation_basis} onChange={(e) => setCharge(i, 'allocation_basis', e.target.value)}>
                  {BASIS.map((b) => <option key={b} value={b}>{b}</option>)}
                </select>
                <label className="col-span-2 flex items-center gap-1 text-xs text-ink">
                  <input type="checkbox" checked={c.recoverable} onChange={(e) => setCharge(i, 'recoverable', e.target.checked)} /> recoverable
                </label>
                <button type="button" className="col-span-1 grid place-items-center rounded-lg border border-slate-300 text-muted hover:bg-slate-50" onClick={() => setCharges((s) => s.filter((_, idx) => idx !== i))}><Trash2 size={15} /></button>
              </div>
            ))}
            <Button variant="ghost" onClick={() => setCharges((s) => [...s, { type: 'other', fc_amount: '', allocation_basis: 'value', recoverable: false }])}><Plus size={15} /> Add charge</Button>
          </div>

          <div className="flex justify-end"><Button type="submit" disabled={create.isPending}>{create.isPending ? 'Creating…' : 'Create & Preview'}</Button></div>
        </form>
      ) : (
        <div className="space-y-4">
          <div className="rounded-xl border border-slate-200 bg-white p-5">
            <div className="mb-3 text-sm font-semibold text-ink">Landed Cost Preview</div>
            <div className="mb-4 flex flex-wrap gap-6 text-sm">
              <div><span className="text-muted">Goods (BC):</span> <b>{money(preview?.goods_bc_total ?? 0)}</b></div>
              <div><span className="text-muted">Non-recoverable charges:</span> <b>{money(preview?.nonrecoverable_total ?? 0)}</b></div>
              <div><span className="text-muted">Landed total:</span> <b>{money(preview?.landed_total ?? 0)}</b></div>
            </div>
            <Table head={['Product', 'Qty', 'Goods (BC)', 'Allocated', 'Landed Value', 'Landed Unit Cost']}>
              {(preview?.lines ?? []).map((l, i) => (
                <tr key={i} className="text-ink">
                  <td className="px-4 py-2.5">{products?.find((p) => p.id === Number(l.product_id))?.name ?? l.product_id}</td>
                  <td className="px-4 py-2.5">{Number(l.qty)}</td>
                  <td className="px-4 py-2.5 text-right">{money(l.goods_bc)}</td>
                  <td className="px-4 py-2.5 text-right">{money(l.allocated)}</td>
                  <td className="px-4 py-2.5 text-right">{money(l.landed_value)}</td>
                  <td className="px-4 py-2.5 text-right">{Number(l.landed_unit_cost).toFixed(4)}</td>
                </tr>
              ))}
            </Table>
          </div>
          <div className="flex justify-end gap-2">
            <Button variant="ghost" onClick={() => { setShipmentId(null); setPreview(null) }}>Back</Button>
            <Button onClick={() => receive.mutate()} disabled={receive.isPending}>{receive.isPending ? 'Receiving…' : 'Receive at Landed Cost'}</Button>
          </div>
        </div>
      )}
    </div>
  )
}
