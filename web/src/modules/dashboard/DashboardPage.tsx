import { useQuery } from '@tanstack/react-query'
import { Activity, Database, Server } from 'lucide-react'
import { fetchHealth } from '../../shared/lib/api'

export function DashboardPage() {
  const { data, isLoading, isError } = useQuery({
    queryKey: ['health'],
    queryFn: fetchHealth,
    refetchInterval: 30_000,
  })

  const dbOk = data?.checks.database === 'ok'

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-xl font-semibold text-ink">Welcome</h2>
        <p className="text-sm text-muted">
          Universal Business ERP — project skeleton. Modules are added phase by phase.
        </p>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <Card
          title="API"
          icon={<Server size={18} />}
          value={isLoading ? 'Checking…' : isError ? 'Unreachable' : (data?.status ?? '—')}
          ok={!isError && data?.status === 'ok'}
        />
        <Card
          title="Database"
          icon={<Database size={18} />}
          value={isLoading ? 'Checking…' : dbOk ? 'ok' : 'unavailable'}
          ok={dbOk}
        />
        <Card
          title="Environment"
          icon={<Activity size={18} />}
          value={data?.environment ?? '—'}
          ok
        />
      </div>
    </div>
  )
}

function Card({
  title,
  icon,
  value,
  ok,
}: {
  title: string
  icon: React.ReactNode
  value: string
  ok: boolean
}) {
  return (
    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <div className="flex items-center justify-between">
        <span className="flex items-center gap-2 text-sm font-medium text-muted">
          {icon}
          {title}
        </span>
        <span
          className={[
            'h-2.5 w-2.5 rounded-full',
            ok ? 'bg-emerald-500' : 'bg-rose-500',
          ].join(' ')}
          aria-hidden
        />
      </div>
      <div className="mt-3 text-2xl font-semibold capitalize text-ink">{value}</div>
    </div>
  )
}
