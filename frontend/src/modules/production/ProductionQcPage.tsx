import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Production, Products, type ProductionOrder } from '../../shared/lib/resources'
import { Banner, Button, Field, Modal, PageHeader, Table, apiError, inputClass } from '../../shared/ui/primitives'

const qty = (v: string | number) => Number(v).toLocaleString(undefined, { maximumFractionDigits: 4 })
const RESULT_STYLE: Record<string, string> = {
  pass: 'bg-emerald-50 text-emerald-700',
  partial: 'bg-amber-50 text-amber-700',
  fail: 'bg-rose-50 text-rose-700',
}

export function ProductionQcPage() {
  const qc = useQueryClient()
  const { data: orders } = useQuery({ queryKey: ['prod-orders'], queryFn: Production.orders })
  const { data: products } = useQuery({ queryKey: ['products'], queryFn: Products.list })
  const { data: records } = useQuery({ queryKey: ['prod-qc'], queryFn: Production.qcList })

  const [target, setTarget] = useState<ProductionOrder | null>(null)
  const [form, setForm] = useState<Record<string, string>>({})
  const [msg, setMsg] = useState<{ kind: 'error' | 'success'; text: string } | null>(null)

  const pName = (id: number) => products?.find((p) => p.id === id)?.name ?? `#${id}`
  const inspectable = (orders ?? []).filter((o) => o.status === 'completed' || o.status === 'released')

  const save = useMutation({
    mutationFn: () => Production.recordQc(target!.id, {
      inspected_qty: Number(form.inspected_qty),
      passed_qty: Number(form.passed_qty),
      rejected_qty: form.rejected_qty ? Number(form.rejected_qty) : undefined,
      inspector: form.inspector || undefined,
      notes: form.notes || undefined,
    }),
    onSuccess: () => { setMsg({ kind: 'success', text: `QC recorded for ${target!.order_no}.` }); setTarget(null); setForm({}); qc.invalidateQueries({ queryKey: ['prod-qc'] }) },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })

  const open = (o: ProductionOrder) => { setTarget(o); setMsg(null); setForm({ inspected_qty: o.produced_qty }) }
  const set = (k: string, v: string) => setForm((s) => ({ ...s, [k]: v }))

  return (
    <div>
      <PageHeader title="Quality Control" subtitle="Inspect produced goods — record passed / rejected quantities and the verdict." />
      {msg && <Banner kind={msg.kind}>{msg.text}</Banner>}

      <h3 className="mb-2 text-sm font-semibold text-ink">Orders to inspect</h3>
      <div className="mb-8">
        <Table head={['Order', 'Product', 'Produced', 'Status', 'Action']}>
          {inspectable.map((o) => (
            <tr key={o.id} className="text-ink">
              <td className="px-4 py-2.5 font-medium">{o.order_no}</td>
              <td className="px-4 py-2.5">{pName(o.product_id)}</td>
              <td className="px-4 py-2.5">{qty(o.produced_qty)}</td>
              <td className="px-4 py-2.5 capitalize">{o.status}</td>
              <td className="px-4 py-2.5"><Button variant="ghost" onClick={() => open(o)}>Record QC</Button></td>
            </tr>
          ))}
          {inspectable.length === 0 && <tr><td colSpan={5} className="px-4 py-8 text-center text-muted">No orders ready for inspection.</td></tr>}
        </Table>
      </div>

      <h3 className="mb-2 text-sm font-semibold text-ink">QC history</h3>
      <Table head={['QC No', 'Order', 'Inspected', 'Passed', 'Rejected', 'Result', 'Inspector']}>
        {(records ?? []).map((r) => (
          <tr key={r.id} className="text-ink">
            <td className="px-4 py-2.5 font-medium">{r.qc_no}</td>
            <td className="px-4 py-2.5">{r.order?.order_no ?? `#${r.production_order_id}`}</td>
            <td className="px-4 py-2.5">{qty(r.inspected_qty)}</td>
            <td className="px-4 py-2.5">{qty(r.passed_qty)}</td>
            <td className="px-4 py-2.5">{qty(r.rejected_qty)}</td>
            <td className="px-4 py-2.5"><span className={`rounded-full px-2 py-0.5 text-xs font-medium capitalize ${RESULT_STYLE[r.result]}`}>{r.result}</span></td>
            <td className="px-4 py-2.5">{r.inspector ?? '—'}</td>
          </tr>
        ))}
        {(records ?? []).length === 0 && <tr><td colSpan={7} className="px-4 py-8 text-center text-muted">No inspections recorded yet.</td></tr>}
      </Table>

      {target && (
        <Modal title={`QC — ${target.order_no}`} onClose={() => setTarget(null)}>
          {msg?.kind === 'error' && <Banner kind="error">{msg.text}</Banner>}
          <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); save.mutate() }}>
            <Field label="Inspected qty"><input className={inputClass} type="number" step="any" value={form.inspected_qty ?? ''} onChange={(e) => set('inspected_qty', e.target.value)} required /></Field>
            <Field label="Passed qty"><input className={inputClass} type="number" step="any" value={form.passed_qty ?? ''} onChange={(e) => set('passed_qty', e.target.value)} required /></Field>
            <Field label="Rejected qty (optional — defaults to inspected − passed)"><input className={inputClass} type="number" step="any" value={form.rejected_qty ?? ''} onChange={(e) => set('rejected_qty', e.target.value)} /></Field>
            <Field label="Inspector"><input className={inputClass} value={form.inspector ?? ''} onChange={(e) => set('inspector', e.target.value)} /></Field>
            <Field label="Notes"><input className={inputClass} value={form.notes ?? ''} onChange={(e) => set('notes', e.target.value)} /></Field>
            <div className="flex justify-end gap-2">
              <Button variant="ghost" onClick={() => setTarget(null)}>Cancel</Button>
              <Button type="submit" disabled={save.isPending}>{save.isPending ? 'Saving…' : 'Record QC'}</Button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  )
}
