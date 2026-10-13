import { type FormEvent, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, ShieldCheck } from 'lucide-react'
import { Banner, Button, Field, Modal, PageHeader, Table, apiError, inputClass } from '../../shared/ui/primitives'
import { Vat } from '../../shared/lib/resources'

const TX = ['local_sale', 'export_sale', 'local_purchase', 'import_purchase', 'service_purchase']
const TREAT = ['standard', 'zero_rated', 'exempt']

export function VatMappingsPage() {
  const qc = useQueryClient()
  const { data: mappings } = useQuery({ queryKey: ['vat-mappings'], queryFn: Vat.mappings })
  const { data: notes } = useQuery({ queryKey: ['vat-notes'], queryFn: Vat.notes })
  const [open, setOpen] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [form, setForm] = useState<Record<string, string>>({})

  const create = useMutation({
    mutationFn: () => Vat.createMapping(form),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['vat-mappings'] }); setOpen(false); setForm({}) },
    onError: (e) => setError(apiError(e)),
  })
  const approve = useMutation({
    mutationFn: (id: number) => Vat.approveMapping(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['vat-mappings'] }),
  })

  const noteCode = (id: number) => notes?.find((n) => n.id === id)?.code ?? `#${id}`

  return (
    <div>
      <PageHeader
        title="VAT Note Mappings"
        subtitle="Classification → Mushak 9.1 Note. Approve = practitioner-verified before it can classify."
        action={<Button onClick={() => { setOpen(true); setError(null) }}><Plus size={16} /> New Mapping</Button>}
      />
      <Table head={['Txn Type', 'Treatment', 'Rate', 'Note', 'Approval', '']}>
        {(mappings ?? []).map((m) => (
          <tr key={m.id} className="text-ink">
            <td className="px-4 py-2.5">{m.transaction_type_code}</td>
            <td className="px-4 py-2.5">{m.tax_treatment_code}</td>
            <td className="px-4 py-2.5">{m.vat_rate_code ?? 'any'}</td>
            <td className="px-4 py-2.5">{noteCode(m.vat_return_note_id)}</td>
            <td className="px-4 py-2.5">
              <span className={m.approval_status === 'approved' ? 'text-emerald-600' : 'text-amber-600'}>
                {m.approval_status}
              </span>
            </td>
            <td className="px-4 py-2.5">
              {m.approval_status !== 'approved' && (
                <Button variant="ghost" onClick={() => approve.mutate(m.id)}>
                  <ShieldCheck size={14} /> Approve
                </Button>
              )}
            </td>
          </tr>
        ))}
        {(mappings ?? []).length === 0 && (
          <tr><td colSpan={6} className="px-4 py-8 text-center text-muted">No mappings yet.</td></tr>
        )}
      </Table>

      {open && (
        <Modal title="New Note Mapping" onClose={() => setOpen(false)}>
          {error && <Banner kind="error">{error}</Banner>}
          <form onSubmit={(e: FormEvent) => { e.preventDefault(); setError(null); create.mutate() }} className="grid gap-4 sm:grid-cols-2">
            <Field label="Transaction Type">
              <select className={inputClass} value={form.transaction_type_code ?? ''} onChange={(e) => setForm((s) => ({ ...s, transaction_type_code: e.target.value }))} required>
                <option value="">Select…</option>
                {TX.map((t) => <option key={t} value={t}>{t}</option>)}
              </select>
            </Field>
            <Field label="Tax Treatment">
              <select className={inputClass} value={form.tax_treatment_code ?? ''} onChange={(e) => setForm((s) => ({ ...s, tax_treatment_code: e.target.value }))} required>
                <option value="">Select…</option>
                {TREAT.map((t) => <option key={t} value={t}>{t}</option>)}
              </select>
            </Field>
            <Field label="VAT Rate code (optional)">
              <input className={inputClass} value={form.vat_rate_code ?? ''} onChange={(e) => setForm((s) => ({ ...s, vat_rate_code: e.target.value }))} />
            </Field>
            <Field label="Note Number">
              <select className={inputClass} value={form.vat_return_note_id ?? ''} onChange={(e) => setForm((s) => ({ ...s, vat_return_note_id: e.target.value }))} required>
                <option value="">Select note…</option>
                {notes?.map((n) => <option key={n.id} value={n.id}>{n.code} — {n.title}</option>)}
              </select>
            </Field>
            <Field label="Subform (optional)">
              <input className={inputClass} value={form.subform ?? ''} onChange={(e) => setForm((s) => ({ ...s, subform: e.target.value }))} />
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
