import { type FormEvent, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { Banner, Button, Field, Modal, PageHeader, Table, apiError, inputClass } from '../../shared/ui/primitives'
import { Production, Products, Warehouses } from '../../shared/lib/resources'

export type ProdFocus = 'orders' | 'planning' | 'issue' | 'fg-receipt' | 'qc'

const FOCUS: Record<ProdFocus, { title: string; subtitle: string; canCreate: boolean; showIssue: boolean; showReceive: boolean; filter: (s: string) => boolean }> = {
  orders: { title: 'Production Orders', subtitle: 'Issue materials to WIP, then receive finished goods', canCreate: true, showIssue: true, showReceive: true, filter: () => true },
  planning: { title: 'Production Planning', subtitle: 'Create and schedule orders before materials are issued', canCreate: true, showIssue: false, showReceive: false, filter: (s) => s === 'draft' },
  issue: { title: 'Material Issue', subtitle: 'Issue BOM materials to work-in-progress', canCreate: false, showIssue: true, showReceive: false, filter: (s) => s === 'draft' },
  'fg-receipt': { title: 'Finished Goods Receipt', subtitle: 'Receive produced goods from released orders into stock', canCreate: false, showIssue: false, showReceive: true, filter: (s) => s === 'released' },
  qc: { title: 'Quality Control', subtitle: 'Completed orders — produced vs scrap for inspection', canCreate: false, showIssue: false, showReceive: false, filter: (s) => s === 'completed' },
}

export function ProductionOrdersPage({ focus = 'orders' }: { focus?: ProdFocus }) {
  const cfg = FOCUS[focus]
  const qc = useQueryClient()
  const { data: allOrders } = useQuery({ queryKey: ['prod-orders'], queryFn: Production.orders })
  const orders = (allOrders ?? []).filter((o) => cfg.filter(o.status))
  const { data: products } = useQuery({ queryKey: ['products'], queryFn: Products.list })
  const { data: boms } = useQuery({ queryKey: ['boms'], queryFn: Production.boms })
  const { data: warehouses } = useQuery({ queryKey: ['warehouses'], queryFn: Warehouses.list })

  const [open, setOpen] = useState(false)
  const [msg, setMsg] = useState<{ kind: 'error' | 'success'; text: string } | null>(null)
  const [form, setForm] = useState<Record<string, string>>({})

  const pName = (id: number) => products?.find((p) => p.id === id)?.name ?? `#${id}`
  const invalidate = () => { qc.invalidateQueries({ queryKey: ['prod-orders'] }); qc.invalidateQueries({ queryKey: ['dashboard'] }); qc.invalidateQueries({ queryKey: ['stock'] }) }

  const create = useMutation({
    mutationFn: () => Production.createOrder({
      product_id: Number(form.product_id),
      bom_id: form.bom_id ? Number(form.bom_id) : undefined,
      warehouse_id: Number(form.warehouse_id),
      planned_qty: Number(form.planned_qty),
    }),
    onSuccess: () => { invalidate(); setOpen(false); setForm({}) },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })
  const issue = useMutation({
    mutationFn: (id: number) => Production.issue(id),
    onSuccess: () => { setMsg({ kind: 'success', text: 'Materials issued to WIP.' }); invalidate() },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })
  const receive = useMutation({
    mutationFn: ({ id, qty }: { id: number; qty: number }) => Production.receive(id, qty),
    onSuccess: () => { setMsg({ kind: 'success', text: 'Finished goods received.' }); invalidate() },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })

  return (
    <div>
      <PageHeader title={cfg.title} subtitle={cfg.subtitle}
        action={cfg.canCreate && <Button onClick={() => { setOpen(true); setMsg(null) }}><Plus size={16} /> New Order</Button>} />
      {msg && <Banner kind={msg.kind}>{msg.text}</Banner>}

      <Table head={['Order', 'Product', 'Planned', 'Produced', 'Status', focus === 'qc' ? 'Scrap' : 'Actions']}>
        {orders.map((o) => (
          <tr key={o.id} className="text-ink">
            <td className="px-4 py-2.5">{o.order_no}</td>
            <td className="px-4 py-2.5">{pName(o.product_id)}</td>
            <td className="px-4 py-2.5">{o.planned_qty}</td>
            <td className="px-4 py-2.5">{o.produced_qty}</td>
            <td className="px-4 py-2.5 capitalize">{o.status}</td>
            <td className="px-4 py-2.5">
              {focus === 'qc' ? (
                <span>{o.scrap_qty}</span>
              ) : (
                <div className="flex gap-2">
                  {cfg.showIssue && o.status !== 'completed' && (
                    <Button variant="ghost" onClick={() => issue.mutate(o.id)}>Issue</Button>
                  )}
                  {cfg.showReceive && o.status !== 'completed' && (
                    <Button variant="ghost" onClick={() => {
                      const qty = Number(prompt('Finished quantity to receive?', o.planned_qty))
                      if (qty > 0) receive.mutate({ id: o.id, qty })
                    }}>Receive</Button>
                  )}
                </div>
              )}
            </td>
          </tr>
        ))}
        {orders.length === 0 && <tr><td colSpan={6} className="px-4 py-8 text-center text-muted">Nothing here.</td></tr>}
      </Table>

      {open && (
        <Modal title="New Production Order" onClose={() => setOpen(false)}>
          <form onSubmit={(e: FormEvent) => { e.preventDefault(); create.mutate() }} className="grid gap-4 sm:grid-cols-2">
            <Field label="Finished Product">
              <select className={inputClass} value={form.product_id ?? ''} onChange={(e) => setForm((s) => ({ ...s, product_id: e.target.value }))} required>
                <option value="">Select…</option>
                {products?.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
              </select>
            </Field>
            <Field label="BOM (optional)">
              <select className={inputClass} value={form.bom_id ?? ''} onChange={(e) => setForm((s) => ({ ...s, bom_id: e.target.value }))}>
                <option value="">None (issue manually)</option>
                {boms?.map((b) => <option key={b.id} value={b.id}>{b.bom_no} — {pName(b.product_id)}</option>)}
              </select>
            </Field>
            <Field label="Warehouse">
              <select className={inputClass} value={form.warehouse_id ?? ''} onChange={(e) => setForm((s) => ({ ...s, warehouse_id: e.target.value }))} required>
                <option value="">Select…</option>
                {warehouses?.map((w) => <option key={w.id} value={w.id}>{w.name}</option>)}
              </select>
            </Field>
            <Field label="Planned Qty">
              <input className={inputClass} type="number" step="any" value={form.planned_qty ?? ''} onChange={(e) => setForm((s) => ({ ...s, planned_qty: e.target.value }))} required />
            </Field>
            <div className="col-span-full flex justify-end gap-2">
              <Button variant="ghost" onClick={() => setOpen(false)}>Cancel</Button>
              <Button type="submit" disabled={create.isPending}>{create.isPending ? 'Saving…' : 'Create Order'}</Button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  )
}
