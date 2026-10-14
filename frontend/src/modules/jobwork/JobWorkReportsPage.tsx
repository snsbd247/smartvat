import { useQuery } from '@tanstack/react-query'
import { JobWork } from '../../shared/lib/resources'
import { PageHeader, Table } from '../../shared/ui/primitives'

/**
 * Customer-owned material reconciliation: received vs consumed vs delivered,
 * kept strictly separate from the company's own inventory.
 */
export function JobWorkReportsPage() {
  const { data: rows, isLoading } = useQuery({ queryKey: ['jobwork-recon'], queryFn: JobWork.reconciliation })
  const data = (rows ?? []) as Record<string, string>[]
  const cols = data.length ? Object.keys(data[0]) : ['product', 'received', 'consumed', 'delivered', 'balance']
  const label = (c: string) => c.replace(/_/g, ' ').replace(/\b\w/g, (m) => m.toUpperCase())

  return (
    <div>
      <PageHeader title="Job Work Ledger & Reports" subtitle="Customer material custody: received, consumed, delivered and balance on hand." />
      <Table head={cols.map(label)}>
        {data.map((r, i) => (
          <tr key={i} className="text-ink">
            {cols.map((c) => (
              <td key={c} className="px-4 py-2.5">{r[c] ?? '—'}</td>
            ))}
          </tr>
        ))}
        {!isLoading && data.length === 0 && (
          <tr><td colSpan={cols.length} className="px-4 py-8 text-center text-muted">No customer material on record.</td></tr>
        )}
      </Table>
    </div>
  )
}
