import { type FormEvent, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { Banner, Button, Field, Modal, PageHeader, Table, apiError, inputClass } from '../../shared/ui/primitives'
import { Accounts, FixedAssets } from '../../shared/lib/resources'

export function AssetCategoriesPage() {
  const qc = useQueryClient()
  const { data: cats } = useQuery({ queryKey: ['asset-categories'], queryFn: FixedAssets.categories })
  const { data: accounts } = useQuery({ queryKey: ['accounts'], queryFn: Accounts.list })
  const assetAcc = (accounts ?? []).filter((a) => a.type === 'asset')
  const expenseAcc = (accounts ?? []).filter((a) => a.type === 'expense')

  const [open, setOpen] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [form, setForm] = useState<Record<string, string>>({ useful_life_months: '60' })

  const create = useMutation({
    mutationFn: () => FixedAssets.createCategory(form),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['asset-categories'] }); setOpen(false); setForm({ useful_life_months: '60' }) },
    onError: (e) => setError(apiError(e)),
  })
  const set = (k: string, v: string) => setForm((s) => ({ ...s, [k]: v }))

  return (
    <div>
      <PageHeader title="Asset Categories" subtitle="Each category maps to its asset / depreciation / accumulated GL accounts"
        action={<Button onClick={() => { setOpen(true); setError(null) }}><Plus size={16} /> New Category</Button>} />
      <Table head={['Code', 'Name', 'Useful life (months)']}>
        {(cats ?? []).map((c) => (
          <tr key={c.id} className="text-ink">
            <td className="px-4 py-2.5">{c.code}</td>
            <td className="px-4 py-2.5">{c.name}</td>
            <td className="px-4 py-2.5">{c.useful_life_months}</td>
          </tr>
        ))}
        {(cats ?? []).length === 0 && <tr><td colSpan={3} className="px-4 py-8 text-center text-muted">No categories yet.</td></tr>}
      </Table>

      {open && (
        <Modal title="New Asset Category" onClose={() => setOpen(false)}>
          {error && <Banner kind="error">{error}</Banner>}
          <form onSubmit={(e: FormEvent) => { e.preventDefault(); setError(null); create.mutate() }} className="grid gap-4 sm:grid-cols-2">
            <Field label="Code"><input className={inputClass} value={form.code ?? ''} onChange={(e) => set('code', e.target.value)} required /></Field>
            <Field label="Name"><input className={inputClass} value={form.name ?? ''} onChange={(e) => set('name', e.target.value)} required /></Field>
            <Field label="Useful life (months)"><input className={inputClass} type="number" value={form.useful_life_months ?? ''} onChange={(e) => set('useful_life_months', e.target.value)} required /></Field>
            <Field label="Asset Account">
              <select className={inputClass} value={form.asset_account_id ?? ''} onChange={(e) => set('asset_account_id', e.target.value)} required>
                <option value="">Select…</option>
                {assetAcc.map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>)}
              </select>
            </Field>
            <Field label="Depreciation Expense Account">
              <select className={inputClass} value={form.depreciation_expense_account_id ?? ''} onChange={(e) => set('depreciation_expense_account_id', e.target.value)} required>
                <option value="">Select…</option>
                {expenseAcc.map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>)}
              </select>
            </Field>
            <Field label="Accumulated Depreciation Account">
              <select className={inputClass} value={form.accumulated_account_id ?? ''} onChange={(e) => set('accumulated_account_id', e.target.value)} required>
                <option value="">Select…</option>
                {assetAcc.map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>)}
              </select>
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
