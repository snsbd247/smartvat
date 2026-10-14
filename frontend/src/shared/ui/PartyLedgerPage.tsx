import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Field, PageHeader, Table, inputClass } from './primitives'
import type { Party } from '../lib/resources'

interface LedgerResponse {
  outstanding: string
  entries: { date: string; entry_no: string; narration: string | null; debit: string; credit: string }[]
}

export function PartyLedgerPage({
  title,
  label,
  fetchParties,
  fetchLedger,
}: {
  title: string
  label: string
  fetchParties: () => Promise<Party[]>
  fetchLedger: (id: number) => Promise<LedgerResponse>
}) {
  const [party, setParty] = useState('')
  const { data: parties } = useQuery({ queryKey: [title, 'parties'], queryFn: fetchParties })
  const { data: ledger } = useQuery({
    queryKey: [title, 'ledger', party],
    queryFn: () => fetchLedger(Number(party)),
    enabled: !!party,
  })

  const money = (v: string) => Number(v).toLocaleString(undefined, { maximumFractionDigits: 2 })

  return (
    <div>
      <PageHeader title={title} />
      <div className="mb-4 max-w-sm">
        <Field label={label}>
          <select className={inputClass} value={party} onChange={(e) => setParty(e.target.value)}>
            <option value="">Select…</option>
            {parties?.map((p) => (
              <option key={p.id} value={p.id}>
                {p.name}
              </option>
            ))}
          </select>
        </Field>
      </div>

      {party && (
        <>
          <div className="mb-4 inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm">
            <span className="text-muted">Outstanding:</span>
            <span className="font-semibold text-ink">৳ {money(ledger?.outstanding ?? '0')}</span>
          </div>
          <Table head={['Date', 'Entry', 'Narration', 'Debit', 'Credit']}>
            {(ledger?.entries ?? []).map((e, i) => (
              <tr key={i} className="text-ink">
                <td className="px-4 py-2.5">{e.date}</td>
                <td className="px-4 py-2.5">{e.entry_no}</td>
                <td className="px-4 py-2.5">{e.narration}</td>
                <td className="px-4 py-2.5 text-right">{money(e.debit)}</td>
                <td className="px-4 py-2.5 text-right">{money(e.credit)}</td>
              </tr>
            ))}
            {(ledger?.entries ?? []).length === 0 && (
              <tr><td colSpan={5} className="px-4 py-8 text-center text-muted">No entries.</td></tr>
            )}
          </Table>
        </>
      )}
    </div>
  )
}
