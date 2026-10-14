import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Lock, Unlock } from 'lucide-react'
import { useState } from 'react'
import { Accounts, type AccountingPeriod } from '../../shared/lib/resources'
import { Banner, Button, PageHeader, Table, apiError } from '../../shared/ui/primitives'

const fmt = (d: string) => (d ? new Date(d).toLocaleDateString() : '—')

/**
 * Accounting periods. In "manage" mode period locking (open/closed) is editable;
 * closed periods reject new postings server-side (PeriodClosed guard).
 */
export function PeriodsPage({ mode = 'manage' }: { mode?: 'manage' | 'view' }) {
  const qc = useQueryClient()
  const [msg, setMsg] = useState<string | null>(null)
  const [err, setErr] = useState<string | null>(null)
  const { data: periods, isLoading } = useQuery({ queryKey: ['periods'], queryFn: Accounts.periods })

  const toggle = useMutation({
    mutationFn: ({ id, status }: { id: number; status: 'open' | 'closed' }) => Accounts.setPeriodStatus(id, status),
    onSuccess: (_d, v) => {
      setErr(null)
      setMsg(v.status === 'closed' ? 'Period closed — postings are now locked.' : 'Period re-opened.')
      qc.invalidateQueries({ queryKey: ['periods'] })
    },
    onError: (e) => setErr(apiError(e)),
  })

  const title = mode === 'manage' ? 'Period Closing' : 'Financial Year'
  const subtitle =
    mode === 'manage'
      ? 'Lock accounting periods to freeze postings. Reversals only after closing.'
      : 'Fiscal periods and their open/closed status.'

  return (
    <div>
      <PageHeader title={title} subtitle={subtitle} />
      {msg && <Banner kind="success">{msg}</Banner>}
      {err && <Banner kind="error">{err}</Banner>}
      <Table head={['Period', 'From', 'To', 'Status', mode === 'manage' ? 'Action' : 'FY']}>
        {(periods ?? []).map((p: AccountingPeriod) => {
          const closed = p.status === 'closed'
          return (
            <tr key={p.id} className="text-ink">
              <td className="px-4 py-2.5 font-medium">{p.name}</td>
              <td className="px-4 py-2.5">{fmt(p.start_date)}</td>
              <td className="px-4 py-2.5">{fmt(p.end_date)}</td>
              <td className="px-4 py-2.5">
                <span
                  className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${
                    closed ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700'
                  }`}
                >
                  {closed ? <Lock size={12} /> : <Unlock size={12} />}
                  {closed ? 'Closed' : 'Open'}
                </span>
              </td>
              <td className="px-4 py-2.5">
                {mode === 'manage' ? (
                  <Button
                    variant={closed ? 'ghost' : 'danger'}
                    onClick={() => toggle.mutate({ id: p.id, status: closed ? 'open' : 'closed' })}
                    disabled={toggle.isPending}
                  >
                    {closed ? <Unlock size={14} /> : <Lock size={14} />}
                    {closed ? 'Re-open' : 'Close'}
                  </Button>
                ) : (
                  <span className="text-muted">{p.fiscal_year ?? '—'}</span>
                )}
              </td>
            </tr>
          )
        })}
        {!isLoading && (periods ?? []).length === 0 && (
          <tr>
            <td colSpan={5} className="px-4 py-8 text-center text-muted">
              No accounting periods defined.
            </td>
          </tr>
        )}
      </Table>
    </div>
  )
}
