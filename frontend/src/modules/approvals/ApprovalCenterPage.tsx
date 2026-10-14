import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Check, X } from 'lucide-react'
import { useState } from 'react'
import { Banner, Button, PageHeader, Table } from '../../shared/ui/primitives'
import { Approvals } from '../../shared/lib/resources'

const money = (v: string | null) => (v ? '৳ ' + Number(v).toLocaleString(undefined, { maximumFractionDigits: 2 }) : '—')

const TABS: { key: 'pending' | 'approved' | 'rejected'; label: string }[] = [
  { key: 'pending', label: 'Pending' },
  { key: 'approved', label: 'Approved' },
  { key: 'rejected', label: 'Rejected' },
]

export type ApprovalKind = 'all' | 'purchase' | 'sales' | 'payment' | 'journal' | 'production'

// Each Approval Center menu scopes to a set of document types.
const KIND: Record<ApprovalKind, { title: string; subtitle: string; types: string[] | null }> = {
  all: { title: 'Approval Center', subtitle: 'Documents across all modules awaiting a decision', types: null },
  purchase: { title: 'Purchase Approvals', subtitle: 'Purchase orders awaiting a decision', types: ['purchase_order'] },
  sales: { title: 'Sales Approvals', subtitle: 'Sales orders and invoices awaiting a decision', types: ['sales_order', 'sales_invoice'] },
  payment: { title: 'Payment Approvals', subtitle: 'Supplier payments and customer receipts awaiting a decision', types: ['supplier_payment', 'customer_receipt'] },
  journal: { title: 'Journal Approvals', subtitle: 'Manual journal entries awaiting a decision', types: ['journal_entry'] },
  production: { title: 'Production Approvals', subtitle: 'Production orders awaiting a decision', types: ['production_order'] },
}

export function ApprovalCenterPage({ kind = 'all' }: { kind?: ApprovalKind }) {
  const qc = useQueryClient()
  const [tab, setTab] = useState<'pending' | 'approved' | 'rejected'>('pending')
  const [msg, setMsg] = useState<string | null>(null)
  const cfg = KIND[kind]

  const { data: all } = useQuery({ queryKey: ['approvals', tab], queryFn: () => Approvals.list(tab) })
  const rows = cfg.types ? (all ?? []).filter((r) => cfg.types!.includes(r.type)) : all

  const refresh = () => {
    qc.invalidateQueries({ queryKey: ['approvals'] })
    qc.invalidateQueries({ queryKey: ['dashboard'] })
  }

  const approve = useMutation({
    mutationFn: (id: number) => Approvals.approve(id),
    onSuccess: () => {
      setMsg('Document approved.')
      refresh()
    },
  })

  const reject = useMutation({
    mutationFn: (id: number) => Approvals.reject(id, window.prompt('Reason for rejection (optional):') ?? undefined),
    onSuccess: () => {
      setMsg('Document rejected.')
      refresh()
    },
  })

  return (
    <div>
      <PageHeader title={cfg.title} subtitle={cfg.subtitle} />
      {msg && <Banner kind="success">{msg}</Banner>}

      <div className="mb-4 flex gap-1 rounded-lg border border-slate-200 bg-workspace p-1 w-fit">
        {TABS.map((t) => (
          <button
            key={t.key}
            onClick={() => setTab(t.key)}
            className={`rounded-md px-4 py-1.5 text-sm font-medium transition ${
              tab === t.key ? 'bg-white text-ink shadow-sm' : 'text-muted hover:text-ink'
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      <Table head={['Type', 'Document', 'Amount', 'Requested by', tab === 'pending' ? 'Action' : 'Decided by']}>
        {(rows ?? []).map((r) => (
          <tr key={r.id} className="text-ink">
            <td className="px-4 py-2.5 capitalize">{r.type.replace(/_/g, ' ')}</td>
            <td className="px-4 py-2.5">{r.document_no ?? '—'}</td>
            <td className="px-4 py-2.5">{money(r.amount)}</td>
            <td className="px-4 py-2.5">{r.requested_by ?? '—'}</td>
            <td className="px-4 py-2.5">
              {tab === 'pending' ? (
                <div className="flex gap-2">
                  <Button variant="ghost" onClick={() => approve.mutate(r.id)}>
                    <Check size={14} /> Approve
                  </Button>
                  <Button variant="ghost" onClick={() => reject.mutate(r.id)}>
                    <X size={14} /> Reject
                  </Button>
                </div>
              ) : (
                <span className="text-xs text-muted">
                  {r.acted_by ?? '—'}
                  {r.note ? ` · ${r.note}` : ''}
                </span>
              )}
            </td>
          </tr>
        ))}
        {(rows ?? []).length === 0 && (
          <tr>
            <td colSpan={5} className="px-4 py-8 text-center text-muted">
              Nothing {tab}.
            </td>
          </tr>
        )}
      </Table>
    </div>
  )
}
