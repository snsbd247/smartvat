import { type FormEvent, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Banner, Button, Field, PageHeader, Table, apiError, inputClass } from '../../shared/ui/primitives'
import { Accounts, BankRec } from '../../shared/lib/resources'

const money = (v: string | number) => Number(v).toLocaleString(undefined, { maximumFractionDigits: 2 })

export function BankReconciliationPage() {
  const qc = useQueryClient()
  const { data: accounts } = useQuery({ queryKey: ['accounts'], queryFn: Accounts.list })
  const assets = (accounts ?? []).filter((a) => a.type === 'asset')
  const [account, setAccount] = useState('')
  const [statementDate, setStatementDate] = useState(new Date().toISOString().slice(0, 10))
  const [statementBalance, setStatementBalance] = useState('')
  const [checked, setChecked] = useState<Set<number>>(new Set())
  const [msg, setMsg] = useState<{ kind: 'error' | 'success'; text: string } | null>(null)

  const { data: lines } = useQuery({
    queryKey: ['bank-unrec', account],
    queryFn: () => BankRec.unreconciled(Number(account)),
    enabled: !!account,
  })

  const m = useMutation({
    mutationFn: () => BankRec.reconcile({ account_id: Number(account), statement_date: statementDate, statement_balance: Number(statementBalance), line_ids: [...checked] }),
    onSuccess: (r: unknown) => {
      const rec = r as { reconciled_balance: string; difference: string }
      setMsg({ kind: 'success', text: `Reconciled balance ৳ ${money(rec.reconciled_balance)} · difference ৳ ${money(rec.difference)}` })
      setChecked(new Set())
      qc.invalidateQueries({ queryKey: ['bank-unrec', account] })
    },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })
  const toggle = (id: number) => setChecked((s) => { const n = new Set(s); n.has(id) ? n.delete(id) : n.add(id); return n })

  return (
    <div>
      <PageHeader title="Bank Reconciliation" subtitle="Tick cleared transactions and enter the statement balance" />
      {msg && <Banner kind={msg.kind}>{msg.text}</Banner>}

      <form onSubmit={(e: FormEvent) => { e.preventDefault(); setMsg(null); m.mutate() }} className="space-y-4">
        <div className="grid max-w-3xl gap-4 sm:grid-cols-3">
          <Field label="Bank/Cash Account">
            <select className={inputClass} value={account} onChange={(e) => setAccount(e.target.value)} required>
              <option value="">Select…</option>
              {assets.map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>)}
            </select>
          </Field>
          <Field label="Statement Date"><input className={inputClass} type="date" value={statementDate} onChange={(e) => setStatementDate(e.target.value)} required /></Field>
          <Field label="Statement Balance"><input className={inputClass} type="number" step="any" value={statementBalance} onChange={(e) => setStatementBalance(e.target.value)} required /></Field>
        </div>

        {account && (
          <>
            <Table head={['Cleared', 'Date', 'Entry', 'Narration', 'Debit', 'Credit']}>
              {(lines ?? []).map((l) => (
                <tr key={l.id} className="text-ink">
                  <td className="px-4 py-2.5"><input type="checkbox" checked={checked.has(l.id)} onChange={() => toggle(l.id)} /></td>
                  <td className="px-4 py-2.5">{l.date}</td>
                  <td className="px-4 py-2.5">{l.entry_no}</td>
                  <td className="px-4 py-2.5">{l.narration}</td>
                  <td className="px-4 py-2.5 text-right">{money(l.debit)}</td>
                  <td className="px-4 py-2.5 text-right">{money(l.credit)}</td>
                </tr>
              ))}
              {(lines ?? []).length === 0 && <tr><td colSpan={6} className="px-4 py-8 text-center text-muted">Nothing to reconcile.</td></tr>}
            </Table>
            <div className="flex justify-end"><Button type="submit" disabled={m.isPending}>{m.isPending ? 'Reconciling…' : 'Reconcile'}</Button></div>
          </>
        )}
      </form>
    </div>
  )
}
