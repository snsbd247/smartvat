import { useLocation } from 'react-router-dom'

const titleFromPath = (path: string) =>
  path
    .split('/')
    .filter(Boolean)
    .map((s) => s.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()))
    .join(' › ') || 'Module'

export function ModulePlaceholder() {
  const { pathname } = useLocation()
  return (
    <div>
      <h2 className="mb-1 text-xl font-semibold text-ink">{titleFromPath(pathname)}</h2>
      <p className="mb-6 text-sm text-muted">
        This screen is part of the PLASTEX ERP plan. The backend API for this area is
        implemented and tested — the UI screen is being built out phase by phase.
      </p>
      <div className="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-muted">
        Coming soon.
      </div>
    </div>
  )
}
