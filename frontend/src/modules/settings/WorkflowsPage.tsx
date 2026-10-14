import { useQuery } from '@tanstack/react-query'
import { ArrowRight, CheckCircle2 } from 'lucide-react'
import { Link } from 'react-router-dom'
import { Approvals } from '../../shared/lib/resources'
import { PageHeader, Table } from '../../shared/ui/primitives'

/**
 * Approval workflows overview. The engine is generic: any document can raise a
 * pending approval, routed to the Approval Center for approve/reject.
 */
const FLOWS = [
  { doc: 'Purchase Order', trigger: 'On creation', then: 'Goods Receipt enabled after approval' },
  { doc: 'Production Order', trigger: 'On creation', then: 'Material issue after approval' },
  { doc: 'Sales / Payment / Journal', trigger: 'Configurable', then: 'Routed to the Approval Center' },
]

export function WorkflowsPage() {
  const { data: pending } = useQuery({ queryKey: ['approvals', 'pending'], queryFn: () => Approvals.list('pending') })

  return (
    <div>
      <PageHeader
        title="Approval Workflows"
        subtitle="How documents move from draft to approved. The engine is generic across all document types."
        action={<Link to="/approvals/purchase" className="text-sm font-medium text-brand hover:underline">Open Approval Center →</Link>}
      />

      <div className="mb-6 grid gap-3 sm:grid-cols-3">
        {FLOWS.map((f) => (
          <div key={f.doc} className="rounded-xl border border-slate-200 bg-white p-4">
            <div className="font-semibold text-ink">{f.doc}</div>
            <div className="mt-2 flex items-center gap-2 text-xs text-muted">
              <span className="rounded bg-slate-100 px-2 py-0.5">{f.trigger}</span>
              <ArrowRight size={14} />
              <CheckCircle2 size={14} className="text-emerald-500" />
            </div>
            <p className="mt-2 text-sm text-ink">{f.then}</p>
          </div>
        ))}
      </div>

      <h3 className="mb-2 text-sm font-semibold text-ink">Currently pending ({pending?.length ?? 0})</h3>
      <Table head={['Type', 'Document', 'Amount', 'Requested by']}>
        {(pending ?? []).map((p) => (
          <tr key={p.id} className="text-ink">
            <td className="px-4 py-2.5 capitalize">{p.type.replace(/_/g, ' ')}</td>
            <td className="px-4 py-2.5">{p.document_no ?? '—'}</td>
            <td className="px-4 py-2.5">{p.amount ? '৳ ' + Number(p.amount).toLocaleString() : '—'}</td>
            <td className="px-4 py-2.5">{p.requested_by ?? '—'}</td>
          </tr>
        ))}
        {(pending ?? []).length === 0 && (
          <tr><td colSpan={4} className="px-4 py-8 text-center text-muted">Nothing pending approval.</td></tr>
        )}
      </Table>
    </div>
  )
}
