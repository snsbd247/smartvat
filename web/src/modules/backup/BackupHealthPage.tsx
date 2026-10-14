import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type ReactNode } from 'react'
import { Database, HardDrive, PlayCircle, RotateCcw, Server, ShieldCheck, Trash2 } from 'lucide-react'
import { Banner, Button, PageHeader, Table } from '../../shared/ui/primitives'
import { Backup } from '../../shared/lib/resources'

export type BackupView = 'health' | 'history' | 'drive' | 'retention' | 'restore' | 'logs'

const META: Record<BackupView, { title: string; subtitle: string }> = {
  health: { title: 'API & Database Health', subtitle: 'Live service health and the most recent backups.' },
  history: { title: 'Backup History', subtitle: 'Every backup job with status, verification and size.' },
  drive: { title: 'Google Drive Backup', subtitle: 'Off-site encrypted backup target and status.' },
  retention: { title: 'Retention Management', subtitle: 'How long encrypted backups are kept.' },
  restore: { title: 'Restore Testing', subtitle: 'Verify that a backup can be restored.' },
  logs: { title: 'Error Logs', subtitle: 'Backup jobs that failed.' },
}

export function BackupHealthPage({ view = 'health' }: { view?: BackupView }) {
  const qc = useQueryClient()
  const { data: health } = useQuery({ queryKey: ['backup-health'], queryFn: Backup.health })
  const { data: jobs } = useQuery({ queryKey: ['backup-jobs'], queryFn: Backup.jobs })
  const [msg, setMsg] = useState<{ kind: 'error' | 'success'; text: string } | null>(null)
  const meta = META[view]

  const run = useMutation({
    mutationFn: () => Backup.run(),
    onSuccess: () => {
      setMsg({ kind: 'success', text: 'Backup created, encrypted and verified.' })
      qc.invalidateQueries({ queryKey: ['backup-jobs'] })
      qc.invalidateQueries({ queryKey: ['backup-health'] })
    },
    onError: () => setMsg({ kind: 'error', text: 'Backup failed — check configuration and logs.' }),
  })

  const failed = (jobs ?? []).filter((j) => j.status === 'failed')
  const showRunButton = view === 'health' || view === 'history'

  return (
    <div>
      <PageHeader
        title={meta.title}
        subtitle={meta.subtitle}
        action={showRunButton && <Button onClick={() => run.mutate()} disabled={run.isPending}><PlayCircle size={16} /> {run.isPending ? 'Running…' : 'Run Backup Now'}</Button>}
      />
      {msg && <Banner kind={msg.kind}>{msg.text}</Banner>}

      {/* Health stat cards */}
      {(view === 'health' || view === 'drive') && (
        <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <Stat icon={<Server size={18} />} label="API" value={health?.api ?? '—'} ok={health?.api === 'ok'} />
          <Stat icon={<Database size={18} />} label="Database" value={health?.database ?? '—'} ok={health?.database === 'ok'} />
          <Stat icon={<ShieldCheck size={18} />} label="Last Backup Verified" value={health?.last_backup_verified ? 'Yes' : 'No'} ok={!!health?.last_backup_verified} />
          <Stat icon={<ShieldCheck size={18} />} label="Failed (24h)" value={String(health?.failed_last_24h ?? 0)} ok={(health?.failed_last_24h ?? 0) === 0} />
        </div>
      )}

      {/* Google Drive target */}
      {view === 'drive' && (
        <InfoCard icon={<HardDrive size={18} />} title="Off-site target">
          Backups are AES-256 encrypted and verified on every run. The storage driver is set by <code>BACKUP_DISK</code>.
          When a Google Drive service account is configured, set it to <code>google</code> for an off-site copy; until then
          backups are retained on the server disk. Last verified backup:{' '}
          <strong className="text-ink">{health?.last_successful_backup ?? 'none yet'}</strong>.
        </InfoCard>
      )}

      {/* Retention policy */}
      {view === 'retention' && (
        <InfoCard icon={<Trash2 size={18} />} title="Retention policy">
          Encrypted backups are kept for <strong className="text-ink">30 days</strong> and pruned automatically after each run.
          The <strong className="text-ink">last known-good backup is never deleted</strong>, even if it is older than the window.
          There {(jobs ?? []).length === 1 ? 'is' : 'are'} currently <strong className="text-ink">{(jobs ?? []).length}</strong> backup(s) on record.
        </InfoCard>
      )}

      {/* Restore testing */}
      {view === 'restore' && (
        <InfoCard icon={<RotateCcw size={18} />} title="Restore testing">
          A backup is only useful if it restores. Each archive is decrypted and its checksum verified at creation
          (<em>Verified = Yes</em> below). To run a full restore drill, use <code>deploy/restore.sh</code> against a
          staging database — never the live one. Last verified backup:{' '}
          <strong className="text-ink">{health?.last_successful_backup ?? 'none yet'}</strong>.
        </InfoCard>
      )}

      {/* Tables: recent (health), all (history), failed (logs) */}
      {(view === 'health' || view === 'history' || view === 'logs') && (
        <>
          <div className="mb-2 text-sm font-semibold text-ink">
            {view === 'logs' ? 'Failed Jobs' : view === 'history' ? 'All Backups' : 'Recent Backups'}
          </div>
          <Table head={['File', 'Status', 'Verified', 'Size', 'Finished']}>
            {(view === 'logs' ? failed : (jobs ?? [])).map((j) => (
              <tr key={j.id} className="text-ink">
                <td className="px-4 py-2.5">{j.filename}</td>
                <td className="px-4 py-2.5">
                  <span className={j.status === 'success' ? 'text-emerald-600' : j.status === 'failed' ? 'text-rose-600' : 'text-amber-600'}>{j.status}</span>
                </td>
                <td className="px-4 py-2.5">{j.verified ? 'Yes' : 'No'}</td>
                <td className="px-4 py-2.5">{(j.size / 1024).toFixed(1)} KB</td>
                <td className="px-4 py-2.5">{j.finished_at ?? '—'}</td>
              </tr>
            ))}
            {(view === 'logs' ? failed : (jobs ?? [])).length === 0 && (
              <tr><td colSpan={5} className="px-4 py-8 text-center text-muted">{view === 'logs' ? 'No failed backups — all healthy.' : 'No backups yet. Run one above.'}</td></tr>
            )}
          </Table>
        </>
      )}
    </div>
  )
}

function Stat({ icon, label, value, ok }: { icon: ReactNode; label: string; value: string; ok: boolean }) {
  return (
    <div className="rounded-xl border border-slate-200 bg-white p-4">
      <div className="flex items-center justify-between">
        <span className="flex items-center gap-2 text-sm text-muted">{icon}{label}</span>
        <span className={['h-2.5 w-2.5 rounded-full', ok ? 'bg-emerald-500' : 'bg-rose-500'].join(' ')} />
      </div>
      <div className="mt-2 text-lg font-semibold capitalize text-ink">{value}</div>
    </div>
  )
}

function InfoCard({ icon, title, children }: { icon: ReactNode; title: string; children: ReactNode }) {
  return (
    <div className="mb-6 flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-5">
      <span className="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand/10 text-brand">{icon}</span>
      <div>
        <div className="mb-1 font-semibold text-ink">{title}</div>
        <p className="text-sm text-ink [&_code]:rounded [&_code]:bg-slate-100 [&_code]:px-1 [&_code]:py-0.5 [&_code]:text-xs">{children}</p>
      </div>
    </div>
  )
}
