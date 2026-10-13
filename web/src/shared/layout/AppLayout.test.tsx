import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { describe, expect, it } from 'vitest'
import { AppLayout } from './AppLayout'
import { NAV_ITEMS } from './navigation'

function renderLayout() {
  return render(
    <MemoryRouter initialEntries={['/']}>
      <Routes>
        <Route element={<AppLayout />}>
          <Route index element={<div>home</div>} />
        </Route>
      </Routes>
    </MemoryRouter>,
  )
}

describe('AppLayout sidebar', () => {
  it('renders the brand and key module links', () => {
    renderLayout()
    expect(screen.getByText('Universal ERP')).toBeInTheDocument()
    expect(screen.getByText('Inventory & Warehouse')).toBeInTheDocument()
    expect(screen.getByText('VAT & Mushak')).toBeInTheDocument()
  })

  it('excludes HR & Payroll from navigation (out of scope)', () => {
    renderLayout()
    expect(screen.queryByText(/payroll/i)).not.toBeInTheDocument()
    expect(screen.queryByText(/^HR$/i)).not.toBeInTheDocument()
    const labels = NAV_ITEMS.map((i) => i.label.toLowerCase())
    expect(labels.some((l) => l.includes('payroll'))).toBe(false)
  })
})
