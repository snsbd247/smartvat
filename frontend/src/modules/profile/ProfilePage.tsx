import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { Profile } from '../../shared/lib/resources'
import { useAuth } from '../../shared/auth/AuthContext'
import { Button, PageHeader } from '../../shared/ui/primitives'

export function ProfilePage() {
  const { user, companies } = useAuth()
  const { data: me } = useQuery({ queryKey: ['me'], queryFn: Profile.me })

  const rows: [string, string][] = [
    ['Name', user?.name ?? '—'],
    ['Email', user?.email ?? '—'],
    ['Username', user?.username ?? '—'],
    ['Roles', (me?.roles ?? []).join(', ') || '—'],
  ]

  return (
    <div className="max-w-2xl">
      <PageHeader
        title="My Profile"
        subtitle="Your account details and company access."
        action={<Link to="/change-password"><Button variant="ghost">Change Password</Button></Link>}
      />

      <div className="rounded-xl border border-slate-200 bg-white p-6">
        <div className="mb-6 flex items-center gap-4">
          <div className="grid h-14 w-14 place-items-center rounded-full bg-brand text-xl font-bold text-white">
            {(user?.name ?? '?').charAt(0).toUpperCase()}
          </div>
          <div>
            <div className="text-lg font-semibold text-ink">{user?.name}</div>
            <div className="text-sm text-muted">{user?.email}</div>
          </div>
        </div>

        <dl className="divide-y divide-slate-100">
          {rows.map(([k, v]) => (
            <div key={k} className="grid grid-cols-3 gap-4 py-3">
              <dt className="text-sm font-medium text-muted">{k}</dt>
              <dd className="col-span-2 text-sm text-ink">{v}</dd>
            </div>
          ))}
          <div className="grid grid-cols-3 gap-4 py-3">
            <dt className="text-sm font-medium text-muted">Companies</dt>
            <dd className="col-span-2 flex flex-wrap gap-2">
              {(companies ?? []).map((c) => (
                <span key={c.id} className="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-ink">
                  {c.code} — {c.name}
                </span>
              ))}
            </dd>
          </div>
        </dl>
      </div>
    </div>
  )
}
