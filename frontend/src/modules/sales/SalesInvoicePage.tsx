import { type FormEvent, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, Trash2 } from 'lucide-react'
import { Banner, Button, Field, PageHeader, apiError, inputClass } from '../../shared/ui/primitives'
import { Customers, PriceDeclarations, Products, Sales } from '../../shared/lib/resources'

interface Line {
  product_id: string
  qty: string
  unit_price: string
}

export function SalesInvoicePage() {
  const qc = useQueryClient()
  const { data: customers } = useQuery({ queryKey: ['customers'], queryFn: Customers.list })
  const { data: products } = useQuery({ queryKey: ['products'], queryFn: Products.list })
  const { data: declared = {} } = useQuery({ queryKey: ['declared-prices'], queryFn: PriceDeclarations.currentMap })

  const [customer, setCustomer] = useState('')
  const [lines, setLines] = useState<Line[]>([{ product_id: '', qty: '', unit_price: '' }])
  const [msg, setMsg] = useState<{ kind: 'error' | 'success'; text: string } | null>(null)

  const total = lines.reduce((s, l) => s + Number(l.qty || 0) * Number(l.unit_price || 0), 0)
  const belowDeclared = lines.filter((l) => {
    const d = declared[Number(l.product_id)]
    return d != null && l.unit_price !== '' && Number(l.unit_price) < d
  })

  const mutation = useMutation({
    mutationFn: () =>
      Sales.invoice({
        customer_id: Number(customer),
        lines: lines.map((l) => ({
          product_id: Number(l.product_id),
          qty: Number(l.qty),
          unit_price: Number(l.unit_price),
          line_total: Number(l.qty) * Number(l.unit_price),
        })),
      }),
    onSuccess: (res: unknown) => {
      const inv = (res as { inv_no?: string }).inv_no
      setMsg({ kind: 'success', text: `Sales invoice ${inv ?? ''} posted — AR and revenue updated.` })
      setLines([{ product_id: '', qty: '', unit_price: '' }])
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
      <PageHeader title="Sales Invoice" subtitle="Local sale — posts Dr AR / Cr Revenue (deliver separately for stock-out)" />
      {msg && <Banner kind={msg.kind}>{msg.text}</Banner>}

      <form onSubmit={submit} className="space-y-5 rounded-xl border border-slate-200 bg-white p-5">
        <Field label="Customer">
          <select className={`${inputClass} sm:max-w-sm`} value={customer} onChange={(e) => setCustomer(e.target.value)} required>
            <option value="">Select customer…</option>
            {customers?.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </select>
        </Field>

        <div className="space-y-2">
          <div className="text-sm font-medium text-ink">Lines</div>
          {lines.map((l, i) => {
            const decl = declared[Number(l.product_id)]
            const below = decl != null && l.unit_price !== '' && Number(l.unit_price) < decl
            return (
            <div key={i}>
              <div className="grid grid-cols-12 items-center gap-2">
                <select className={`${inputClass} col-span-5`} value={l.product_id}
                  onChange={(e) => setLine(i, 'product_id', e.target.value)} required>
                  <option value="">Product…</option>
                  {products?.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                </select>
                <input className={`${inputClass} col-span-2`} placeholder="Qty" type="number" step="any"
                  value={l.qty} onChange={(e) => setLine(i, 'qty', e.target.value)} required />
                <input className={`${inputClass} col-span-2 ${below ? 'border-rose-400 focus:border-rose-400 focus:ring-rose-200' : ''}`} placeholder="Price" type="number" step="any"
                  value={l.unit_price} onChange={(e) => setLine(i, 'unit_price', e.target.value)} required />
                <div className="col-span-2 text-right text-sm text-ink">
                  ৳ {(Number(l.qty || 0) * Number(l.unit_price || 0)).toLocaleString()}
                </div>
                <button type="button" className="col-span-1 grid place-items-center rounded-lg border border-slate-300 text-muted hover:bg-slate-50"
                  onClick={() => setLines((s) => s.filter((_, idx) => idx !== i))}>
                  <Trash2 size={15} />
                </button>
              </div>
              {decl != null && (
                <div className={`col-span-12 pl-1 pt-0.5 text-xs ${below ? 'text-rose-600' : 'text-muted'}`}>
                  Declared price ৳ {decl.toLocaleString()}
                  {' · '}
                  <button type="button" className="font-medium text-brand hover:underline" onClick={() => setLine(i, 'unit_price', String(decl))}>use</button>
                  {below && ' — selling below the declared value'}
                </div>
              )}
            </div>
          )})}
          <Button variant="ghost" onClick={() => setLines((s) => [...s, { product_id: '', qty: '', unit_price: '' }])}>
            <Plus size={15} /> Add line
          </Button>
        </div>

        {belowDeclared.length > 0 && (
          <div className="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
            {belowDeclared.length} line(s) are priced below the declared VAT value. You can still post, but review the price declaration.
          </div>
        )}

        <div className="flex items-center justify-between border-t border-slate-100 pt-4">
          <div className="text-sm text-muted">Total</div>
          <div className="text-lg font-semibold text-ink">৳ {total.toLocaleString()}</div>
        </div>
        <div className="flex justify-end">
          <Button type="submit" disabled={mutation.isPending}>
            {mutation.isPending ? 'Posting…' : 'Post Sales Invoice'}
          </Button>
        </div>
      </form>
    </div>
  )
}
