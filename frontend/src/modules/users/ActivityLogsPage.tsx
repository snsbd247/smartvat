import { useQuery } from '@tanstack/react-query'
import { PageHeader, Table } from '../../shared/ui/primitives'
import { System, type ActivityLog } from '../../shared/lib/resources'

export function LogsTable({ title, subtitle, fetcher, showTarget }: { title: string; subtitle: string; fetcher: () => Promise<ActivityLog[]>; showTarget?: boolean }) {
  const { data } = useQuery({ queryKey: [title], queryFn: fetcher })
  const head = showTarget ? ['When', 'User', 'Event', 'Target', 'IP'] : ['When', 'User', 'Event', 'IP']
  return (
    <div>
      <PageHeader title={title} subtitle={subtitle} />
      <Table head={head}>
        {(data ?? []).map((l) => (
          <tr key={l.id} className="text-ink">
            <td className="px-4 py-2.5">{l.created_at ? new Date(l.created_at).toLocaleString() : '—'}</td>
            <td className="px-4 py-2.5">{l.user ?? '—'}</td>
            <td className="px-4 py-2.5 capitalize">{l.event}</td>
            {showTarget && <td className="px-4 py-2.5">{l.auditable_type ? `${l.auditable_type} #${l.auditable_id}` : '—'}</td>}
            <td className="px-4 py-2.5">{l.ip_address ?? '—'}</td>
          </tr>
        ))}
        {(data ?? []).length === 0 && <tr><td colSpan={head.length} className="px-4 py-8 text-center text-muted">No records.</td></tr>}
      </Table>
    </div>
  )
}

export function ActivityLogsPage() {
  return <LogsTable title="Activity Logs" subtitle="Audit trail of sensitive changes" fetcher={System.activityLogs} showTarget />
}
export function LoginHistoryPage() {
  return <LogsTable title="Login History" subtitle="User sign-in events" fetcher={System.loginHistory} />
}
