import { useQuery } from '@tanstack/react-query'
import { PageHeader, Table } from '../../shared/ui/primitives'
import { Vat } from '../../shared/lib/resources'

export function VatUnmappedPage() {
  const { data: rows } = useQuery({ queryKey: ['vat-unmapped'], queryFn: Vat.unmapped })

  return (
    <div>
      <PageHeader title="Unmapped VAT Transactions" subtitle="Flagged for practitioner review — never auto-classified into a guessed note" />
      <Table head={['Date', 'Txn Type', 'Treatment', 'Rate', 'Taxable', 'VAT']}>
        {(rows ?? []).map((r, i) => (
          <tr key={i} className="text-ink">
            <td className="px-4 py-2.5">{r.date}</td>
            <td className="px-4 py-2.5">{r.transaction_type_code}</td>
            <td className="px-4 py-2.5">{r.tax_treatment_code}</td>
            <td className="px-4 py-2.5">{r.vat_rate_code ?? '—'}</td>
            <td className="px-4 py-2.5 text-right">{Number(r.taxable_amount).toLocaleString()}</td>
            <td className="px-4 py-2.5 text-right">{Number(r.vat_amount).toLocaleString()}</td>
          </tr>
        ))}
        {(rows ?? []).length === 0 && (
          <tr><td colSpan={6} className="px-4 py-8 text-center text-muted">No unmapped transactions.</td></tr>
        )}
      </Table>
    </div>
  )
}
