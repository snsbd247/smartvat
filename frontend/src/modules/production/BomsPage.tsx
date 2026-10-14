import { type FormEvent, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, Trash2 } from 'lucide-react'
import { Banner, Button, Field, Modal, PageHeader, Table, apiError, inputClass } from '../../shared/ui/primitives'
import { Production, Products } from '../../shared/lib/resources'

interface Line {
  component_product_id: string
  qty: string
}

export function BomsPage() {
  const qc = useQueryClient()
  const { data: boms } = useQuery({ queryKey: ['boms'], queryFn: Production.boms })
  const { data: products } = useQuery({ queryKey: ['products'], queryFn: Products.list })
  const [open, setOpen] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [product, setProduct] = useState('')
  const [outputQty, setOutputQty] = useState('1')
  const [lines, setLines] = useState<Line[]>([{ component_product_id: '', qty: '' }])

  const pName = (id: number) => products?.find((p) => p.id === id)?.name ?? `#${id}`

  const create = useMutation({
    mutationFn: () =>
      Production.createBom({
        product_id: Number(product),
        output_qty: Number(outputQty),
        lines: lines.map((l) => ({ component_product_id: Number(l.component_product_id), qty: Number(l.qty) })),
      }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['boms'] }); setOpen(false); setLines([{ component_product_id: '', qty: '' }]); setProduct('') },
    onError: (e) => setError(apiError(e)),
  })
  const set = (i: number, k: keyof Line, v: string) => setLines((s) => s.map((l, idx) => (idx === i ? { ...l, [k]: v } : l)))

  return (
    <div>
      <PageHeader title="Bill of Materials" subtitle="Recipes / input-output coefficients"
        action={<Button onClick={() => { setOpen(true); setError(null) }}><Plus size={16} /> New BOM</Button>} />
      <Table head={['BOM No', 'Product', 'Version', 'Output Qty']}>
        {(boms ?? []).map((b) => (
          <tr key={b.id} className="text-ink">
            <td className="px-4 py-2.5">{b.bom_no}</td>
            <td className="px-4 py-2.5">{pName(b.product_id)}</td>
            <td className="px-4 py-2.5">{b.version}</td>
            <td className="px-4 py-2.5">{b.output_qty}</td>
          </tr>
        ))}
        {(boms ?? []).length === 0 && <tr><td colSpan={4} className="px-4 py-8 text-center text-muted">No BOMs yet.</td></tr>}
      </Table>

      {open && (
        <Modal title="New BOM" onClose={() => setOpen(false)}>
          {error && <Banner kind="error">{error}</Banner>}
          <form onSubmit={(e: FormEvent) => { e.preventDefault(); setError(null); create.mutate() }} className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label="Finished Product">
                <select className={inputClass} value={product} onChange={(e) => setProduct(e.target.value)} required>
                  <option value="">Select…</option>
                  {products?.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                </select>
              </Field>
              <Field label="Output Qty">
                <input className={inputClass} type="number" step="any" value={outputQty} onChange={(e) => setOutputQty(e.target.value)} required />
              </Field>
            </div>
            <div className="space-y-2">
              <div className="text-sm font-medium text-ink">Components (per output qty)</div>
              {lines.map((l, i) => (
                <div key={i} className="grid grid-cols-12 gap-2">
                  <select className={`${inputClass} col-span-8`} value={l.component_product_id} onChange={(e) => set(i, 'component_product_id', e.target.value)} required>
                    <option value="">Component…</option>
                    {products?.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                  </select>
                  <input className={`${inputClass} col-span-3`} placeholder="Qty" type="number" step="any" value={l.qty} onChange={(e) => set(i, 'qty', e.target.value)} required />
                  <button type="button" className="col-span-1 grid place-items-center rounded-lg border border-slate-300 text-muted hover:bg-slate-50" onClick={() => setLines((s) => s.filter((_, idx) => idx !== i))}><Trash2 size={15} /></button>
                </div>
              ))}
              <Button variant="ghost" onClick={() => setLines((s) => [...s, { component_product_id: '', qty: '' }])}><Plus size={15} /> Add component</Button>
            </div>
            <div className="flex justify-end gap-2">
              <Button variant="ghost" onClick={() => setOpen(false)}>Cancel</Button>
              <Button type="submit" disabled={create.isPending}>{create.isPending ? 'Saving…' : 'Save BOM'}</Button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  )
}
