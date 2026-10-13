import { type FormEvent, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { Banner, Button, Field, Modal, PageHeader, Table, apiError, inputClass } from '../../shared/ui/primitives'
import { Customers, JobWork } from '../../shared/lib/resources'

export function JobWorkContractsPage() {
  const qc = useQueryClient()
  const { data: contracts } = useQuery({ queryKey: ['jw-contracts'], queryFn: JobWork.contracts })
  const { data: customers } = useQuery({ queryKey: ['customers'], queryFn: Customers.list })
  const [open, setOpen] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [form, setForm] = useState<Record<string, string>>({ scrap_ownership: 'customer' })

  const cName = (id: number) => customers?.find((c) => c.id === id)?.name ?? `#${id}`
  const create = useMutation({
    mutationFn: () => JobWork.createContract(form),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['jw-contracts'] }); setOpen(false); setForm({ scrap_ownership: 'customer' }) },
    onError: (e) => setError(apiError(e)),
  })
  const set = (k: string, v: string) => setForm((s) => ({ ...s, [k]: v }))

  return (
    <div>
      <PageHeader title="Customer Contracts" subtitle="Contract manufacturing terms (customer-owned material)"
        action={<Button onClick={() => { setOpen(true); setError(null) }}><Plus size={16} /> New Contract</Button>} />
      <Table head={['Contract No', 'Customer', 'Processing Rate', 'Scrap Ownership']}>
        {(contracts ?? []).map((c) => (
          <tr key={c.id} className="text-ink">
            <td className="px-4 py-2.5">{c.contract_no}</td>
            <td className="px-4 py-2.5">{cName(c.customer_id)}</td>
            <td className="px-4 py-2.5">{c.processing_rate}</td>
            <td className="px-4 py-2.5">{c.scrap_ownership}</td>
          </tr>
        ))}
        {(contracts ?? []).length === 0 && <tr><td colSpan={4} className="px-4 py-8 text-center text-muted">No contracts yet.</td></tr>}
      </Table>

      {open && (
        <Modal title="New Contract" onClose={() => setOpen(false)}>
          {error && <Banner kind="error">{error}</Banner>}
          <form onSubmit={(e: FormEvent) => { e.preventDefault(); setError(null); create.mutate() }} className="grid gap-4 sm:grid-cols-2">
            <Field label="Customer">
              <select className={inputClass} value={form.customer_id ?? ''} onChange={(e) => set('customer_id', e.target.value)} required>
                <option value="">Select…</option>
                {customers?.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
              </select>
            </Field>
            <Field label="Processing Rate (per unit)"><input className={inputClass} type="number" step="any" value={form.processing_rate ?? ''} onChange={(e) => set('processing_rate', e.target.value)} /></Field>
            <Field label="Scrap Ownership">
              <select className={inputClass} value={form.scrap_ownership ?? ''} onChange={(e) => set('scrap_ownership', e.target.value)}>
                <option value="customer">Customer</option>
                <option value="company">Company</option>
              </select>
            </Field>
            <Field label="Terms"><input className={inputClass} value={form.terms ?? ''} onChange={(e) => set('terms', e.target.value)} /></Field>
            <div className="col-span-full mt-2 flex justify-end gap-2">
              <Button variant="ghost" onClick={() => setOpen(false)}>Cancel</Button>
              <Button type="submit" disabled={create.isPending}>{create.isPending ? 'Saving…' : 'Save'}</Button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  )
}
