import axios from 'axios'

/**
 * Shared Axios instance for the ERP API.
 *
 * Base URL defaults to the same-origin `/api` (Vite proxies this to the Laravel
 * backend in dev; Nginx reverse-proxies it in production). Sanctum token, if
 * present, is attached automatically.
 */
export const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? '/api',
  headers: {
    Accept: 'application/json',
  },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('erp_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

export interface HealthResponse {
  status: 'ok' | 'degraded'
  app: string
  version: string
  environment: string
  time: string
  checks: { database: 'ok' | 'unavailable' }
}

export async function fetchHealth(): Promise<HealthResponse> {
  const { data } = await api.get<HealthResponse>('/health')
  return data
}
