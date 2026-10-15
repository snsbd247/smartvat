import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AlertTriangle, Bell, CheckCircle2, Clock, ShieldCheck } from 'lucide-react'
import { Link } from 'react-router-dom'
import type { ReactNode } from 'react'
import { Approvals, Backup, Notify, Vat } from '../../shared/lib/resources'
import { Button, PageHeader } from '../../shared/ui/primitives'

interface Note {
  icon: ReactNode
  tone: string
  title: string
  detail: string
  to: string
}

export function NotificationsPage() {
  const qc = useQueryClient()
  const { data: approvals } = useQuery({ queryKey: ['approvals', 'pending'], queryFn: () => Approvals.list('pending') })
  const { data: unmapped } = useQuery({ queryKey: ['vat-unmapped'], queryFn: Vat.unmapped })
  const { data: health } = useQuery({ queryKey: ['backup-health'], queryFn: Backup.health })
  const { data: feed } = useQuery({ queryKey: ['notifications'], queryFn: Notify.list })

  const markRead = useMutation({
    mutationFn: (id: number) => Notify.markRead(id),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['notifications'] }); qc.invalidateQueries({ queryKey: ['notifications-unread'] }) },
  })
  const markAll = useMutation({
    mutationFn: () => Notify.markAllRead(),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['notifications'] }); qc.invalidateQueries({ queryKey: ['notifications-unread'] }) },
  })

  const notes: Note[] = []

  const pending = approvals?.length ?? 0
  if (pending > 0) {
    notes.push({
      icon: <Clock size={18} />,
      tone: 'text-amber-600 bg-amber-50',
      title: `${pending} document${pending > 1 ? 's' : ''} awaiting approval`,
      detail: 'Review and approve or reject pending documents in the Approval Center.',
      to: '/approvals/purchase',
    })
  }

  const unmappedCount = unmapped?.length ?? 0
  if (unmappedCount > 0) {
    notes.push({
      icon: <AlertTriangle size={18} />,
      tone: 'text-rose-600 bg-rose-50',
      title: `${unmappedCount} transaction${unmappedCount > 1 ? 's' : ''} need VAT review`,
      detail: 'Unmapped transactions were flagged "review_required" and never auto-assigned a Mushak note.',
      to: '/vat/reconciliation',
    })
  }

  if (health) {
    if (health.failed_last_24h > 0) {
      notes.push({
        icon: <AlertTriangle size={18} />,
        tone: 'text-rose-600 bg-rose-50',
        title: `${health.failed_last_24h} backup failure(s) in the last 24h`,
        detail: 'Check backup history and system health.',
        to: '/backup/health',
      })
    } else if (!health.last_backup_verified) {
      notes.push({
        icon: <ShieldCheck size={18} />,
        tone: 'text-amber-600 bg-amber-50',
        title: 'Last backup not yet verified',
        detail: 'A backup exists but its integrity has not been confirmed.',
        to: '/backup/health',
      })
    }
  }

  return (
    <div>
      <PageHeader title="Notifications" subtitle="Action items drawn from approvals, compliance and system health." />

      {notes.length === 0 ? (
        <div className="flex flex-col items-center justify-center rounded-xl border border-slate-200 bg-white py-16 text-center">
          <CheckCircle2 className="text-emerald-500" size={40} />
          <p className="mt-3 font-medium text-ink">All clear</p>
          <p className="text-sm text-muted">No pending approvals, compliance flags or backup issues.</p>
        </div>
      ) : (
        <div className="space-y-3">
          {notes.map((n, i) => (
            <Link
              key={i}
              to={n.to}
              className="flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-4 transition hover:border-brand/40 hover:shadow-sm"
            >
              <span className={`grid h-9 w-9 place-items-center rounded-lg ${n.tone}`}>{n.icon}</span>
              <div>
                <div className="font-medium text-ink">{n.title}</div>
                <div className="text-sm text-muted">{n.detail}</div>
              </div>
            </Link>
          ))}
        </div>
      )}

      <div className="mt-8 mb-2 flex items-center justify-between">
        <h3 className="flex items-center gap-2 text-sm font-semibold text-ink"><Bell size={15} /> Recent notifications</h3>
        {(feed ?? []).some((n) => !n.read_at) && (
          <Button variant="ghost" onClick={() => markAll.mutate()}>Mark all read</Button>
        )}
      </div>
      <div className="space-y-2">
        {(feed ?? []).map((n) => {
          const body = (
            <div className={`flex items-start justify-between gap-3 rounded-xl border p-4 ${n.read_at ? 'border-slate-200 bg-white' : 'border-brand/30 bg-brand/5'}`}>
              <div>
                <div className="font-medium text-ink">{n.title}</div>
                {n.message && <div className="text-sm text-muted">{n.message}</div>}
                <div className="mt-0.5 text-xs text-muted">{n.created_at ? new Date(n.created_at).toLocaleString() : ''}</div>
              </div>
              {!n.read_at && (
                <button onClick={(e) => { e.preventDefault(); markRead.mutate(n.id) }} className="shrink-0 rounded px-2 py-1 text-xs font-medium text-brand hover:bg-brand/10">Mark read</button>
              )}
            </div>
          )
          return n.link ? <Link key={n.id} to={n.link} onClick={() => !n.read_at && markRead.mutate(n.id)}>{body}</Link> : <div key={n.id}>{body}</div>
        })}
        {(feed ?? []).length === 0 && (
          <div className="rounded-xl border border-slate-200 bg-white py-8 text-center text-sm text-muted">No notifications yet.</div>
        )}
      </div>
    </div>
  )
}
