import { type FormEvent, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { CheckCircle2, Plus } from 'lucide-react'
import { PriceDeclarations, Products } from '../../shared/lib/resources'
import { Banner, Button, Field, Modal, PageHeader, Table, apiError, inputClass } from '../../shared/ui/primitives'

const money = (v: string | null) => (v == null ? '—' : '৳ ' + Number(v).toLocaleString(undefined, { maximumFractionDigits: 2 }))

export function PriceDeclarationPage() {
  const qc = useQueryClient()
  const { data: rows } = useQuery({ queryKey: ['price-declarations'], queryFn: PriceDeclarations.list })
  const { data: products } = useQuery({ queryKey: ['products'], queryFn: Products.list })
  const [open, setOpen] = useState(false)
  const [form, setForm] = useState<Record<string, string>>({})
  const [msg, setMsg] = useState<{ kind: 'error' | 'success'; text: string } | null>(null)

  const pName = (id: number) => products?.find((p) => p.id === id)?.name ?? `#${id}`
  const refresh = () => qc.invalidateQueries({ queryKey: ['price-declarations'] })

  const create = useMutation({
    mutationFn: () => PriceDeclarations.create({
      product_id: Number(form.product_id),
      declared_price: Number(form.declared_price),
      purchase_value: form.purchase_value ? Number(form.purchase_value) : undefined,
      effective_from: form.effective_from || undefined,
      note: form.note || undefined,
    }),
    onSuccess: () => { setMsg({ kind: 'success', text: 'Price declaration created (draft).' }); setOpen(false); setForm({}); refresh() },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })
  const approve = useMutation({
    mutationFn: (id: number) => PriceDeclarations.approve(id),
    onSuccess: () => { setMsg({ kind: 'success', text: 'Declaration approved.' }); refresh() },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })

  const set = (k: string, v: string) => setForm((s) => ({ ...s, [k]: v }))
  function submit(e: FormEvent) { e.preventDefault(); setMsg(null); create.mutate() }

  return (
    <div>
      <PageHeader title="Price Declaration (মূল্য ঘোষণা)" subtitle="Declare the selling value of a product for VAT. The approved, effective declaration is the current declared price."
        action={<Button onClick={() => { setOpen(true); setMsg(null) }}><Plus size={16} /> New Declaration</Button>} />
      {msg && <Banner kind={msg.kind}>{msg.text}</Banner>}

      <Table head={['No.', 'Product', 'Purchase value', 'Declared price', 'Effective from', 'Status', 'Action']}>
        {(rows ?? []).map((r) => (
          <tr key={r.id} className="text-ink">
            <td className="px-4 py-2.5 font-medium">{r.decl_no}</td>
            <td className="px-4 py-2.5">{r.product?.name ?? pName(r.product_id)}</td>
            <td className="px-4 py-2.5">{money(r.purchase_value)}</td>
            <td className="px-4 py-2.5 font-medium">{money(r.declared_price)}</td>
            <td className="px-4 py-2.5">{r.effective_from ? new Date(r.effective_from).toLocaleDateString() : '—'}</td>
            <td className="px-4 py-2.5">
              <span className={`rounded-full px-2 py-0.5 text-xs font-medium capitalize ${r.status === 'approved' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'}`}>{r.status}</span>
            </td>
            <td className="px-4 py-2.5">
              {r.status === 'draft'
                ? <Button variant="ghost" onClick={() => approve.mutate(r.id)}><CheckCircle2 size={14} /> Approve</Button>
                : <span className="text-xs text-muted">—</span>}
            </td>
          </tr>
        ))}
        {(rows ?? []).length === 0 && <tr><td colSpan={7} className="px-4 py-8 text-center text-muted">No price declarations yet.</td></tr>}
      </Table>

      {open && (
        <Modal title="New Price Declaration" onClose={() => setOpen(false)}>
          {msg?.kind === 'error' && <Banner kind="error">{msg.text}</Banner>}
          <form className="grid gap-4 sm:grid-cols-2" onSubmit={submit}>
            <Field label="Product">
              <select className={inputClass} value={form.product_id ?? ''} onChange={(e) => set('product_id', e.target.value)} required>
                <option value="">Select…</option>
                {products?.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
              </select>
            </Field>
            <Field label="Purchase / landed value (per unit)">
              <input className={inputClass} type="number" step="any" value={form.purchase_value ?? ''} onChange={(e) => set('purchase_value', e.target.value)} />
            </Field>
            <Field label="Declared selling price (per unit)">
              <input className={inputClass} type="number" step="any" value={form.declared_price ?? ''} onChange={(e) => set('declared_price', e.target.value)} required />
            </Field>
            <Field label="Effective from">
              <input className={inputClass} type="date" value={form.effective_from ?? ''} onChange={(e) => set('effective_from', e.target.value)} />
            </Field>
            <Field label="Note"><input className={inputClass} value={form.note ?? ''} onChange={(e) => set('note', e.target.value)} /></Field>
            <div className="col-span-full flex justify-end gap-2">
              <Button variant="ghost" onClick={() => setOpen(false)}>Cancel</Button>
              <Button type="submit" disabled={create.isPending}>{create.isPending ? 'Saving…' : 'Create'}</Button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  )
}
