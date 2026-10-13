import { NavLink, Outlet } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { LogOut } from 'lucide-react'
import { NAV_ITEMS } from './navigation'
import { useAuth } from '../auth/AuthContext'
import { fetchCompanies, fetchMe, fetchModules } from '../lib/api'

// nav key -> module catalog key (config/erp.php uses snake_case)
const moduleKey = (navKey: string) => navKey.replace(/-/g, '_')

export function AppLayout() {
  const { companies: authCompanies, companyId, setCompany, logout } = useAuth()

  // Fetched fresh so the header survives a page reload (auth state holds only the token).
  const { data: me } = useQuery({ queryKey: ['me', companyId], queryFn: fetchMe })
  const { data: companiesData } = useQuery({ queryKey: ['companies'], queryFn: fetchCompanies })
  const companies = companiesData ?? authCompanies

  const { data: modules } = useQuery({
    queryKey: ['modules', companyId],
    queryFn: fetchModules,
    staleTime: 60_000,
  })

  const items = NAV_ITEMS.filter((item) => {
    if (item.key === 'dashboard') return true
    if (!modules) return true // until loaded, show everything
    return modules.includes(moduleKey(item.key))
  })

  return (
    <div className="flex min-h-screen">
      <aside className="flex w-64 shrink-0 flex-col bg-sidebar text-slate-200">
        <div className="flex h-16 items-center gap-2 border-b border-white/10 px-5">
          <span className="grid h-8 w-8 place-items-center rounded-md bg-brand font-bold text-white">
            E
          </span>
          <span className="text-sm font-semibold tracking-wide text-white">Universal ERP</span>
        </div>
        <nav className="flex-1 overflow-y-auto px-2 py-3">
          {items.map(({ key, label, to, icon: Icon }) => (
            <NavLink
              key={key}
              to={to}
              end={to === '/'}
              className={({ isActive }) =>
                [
                  'mb-0.5 flex items-center gap-3 rounded-md px-3 py-2 text-sm transition-colors',
                  isActive
                    ? 'bg-sidebar-active text-white'
                    : 'text-slate-300 hover:bg-sidebar-hover hover:text-white',
                ].join(' ')
              }
            >
              <Icon size={18} strokeWidth={1.75} />
              <span>{label}</span>
            </NavLink>
          ))}
        </nav>
        <div className="border-t border-white/10 px-5 py-3 text-xs text-slate-400">v0.1.0-dev</div>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex h-16 items-center justify-between border-b border-slate-200 bg-white px-6">
          <h1 className="text-lg font-semibold text-ink">Dashboard</h1>
          <div className="flex items-center gap-4">
            {companies.length > 0 && (
              <select
                value={companyId ?? ''}
                onChange={(e) => setCompany(Number(e.target.value))}
                className="rounded-md border border-slate-300 px-2 py-1.5 text-sm text-ink outline-none focus:border-brand"
              >
                {companies.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </select>
            )}
            <span className="text-sm text-muted">{me?.name ?? 'Signed in'}</span>
            <button
              type="button"
              onClick={() => logout()}
              className="flex items-center gap-1.5 rounded-md border border-slate-300 px-2.5 py-1.5 text-sm text-ink hover:bg-slate-50"
            >
              <LogOut size={15} />
              Logout
            </button>
          </div>
        </header>
        <main className="flex-1 overflow-y-auto p-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
