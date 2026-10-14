import { type FormEvent, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, Trash2 } from 'lucide-react'
import { Banner, Button, Field, PageHeader, apiError, inputClass } from '../../shared/ui/primitives'
import { Accounts, Journals } from '../../shared/lib/resources'

interface Line {
  account_id: string
  debit: string
  credit: string
}

export function JournalEntryPage() {
  const qc = useQueryClient()
  const { data: accounts } = useQuery({ queryKey: ['accounts'], queryFn: Accounts.list })

  const today = new Date().toISOString().slice(0, 10)
  const [date, setDate] = useState(today)
  const [narration, setNarration] = useState('')
  const [lines, setLines] = useState<Line[]>([
    { account_id: '', debit: '', credit: '' },
    { account_id: '', debit: '', credit: '' },
  ])
  const [msg, setMsg] = useState<{ kind: 'error' | 'success'; text: string } | null>(null)

  const totalDebit = lines.reduce((s, l) => s + Number(l.debit || 0), 0)
  const totalCredit = lines.reduce((s, l) => s + Number(l.credit || 0), 0)
  const balanced = totalDebit > 0 && Math.abs(totalDebit - totalCredit) < 0.0001

  const mutation = useMutation({
    mutationFn: () =>
      Journals.create({
        date,
        narration,
        lines: lines
          .filter((l) => l.account_id)
          .map((l) => ({
            account_id: Number(l.account_id),
            debit: Number(l.debit || 0),
            credit: Number(l.credit || 0),
          })),
      }),
    onSuccess: (res: unknown) => {
      const no = (res as { entry_no?: string }).entry_no
      setMsg({ kind: 'success', text: `Journal entry ${no ?? ''} posted.` })
      setLines([{ account_id: '', debit: '', credit: '' }, { account_id: '', debit: '', credit: '' }])
      setNarration('')
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
      <PageHeader title="Journal Entry" subtitle="Manual double-entry — must balance to post" />
      {msg && <Banner kind={msg.kind}>{msg.text}</Banner>}

      <form onSubmit={submit} className="space-y-5 rounded-xl border border-slate-200 bg-white p-5">
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Date">
            <input type="date" className={inputClass} value={date} onChange={(e) => setDate(e.target.value)} required />
          </Field>
          <Field label="Narration">
            <input className={inputClass} value={narration} onChange={(e) => setNarration(e.target.value)} />
          </Field>
        </div>

        <div className="space-y-2">
          <div className="grid grid-cols-12 gap-2 text-xs font-medium text-muted">
            <div className="col-span-6">Account</div>
            <div className="col-span-3 text-right">Debit</div>
            <div className="col-span-3 text-right">Credit</div>
          </div>
          {lines.map((l, i) => (
            <div key={i} className="grid grid-cols-12 gap-2">
              <select className={`${inputClass} col-span-6`} value={l.account_id}
                onChange={(e) => setLine(i, 'account_id', e.target.value)}>
                <option value="">Account…</option>
                {accounts?.map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>)}
              </select>
              <input className={`${inputClass} col-span-3 text-right`} placeholder="0" type="number" step="any"
                value={l.debit} onChange={(e) => setLine(i, 'debit', e.target.value)} />
              <div className="col-span-3 flex gap-1">
                <input className={`${inputClass} text-right`} placeholder="0" type="number" step="any"
                  value={l.credit} onChange={(e) => setLine(i, 'credit', e.target.value)} />
                <button type="button" className="shrink-0 rounded-lg border border-slate-300 px-2 text-muted hover:bg-slate-50"
                  onClick={() => setLines((s) => s.filter((_, idx) => idx !== i))}>
                  <Trash2 size={14} />
                </button>
              </div>
            </div>
          ))}
          <Button variant="ghost" onClick={() => setLines((s) => [...s, { account_id: '', debit: '', credit: '' }])}>
            <Plus size={15} /> Add line
          </Button>
        </div>

        <div className="flex items-center justify-between border-t border-slate-100 pt-4 text-sm">
          <span className={balanced ? 'text-emerald-600' : 'text-rose-600'}>
            {balanced ? 'Balanced' : 'Not balanced'}
          </span>
          <span className="text-ink">
            Dr {totalDebit.toLocaleString()} · Cr {totalCredit.toLocaleString()}
          </span>
        </div>
        <div className="flex justify-end">
          <Button type="submit" disabled={mutation.isPending || !balanced}>
            {mutation.isPending ? 'Posting…' : 'Post Entry'}
          </Button>
        </div>
      </form>
    </div>
  )
}
