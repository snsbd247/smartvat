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
  type LucideIcon,
} from 'lucide-react'

export interface NavItem {
  key: string
  label: string
  to: string
  icon: LucideIcon
}

/**
 * Sidebar modules per the spec (section 18). HR & Payroll is intentionally
 * excluded. In later phases each item's visibility is driven by the company's
 * enabled business-type modules and the user's permissions — backend-enforced,
 * not merely hidden here.
 */
export const NAV_ITEMS: NavItem[] = [
  { key: 'dashboard', label: 'Dashboard', to: '/', icon: LayoutDashboard },
  { key: 'organization', label: 'Organization', to: '/organization', icon: Building2 },
  { key: 'users', label: 'User Management', to: '/users', icon: Users },
  { key: 'master-data', label: 'Master Data', to: '/master-data', icon: Database },
  { key: 'inventory', label: 'Inventory & Warehouse', to: '/inventory', icon: Warehouse },
  { key: 'production', label: 'Production', to: '/production', icon: Factory },
  { key: 'purchase', label: 'Purchase', to: '/purchase', icon: ShoppingCart },
  { key: 'sales', label: 'Sales & Distribution', to: '/sales', icon: Truck },
  { key: 'job-work', label: 'Contract Mfg / Job Work', to: '/job-work', icon: Handshake },
  { key: 'accounting', label: 'Accounting & Finance', to: '/accounting', icon: Calculator },
  { key: 'vat', label: 'VAT & Mushak', to: '/vat', icon: ReceiptText },
  { key: 'vds', label: 'VDS Management', to: '/vds', icon: Percent },
  { key: 'assets', label: 'Fixed Assets', to: '/assets', icon: Landmark },
  { key: 'reports', label: 'Reports & MIS', to: '/reports', icon: BarChart3 },
  { key: 'documents', label: 'Document Center', to: '/documents', icon: FileText },
  { key: 'backup', label: 'Backup & Health', to: '/backup', icon: ShieldCheck },
  { key: 'approvals', label: 'Approval Center', to: '/approvals', icon: CheckCircle2 },
  { key: 'settings', label: 'Settings & Admin', to: '/settings', icon: Settings },
]
