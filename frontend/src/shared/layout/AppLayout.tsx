import { Outlet } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Sidebar } from './Sidebar'
import { Topbar } from './Topbar'
import { useAuth } from '../auth/AuthContext'
import { fetchCompanies, fetchMe, fetchModules } from '../lib/api'

export function AppLayout() {
  const { companies: authCompanies, companyId, setCompany } = useAuth()

  const { data: me } = useQuery({ queryKey: ['me', companyId], queryFn: fetchMe })
  const { data: companiesData } = useQuery({ queryKey: ['companies'], queryFn: fetchCompanies })
  const { data: modules } = useQuery({ queryKey: ['modules', companyId], queryFn: fetchModules, staleTime: 60_000 })

  const companies = companiesData ?? authCompanies

  return (
    <div className="flex h-screen overflow-hidden bg-workspace">
      <Sidebar modules={modules} />
      <div className="flex min-w-0 flex-1 flex-col">
        <Topbar
          companies={companies}
          companyId={companyId}
          onCompanyChange={setCompany}
          userName={me?.name ?? 'User'}
        />
        <main className="flex-1 overflow-y-auto p-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
