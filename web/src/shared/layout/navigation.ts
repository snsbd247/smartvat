import {
  LayoutDashboard,
  Building2,
  Users,
  Database,
  Warehouse,
  Factory,
  ShoppingCart,
  Truck,
  Handshake,
  Calculator,
  ReceiptText,
  Percent,
  Landmark,
  BarChart3,
  FileText,
  ShieldCheck,
  CheckCircle2,
  Settings,
  UserCircle,
  KeyRound,
  Bell,
  LifeBuoy,
  LogOut,
  type LucideIcon,
} from 'lucide-react'

export interface NavChild {
  key: string
  label: string
  to: string
}
export interface NavGroup {
  key: string
  label: string
  to: string
  icon: LucideIcon
  moduleKey?: string // config/erp.php module key; omitted = always shown
  children?: NavChild[]
}

const sub = (base: string, items: [string, string][]): NavChild[] =>
  items.map(([key, label]) => ({ key, label, to: `${base}/${key}` }))

export const NAV_GROUPS: NavGroup[] = [
  { key: 'dashboard', label: 'Dashboard', to: '/', icon: LayoutDashboard },
  {
    key: 'organization',
    label: 'Organization Management',
    to: '/organization',
    icon: Building2,
    children: sub('/organization', [
      ['company-profile', 'Company Profile'],
      ['business-type', 'Business Type & Industry'],
      ['factory-branch', 'Factory & Branch'],
      ['warehouse', 'Warehouse'],
      ['financial-year', 'Financial Year'],
      ['configuration', 'Company Configuration'],
    ]),
  },
  {
    key: 'users',
    label: 'User Management',
    to: '/users',
    icon: Users,
    children: sub('/users', [
      ['users', 'Users'],
      ['roles', 'Roles'],
      ['permissions', 'Permissions'],
      ['access', 'Factory/Warehouse Access'],
      ['login-history', 'Login History'],
      ['activity-logs', 'Activity Logs'],
    ]),
  },
  {
    key: 'master-data',
    label: 'Master Data',
    to: '/master-data',
    icon: Database,
    children: sub('/master-data', [
      ['products', 'Products & Categories'],
      ['uom', 'Units of Measure'],
      ['bom', 'BOM & Recipes'],
      ['customers', 'Customers'],
      ['suppliers', 'Suppliers'],
      ['tax-codes', 'Tax Codes & VAT Rates'],
      ['currencies', 'Currencies & Exchange Rates'],
      ['coa', 'Chart of Accounts'],
      ['cost-centres', 'Cost Centres'],
      ['machines', 'Machines & Work Centres'],
    ]),
  },
  {
    key: 'inventory',
    label: 'Inventory & Warehouse',
    to: '/inventory',
    icon: Warehouse,
    moduleKey: 'inventory',
    children: sub('/inventory', [
      ['overview', 'Stock Overview'],
      ['ledger', 'Stock Ledger'],
      ['goods-receipt', 'Goods Receipt'],
      ['material-issue', 'Material Issue'],
      ['transfer', 'Stock Transfer'],
      ['adjustment', 'Stock Adjustment'],
      ['count', 'Physical Stock Count'],
      ['valuation', 'Inventory Valuation'],
    ]),
  },
  {
    key: 'production',
    label: 'Production Management',
    to: '/production',
    icon: Factory,
    moduleKey: 'production',
    children: sub('/production', [
      ['bom', 'BOM / Recipe'],
      ['planning', 'Production Planning'],
      ['orders', 'Production Orders'],
      ['issue', 'Material Issue'],
      ['wip', 'Work in Progress'],
      ['fg-receipt', 'Finished Goods Receipt'],
      ['qc', 'Quality Control'],
      ['costing', 'Production Costing'],
      ['variance', 'Variance Analysis'],
    ]),
  },
  {
    key: 'purchase',
    label: 'Purchase Management',
    to: '/purchase',
    icon: ShoppingCart,
    children: sub('/purchase', [
      ['local', 'Local Purchase'],
      ['import', 'Import Purchase'],
      ['requisition', 'Purchase Requisition'],
      ['po', 'Purchase Order'],
      ['grn', 'Goods Receipt Note'],
      ['invoice', 'Purchase Invoice'],
      ['return', 'Purchase Return'],
      ['payment', 'Supplier Payment'],
      ['landed-cost', 'Landed Cost Allocation'],
    ]),
  },
  {
    key: 'sales',
    label: 'Sales & Distribution',
    to: '/sales',
    icon: Truck,
    children: sub('/sales', [
      ['local', 'Local Sales'],
      ['export', 'Export Sales'],
      ['quotation', 'Quotation'],
      ['order', 'Sales Order'],
      ['invoice', 'Sales Invoice'],
      ['challan', 'Delivery Challan'],
      ['return', 'Sales Return & Credit Note'],
      ['receipt', 'Customer Receipt'],
      ['realization', 'Export Realization'],
    ]),
  },
  {
    key: 'job-work',
    label: 'Job Work / Contract Mfg.',
    to: '/job-work',
    icon: Handshake,
    moduleKey: 'job_work',
    children: sub('/job-work', [
      ['contracts', 'Customer Contracts'],
      ['orders', 'Job Work Orders'],
      ['material-receipt', 'Customer Material Receipt'],
      ['consumption', 'Material Consumption'],
      ['output', 'Production Output'],
      ['delivery', 'Finished Goods Delivery'],
      ['processing-fee', 'Processing Fee Invoice'],
      ['reports', 'Job Work Ledger & Reports'],
    ]),
  },
  {
    key: 'accounting',
    label: 'Accounting & Finance',
    to: '/accounting',
    icon: Calculator,
    children: sub('/accounting', [
      ['coa', 'Chart of Accounts'],
      ['journals', 'Journal Entries'],
      ['ledger', 'General Ledger'],
      ['receipts-payments', 'Receipts & Payments'],
      ['bank-rec', 'Bank Reconciliation'],
      ['trial-balance', 'Trial Balance'],
      ['profit-loss', 'Profit & Loss'],
      ['balance-sheet', 'Balance Sheet'],
      ['period-closing', 'Period Closing'],
    ]),
  },
  {
    key: 'vat',
    label: 'VAT & Mushak',
    to: '/vat',
    icon: ReceiptText,
    children: sub('/vat', [
      ['configuration', 'VAT Configuration'],
      ['catalogue', 'Mushak Form Catalogue'],
      ['registers', 'Purchase & Sales Registers'],
      ['mushak-6-3', 'Mushak 6.3'],
      ['mushak-9-1', 'Mushak 9.1'],
      ['reconciliation', 'VAT Reconciliation'],
      ['returns', 'VAT Return Reports'],
    ]),
  },
  {
    key: 'vds',
    label: 'VDS Management',
    to: '/vds',
    icon: Percent,
    children: sub('/vds', [
      ['rules', 'VDS Rules & Rates'],
      ['deduction', 'Deduction Entry'],
      ['register', 'VDS Register'],
      ['certificate', 'Certificate Management'],
      ['treasury', 'Treasury Deposit'],
      ['reconciliation', 'Deposit Reconciliation'],
      ['reports', 'VDS Reports'],
    ]),
  },
  {
    key: 'assets',
    label: 'Fixed Assets',
    to: '/assets',
    icon: Landmark,
    children: sub('/assets', [
      ['register', 'Asset Register'],
      ['categories', 'Asset Categories'],
      ['acquisition', 'Acquisition'],
      ['depreciation', 'Depreciation'],
      ['disposal', 'Disposal'],
      ['reports', 'Asset Reports'],
    ]),
  },
  {
    key: 'reports',
    label: 'Reports & MIS',
    to: '/reports',
    icon: BarChart3,
    children: sub('/reports', [
      ['sales', 'Sales Reports'],
      ['purchase', 'Purchase Reports'],
      ['inventory', 'Inventory Reports'],
      ['accounting', 'Accounting Reports'],
      ['vat-vds', 'VAT & VDS Reports'],
      ['aging', 'Receivable/Payable Aging'],
      ['builder', 'Custom Report Builder'],
    ]),
  },
  {
    key: 'documents',
    label: 'Document Center',
    to: '/documents',
    icon: FileText,
    children: sub('/documents', [
      ['repository', 'Document Repository'],
      ['pdf-export', 'PDF Export'],
      ['excel-export', 'Excel Export'],
      ['access', 'Access Control'],
    ]),
  },
  {
    key: 'backup',
    label: 'Backup & System Health',
    to: '/backup',
    icon: ShieldCheck,
    children: sub('/backup', [
      ['drive', 'Google Drive Backup'],
      ['history', 'Backup History'],
      ['retention', 'Retention Management'],
      ['restore', 'Restore Testing'],
      ['health', 'API & Database Health'],
      ['logs', 'Error Logs'],
    ]),
  },
  {
    key: 'approvals',
    label: 'Approval Center',
    to: '/approvals',
    icon: CheckCircle2,
    children: sub('/approvals', [
      ['purchase', 'Purchase Approvals'],
      ['sales', 'Sales Approvals'],
      ['payment', 'Payment Approvals'],
      ['journal', 'Journal Approvals'],
      ['production', 'Production Approvals'],
      ['vat', 'VAT Review'],
    ]),
  },
  {
    key: 'settings',
    label: 'Settings & Administration',
    to: '/settings',
    icon: Settings,
    children: sub('/settings', [
      ['general', 'General Settings'],
      ['numbering', 'Document Numbering'],
      ['workflows', 'Approval Workflows'],
      ['notifications', 'Notification Settings'],
      ['email', 'Email Configuration'],
      ['vat-versions', 'VAT Rule Versions'],
      ['audit-policy', 'Audit Policy'],
    ]),
  },
]

export interface BottomItem {
  key: string
  label: string
  icon: LucideIcon
  to?: string
  action?: 'logout'
}

export const BOTTOM_ITEMS: BottomItem[] = [
  { key: 'profile', label: 'My Profile', icon: UserCircle, to: '/profile' },
  { key: 'change-password', label: 'Change Password', icon: KeyRound, to: '/change-password' },
  { key: 'notifications', label: 'Notifications', icon: Bell, to: '/notifications' },
  { key: 'help', label: 'Help & Documentation', icon: LifeBuoy, to: '/help' },
  { key: 'logout', label: 'Logout', icon: LogOut, action: 'logout' },
]
