import { Navigate, Route, Routes } from 'react-router-dom'
import { AppLayout } from './shared/layout/AppLayout'
import { DashboardPage } from './modules/dashboard/DashboardPage'
import { LoginPage } from './modules/auth/LoginPage'
import { NAV_ITEMS } from './shared/layout/navigation'
import { useAuth } from './shared/auth/AuthContext'
import type { ReactNode } from 'react'

function Placeholder({ title }: { title: string }) {
  return (
    <div className="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center">
      <h2 className="text-lg font-semibold text-ink">{title}</h2>
      <p className="mt-2 text-sm text-muted">
        This module will get its screens in an upcoming frontend phase. The API is ready.
      </p>
    </div>
  )
}

function RequireAuth({ children }: { children: ReactNode }) {
  const { isAuthenticated } = useAuth()
  if (!isAuthenticated) {
    return <Navigate to="/login" replace />
  }
  return <>{children}</>
}

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route
        element={
          <RequireAuth>
            <AppLayout />
          </RequireAuth>
        }
      >
        <Route index element={<DashboardPage />} />
        {NAV_ITEMS.filter((item) => item.to !== '/').map((item) => (
          <Route key={item.key} path={item.to} element={<Placeholder title={item.label} />} />
        ))}
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
