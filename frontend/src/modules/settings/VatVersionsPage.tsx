import { useQuery } from '@tanstack/react-query'
import { Vat } from '../../shared/lib/resources'
import { PageHeader, Table } from '../../shared/ui/primitives'

const badge = (ok: boolean, text: string) => (
  <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${ok ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'}`}>{text}</span>
)

export function VatVersionsPage() {
  const { data: rates } = useQuery({ queryKey: ['vat-rates'], queryFn: Vat.rates })
  const { data: mappings } = useQuery({ queryKey: ['vat-mappings'], queryFn: Vat.mappings })

  return (
    <div>
      <PageHeader
        title="VAT Rule Versions"
        subtitle="Effective-dated, practitioner-verified VAT rates and Mushak note mappings. Nothing is hard-coded."
      />

      <h3 className="mb-2 text-sm font-semibold text-ink">VAT Rates</h3>
      <div className="mb-8">
        <Table head={['Code', 'Rate %', 'Description', 'Effective from', 'Effective to']}>
          {(rates ?? []).map((rate, i) => {
            const r = rate as unknown as Record<string, string>
            return (
              <tr key={r.id ?? i} className="text-ink">
                <td className="px-4 py-2.5 font-medium">{r.code ?? '—'}</td>
                <td className="px-4 py-2.5">{r.rate ?? '—'}</td>
                <td className="px-4 py-2.5">{r.description ?? r.name ?? '—'}</td>
                <td className="px-4 py-2.5">{r.effective_from ?? '—'}</td>
                <td className="px-4 py-2.5">{r.effective_to ?? 'current'}</td>
              </tr>
            )
          })}
          {(rates ?? []).length === 0 && (
            <tr><td colSpan={5} className="px-4 py-8 text-center text-muted">No VAT rates configured.</td></tr>
          )}
        </Table>
      </div>

      <h3 className="mb-2 text-sm font-semibold text-ink">Mushak Note Mappings</h3>
      <Table head={['Transaction type', 'Tax treatment', 'Note', 'Verification', 'Approval']}>
        {(mappings ?? []).map((m) => {
          const r = m as unknown as Record<string, string>
          return (
            <tr key={r.id} className="text-ink">
              <td className="px-4 py-2.5">{r.transaction_type_code ?? '—'}</td>
              <td className="px-4 py-2.5">{r.tax_treatment_code ?? '—'}</td>
              <td className="px-4 py-2.5 font-medium">{r.note_code ?? r.vat_note_code ?? '—'}</td>
              <td className="px-4 py-2.5">{badge(r.verification_status === 'verified', r.verification_status ?? 'unverified')}</td>
              <td className="px-4 py-2.5">{badge(r.approval_status === 'approved', r.approval_status ?? 'pending')}</td>
            </tr>
          )
        })}
        {(mappings ?? []).length === 0 && (
          <tr><td colSpan={5} className="px-4 py-8 text-center text-muted">No note mappings configured.</td></tr>
        )}
      </Table>
    </div>
  )
}
