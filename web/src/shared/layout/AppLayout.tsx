import { NavLink, Outlet } from 'react-router-dom'
import { NAV_ITEMS } from './navigation'

export function AppLayout() {
  return (
    <div className="flex min-h-screen">
      {/* Navy sidebar */}
      <aside className="flex w-64 shrink-0 flex-col bg-sidebar text-slate-200">
        <div className="flex h-16 items-center gap-2 border-b border-white/10 px-5">
          <span className="grid h-8 w-8 place-items-center rounded-md bg-brand font-bold text-white">
            E
          </span>
          <span className="text-sm font-semibold tracking-wide text-white">
            Universal ERP
          </span>
        </div>
        <nav className="flex-1 overflow-y-auto px-2 py-3">
          {NAV_ITEMS.map(({ key, label, to, icon: Icon }) => (
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
        <div className="border-t border-white/10 px-5 py-3 text-xs text-slate-400">
          v0.1.0-dev
        </div>
      </aside>

      {/* Workspace */}
      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex h-16 items-center justify-between border-b border-slate-200 bg-white px-6">
          <h1 className="text-lg font-semibold text-ink">Dashboard</h1>
          <div className="text-sm text-muted">Phase 1 · Skeleton</div>
        </header>
        <main className="flex-1 overflow-y-auto p-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
