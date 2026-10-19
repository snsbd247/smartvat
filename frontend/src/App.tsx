import { Navigate, Route, Routes, useParams } from 'react-router-dom'
import type { ReactNode } from 'react'
import { AppLayout } from './shared/layout/AppLayout'
import { DashboardPage } from './modules/dashboard/DashboardPage'
import { LoginPage } from './modules/auth/LoginPage'
import { ForgotPasswordPage } from './modules/auth/ForgotPasswordPage'
import { ResetPasswordPage } from './modules/auth/ResetPasswordPage'
import { ModulePlaceholder } from './shared/components/ModulePlaceholder'
import { useAuth } from './shared/auth/AuthContext'
import { ProductsPage } from './modules/masterdata/ProductsPage'
import { SuppliersPage } from './modules/masterdata/SuppliersPage'
import { CustomersPage } from './modules/masterdata/CustomersPage'
import { StockOverviewPage } from './modules/inventory/StockOverviewPage'
import { GoodsReceiptPage } from './modules/inventory/GoodsReceiptPage'
import { SalesInvoicePage } from './modules/sales/SalesInvoicePage'
import { SalesOrderPage } from './modules/sales/SalesOrderPage'
import { DeliveryChallanPage } from './modules/sales/DeliveryChallanPage'
import { CustomerReceiptPage } from './modules/sales/CustomerReceiptPage'
import { CustomerLedgerPage } from './modules/sales/CustomerLedgerPage'
import { PurchaseOrderPage } from './modules/purchase/PurchaseOrderPage'
import { PurchaseInvoicePage } from './modules/purchase/PurchaseInvoicePage'
import { SupplierPaymentPage } from './modules/purchase/SupplierPaymentPage'
import { SupplierLedgerPage } from './modules/purchase/SupplierLedgerPage'
import { ChartOfAccountsPage } from './modules/accounting/ChartOfAccountsPage'
import { OpeningBalancesPage } from './modules/accounting/OpeningBalancesPage'
import { JournalEntryPage } from './modules/accounting/JournalEntryPage'
import { ReportsPage } from './modules/reports/ReportsPage'
import { ReportViewPage } from './modules/reports/ReportViewPage'
import { VatRatesPage } from './modules/vat/VatRatesPage'
import { PriceDeclarationPage } from './modules/vat/PriceDeclarationPage'
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
import { ProductionWipPage, ProductionCostingPage, ProductionVariancePage } from './modules/production/ProductionAnalyticsPage'
import { ProductionQcPage } from './modules/production/ProductionQcPage'
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
import { DriveSettingsPage } from './modules/backup/DriveSettingsPage'
import { PurchaseReturnPage } from './modules/purchase/PurchaseReturnPage'
import { SalesReturnPage } from './modules/sales/SalesReturnPage'
import { ActivityLogsPage, LoginHistoryPage } from './modules/users/ActivityLogsPage'
import { RolesPage, PermissionsPage } from './modules/users/RolesPermissionsPage'
import { ImportShipmentPage } from './modules/purchase/ImportShipmentPage'
import { ExportInvoicePage } from './modules/sales/ExportInvoicePage'
import { ExportRealizationPage } from './modules/sales/ExportRealizationPage'
import { CashBookPage } from './modules/accounting/CashBookPage'
import { MaterialIssuePage } from './modules/inventory/MaterialIssuePage'
import { StockTransferPage } from './modules/inventory/StockTransferPage'
import { StockAdjustmentPage } from './modules/inventory/StockAdjustmentPage'
import { PhysicalCountPage } from './modules/inventory/PhysicalCountPage'
import { StockLedgerPage } from './modules/inventory/StockLedgerPage'
import { CostCentersPage } from './modules/accounting/CostCentersPage'
import { FundTransferPage } from './modules/accounting/FundTransferPage'
import { ExpensePage } from './modules/accounting/ExpensePage'
import { BudgetPage } from './modules/accounting/BudgetPage'
import { BankReconciliationPage } from './modules/accounting/BankReconciliationPage'
import { TradePage } from './modules/trade/TradePage'
import { PeriodsPage } from './modules/accounting/PeriodsPage'
import { AssetDisposalPage } from './modules/assets/AssetDisposalPage'
import { AssetLifecyclePage } from './modules/assets/AssetLifecyclePage'
import { JobWorkReportsPage } from './modules/jobwork/JobWorkReportsPage'
import { VdsReportsPage } from './modules/vds/VdsReportsPage'
import { NotificationsPage } from './modules/notifications/NotificationsPage'
import { ProfilePage } from './modules/profile/ProfilePage'
import { ChangePasswordPage } from './modules/profile/ChangePasswordPage'
import { HelpPage } from './modules/help/HelpPage'
import { SettingsPage } from './modules/settings/SettingsPage'
import { ManagedListPage } from './modules/settings/ManagedListPage'
import { VatVersionsPage } from './modules/settings/VatVersionsPage'
import { WorkflowsPage } from './modules/settings/WorkflowsPage'
import { TwoFactorPage } from './modules/settings/TwoFactorPage'
import { DocumentRepositoryPage } from './modules/documents/DocumentRepositoryPage'
import { DocumentAccessPage } from './modules/documents/DocumentAccessPage'

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
      <Route path="/forgot-password" element={<ForgotPasswordPage />} />
      <Route path="/reset-password" element={<ResetPasswordPage />} />
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
        <Route path="organization/company-profile" element={<SettingsPage group="general" />} />
        <Route path="organization/factory-branch" element={<FactoriesPage />} />
        <Route path="organization/warehouse" element={<WarehousesPage />} />
        <Route path="organization/financial-year" element={<PeriodsPage mode="view" />} />
        <Route path="organization/configuration" element={<CompanyConfigPage title="Company Configuration" subtitle="Business types drive which modules are enabled (enforced in the backend)" />} />
        <Route path="organization/business-type" element={<CompanyConfigPage title="Business Type & Industry" subtitle="Select the business types that activate each module" />} />

        {/* Users */}
        <Route path="users/users" element={<UsersPage />} />
        <Route path="users/roles" element={<RolesPage />} />
        <Route path="users/permissions" element={<PermissionsPage />} />
        <Route path="users/access" element={<UsersPage />} />
        <Route path="users/login-history" element={<LoginHistoryPage />} />
        <Route path="users/activity-logs" element={<ActivityLogsPage />} />

        {/* Backup & System Health */}
        <Route path="backup/health" element={<BackupHealthPage view="health" />} />
        <Route path="backup/history" element={<BackupHealthPage view="history" />} />
        <Route path="backup/drive" element={<DriveSettingsPage />} />
        <Route path="backup/retention" element={<BackupHealthPage view="retention" />} />
        <Route path="backup/restore" element={<BackupHealthPage view="restore" />} />
        <Route path="backup/logs" element={<BackupHealthPage view="logs" />} />

        {/* Master data */}
        <Route path="master-data/products" element={<ProductsPage />} />
        <Route path="master-data/uom" element={<ManagedListPage list="uom" />} />
        <Route path="master-data/bom" element={<BomsPage />} />
        <Route path="master-data/suppliers" element={<SuppliersPage />} />
        <Route path="master-data/customers" element={<CustomersPage />} />
        <Route path="master-data/tax-codes" element={<VatRatesPage />} />
        <Route path="master-data/currencies" element={<ManagedListPage list="currencies" />} />
        <Route path="master-data/coa" element={<ChartOfAccountsPage />} />
        <Route path="master-data/machines" element={<ManagedListPage list="machines" />} />

        {/* Inventory */}
        <Route path="inventory/overview" element={<StockOverviewPage />} />
        <Route path="inventory/goods-receipt" element={<GoodsReceiptPage />} />
        <Route path="inventory/material-issue" element={<MaterialIssuePage />} />
        <Route path="inventory/transfer" element={<StockTransferPage />} />
        <Route path="inventory/adjustment" element={<StockAdjustmentPage />} />
        <Route path="inventory/ledger" element={<StockLedgerPage />} />
        <Route path="inventory/count" element={<PhysicalCountPage />} />
        <Route path="inventory/valuation" element={<ReportViewPage reportKey="stock-valuation" />} />

        {/* Purchase */}
        <Route path="purchase/po" element={<PurchaseOrderPage />} />
        <Route path="purchase/requisition" element={<PurchaseOrderPage title="Purchase Requisition" subtitle="Raise an internal purchase request (becomes a PO on approval)" />} />
        <Route path="purchase/import" element={<ImportShipmentPage />} />
        <Route path="purchase/grn" element={<GoodsReceiptPage />} />
        <Route path="purchase/invoice" element={<PurchaseInvoicePage />} />
        <Route path="purchase/payment" element={<SupplierPaymentPage />} />
        <Route path="purchase/return" element={<PurchaseReturnPage />} />
        <Route path="purchase/landed-cost" element={<ImportShipmentPage title="Landed Cost Allocation" subtitle="Allocate freight, duty and charges across an import shipment" />} />
        <Route path="purchase/local" element={<SupplierLedgerPage />} />

        {/* Sales */}
        <Route path="sales/quotation" element={<SalesOrderPage mode="quotation" />} />
        <Route path="sales/order" element={<SalesOrderPage mode="order" />} />
        <Route path="sales/invoice" element={<SalesInvoicePage />} />
        <Route path="sales/export" element={<ExportInvoicePage />} />
        <Route path="sales/realization" element={<ExportRealizationPage />} />
        <Route path="sales/challan" element={<DeliveryChallanPage />} />
        <Route path="sales/receipt" element={<CustomerReceiptPage />} />
        <Route path="sales/return" element={<SalesReturnPage />} />
        <Route path="sales/local" element={<CustomerLedgerPage />} />

        {/* Trade & Logistics */}
        <Route path="trade/lc-tt" element={<TradePage kind="finance" />} />
        <Route path="trade/shipments" element={<TradePage kind="shipments" />} />
        <Route path="trade/export-docs" element={<TradePage kind="export-docs" />} />

        {/* Accounting */}
        <Route path="accounting/coa" element={<ChartOfAccountsPage />} />
        <Route path="accounting/opening-balances" element={<OpeningBalancesPage />} />
        <Route path="accounting/journals" element={<JournalEntryPage />} />
        <Route path="accounting/receipts-payments" element={<CashBookPage title="Receipts & Payments" subtitle="Cash/bank account movements — money in and out" />} />
        <Route path="accounting/ledger" element={<CashBookPage title="General Ledger" subtitle="Account ledger with running balance" />} />
        <Route path="accounting/fund-transfer" element={<FundTransferPage />} />
        <Route path="accounting/bank-rec" element={<BankReconciliationPage />} />
        <Route path="accounting/expenses" element={<ExpensePage />} />
        <Route path="accounting/budget" element={<BudgetPage />} />
        <Route path="accounting/period-closing" element={<PeriodsPage mode="manage" />} />
        <Route path="master-data/cost-centres" element={<CostCentersPage />} />
        <Route path="accounting/trial-balance" element={<ReportViewPage reportKey="trial-balance" />} />
        <Route path="accounting/profit-loss" element={<ReportViewPage reportKey="profit-loss" />} />
        <Route path="accounting/balance-sheet" element={<ReportViewPage reportKey="balance-sheet" />} />

        {/* VAT */}
        <Route path="vat/configuration" element={<VatRatesPage />} />
        <Route path="vat/price-declaration" element={<PriceDeclarationPage />} />
        <Route path="vat/catalogue" element={<VatNotesPage />} />
        <Route path="vat/registers" element={<VatMappingsPage />} />
        <Route path="vat/reconciliation" element={<VatUnmappedPage />} />
        <Route path="vat/returns" element={<VatClassifyPage title="VAT Return Preparation" subtitle="Classify period transactions to verified Mushak notes for the return" />} />
        <Route path="vat/mushak-6-3" element={<VatClassifyPage title="Mushak 6.3 — Tax Invoice" subtitle="Classify a transaction to a verified Mushak note (never guessed)" />} />
        <Route path="vat/mushak-9-1" element={<ReportViewPage reportKey="vat-note-summary" />} />

        {/* VDS */}
        <Route path="vds/rules" element={<VdsRulesPage />} />
        <Route path="vds/deduction" element={<VdsDeductionPage />} />
        <Route path="vds/register" element={<VdsRegisterPage />} />
        <Route path="vds/certificate" element={<VdsRegisterPage title="Certificate Management" subtitle="Issue and track VDS deduction certificates" />} />
        <Route path="vds/treasury" element={<VdsReconPage title="Treasury Deposit" subtitle="Deposit deducted VDS to the treasury and reconcile" />} />
        <Route path="vds/reconciliation" element={<VdsReconPage />} />
        <Route path="vds/reports" element={<VdsReportsPage />} />

        {/* Production */}
        <Route path="production/bom" element={<BomsPage />} />
        <Route path="production/planning" element={<ProductionOrdersPage focus="planning" />} />
        <Route path="production/orders" element={<ProductionOrdersPage focus="orders" />} />
        <Route path="production/issue" element={<ProductionOrdersPage focus="issue" />} />
        <Route path="production/wip" element={<ProductionWipPage />} />
        <Route path="production/fg-receipt" element={<ProductionOrdersPage focus="fg-receipt" />} />
        <Route path="production/qc" element={<ProductionQcPage />} />
        <Route path="production/costing" element={<ProductionCostingPage />} />
        <Route path="production/variance" element={<ProductionVariancePage />} />

        {/* Fixed Assets */}
        <Route path="assets/register" element={<FixedAssetsPage title="Asset Register" subtitle="All fixed assets with cost, depreciation and book value" />} />
        <Route path="assets/acquisition" element={<FixedAssetsPage title="Asset Acquisition" subtitle="Record a newly acquired fixed asset" />} />
        <Route path="assets/depreciation" element={<FixedAssetsPage title="Depreciation" subtitle="Run straight-line depreciation for the period" />} />
        <Route path="assets/transfer" element={<AssetLifecyclePage action="transfer" />} />
        <Route path="assets/revaluation" element={<AssetLifecyclePage action="revaluation" />} />
        <Route path="assets/maintenance" element={<AssetLifecyclePage action="maintenance" />} />
        <Route path="assets/disposal" element={<AssetDisposalPage />} />
        <Route path="assets/categories" element={<AssetCategoriesPage />} />
        <Route path="assets/reports" element={<ReportsPage />} />

        {/* Job Work */}
        <Route path="job-work/contracts" element={<JobWorkContractsPage />} />
        <Route path="job-work/orders" element={<JobWorkOrdersPage focus="orders" />} />
        <Route path="job-work/material-receipt" element={<JobWorkOrdersPage focus="material-receipt" />} />
        <Route path="job-work/consumption" element={<JobWorkOrdersPage focus="consumption" />} />
        <Route path="job-work/output" element={<JobWorkOrdersPage focus="output" />} />
        <Route path="job-work/delivery" element={<JobWorkOrdersPage focus="delivery" />} />
        <Route path="job-work/processing-fee" element={<JobWorkOrdersPage focus="processing-fee" />} />
        <Route path="job-work/reports" element={<JobWorkReportsPage />} />

        {/* Approval Center */}
        <Route path="approvals/purchase" element={<ApprovalCenterPage kind="purchase" />} />
        <Route path="approvals/sales" element={<ApprovalCenterPage kind="sales" />} />
        <Route path="approvals/payment" element={<ApprovalCenterPage kind="payment" />} />
        <Route path="approvals/journal" element={<ApprovalCenterPage kind="journal" />} />
        <Route path="approvals/production" element={<ApprovalCenterPage kind="production" />} />
        <Route path="approvals/vat" element={<VatUnmappedPage />} />

        {/* Reports hub */}
        <Route path="reports/sales" element={<ReportsPage category="sales" />} />
        <Route path="reports/purchase" element={<ReportsPage category="purchase" />} />
        <Route path="reports/inventory" element={<ReportsPage category="inventory" />} />
        <Route path="reports/accounting" element={<ReportsPage category="accounting" />} />
        <Route path="reports/vat-vds" element={<ReportsPage category="vat-vds" />} />
        <Route path="reports/aging" element={<ReportsPage category="aging" />} />
        <Route path="reports/builder" element={<ReportsPage category="all" />} />
        <Route path="reports/view/:key" element={<ReportViewRoute />} />

        {/* Document Center */}
        <Route path="documents/repository" element={<DocumentRepositoryPage />} />
        <Route path="documents/pdf-export" element={<ReportsPage />} />
        <Route path="documents/excel-export" element={<ReportsPage />} />
        <Route path="documents/access" element={<DocumentAccessPage />} />

        {/* Settings & Administration */}
        <Route path="settings/general" element={<SettingsPage group="general" />} />
        <Route path="settings/numbering" element={<SettingsPage group="numbering" />} />
        <Route path="settings/workflows" element={<WorkflowsPage />} />
        <Route path="settings/notifications" element={<SettingsPage group="notifications" />} />
        <Route path="settings/email" element={<SettingsPage group="email" />} />
        <Route path="settings/two-factor" element={<TwoFactorPage />} />
        <Route path="settings/vat-versions" element={<VatVersionsPage />} />
        <Route path="settings/audit-policy" element={<SettingsPage group="audit-policy" />} />

        {/* Account (bottom bar) */}
        <Route path="profile" element={<ProfilePage />} />
        <Route path="change-password" element={<ChangePasswordPage />} />
        <Route path="notifications" element={<NotificationsPage />} />
        <Route path="help" element={<HelpPage />} />

        {/* Everything else: titled placeholder (API ready) */}
        <Route path="*" element={<ModulePlaceholder />} />
      </Route>
    </Routes>
  )
}
