import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { Aging } from '../../shared/lib/resources'
import { PageHeader, Table } from '../../shared/ui/primitives'

const money = (v: string) => '৳ ' + Number(v).toLocaleString(undefined, { maximumFractionDigits: 0 })

export function AgingPage() {
  const [tab, setTab] = useState<'receivable' | 'payable'>('receivable')
  const { data } = useQuery({ queryKey: ['aging', tab], queryFn: () => (tab === 'receivable' ? Aging.receivable() : Aging.payable()) })

  const head = [tab === 'receivable' ? 'Customer' : 'Supplier', 'Current (0-30)', '31-60', '61-90', '91-120', '120+', 'Total']
  const t = data?.totals

  return (
    <div>
      <PageHeader title="Receivable / Payable Aging" subtitle="Outstanding balances bucketed by age (payments allocated oldest-invoice first)." />

      <div className="mb-4 flex gap-1 rounded-lg border border-slate-200 bg-workspace p-1 w-fit">
        {(['receivable', 'payable'] as const).map((x) => (
          <button key={x} onClick={() => setTab(x)} className={`rounded-md px-4 py-1.5 text-sm font-medium capitalize transition ${tab === x ? 'bg-white text-ink shadow-sm' : 'text-muted hover:text-ink'}`}>
            {x === 'receivable' ? 'Receivable (AR)' : 'Payable (AP)'}
          </button>
        ))}
      </div>

      <Table head={head}>
        {(data?.rows ?? []).map((r, i) => (
          <tr key={i} className="text-ink">
            <td className="px-4 py-2.5 font-medium">{r.party}</td>
            <td className="px-4 py-2.5">{money(r.current)}</td>
            <td className="px-4 py-2.5">{money(r.d30)}</td>
            <td className="px-4 py-2.5">{money(r.d60)}</td>
            <td className="px-4 py-2.5">{money(r.d90)}</td>
            <td className={`px-4 py-2.5 ${Number(r.older) > 0 ? 'font-medium text-rose-600' : ''}`}>{money(r.older)}</td>
            <td className="px-4 py-2.5 font-semibold">{money(r.total)}</td>
          </tr>
        ))}
        {(data?.rows ?? []).length === 0 && <tr><td colSpan={7} className="px-4 py-8 text-center text-muted">Nothing outstanding.</td></tr>}
        {t && (data?.rows ?? []).length > 0 && (
          <tr className="bg-slate-50 font-semibold text-ink">
            <td className="px-4 py-2.5">Total</td>
            <td className="px-4 py-2.5">{money(t.current)}</td>
            <td className="px-4 py-2.5">{money(t.d30)}</td>
            <td className="px-4 py-2.5">{money(t.d60)}</td>
            <td className="px-4 py-2.5">{money(t.d90)}</td>
            <td className="px-4 py-2.5">{money(t.older)}</td>
            <td className="px-4 py-2.5">{money(t.total)}</td>
          </tr>
        )}
      </Table>
    </div>
  )
}
