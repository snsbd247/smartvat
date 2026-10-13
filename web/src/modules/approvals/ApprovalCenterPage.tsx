import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Check } from 'lucide-react'
import { Banner, Button, PageHeader, Table } from '../../shared/ui/primitives'
import { Approvals } from '../../shared/lib/resources'
import { useState } from 'react'

const money = (v: string | null) => (v ? '৳ ' + Number(v).toLocaleString(undefined, { maximumFractionDigits: 2 }) : '—')

export function ApprovalCenterPage() {
  const qc = useQueryClient()
  const { data: pending } = useQuery({ queryKey: ['approvals'], queryFn: Approvals.pending })
  const [msg, setMsg] = useState<string | null>(null)

  const approve = useMutation({
    mutationFn: (id: number) => Approvals.approvePurchaseOrder(id),
    onSuccess: () => {
      setMsg('Purchase order approved.')
      qc.invalidateQueries({ queryKey: ['approvals'] })
      qc.invalidateQueries({ queryKey: ['dashboard'] })
    },
  })

  return (
    <div>
      <PageHeader title="Approval Center" subtitle="Pending documents awaiting approval" />
      {msg && <Banner kind="success">{msg}</Banner>}
      <Table head={['Type', 'Number', 'Amount', 'Action']}>
        {(pending ?? []).map((p) => (
          <tr key={`${p.type}-${p.id}`} className="text-ink">
            <td className="px-4 py-2.5 capitalize">{p.type.replace(/_/g, ' ')}</td>
            <td className="px-4 py-2.5">{p.number}</td>
            <td className="px-4 py-2.5">{money(p.amount)}</td>
            <td className="px-4 py-2.5">
              {p.type === 'purchase_order' ? (
                <Button variant="ghost" onClick={() => approve.mutate(p.id)}>
                  <Check size={14} /> Approve
                </Button>
              ) : (
                <span className="text-xs text-muted">Pending</span>
              )}
            </td>
          </tr>
        ))}
        {(pending ?? []).length === 0 && (
          <tr><td colSpan={4} className="px-4 py-8 text-center text-muted">Nothing pending.</td></tr>
        )}
      </Table>
    </div>
  )
}
