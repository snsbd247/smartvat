import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { describe, expect, it } from 'vitest'
import { AppLayout } from './AppLayout'
import { NAV_ITEMS } from './navigation'
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
  it('renders the brand and key module links', () => {
    renderLayout()
    expect(screen.getByText('Universal ERP')).toBeInTheDocument()
    // Modules not loaded in test -> all nav items shown.
    expect(screen.getByText('Inventory & Warehouse')).toBeInTheDocument()
    expect(screen.getByText('VAT & Mushak')).toBeInTheDocument()
  })

  it('excludes HR & Payroll from navigation (out of scope)', () => {
    renderLayout()
    expect(screen.queryByText(/payroll/i)).not.toBeInTheDocument()
    const labels = NAV_ITEMS.map((i) => i.label.toLowerCase())
    expect(labels.some((l) => l.includes('payroll'))).toBe(false)
  })
})
