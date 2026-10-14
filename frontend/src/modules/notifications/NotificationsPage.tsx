import { useQuery } from '@tanstack/react-query'
import { AlertTriangle, CheckCircle2, Clock, ShieldCheck } from 'lucide-react'
import { Link } from 'react-router-dom'
import type { ReactNode } from 'react'
import { Approvals, Backup, Vat } from '../../shared/lib/resources'
import { PageHeader } from '../../shared/ui/primitives'

interface Note {
  icon: ReactNode
  tone: string
  title: string
  detail: string
  to: string
}

export function NotificationsPage() {
  const { data: approvals } = useQuery({ queryKey: ['approvals', 'pending'], queryFn: () => Approvals.list('pending') })
  const { data: unmapped } = useQuery({ queryKey: ['vat-unmapped'], queryFn: Vat.unmapped })
  const { data: health } = useQuery({ queryKey: ['backup-health'], queryFn: Backup.health })

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
    </div>
  )
}
