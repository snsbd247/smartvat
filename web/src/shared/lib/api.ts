import axios from 'axios'

// Default to the API under the app's base path (e.g. "/irlvat/api").
const defaultBaseUrl = `${import.meta.env.BASE_URL.replace(/\/+$/, '')}/api`

export const TOKEN_KEY = 'erp_token'
export const COMPANY_KEY = 'erp_company'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? defaultBaseUrl,
  headers: { Accept: 'application/json' },
})

api.interceptors.request.use((config) => {
  try {
    const token = localStorage.getItem(TOKEN_KEY)
    if (token) config.headers.Authorization = `Bearer ${token}`
    const company = localStorage.getItem(COMPANY_KEY)
    if (company) config.headers['X-Company-Id'] = company
  } catch {
    /* localStorage may be unavailable (private mode) */
  }
  return config
})

// On an expired/invalid token, drop it and bounce to the login screen.
api.interceptors.response.use(
  (res) => res,
  (error) => {
    const status = error?.response?.status
    const isLoginCall = String(error?.config?.url ?? '').includes('/auth/login')
    if (status === 401 && !isLoginCall) {
      try {
        localStorage.removeItem(TOKEN_KEY)
        localStorage.removeItem(COMPANY_KEY)
      } catch {
        /* ignore */
      }
      const base = import.meta.env.BASE_URL.replace(/\/+$/, '')
      if (!window.location.pathname.endsWith('/login')) {
        window.location.assign(`${base}/login`)
      }
    }
    return Promise.reject(error)
  },
)

// ---- types ----
export interface Company {
  id: number
  code: string
  name: string
  is_default?: boolean
}
export interface LoginResponse {
  token: string
  user: { id: number; name: string; email: string; username: string | null }
  companies: Company[]
}
export interface Executive {
  revenue: string
  expense: string
  net_profit: string
  receivables: string
  payables: string
  vds_payable: string
  stock_value: string
  cash_bank: string
  pending_approvals: number
  vat_unmapped: number
}

// ---- auth ----
export async function login(loginId: string, password: string): Promise<LoginResponse> {
  const { data } = await api.post<LoginResponse>('/auth/login', {
    login: loginId,
    password,
  })
  return data
}

export async function logout(): Promise<void> {
  try {
    await api.post('/auth/logout')
  } catch {
    /* ignore — token is cleared locally regardless */
  }
}

export interface Me {
  id: number
  name: string
  email: string
  username: string | null
  roles: string[]
  permissions: string[]
}

export async function fetchMe(): Promise<Me> {
  const { data } = await api.get<Me>('/auth/me')
  return data
}

export async function fetchCompanies(): Promise<Company[]> {
  const { data } = await api.get<{ data: Company[] }>('/companies')
  // CompanyResource collection is wrapped in { data: [...] }
  return (data as unknown as { data: Company[] }).data ?? (data as unknown as Company[])
}

export async function fetchModules(): Promise<string[]> {
  const { data } = await api.get<{ modules: string[] }>('/company/modules')
  return data.modules
}

export async function fetchExecutive(): Promise<Executive> {
  const { data } = await api.get<Executive>('/mis/executive')
  return data
}

// token helpers
export const tokenStore = {
  get: () => {
    try {
      return localStorage.getItem(TOKEN_KEY)
    } catch {
      return null
    }
  },
  set: (token: string, companyId?: number) => {
    try {
      localStorage.setItem(TOKEN_KEY, token)
      if (companyId != null) localStorage.setItem(COMPANY_KEY, String(companyId))
    } catch {
      /* ignore */
    }
  },
  setCompany: (companyId: number) => {
    try {
      localStorage.setItem(COMPANY_KEY, String(companyId))
    } catch {
      /* ignore */
    }
  },
  getCompany: () => {
    try {
      return localStorage.getItem(COMPANY_KEY)
    } catch {
      return null
    }
  },
  clear: () => {
    try {
      localStorage.removeItem(TOKEN_KEY)
      localStorage.removeItem(COMPANY_KEY)
    } catch {
      /* ignore */
    }
  },
}
