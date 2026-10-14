import { type FormEvent, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowRight, FileText, Plus, Trash2 } from 'lucide-react'
import { Banner, Button, Field, Modal, PageHeader, Table, apiError, inputClass } from '../../shared/ui/primitives'
import { Customers, Products, SalesOrders, type SalesOrderRow } from '../../shared/lib/resources'

interface Line { product_id: string; qty: string; unit_price: string }

const money = (v: string | number) => '৳ ' + Number(v).toLocaleString(undefined, { maximumFractionDigits: 2 })

const STATUS_STYLE: Record<string, string> = {
  quotation: 'bg-sky-50 text-sky-700',
  confirmed: 'bg-amber-50 text-amber-700',
  invoiced: 'bg-emerald-50 text-emerald-700',
  cancelled: 'bg-slate-100 text-slate-500',
}

/**
 * mode "quotation": create & list quotations, each convertible to a firm order.
 * mode "order":     create & list confirmed orders, each invoiceable.
 */
export function SalesOrderPage({ mode }: { mode: 'quotation' | 'order' }) {
  const qc = useQueryClient()
  const { data: customers } = useQuery({ queryKey: ['customers'], queryFn: Customers.list })
  const { data: products } = useQuery({ queryKey: ['products'], queryFn: Products.list })

  // Quotation view shows quotations; Order view shows confirmed + invoiced.
  const { data: rows } = useQuery({
    queryKey: ['sales-orders', mode],
    queryFn: async () =>
      mode === 'quotation'
        ? SalesOrders.list('quotation')
        : [...(await SalesOrders.list('confirmed')), ...(await SalesOrders.list('invoiced'))],
  })

  const [open, setOpen] = useState(false)
  const [customer, setCustomer] = useState('')
  const [reference, setReference] = useState('')
  const [lines, setLines] = useState<Line[]>([{ product_id: '', qty: '', unit_price: '' }])
  const [msg, setMsg] = useState<{ kind: 'error' | 'success'; text: string } | null>(null)

  const nameOf = (id: number) => customers?.find((c) => c.id === id)?.name ?? `#${id}`
  const title = mode === 'quotation' ? 'Quotation' : 'Sales Order'
  const total = lines.reduce((s, l) => s + Number(l.qty || 0) * Number(l.unit_price || 0), 0)

  const refresh = () => qc.invalidateQueries({ queryKey: ['sales-orders'] })

  const create = useMutation({
    mutationFn: () =>
      SalesOrders.create({
        customer_id: Number(customer),
        status: mode === 'quotation' ? 'quotation' : 'confirmed',
        reference: reference || undefined,
        lines: lines.map((l) => ({
          product_id: Number(l.product_id),
          qty: Number(l.qty),
          unit_price: Number(l.unit_price),
          line_total: Number(l.qty) * Number(l.unit_price),
        })),
      }),
    onSuccess: (res: unknown) => {
      setMsg({ kind: 'success', text: `${title} ${(res as { so_no?: string }).so_no ?? ''} created.` })
      setOpen(false); setCustomer(''); setReference(''); setLines([{ product_id: '', qty: '', unit_price: '' }])
      refresh()
    },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })

  const confirm = useMutation({
    mutationFn: (id: number) => SalesOrders.confirm(id),
    onSuccess: () => { setMsg({ kind: 'success', text: 'Quotation confirmed into an order.' }); refresh() },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })

  const invoice = useMutation({
    mutationFn: (id: number) => SalesOrders.invoice(id),
    onSuccess: (res: unknown) => {
      setMsg({ kind: 'success', text: `Invoice ${(res as { inv_no?: string }).inv_no ?? ''} raised — AR & revenue posted.` })
      refresh(); qc.invalidateQueries({ queryKey: ['dashboard'] })
    },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })

  function submit(e: FormEvent) { e.preventDefault(); setMsg(null); create.mutate() }
  const setLine = (i: number, k: keyof Line, v: string) => setLines((s) => s.map((l, idx) => (idx === i ? { ...l, [k]: v } : l)))

  return (
    <div>
      <PageHeader
        title={mode === 'quotation' ? 'Quotations' : 'Sales Orders'}
        subtitle={mode === 'quotation'
          ? 'Offers to customers. Confirm a quotation to turn it into a firm order.'
          : 'Firm orders. Raise an invoice to post AR and revenue.'}
        action={<Button onClick={() => { setOpen(true); setMsg(null) }}><Plus size={16} /> New {title}</Button>}
      />
      {msg && <Banner kind={msg.kind}>{msg.text}</Banner>}

      <Table head={['No.', 'Customer', 'Date', 'Total', 'Status', 'Action']}>
        {(rows ?? []).map((o: SalesOrderRow) => (
          <tr key={o.id} className="text-ink">
            <td className="px-4 py-2.5 font-medium">{o.so_no}</td>
            <td className="px-4 py-2.5">{nameOf(o.customer_id)}</td>
            <td className="px-4 py-2.5">{o.date ? new Date(o.date).toLocaleDateString() : '—'}</td>
            <td className="px-4 py-2.5">{money(o.total)}</td>
            <td className="px-4 py-2.5">
              <span className={`rounded-full px-2 py-0.5 text-xs font-medium capitalize ${STATUS_STYLE[o.status] ?? ''}`}>{o.status}</span>
            </td>
            <td className="px-4 py-2.5">
              {mode === 'quotation' && o.status === 'quotation' && (
                <Button variant="ghost" onClick={() => confirm.mutate(o.id)} disabled={confirm.isPending}>
                  <ArrowRight size={14} /> Confirm
                </Button>
              )}
              {mode === 'order' && o.status === 'confirmed' && (
                <Button variant="ghost" onClick={() => invoice.mutate(o.id)} disabled={invoice.isPending}>
                  <FileText size={14} /> Invoice
                </Button>
              )}
              {o.status === 'invoiced' && <span className="text-xs text-muted">Invoiced</span>}
            </td>
          </tr>
        ))}
        {(rows ?? []).length === 0 && (
          <tr><td colSpan={6} className="px-4 py-8 text-center text-muted">No {mode === 'quotation' ? 'quotations' : 'orders'} yet.</td></tr>
        )}
      </Table>

      {open && (
        <Modal title={`New ${title}`} onClose={() => setOpen(false)}>
          {msg?.kind === 'error' && <Banner kind="error">{msg.text}</Banner>}
          <form onSubmit={submit} className="space-y-4">
            <Field label="Customer">
              <select className={inputClass} value={customer} onChange={(e) => setCustomer(e.target.value)} required>
                <option value="">Select customer…</option>
                {customers?.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
              </select>
            </Field>
            <Field label="Reference (optional)">
              <input className={inputClass} value={reference} onChange={(e) => setReference(e.target.value)} placeholder="PO number, enquiry ref…" />
            </Field>

            <div className="space-y-2">
              <div className="text-sm font-medium text-ink">Lines</div>
              {lines.map((l, i) => (
                <div key={i} className="grid grid-cols-12 items-center gap-2">
                  <select className={`${inputClass} col-span-5`} value={l.product_id} onChange={(e) => setLine(i, 'product_id', e.target.value)} required>
                    <option value="">Product…</option>
                    {products?.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                  </select>
                  <input className={`${inputClass} col-span-2`} placeholder="Qty" type="number" step="any" value={l.qty} onChange={(e) => setLine(i, 'qty', e.target.value)} required />
                  <input className={`${inputClass} col-span-2`} placeholder="Price" type="number" step="any" value={l.unit_price} onChange={(e) => setLine(i, 'unit_price', e.target.value)} required />
                  <div className="col-span-2 text-right text-sm text-ink">{money(Number(l.qty || 0) * Number(l.unit_price || 0))}</div>
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
              <div className="text-sm text-muted">Total</div>
              <div className="text-lg font-semibold text-ink">{money(total)}</div>
            </div>
            <div className="flex justify-end gap-2">
              <Button variant="ghost" onClick={() => setOpen(false)}>Cancel</Button>
              <Button type="submit" disabled={create.isPending}>{create.isPending ? 'Saving…' : `Create ${title}`}</Button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  )
}
