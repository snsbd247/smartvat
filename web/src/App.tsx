import { Navigate, Route, Routes, useParams } from 'react-router-dom'
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
import { DeliveryChallanPage } from './modules/sales/DeliveryChallanPage'
import { CustomerReceiptPage } from './modules/sales/CustomerReceiptPage'
import { CustomerLedgerPage } from './modules/sales/CustomerLedgerPage'
import { PurchaseOrderPage } from './modules/purchase/PurchaseOrderPage'
import { PurchaseInvoicePage } from './modules/purchase/PurchaseInvoicePage'
import { SupplierPaymentPage } from './modules/purchase/SupplierPaymentPage'
import { SupplierLedgerPage } from './modules/purchase/SupplierLedgerPage'
import { ChartOfAccountsPage } from './modules/accounting/ChartOfAccountsPage'
import { JournalEntryPage } from './modules/accounting/JournalEntryPage'
import { ReportsPage } from './modules/reports/ReportsPage'
import { ReportViewPage } from './modules/reports/ReportViewPage'
import { VatRatesPage } from './modules/vat/VatRatesPage'
import { VatNotesPage } from './modules/vat/VatNotesPage'
import { VatMappingsPage } from './modules/vat/VatMappingsPage'
import { VatClassifyPage } from './modules/vat/VatClassifyPage'
import { VatUnmappedPage } from './modules/vat/VatUnmappedPage'
import { VdsRulesPage } from './modules/vds/VdsRulesPage'
import { VdsDeductionPage } from './modules/vds/VdsDeductionPage'
import { VdsReconPage } from './modules/vds/VdsReconPage'
import { VdsRegisterPage } from './modules/vds/VdsRegisterPage'
import { BomsPage } from './modules/production/BomsPage'
import { ProductionOrdersPage } from './modules/production/ProductionOrdersPage'
import { AssetCategoriesPage } from './modules/assets/AssetCategoriesPage'
import { FixedAssetsPage } from './modules/assets/FixedAssetsPage'
import { JobWorkContractsPage } from './modules/jobwork/JobWorkContractsPage'
import { JobWorkOrdersPage } from './modules/jobwork/JobWorkOrdersPage'
import { ApprovalCenterPage } from './modules/approvals/ApprovalCenterPage'
import { FactoriesPage } from './modules/organization/FactoriesPage'
import { WarehousesPage } from './modules/organization/WarehousesPage'
import { CompanyConfigPage } from './modules/organization/CompanyConfigPage'
import { UsersPage } from './modules/users/UsersPage'
import { BackupHealthPage } from './modules/backup/BackupHealthPage'
import { PurchaseReturnPage } from './modules/purchase/PurchaseReturnPage'
import { SalesReturnPage } from './modules/sales/SalesReturnPage'
import { ActivityLogsPage, LoginHistoryPage } from './modules/users/ActivityLogsPage'
import { RolesPage, PermissionsPage } from './modules/users/RolesPermissionsPage'
import { ImportShipmentPage } from './modules/purchase/ImportShipmentPage'
import { ExportInvoicePage } from './modules/sales/ExportInvoicePage'
import { ExportRealizationPage } from './modules/sales/ExportRealizationPage'
import { CashBookPage } from './modules/accounting/CashBookPage'

function RequireAuth({ children }: { children: ReactNode }) {
  const { isAuthenticated } = useAuth()
  if (!isAuthenticated) return <Navigate to="/login" replace />
  return <>{children}</>
}

function ReportViewRoute() {
  const { key = 'trial-balance' } = useParams()
  return <ReportViewPage reportKey={key} />
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

        {/* Organization */}
        <Route path="organization/factory-branch" element={<FactoriesPage />} />
        <Route path="organization/warehouse" element={<WarehousesPage />} />
        <Route path="organization/configuration" element={<CompanyConfigPage />} />
        <Route path="organization/business-type" element={<CompanyConfigPage />} />

        {/* Users */}
        <Route path="users/users" element={<UsersPage />} />
        <Route path="users/roles" element={<RolesPage />} />
        <Route path="users/permissions" element={<PermissionsPage />} />
        <Route path="users/login-history" element={<LoginHistoryPage />} />
        <Route path="users/activity-logs" element={<ActivityLogsPage />} />

        {/* Backup & System Health */}
        <Route path="backup/health" element={<BackupHealthPage />} />
        <Route path="backup/history" element={<BackupHealthPage />} />
        <Route path="backup/drive" element={<BackupHealthPage />} />

        {/* Master data */}
        <Route path="master-data/products" element={<ProductsPage />} />
        <Route path="master-data/suppliers" element={<SuppliersPage />} />
        <Route path="master-data/customers" element={<CustomersPage />} />

        {/* Inventory */}
        <Route path="inventory/overview" element={<StockOverviewPage />} />
        <Route path="inventory/goods-receipt" element={<GoodsReceiptPage />} />
        <Route path="inventory/valuation" element={<ReportViewPage reportKey="stock-valuation" />} />

        {/* Purchase */}
        <Route path="purchase/po" element={<PurchaseOrderPage />} />
        <Route path="purchase/requisition" element={<PurchaseOrderPage />} />
        <Route path="purchase/import" element={<ImportShipmentPage />} />
        <Route path="purchase/grn" element={<GoodsReceiptPage />} />
        <Route path="purchase/invoice" element={<PurchaseInvoicePage />} />
        <Route path="purchase/payment" element={<SupplierPaymentPage />} />
        <Route path="purchase/return" element={<PurchaseReturnPage />} />
        <Route path="purchase/local" element={<SupplierLedgerPage />} />

        {/* Sales */}
        <Route path="sales/invoice" element={<SalesInvoicePage />} />
        <Route path="sales/export" element={<ExportInvoicePage />} />
        <Route path="sales/realization" element={<ExportRealizationPage />} />
        <Route path="sales/challan" element={<DeliveryChallanPage />} />
        <Route path="sales/receipt" element={<CustomerReceiptPage />} />
        <Route path="sales/return" element={<SalesReturnPage />} />
        <Route path="sales/local" element={<CustomerLedgerPage />} />

        {/* Accounting */}
        <Route path="accounting/coa" element={<ChartOfAccountsPage />} />
        <Route path="accounting/journals" element={<JournalEntryPage />} />
        <Route path="accounting/receipts-payments" element={<CashBookPage />} />
        <Route path="accounting/ledger" element={<CashBookPage />} />
        <Route path="accounting/trial-balance" element={<ReportViewPage reportKey="trial-balance" />} />
        <Route path="accounting/profit-loss" element={<ReportViewPage reportKey="profit-loss" />} />
        <Route path="accounting/balance-sheet" element={<ReportViewPage reportKey="balance-sheet" />} />

        {/* VAT */}
        <Route path="vat/configuration" element={<VatRatesPage />} />
        <Route path="vat/catalogue" element={<VatNotesPage />} />
        <Route path="vat/registers" element={<VatMappingsPage />} />
        <Route path="vat/reconciliation" element={<VatUnmappedPage />} />
        <Route path="vat/returns" element={<VatClassifyPage />} />
        <Route path="vat/mushak-9-1" element={<ReportViewPage reportKey="vat-note-summary" />} />

        {/* VDS */}
        <Route path="vds/rules" element={<VdsRulesPage />} />
        <Route path="vds/deduction" element={<VdsDeductionPage />} />
        <Route path="vds/register" element={<VdsRegisterPage />} />
        <Route path="vds/certificate" element={<VdsRegisterPage />} />
        <Route path="vds/treasury" element={<VdsReconPage />} />
        <Route path="vds/reconciliation" element={<VdsReconPage />} />

        {/* Production */}
        <Route path="production/bom" element={<BomsPage />} />
        <Route path="production/orders" element={<ProductionOrdersPage />} />
        <Route path="production/issue" element={<ProductionOrdersPage />} />
        <Route path="production/fg-receipt" element={<ProductionOrdersPage />} />

        {/* Fixed Assets */}
        <Route path="assets/register" element={<FixedAssetsPage />} />
        <Route path="assets/acquisition" element={<FixedAssetsPage />} />
        <Route path="assets/depreciation" element={<FixedAssetsPage />} />
        <Route path="assets/categories" element={<AssetCategoriesPage />} />

        {/* Job Work */}
        <Route path="job-work/contracts" element={<JobWorkContractsPage />} />
        <Route path="job-work/orders" element={<JobWorkOrdersPage />} />
        <Route path="job-work/material-receipt" element={<JobWorkOrdersPage />} />
        <Route path="job-work/processing-fee" element={<JobWorkOrdersPage />} />

        {/* Approval Center */}
        <Route path="approvals/purchase" element={<ApprovalCenterPage />} />
        <Route path="approvals/sales" element={<ApprovalCenterPage />} />
        <Route path="approvals/payment" element={<ApprovalCenterPage />} />
        <Route path="approvals/journal" element={<ApprovalCenterPage />} />
        <Route path="approvals/production" element={<ApprovalCenterPage />} />
        <Route path="approvals/vat" element={<ApprovalCenterPage />} />

        {/* Reports hub */}
        <Route path="reports/builder" element={<ReportsPage />} />
        <Route path="reports/view/:key" element={<ReportViewRoute />} />

        {/* Everything else: titled placeholder (API ready) */}
        <Route path="*" element={<ModulePlaceholder />} />
      </Route>
    </Routes>
  )
}
