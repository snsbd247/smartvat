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

export function ApprovalCenterPage() {
  const qc = useQueryClient()
  const [tab, setTab] = useState<'pending' | 'approved' | 'rejected'>('pending')
  const [msg, setMsg] = useState<string | null>(null)

  const { data: rows } = useQuery({ queryKey: ['approvals', tab], queryFn: () => Approvals.list(tab) })

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
      <PageHeader title="Approval Center" subtitle="Documents across all modules awaiting a decision" />
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
