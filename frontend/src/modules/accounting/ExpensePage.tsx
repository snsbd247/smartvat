import { type FormEvent, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Banner, Button, Field, PageHeader, Table, apiError, inputClass } from '../../shared/ui/primitives'
import { Accounts, Expenses } from '../../shared/lib/resources'

const money = (v: string) => Number(v).toLocaleString(undefined, { maximumFractionDigits: 2 })

export function ExpensePage() {
  const qc = useQueryClient()
  const { data: accounts } = useQuery({ queryKey: ['accounts'], queryFn: Accounts.list })
  const { data: list } = useQuery({ queryKey: ['expenses'], queryFn: Expenses.list })
  const expenseAcc = (accounts ?? []).filter((a) => a.type === 'expense')
  const assets = (accounts ?? []).filter((a) => a.type === 'asset')

  const [f, setF] = useState<Record<string, string>>({})
  const [msg, setMsg] = useState<{ kind: 'error' | 'success'; text: string } | null>(null)

  const m = useMutation({
    mutationFn: () => Expenses.create({ expense_account_id: Number(f.expense), paid_from_account_id: Number(f.paid), amount: Number(f.amount), narration: f.narration }),
    onSuccess: () => { setMsg({ kind: 'success', text: 'Expense posted (Dr Expense / Cr Cash·Bank).' }); setF({}); qc.invalidateQueries({ queryKey: ['expenses'] }); qc.invalidateQueries({ queryKey: ['dashboard'] }) },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })
  const set = (k: string, v: string) => setF((s) => ({ ...s, [k]: v }))

  return (
    <div>
      <PageHeader title="Expenses" subtitle="Record an expense — Dr Expense account / Cr Cash·Bank" />
      {msg && <Banner kind={msg.kind}>{msg.text}</Banner>}
      <form onSubmit={(e: FormEvent) => { e.preventDefault(); setMsg(null); m.mutate() }} className="mb-6 grid max-w-2xl gap-4 rounded-xl border border-slate-200 bg-white p-5 sm:grid-cols-2">
        <Field label="Expense Account">
          <select className={inputClass} value={f.expense ?? ''} onChange={(e) => set('expense', e.target.value)} required>
            <option value="">Select…</option>
            {expenseAcc.map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>)}
          </select>
        </Field>
        <Field label="Paid from (Cash/Bank)">
          <select className={inputClass} value={f.paid ?? ''} onChange={(e) => set('paid', e.target.value)} required>
            <option value="">Select…</option>
            {assets.map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>)}
          </select>
        </Field>
        <Field label="Amount"><input className={inputClass} type="number" step="any" value={f.amount ?? ''} onChange={(e) => set('amount', e.target.value)} required /></Field>
        <Field label="Narration"><input className={inputClass} value={f.narration ?? ''} onChange={(e) => set('narration', e.target.value)} /></Field>
        <div className="col-span-full flex justify-end"><Button type="submit" disabled={m.isPending}>{m.isPending ? 'Posting…' : 'Post Expense'}</Button></div>
      </form>

      <div className="mb-2 text-sm font-semibold text-ink">Recent Expenses</div>
      <Table head={['No', 'Date', 'Amount', 'Narration']}>
        {(list ?? []).map((e) => (
          <tr key={e.id} className="text-ink">
            <td className="px-4 py-2.5">{e.expense_no}</td>
            <td className="px-4 py-2.5">{e.date}</td>
            <td className="px-4 py-2.5 text-right">{money(e.amount)}</td>
            <td className="px-4 py-2.5">{e.narration}</td>
          </tr>
        ))}
        {(list ?? []).length === 0 && <tr><td colSpan={4} className="px-4 py-8 text-center text-muted">No expenses yet.</td></tr>}
      </Table>
    </div>
  )
}
