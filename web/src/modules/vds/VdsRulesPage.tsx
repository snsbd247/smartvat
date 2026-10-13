import { type FormEvent, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, ShieldCheck } from 'lucide-react'
import { Banner, Button, Field, Modal, PageHeader, Table, apiError, inputClass } from '../../shared/ui/primitives'
import { Vds } from '../../shared/lib/resources'

export function VdsRulesPage() {
  const qc = useQueryClient()
  const { data: rules } = useQuery({ queryKey: ['vds-rules'], queryFn: Vds.rules })
  const [open, setOpen] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [form, setForm] = useState<Record<string, string>>({})

  const create = useMutation({
    mutationFn: () => Vds.createRule(form),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['vds-rules'] }); setOpen(false); setForm({}) },
    onError: (e) => setError(apiError(e)),
  })
  const approve = useMutation({
    mutationFn: (id: number) => Vds.approveRule(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['vds-rules'] }),
  })

  return (
    <div>
      <PageHeader
        title="VDS Rules & Rates"
        subtitle="Configurable, verified deduction rates (no hard-coded statutory rate)"
        action={<Button onClick={() => { setOpen(true); setError(null) }}><Plus size={16} /> New Rule</Button>}
      />
      <Table head={['Service Category', 'Rate %', 'Approval', 'Verification', '']}>
        {(rules ?? []).map((r) => (
          <tr key={r.id} className="text-ink">
            <td className="px-4 py-2.5">{r.service_category}</td>
            <td className="px-4 py-2.5">{r.rate}</td>
            <td className="px-4 py-2.5">
              <span className={r.approval_status === 'approved' ? 'text-emerald-600' : 'text-amber-600'}>{r.approval_status}</span>
            </td>
            <td className="px-4 py-2.5">{r.verification_status}</td>
            <td className="px-4 py-2.5">
              {r.approval_status !== 'approved' && (
                <Button variant="ghost" onClick={() => approve.mutate(r.id)}><ShieldCheck size={14} /> Approve</Button>
              )}
            </td>
          </tr>
        ))}
        {(rules ?? []).length === 0 && (
          <tr><td colSpan={5} className="px-4 py-8 text-center text-muted">No rules yet.</td></tr>
        )}
      </Table>

      {open && (
        <Modal title="New VDS Rule" onClose={() => setOpen(false)}>
          {error && <Banner kind="error">{error}</Banner>}
          <form onSubmit={(e: FormEvent) => { e.preventDefault(); setError(null); create.mutate() }} className="grid gap-4 sm:grid-cols-2">
            <Field label="Service Category">
              <input className={inputClass} value={form.service_category ?? ''} onChange={(e) => setForm((s) => ({ ...s, service_category: e.target.value }))} required />
            </Field>
            <Field label="Rate %">
              <input className={inputClass} type="number" step="any" value={form.rate ?? ''} onChange={(e) => setForm((s) => ({ ...s, rate: e.target.value }))} required />
            </Field>
            <Field label="Effective From (optional)">
              <input className={inputClass} value={form.effective_from ?? ''} onChange={(e) => setForm((s) => ({ ...s, effective_from: e.target.value }))} placeholder="YYYY-MM-DD" />
            </Field>
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
