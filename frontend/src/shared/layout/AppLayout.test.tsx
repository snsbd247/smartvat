import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { describe, expect, it } from 'vitest'
import { AppLayout } from './AppLayout'
import { NAV_GROUPS } from './navigation'
import { AuthProvider } from '../auth/AuthContext'

function renderLayout() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <AuthProvider>
        <MemoryRouter initialEntries={['/']}>
          <Routes>
            <Route element={<AppLayout />}>
              <Route index element={<div>home</div>} />
            </Route>
          </Routes>
        </MemoryRouter>
      </AuthProvider>
    </QueryClientProvider>,
  )
}

describe('AppLayout sidebar', () => {
  it('renders the PLASTEX brand and key modules', () => {
    renderLayout()
    expect(screen.getByText('PLASTEX ERP')).toBeInTheDocument()
    expect(screen.getByText('Inventory & Warehouse')).toBeInTheDocument()
    expect(screen.getByText('VAT & Mushak')).toBeInTheDocument()
    expect(screen.getByText('Logout')).toBeInTheDocument()
  })

  it('excludes HR & Payroll from navigation (out of scope)', () => {
    renderLayout()
    expect(screen.queryByText(/payroll/i)).not.toBeInTheDocument()
    const labels = NAV_GROUPS.map((i) => i.label.toLowerCase())
    expect(labels.some((l) => l.includes('payroll') || l.includes('hr'))).toBe(false)
  })
})
