import { createContext, useContext, useMemo, useState, type ReactNode } from 'react'
import {
  type Company,
  login as apiLogin,
  logout as apiLogout,
  tokenStore,
} from '../lib/api'

interface AuthUser {
  id: number
  name: string
  email: string
  username: string | null
}

interface AuthState {
  isAuthenticated: boolean
  user: AuthUser | null
  companies: Company[]
  companyId: number | null
  login: (loginId: string, password: string) => Promise<void>
  logout: () => Promise<void>
  setCompany: (companyId: number) => void
}

const AuthContext = createContext<AuthState | undefined>(undefined)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [token, setToken] = useState<string | null>(() => tokenStore.get())
  const [user, setUser] = useState<AuthUser | null>(null)
  const [companies, setCompanies] = useState<Company[]>([])
  const [companyId, setCompanyId] = useState<number | null>(() => {
    const c = tokenStore.getCompany()
    return c ? Number(c) : null
  })

  const value = useMemo<AuthState>(
    () => ({
      isAuthenticated: !!token,
      user,
      companies,
      companyId,
      login: async (loginId, password) => {
        const res = await apiLogin(loginId, password)
        const def = res.companies.find((c) => c.is_default) ?? res.companies[0]
        tokenStore.set(res.token, def?.id)
        setToken(res.token)
        setUser(res.user)
        setCompanies(res.companies)
        setCompanyId(def?.id ?? null)
      },
      logout: async () => {
        await apiLogout()
        tokenStore.clear()
        setToken(null)
        setUser(null)
        setCompanies([])
        setCompanyId(null)
      },
      setCompany: (id: number) => {
        tokenStore.setCompany(id)
        setCompanyId(id)
        // Reload so every query re-fetches under the new company context.
        window.location.reload()
      },
    }),
    [token, user, companies, companyId],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthState {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used within AuthProvider')
  return ctx
}
