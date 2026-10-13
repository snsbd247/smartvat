import { type FormEvent, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { Banner, Button, Field, Modal, PageHeader, Table, apiError, inputClass } from '../../shared/ui/primitives'
import { Customers, JobWork, Products, Warehouses } from '../../shared/lib/resources'

export function JobWorkOrdersPage() {
  const qc = useQueryClient()
  const { data: orders } = useQuery({ queryKey: ['jw-orders'], queryFn: JobWork.orders })
  const { data: customers } = useQuery({ queryKey: ['customers'], queryFn: Customers.list })
  const { data: contracts } = useQuery({ queryKey: ['jw-contracts'], queryFn: JobWork.contracts })
  const { data: products } = useQuery({ queryKey: ['products'], queryFn: Products.list })
  const { data: warehouses } = useQuery({ queryKey: ['warehouses'], queryFn: Warehouses.list })

  const [open, setOpen] = useState(false)
  const [matFor, setMatFor] = useState<number | null>(null)
  const [matAction, setMatAction] = useState<'receive' | 'consume' | 'deliver'>('receive')
  const [mat, setMat] = useState<{ product_id: string; qty: string }>({ product_id: '', qty: '' })
  const [msg, setMsg] = useState<{ kind: 'error' | 'success'; text: string } | null>(null)
  const [form, setForm] = useState<Record<string, string>>({})

  const cName = (id: number) => customers?.find((c) => c.id === id)?.name ?? `#${id}`

  const create = useMutation({
    mutationFn: () => JobWork.createOrder({
      customer_id: Number(form.customer_id),
      manufacturing_contract_id: form.manufacturing_contract_id ? Number(form.manufacturing_contract_id) : undefined as unknown as number,
      product_id: form.product_id ? Number(form.product_id) : undefined as unknown as number,
      warehouse_id: Number(form.warehouse_id),
      planned_qty: Number(form.planned_qty || 0),
    }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['jw-orders'] }); setOpen(false); setForm({}) },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })
  const material = useMutation({
    mutationFn: () => {
      const lines = [{ product_id: Number(mat.product_id), qty: Number(mat.qty) }]
      if (matAction === 'receive') return JobWork.receiveMaterial(matFor!, lines)
      if (matAction === 'consume') return JobWork.consumeMaterial(matFor!, lines)
      return JobWork.deliver(matFor!, lines)
    },
    onSuccess: () => {
      const label = { receive: 'received into custody', consume: 'consumed', deliver: 'delivered' }[matAction]
      setMsg({ kind: 'success', text: `Customer material ${label} (not company inventory).` })
      setMatFor(null); setMat({ product_id: '', qty: '' })
    },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })
  const invoice = useMutation({
    mutationFn: ({ customer_id, job_work_order_id, qty, rate }: { customer_id: number; job_work_order_id: number; qty: number; rate: number }) =>
      JobWork.processingInvoice({ customer_id, job_work_order_id, qty, rate }),
    onSuccess: () => { setMsg({ kind: 'success', text: 'Processing (service) invoice posted — AR / processing income.' }); qc.invalidateQueries({ queryKey: ['dashboard'] }) },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })
  const set = (k: string, v: string) => setForm((s) => ({ ...s, [k]: v }))

  return (
    <div>
      <PageHeader title="Job Work Orders" subtitle="Customer-owned material custody + processing (service) invoicing"
        action={<Button onClick={() => { setOpen(true); setMsg(null) }}><Plus size={16} /> New Order</Button>} />
      {msg && <Banner kind={msg.kind}>{msg.text}</Banner>}

      <Table head={['Order', 'Customer', 'Status', 'Actions']}>
        {(orders ?? []).map((o) => (
          <tr key={o.id} className="text-ink">
            <td className="px-4 py-2.5">{o.order_no}</td>
            <td className="px-4 py-2.5">{cName(o.customer_id)}</td>
            <td className="px-4 py-2.5">{o.status}</td>
            <td className="px-4 py-2.5">
              <div className="flex flex-wrap gap-2">
                <Button variant="ghost" onClick={() => { setMatFor(o.id); setMatAction('receive'); setMsg(null) }}>Receive</Button>
                <Button variant="ghost" onClick={() => { setMatFor(o.id); setMatAction('consume'); setMsg(null) }}>Consume</Button>
                <Button variant="ghost" onClick={() => { setMatFor(o.id); setMatAction('deliver'); setMsg(null) }}>Deliver</Button>
                <Button variant="ghost" onClick={() => {
                  const qty = Number(prompt('Processing qty?'))
                  const rate = Number(prompt('Processing rate per unit?'))
                  if (qty > 0 && rate >= 0) invoice.mutate({ customer_id: o.customer_id, job_work_order_id: o.id, qty, rate })
                }}>Invoice</Button>
              </div>
            </td>
          </tr>
        ))}
        {(orders ?? []).length === 0 && <tr><td colSpan={4} className="px-4 py-8 text-center text-muted">No orders yet.</td></tr>}
      </Table>

      {open && (
        <Modal title="New Job Work Order" onClose={() => setOpen(false)}>
          <form onSubmit={(e: FormEvent) => { e.preventDefault(); create.mutate() }} className="grid gap-4 sm:grid-cols-2">
            <Field label="Customer">
              <select className={inputClass} value={form.customer_id ?? ''} onChange={(e) => set('customer_id', e.target.value)} required>
                <option value="">Select…</option>
                {customers?.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
              </select>
            </Field>
            <Field label="Contract (optional)">
              <select className={inputClass} value={form.manufacturing_contract_id ?? ''} onChange={(e) => set('manufacturing_contract_id', e.target.value)}>
                <option value="">None</option>
                {contracts?.map((c) => <option key={c.id} value={c.id}>{c.contract_no}</option>)}
              </select>
            </Field>
            <Field label="Output Product (optional)">
              <select className={inputClass} value={form.product_id ?? ''} onChange={(e) => set('product_id', e.target.value)}>
                <option value="">None</option>
                {products?.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
              </select>
            </Field>
            <Field label="Warehouse">
              <select className={inputClass} value={form.warehouse_id ?? ''} onChange={(e) => set('warehouse_id', e.target.value)} required>
                <option value="">Select…</option>
                {warehouses?.map((w) => <option key={w.id} value={w.id}>{w.name}</option>)}
              </select>
            </Field>
            <Field label="Planned Qty"><input className={inputClass} type="number" step="any" value={form.planned_qty ?? ''} onChange={(e) => set('planned_qty', e.target.value)} /></Field>
            <div className="col-span-full flex justify-end gap-2">
              <Button variant="ghost" onClick={() => setOpen(false)}>Cancel</Button>
              <Button type="submit" disabled={create.isPending}>{create.isPending ? 'Saving…' : 'Create Order'}</Button>
            </div>
          </form>
        </Modal>
      )}

      {matFor !== null && (
        <Modal title={`${matAction[0].toUpperCase()}${matAction.slice(1)} Customer Material`} onClose={() => setMatFor(null)}>
          <form onSubmit={(e: FormEvent) => { e.preventDefault(); material.mutate() }} className="grid gap-4">
            <Field label="Product">
              <select className={inputClass} value={mat.product_id} onChange={(e) => setMat((m) => ({ ...m, product_id: e.target.value }))} required>
                <option value="">Select…</option>
                {products?.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
              </select>
            </Field>
            <Field label="Quantity"><input className={inputClass} type="number" step="any" value={mat.qty} onChange={(e) => setMat((m) => ({ ...m, qty: e.target.value }))} required /></Field>
            <div className="flex justify-end gap-2">
              <Button variant="ghost" onClick={() => setMatFor(null)}>Cancel</Button>
              <Button type="submit" disabled={material.isPending}>{material.isPending ? 'Saving…' : 'Confirm'}</Button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  )
}
