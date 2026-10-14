import { Bell, CalendarDays, Search } from 'lucide-react'
import type { Company } from '../lib/api'

export function Topbar({
  companies,
  companyId,
  onCompanyChange,
  userName,
}: {
  companies: Company[]
  companyId: number | null
  onCompanyChange: (id: number) => void
  userName: string
}) {
  return (
    <header className="flex h-16 shrink-0 items-center gap-4 border-b border-slate-200 bg-white px-5">
      <div className="relative hidden flex-1 md:block">
        <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
        <input
          type="search"
          placeholder="Search anything (product, customer, invoice, PO, challan…)"
          className="w-full max-w-xl rounded-lg border border-slate-200 bg-slate-50 py-2 pl-9 pr-3 text-sm outline-none focus:border-brand focus:bg-white"
        />
      </div>

      <div className="ml-auto flex items-center gap-3">
        {companies.length > 0 && (
          <select
            value={companyId ?? ''}
            onChange={(e) => onCompanyChange(Number(e.target.value))}
            className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-ink outline-none focus:border-brand"
          >
            {companies.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </select>
        )}

        <div className="hidden items-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-muted lg:flex">
          <CalendarDays size={15} />
          {new Date().getFullYear()} · This Year
        </div>

        <button
          type="button"
          className="relative rounded-lg border border-slate-200 p-2 text-muted hover:bg-slate-50"
          aria-label="Notifications"
        >
          <Bell size={16} />
          <span className="absolute -right-1 -top-1 grid h-4 min-w-4 place-items-center rounded-full bg-brand px-1 text-[10px] font-semibold text-white">
            0
          </span>
        </button>

        <div className="flex items-center gap-2">
          <span className="grid h-8 w-8 place-items-center rounded-full bg-sidebar text-xs font-semibold text-white">
            {userName.slice(0, 1).toUpperCase()}
          </span>
          <div className="hidden leading-tight sm:block">
            <div className="text-sm font-medium text-ink">{userName}</div>
            <div className="text-[11px] text-muted">Signed in</div>
          </div>
        </div>
      </div>
    </header>
  )
}
