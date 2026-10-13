import { Route, Routes } from 'react-router-dom'
import { AppLayout } from './shared/layout/AppLayout'
import { DashboardPage } from './modules/dashboard/DashboardPage'
import { NAV_ITEMS } from './shared/layout/navigation'

function Placeholder({ title }: { title: string }) {
  return (
    <div className="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center">
      <h2 className="text-lg font-semibold text-ink">{title}</h2>
      <p className="mt-2 text-sm text-muted">
        This module will be implemented in a later phase.
      </p>
    </div>
  )
}

export default function App() {
  return (
    <Routes>
      <Route element={<AppLayout />}>
        <Route index element={<DashboardPage />} />
        {NAV_ITEMS.filter((item) => item.to !== '/').map((item) => (
          <Route
            key={item.key}
            path={item.to}
            element={<Placeholder title={item.label} />}
          />
        ))}
      </Route>
    </Routes>
  )
}
