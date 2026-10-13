import { Navigate, Route, Routes } from 'react-router-dom'
import type { ReactNode } from 'react'
import { AppLayout } from './shared/layout/AppLayout'
import { DashboardPage } from './modules/dashboard/DashboardPage'
import { LoginPage } from './modules/auth/LoginPage'
import { ModulePlaceholder } from './shared/components/ModulePlaceholder'
import { useAuth } from './shared/auth/AuthContext'
import { ProductsPage } from './modules/masterdata/ProductsPage'
import { SuppliersPage } from './modules/masterdata/SuppliersPage'
import { CustomersPage } from './modules/masterdata/CustomersPage'
import { StockOverviewPage } from './modules/inventory/StockOverviewPage'
import { GoodsReceiptPage } from './modules/inventory/GoodsReceiptPage'
import { SalesInvoicePage } from './modules/sales/SalesInvoicePage'

function RequireAuth({ children }: { children: ReactNode }) {
  const { isAuthenticated } = useAuth()
  if (!isAuthenticated) return <Navigate to="/login" replace />
  return <>{children}</>
}

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route
        path="/*"
        element={
          <RequireAuth>
            <AppLayout />
          </RequireAuth>
        }
      >
        <Route index element={<DashboardPage />} />

        {/* Implemented module screens (write + read real data) */}
        <Route path="master-data/products" element={<ProductsPage />} />
        <Route path="master-data/suppliers" element={<SuppliersPage />} />
        <Route path="master-data/customers" element={<CustomersPage />} />
        <Route path="inventory/overview" element={<StockOverviewPage />} />
        <Route path="inventory/goods-receipt" element={<GoodsReceiptPage />} />
        <Route path="sales/invoice" element={<SalesInvoicePage />} />

        {/* Everything else: titled placeholder (API ready) */}
        <Route path="*" element={<ModulePlaceholder />} />
      </Route>
    </Routes>
  )
}
