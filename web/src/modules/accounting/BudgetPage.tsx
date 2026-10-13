import { type FormEvent, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, Trash2 } from 'lucide-react'
import { Banner, Button, Field, Modal, PageHeader, Table, apiError, inputClass } from '../../shared/ui/primitives'
import { Accounts, Budgets } from '../../shared/lib/resources'

interface Line { account_id: string; amount: string }
const money = (v: string) => Number(v).toLocaleString(undefined, { maximumFractionDigits: 2 })

export function BudgetPage() {
  const qc = useQueryClient()
  const { data: budgets } = useQuery({ queryKey: ['budgets'], queryFn: Budgets.list })
  const { data: accounts } = useQuery({ queryKey: ['accounts'], queryFn: Accounts.list })
  const [open, setOpen] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [name, setName] = useState('')
  const [lines, setLines] = useState<Line[]>([{ account_id: '', amount: '' }])
  const [viewId, setViewId] = useState<number | null>(null)

  const { data: vsActual } = useQuery({
    queryKey: ['budget-va', viewId],
    queryFn: () => Budgets.vsActual(viewId!),
    enabled: viewId !== null,
  })

  const create = useMutation({
    mutationFn: () => Budgets.create({ name, lines: lines.map((l) => ({ account_id: Number(l.account_id), amount: Number(l.amount) })) }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['budgets'] }); setOpen(false); setName(''); setLines([{ account_id: '', amount: '' }]) },
    onError: (e) => setError(apiError(e)),
  })
  const set = (i: number, k: keyof Line, v: string) => setLines((s) => s.map((l, idx) => (idx === i ? { ...l, [k]: v } : l)))

  return (
    <div>
      <PageHeader title="Budgets" subtitle="Budget by account, then compare against actuals"
        action={<Button onClick={() => { setOpen(true); setError(null) }}><Plus size={16} /> New Budget</Button>} />
      <Table head={['Name', 'Status', '']}>
        {(budgets ?? []).map((b) => (
          <tr key={b.id} className="text-ink">
            <td className="px-4 py-2.5">{b.name}</td>
            <td className="px-4 py-2.5">{b.status}</td>
            <td className="px-4 py-2.5"><Button variant="ghost" onClick={() => setViewId(b.id)}>Budget vs Actual</Button></td>
          </tr>
        ))}
        {(budgets ?? []).length === 0 && <tr><td colSpan={3} className="px-4 py-8 text-center text-muted">No budgets yet.</td></tr>}
      </Table>

      {viewId !== null && (
        <div className="mt-6">
          <div className="mb-2 text-sm font-semibold text-ink">Budget vs Actual</div>
          <Table head={['Account', 'Budget', 'Actual', 'Variance']}>
            {(vsActual ?? []).map((r, i) => (
              <tr key={i} className="text-ink">
                <td className="px-4 py-2.5">{r.account}</td>
                <td className="px-4 py-2.5 text-right">{money(r.budget)}</td>
                <td className="px-4 py-2.5 text-right">{money(r.actual)}</td>
                <td className={`px-4 py-2.5 text-right ${Number(r.variance) < 0 ? 'text-rose-600' : 'text-emerald-600'}`}>{money(r.variance)}</td>
              </tr>
            ))}
          </Table>
        </div>
      )}

      {open && (
        <Modal title="New Budget" onClose={() => setOpen(false)}>
          {error && <Banner kind="error">{error}</Banner>}
          <form onSubmit={(e: FormEvent) => { e.preventDefault(); setError(null); create.mutate() }} className="space-y-4">
            <Field label="Name"><input className={inputClass} value={name} onChange={(e) => setName(e.target.value)} required /></Field>
            <div className="space-y-2">
              <div className="text-sm font-medium text-ink">Lines</div>
              {lines.map((l, i) => (
                <div key={i} className="grid grid-cols-12 gap-2">
                  <select className={`${inputClass} col-span-8`} value={l.account_id} onChange={(e) => set(i, 'account_id', e.target.value)} required>
                    <option value="">Account…</option>
                    {accounts?.map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>)}
                  </select>
                  <input className={`${inputClass} col-span-3`} placeholder="Amount" type="number" step="any" value={l.amount} onChange={(e) => set(i, 'amount', e.target.value)} required />
                  <button type="button" className="col-span-1 grid place-items-center rounded-lg border border-slate-300 text-muted hover:bg-slate-50" onClick={() => setLines((s) => s.filter((_, idx) => idx !== i))}><Trash2 size={15} /></button>
                </div>
              ))}
              <Button variant="ghost" onClick={() => setLines((s) => [...s, { account_id: '', amount: '' }])}><Plus size={15} /> Add line</Button>
            </div>
            <div className="flex justify-end gap-2">
              <Button variant="ghost" onClick={() => setOpen(false)}>Cancel</Button>
              <Button type="submit" disabled={create.isPending}>{create.isPending ? 'Saving…' : 'Save'}</Button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  )
}
