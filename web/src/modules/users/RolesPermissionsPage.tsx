import { useQuery } from '@tanstack/react-query'
import { PageHeader } from '../../shared/ui/primitives'
import { System } from '../../shared/lib/resources'

export function RolesPage() {
  const { data: roles } = useQuery({ queryKey: ['roles'], queryFn: System.roles })
  return (
    <div>
      <PageHeader title="Roles" subtitle="Roles are assigned per company (spatie teams)" />
      <div className="flex flex-wrap gap-2">
        {(roles ?? []).map((r) => (
          <span key={r} className="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm text-ink">{r}</span>
        ))}
      </div>
    </div>
  )
}

export function PermissionsPage() {
  const { data: perms } = useQuery({ queryKey: ['permissions'], queryFn: System.permissions })
  return (
    <div>
      <PageHeader title="Permissions" subtitle="Granular per-action permissions" />
      <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
        {(perms ?? []).map((p) => (
          <span key={p} className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-ink">{p}</span>
        ))}
      </div>
    </div>
  )
}
