import { useState } from 'react'
import { Link, NavLink, useLocation } from 'react-router-dom'
import { ChevronDown } from 'lucide-react'
import { BOTTOM_ITEMS, NAV_GROUPS } from './navigation'
import { useAuth } from '../auth/AuthContext'

export function Sidebar({ modules }: { modules?: string[] }) {
  const { logout } = useAuth()
  const location = useLocation()
  const [open, setOpen] = useState<string | null>(null)

  const groups = NAV_GROUPS.filter(
    (g) => !g.moduleKey || !modules || modules.includes(g.moduleKey),
  )

  const isActive = (to: string) =>
    to === '/' ? location.pathname === '/' : location.pathname.startsWith(to)

  return (
    <aside className="flex h-screen w-64 shrink-0 flex-col bg-sidebar text-slate-200">
      <div className="flex h-16 shrink-0 items-center gap-2.5 border-b border-white/10 px-4">
        <span className="grid h-9 w-9 place-items-center rounded-lg bg-brand text-sm font-bold text-white">
          PX
        </span>
        <div className="leading-tight">
          <div className="text-sm font-bold text-white">PLASTEX ERP</div>
          <div className="text-[10px] text-slate-400">Universal Business ERP</div>
        </div>
      </div>

      <nav className="flex-1 overflow-y-auto px-2 py-2">
        {groups.map((g) => {
          const active = isActive(g.to)
          const expanded = open === g.key || (active && g.children && open === null)
          return (
            <div key={g.key} className="mb-0.5">
              {g.children ? (
                <button
                  type="button"
                  onClick={() => setOpen(expanded ? '' : g.key)}
                  className={[
                    'flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm transition-colors',
                    active ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white',
                  ].join(' ')}
                >
                  <g.icon size={18} strokeWidth={1.75} />
                  <span className="flex-1 text-left">{g.label}</span>
                  <ChevronDown
                    size={15}
                    className={['transition-transform', expanded ? 'rotate-180' : ''].join(' ')}
                  />
                </button>
              ) : (
                <NavLink
                  to={g.to}
                  end
                  className={({ isActive: a }) =>
                    [
                      'flex items-center gap-3 rounded-md px-3 py-2 text-sm transition-colors',
                      a ? 'bg-sidebar-active text-white' : 'text-slate-300 hover:bg-sidebar-hover hover:text-white',
                    ].join(' ')
                  }
                >
                  <g.icon size={18} strokeWidth={1.75} />
                  <span>{g.label}</span>
                </NavLink>
              )}

              {g.children && expanded && (
                <div className="mb-1 ml-5 mt-0.5 space-y-0.5 border-l border-white/10 pl-3">
                  {g.children.map((c) => (
                    <NavLink
                      key={c.key}
                      to={c.to}
                      className={({ isActive: a }) =>
                        [
                          'block rounded px-2.5 py-1.5 text-[13px] transition-colors',
                          a ? 'bg-white/10 text-white' : 'text-slate-400 hover:text-white',
                        ].join(' ')
                      }
                    >
                      {c.label}
                    </NavLink>
                  ))}
                </div>
              )}
            </div>
          )
        })}
      </nav>

      <div className="shrink-0 border-t border-white/10 px-2 py-2">
        {BOTTOM_ITEMS.map((item) =>
          item.action === 'logout' ? (
            <button
              key={item.key}
              type="button"
              onClick={() => logout()}
              className="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm text-rose-300 hover:bg-sidebar-hover hover:text-rose-200"
            >
              <item.icon size={18} strokeWidth={1.75} />
              {item.label}
            </button>
          ) : (
            <Link
              key={item.key}
              to={item.to ?? '#'}
              className="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-slate-300 hover:bg-sidebar-hover hover:text-white"
            >
              <item.icon size={18} strokeWidth={1.75} />
              {item.label}
            </Link>
          ),
        )}
      </div>
    </aside>
  )
}
