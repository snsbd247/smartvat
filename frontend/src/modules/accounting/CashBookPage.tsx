import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Field, PageHeader, Table, inputClass } from '../../shared/ui/primitives'
import { Accounts } from '../../shared/lib/resources'

const money = (v: string) => Number(v).toLocaleString(undefined, { maximumFractionDigits: 2 })

export function CashBookPage({ title = 'Cash / Bank Book', subtitle = 'Ledger of a cash or bank account with running balance' }: { title?: string; subtitle?: string }) {
  const { data: accounts } = useQuery({ queryKey: ['accounts'], queryFn: Accounts.list })
  const [account, setAccount] = useState('')
  const { data: book } = useQuery({
    queryKey: ['cash-book', account],
    queryFn: () => Accounts.cashBook(Number(account)),
    enabled: !!account,
  })

  return (
    <div>
      <PageHeader title={title} subtitle={subtitle} />
      <div className="mb-4 max-w-sm">
        <Field label="Account">
          <select className={inputClass} value={account} onChange={(e) => setAccount(e.target.value)}>
            <option value="">Select account…</option>
            {accounts?.filter((a) => a.type === 'asset').map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>)}
          </select>
        </Field>
      </div>

      {account && (
        <>
          <div className="mb-4 inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm">
            <span className="text-muted">Balance:</span>
            <span className="font-semibold text-ink">৳ {money(book?.balance ?? '0')}</span>
          </div>
          <Table head={['Date', 'Entry', 'Narration', 'Debit', 'Credit', 'Balance']}>
            {(book?.rows ?? []).map((r, i) => (
              <tr key={i} className="text-ink">
                <td className="px-4 py-2.5">{r.date}</td>
                <td className="px-4 py-2.5">{r.entry_no}</td>
                <td className="px-4 py-2.5">{r.narration}</td>
                <td className="px-4 py-2.5 text-right">{money(r.debit)}</td>
                <td className="px-4 py-2.5 text-right">{money(r.credit)}</td>
                <td className="px-4 py-2.5 text-right">{money(r.balance)}</td>
              </tr>
            ))}
            {(book?.rows ?? []).length === 0 && <tr><td colSpan={6} className="px-4 py-8 text-center text-muted">No entries.</td></tr>}
          </Table>
        </>
      )}
    </div>
  )
}
