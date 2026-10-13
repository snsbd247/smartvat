import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type ReactNode } from 'react'
import { Database, PlayCircle, Server, ShieldCheck } from 'lucide-react'
import { Banner, Button, PageHeader, Table } from '../../shared/ui/primitives'
import { Backup } from '../../shared/lib/resources'

export function BackupHealthPage() {
  const qc = useQueryClient()
  const { data: health } = useQuery({ queryKey: ['backup-health'], queryFn: Backup.health })
  const { data: jobs } = useQuery({ queryKey: ['backup-jobs'], queryFn: Backup.jobs })
  const [msg, setMsg] = useState<{ kind: 'error' | 'success'; text: string } | null>(null)

  const run = useMutation({
    mutationFn: () => Backup.run(),
    onSuccess: () => {
      setMsg({ kind: 'success', text: 'Backup created, encrypted and verified.' })
      qc.invalidateQueries({ queryKey: ['backup-jobs'] })
      qc.invalidateQueries({ queryKey: ['backup-health'] })
    },
    onError: () => setMsg({ kind: 'error', text: 'Backup failed — check configuration and logs.' }),
  })

  return (
    <div>
      <PageHeader title="Backup & System Health"
        action={<Button onClick={() => run.mutate()} disabled={run.isPending}><PlayCircle size={16} /> {run.isPending ? 'Running…' : 'Run Backup Now'}</Button>} />
      {msg && <Banner kind={msg.kind}>{msg.text}</Banner>}

      <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Stat icon={<Server size={18} />} label="API" value={health?.api ?? '—'} ok={health?.api === 'ok'} />
        <Stat icon={<Database size={18} />} label="Database" value={health?.database ?? '—'} ok={health?.database === 'ok'} />
        <Stat icon={<ShieldCheck size={18} />} label="Last Backup Verified" value={health?.last_backup_verified ? 'Yes' : 'No'} ok={!!health?.last_backup_verified} />
        <Stat icon={<ShieldCheck size={18} />} label="Failed (24h)" value={String(health?.failed_last_24h ?? 0)} ok={(health?.failed_last_24h ?? 0) === 0} />
      </div>

      <div className="mb-2 text-sm font-semibold text-ink">Recent Backups</div>
      <Table head={['File', 'Status', 'Verified', 'Size', 'Finished']}>
        {(jobs ?? []).map((j) => (
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
        {(jobs ?? []).length === 0 && <tr><td colSpan={5} className="px-4 py-8 text-center text-muted">No backups yet. Run one above.</td></tr>}
      </Table>
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
