import { useQuery } from '@tanstack/react-query'
import { Vds } from '../../shared/lib/resources'
import { PageHeader, Table } from '../../shared/ui/primitives'

const money = (v: string | undefined) => '৳ ' + Number(v ?? 0).toLocaleString(undefined, { maximumFractionDigits: 2 })

export function VdsReportsPage() {
  const { data: recon } = useQuery({ queryKey: ['vds-recon'], queryFn: Vds.reconciliation })
  const { data: deductions } = useQuery({ queryKey: ['vds-deductions'], queryFn: Vds.deductions })

  const cards = [
    { label: 'Total Deducted', value: money(recon?.deducted), tone: 'text-brand' },
    { label: 'Deposited to Treasury', value: money(recon?.deposited), tone: 'text-emerald-600' },
    { label: 'Outstanding Payable', value: money(recon?.payable), tone: 'text-rose-600' },
  ]

  return (
    <div>
      <PageHeader title="VDS Reports" subtitle="Deducted vs deposited reconciliation and the full deduction register." />

      <div className="mb-6 grid gap-4 sm:grid-cols-3">
        {cards.map((c) => (
          <div key={c.label} className="rounded-xl border border-slate-200 bg-white p-4">
            <div className="text-xs font-medium text-muted">{c.label}</div>
            <div className={`mt-1 text-2xl font-bold ${c.tone}`}>{c.value}</div>
          </div>
        ))}
      </div>

      <Table head={['Date', 'Deduction No', 'Service Category', 'Base', 'Rate', 'VDS', 'Status']}>
        {(deductions ?? []).map((d) => (
          <tr key={d.id} className="text-ink">
            <td className="px-4 py-2.5">{d.date ? new Date(d.date).toLocaleDateString() : '—'}</td>
            <td className="px-4 py-2.5 font-medium">{d.deduction_no}</td>
            <td className="px-4 py-2.5">{d.service_category}</td>
            <td className="px-4 py-2.5">{money(d.base_amount)}</td>
            <td className="px-4 py-2.5">{Number(d.rate)}%</td>
            <td className="px-4 py-2.5">{money(d.vds_amount)}</td>
            <td className="px-4 py-2.5 capitalize">{d.status}</td>
          </tr>
        ))}
        {(deductions ?? []).length === 0 && (
          <tr><td colSpan={7} className="px-4 py-8 text-center text-muted">No VDS deductions recorded.</td></tr>
        )}
      </Table>
    </div>
  )
}
