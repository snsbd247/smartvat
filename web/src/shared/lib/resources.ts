import { api } from './api'

export interface Paginated<T> {
  data: T[]
  total?: number
}

export interface Product {
  id: number
  code: string
  name: string
  type: string
  base_uom: string
  costing_method: string
  status: string
}
export interface Party {
  id: number
  code: string
  name: string
  status: string
}
export interface Warehouse {
  id: number
  code: string
  name: string
}
export interface StockRow {
  product_id: number
  warehouse_id: number
  qty: string
  value: string
  avg_cost: string
}

const list = async <T>(url: string): Promise<T[]> => {
  const { data } = await api.get<Paginated<T>>(url)
  return data.data ?? (data as unknown as T[])
}

export const Products = {
  list: () => list<Product>('/products?per_page=100'),
  create: (body: Partial<Product>) => api.post('/products', body).then((r) => r.data),
}
export const Suppliers = {
  list: () => list<Party>('/suppliers?per_page=100'),
  create: (body: Partial<Party>) => api.post('/suppliers', body).then((r) => r.data),
}
export const Customers = {
  list: () => list<Party>('/customers?per_page=100'),
  create: (body: Partial<Party>) => api.post('/customers', body).then((r) => r.data),
}
export const Warehouses = {
  list: () => list<Warehouse>('/warehouses?per_page=100'),
  create: (b: Record<string, string>) => api.post('/warehouses', b).then((r) => r.data),
}

export interface Factory { id: number; code: string; name: string; address: string | null; status: string }
export const Factories = {
  list: () => list<Factory>('/factories?per_page=100'),
  create: (b: Record<string, string>) => api.post('/factories', b).then((r) => r.data),
}

export interface AppUser { id: number; name: string; email: string; username: string | null; status: string }
export const Users = {
  list: async (): Promise<AppUser[]> => {
    const { data } = await api.get<AppUser[]>('/users')
    return Array.isArray(data) ? data : (data as unknown as { data: AppUser[] }).data
  },
  create: (b: Record<string, string>) => api.post('/users', b).then((r) => r.data),
}

export interface CompanyConfig {
  primary_business_type: string | null
  business_types: string[]
  modules: { module_key: string; enabled: boolean }[]
}
export const Company = {
  config: () => api.get<CompanyConfig>('/company/config').then((r) => r.data),
  update: (b: { business_types: string[]; primary_business_type?: string; industry_template?: string }) =>
    api.put('/company/config', b).then((r) => r.data),
}

export interface BackupHealth {
  api: string
  database: string
  last_successful_backup: string | null
  last_backup_verified: boolean
  failed_last_24h: number
}
export interface BackupJob {
  id: number
  filename: string
  status: string
  verified: boolean
  size: number
  finished_at: string | null
}
export const Backup = {
  health: () => api.get<BackupHealth>('/backup/health').then((r) => r.data),
  jobs: () => api.get<BackupJob[]>('/backup/jobs').then((r) => r.data),
  run: () => api.post('/backup/run', {}).then((r) => r.data),
}
export const Inventory = {
  stock: async (): Promise<StockRow[]> => {
    const { data } = await api.get<{ data: StockRow[] }>('/inventory/stock')
    return data.data
  },
  goodsReceipt: (body: {
    supplier_id: number
    warehouse_id: number
    lines: { product_id: number; qty: number; unit_cost: number }[]
  }) => api.post('/goods-receipts', body).then((r) => r.data),
  issue: (body: { warehouse_id: number; lines: { product_id: number; qty: number }[] }) =>
    api.post('/inventory/issues', body).then((r) => r.data),
  transfer: (body: { warehouse_id: number; to_warehouse_id: number; lines: { product_id: number; qty: number }[] }) =>
    api.post('/inventory/transfers', body).then((r) => r.data),
  adjustment: (body: { warehouse_id: number; lines: { product_id: number; qty: number; unit_cost?: number }[] }) =>
    api.post('/inventory/adjustments', body).then((r) => r.data),
  ledger: (params: { product_id?: number; warehouse_id?: number }) =>
    api.get<{ data: Record<string, string>[] }>('/inventory/ledger', { params }).then((r) => r.data.data),
}
export const Sales = {
  invoice: (body: {
    customer_id: number
    kind?: string
    currency?: string
    exchange_rate?: number
    lines: { product_id: number; qty: number; unit_price: number; line_total: number }[]
  }) => api.post('/sales-invoices', body).then((r) => r.data),
  exportReceipt: (body: {
    customer_id: number
    fc_amount: number
    realization_rate: number
    invoice_rate: number
    received_in_account_id: number
  }) => api.post('/customer-receipts', { ...body, kind: 'export' }).then((r) => r.data),
  challan: (body: {
    customer_id: number
    warehouse_id: number
    lines: { product_id: number; qty: number }[]
  }) => api.post('/delivery-challans', body).then((r) => r.data),
  receipt: (body: { customer_id: number; amount: number; received_in_account_id: number }) =>
    api.post('/customer-receipts', body).then((r) => r.data),
  ledger: (customerId: number) => api.get(`/customers/${customerId}/ledger`).then((r) => r.data),
  returnGoods: (body: { customer_id: number; warehouse_id: number; lines: { product_id: number; qty: number; unit_price: number }[] }) =>
    api.post('/sales-returns', body).then((r) => r.data),
}

export interface Account {
  id: number
  code: string
  name: string
  type: string
  normal_balance: string
}
export const Accounts = {
  list: async (): Promise<Account[]> => {
    const { data } = await api.get<Account[]>('/accounting/accounts')
    return Array.isArray(data) ? data : (data as unknown as { data: Account[] }).data
  },
  create: (body: Record<string, string>) => api.post('/accounting/accounts', body).then((r) => r.data),
  cashBook: (id: number) =>
    api.get<{ account: string; balance: string; rows: { date: string; entry_no: string; narration: string | null; debit: string; credit: string; balance: string }[] }>(
      `/accounting/accounts/${id}/cash-book`,
    ).then((r) => r.data),
  periods: () =>
    api.get<AccountingPeriod[]>('/accounting/periods').then((r) => (Array.isArray(r.data) ? r.data : (r.data as unknown as { data: AccountingPeriod[] }).data)),
  setPeriodStatus: (id: number, status: 'open' | 'closed') =>
    api.patch(`/accounting/periods/${id}/status`, { status }).then((r) => r.data),
}
export interface AccountingPeriod {
  id: number
  name: string
  start_date: string
  end_date: string
  status: string
  fiscal_year?: string | number
}

export const Import = {
  create: (body: {
    supplier_id: number
    warehouse_id: number
    currency: string
    exchange_rate: number
    lines: { product_id: number; qty: number; fc_unit_price: number; weight?: number }[]
    charges: { type: string; fc_amount: number; allocation_basis: string; recoverable?: boolean }[]
  }) => api.post('/import-shipments', body).then((r) => r.data),
  preview: (id: number) =>
    api.get<{ landed_total: string; goods_bc_total: string; nonrecoverable_total: string; lines: Record<string, string>[] }>(
      `/import-shipments/${id}/landed-cost`,
    ).then((r) => r.data),
  receive: (id: number) => api.post(`/import-shipments/${id}/receive`, {}).then((r) => r.data),
}
export interface SalesOrderLine { id?: number; product_id: number | null; description?: string | null; qty: string | number; unit_price: string | number; line_total: string | number }
export interface SalesOrderRow {
  id: number
  so_no: string
  customer_id: number
  kind: string
  status: 'quotation' | 'confirmed' | 'invoiced' | 'cancelled'
  date: string
  total: string
  reference: string | null
  lines: SalesOrderLine[]
}
export const SalesOrders = {
  list: (status?: 'quotation' | 'confirmed' | 'invoiced' | 'cancelled') =>
    api.get<SalesOrderRow[]>(`/sales-orders${status ? `?status=${status}` : ''}`).then((r) => r.data),
  create: (body: {
    customer_id: number
    status: 'quotation' | 'confirmed'
    reference?: string
    lines: { product_id: number | null; qty: number; unit_price: number; line_total: number }[]
  }) => api.post('/sales-orders', body).then((r) => r.data),
  confirm: (id: number) => api.post(`/sales-orders/${id}/confirm`, {}).then((r) => r.data),
  invoice: (id: number) => api.post(`/sales-orders/${id}/invoice`, {}).then((r) => r.data),
}
export const Journals = {
  create: (body: {
    date: string
    narration?: string
    lines: { account_id: number; debit?: number; credit?: number }[]
  }) => api.post('/accounting/journals', body).then((r) => r.data),
}
export interface CostCenter { id: number; code: string; name: string }
export const CostCenters = {
  list: () => api.get<CostCenter[]>('/accounting/cost-centers').then((r) => r.data),
  create: (b: Record<string, string>) => api.post('/accounting/cost-centers', b).then((r) => r.data),
}
export const FundTransfers = {
  create: (b: { from_account_id: number; to_account_id: number; amount: number; narration?: string }) =>
    api.post('/accounting/fund-transfers', b).then((r) => r.data),
}

export interface BankLine { id: number; date: string; entry_no: string; narration: string | null; debit: string; credit: string }
export const BankRec = {
  unreconciled: (accountId: number) => api.get<BankLine[]>(`/accounting/accounts/${accountId}/unreconciled`).then((r) => r.data),
  reconcile: (b: { account_id: number; statement_date: string; statement_balance: number; line_ids: number[] }) =>
    api.post('/accounting/bank-reconciliations', b).then((r) => r.data),
}

export interface BudgetRow { id: number; name: string; status: string }
export const Budgets = {
  list: () => api.get<BudgetRow[]>('/accounting/budgets').then((r) => r.data),
  create: (b: { name: string; lines: { account_id: number; amount: number }[] }) =>
    api.post('/accounting/budgets', b).then((r) => r.data),
  vsActual: (id: number) =>
    api.get<{ rows: { account: string; budget: string; actual: string; variance: string }[] }>(`/accounting/budgets/${id}/vs-actual`).then((r) => r.data.rows),
}

export interface ExpenseRow { id: number; expense_no: string; date: string; amount: string; narration: string | null }
export const Expenses = {
  list: () => api.get<ExpenseRow[]>('/accounting/expenses').then((r) => r.data),
  create: (b: { expense_account_id: number; paid_from_account_id: number; amount: number; narration?: string; cost_center_id?: number }) =>
    api.post('/accounting/expenses', b).then((r) => r.data),
}

export const PurchaseApi = {
  order: (body: { supplier_id: number; lines: { product_id: number; qty: number; unit_price: number }[] }) =>
    api.post('/purchase-orders', body).then((r) => r.data),
  invoice: (body: {
    supplier_id: number
    type: string
    lines: { product_id?: number; account_id?: number; line_total: number }[]
  }) => api.post('/purchase-invoices', body).then((r) => r.data),
  payment: (body: { supplier_id: number; amount: number; paid_from_account_id: number }) =>
    api.post('/supplier-payments', body).then((r) => r.data),
  returnGoods: (body: { supplier_id: number; warehouse_id: number; lines: { product_id: number; qty: number }[] }) =>
    api.post('/purchase-returns', body).then((r) => r.data),
  ledger: (supplierId: number) => api.get(`/suppliers/${supplierId}/ledger`).then((r) => r.data),
}

export interface ReportDef {
  title: string
  subtitle?: string | null
  columns: { key: string; label: string; align?: string }[]
  rows: Record<string, string | number>[]
}
export interface VatRate { id: number; code: string; rate: string; effective_from: string | null }
export interface VatNote { id: number; code: string; title: string; subform: string | null; verification_status: string }
export interface VatMapping {
  id: number
  transaction_type_code: string
  tax_treatment_code: string
  vat_rate_code: string | null
  vat_return_note_id: number
  approval_status: string
  verification_status: string
}
export const Vat = {
  rates: () => api.get<VatRate[]>('/vat/rates').then((r) => r.data),
  createRate: (b: Record<string, string>) => api.post('/vat/rates', b).then((r) => r.data),
  notes: () => api.get<VatNote[]>('/vat/notes').then((r) => r.data),
  createNote: (b: Record<string, string>) => api.post('/vat/notes', b).then((r) => r.data),
  mappings: () => api.get<VatMapping[]>('/vat/mappings').then((r) => r.data),
  createMapping: (b: Record<string, unknown>) => api.post('/vat/mappings', b).then((r) => r.data),
  approveMapping: (id: number) => api.patch(`/vat/mappings/${id}/approve`).then((r) => r.data),
  classify: (b: Record<string, unknown>) => api.post('/vat/classify', b).then((r) => r.data),
  unmapped: () => api.get<{ rows: Record<string, string>[] }>('/vat/unmapped').then((r) => r.data.rows),
  types: () => api.get<{ id: number; code: string; name: string }[]>('/vat/transaction-types').then((r) => r.data),
  createType: (b: Record<string, string>) => api.post('/vat/transaction-types', b).then((r) => r.data),
  treatments: () => api.get<{ id: number; code: string; name: string }[]>('/vat/tax-treatments').then((r) => r.data),
  createTreatment: (b: Record<string, string>) => api.post('/vat/tax-treatments', b).then((r) => r.data),
}

export interface VdsRule { id: number; service_category: string; rate: string; approval_status: string; verification_status: string }
export const Vds = {
  rules: () => api.get<VdsRule[]>('/vds/rules').then((r) => r.data),
  createRule: (b: Record<string, string>) => api.post('/vds/rules', b).then((r) => r.data),
  approveRule: (id: number) => api.patch(`/vds/rules/${id}/approve`).then((r) => r.data),
  deduct: (b: { supplier_id: number; service_category: string; base_amount: number }) =>
    api.post('/vds/deductions', b).then((r) => r.data),
  deposit: (b: { amount: number; paid_from_account_id: number }) => api.post('/vds/deposits', b).then((r) => r.data),
  reconciliation: () => api.get<{ deducted: string; deposited: string; payable: string }>('/vds/reconciliation').then((r) => r.data),
  deductions: () => api.get<{ id: number; deduction_no: string; service_category: string; base_amount: string; rate: string; vds_amount: string; status: string; date: string }[]>('/vds/deductions').then((r) => r.data),
  certificate: (id: number) => api.post(`/vds/deductions/${id}/certificate`, {}).then((r) => r.data),
}

export interface Bom { id: number; bom_no: string; product_id: number; version: number; output_qty: string }
export interface ProductionOrder {
  id: number
  order_no: string
  product_id: number
  planned_qty: string
  produced_qty: string
  scrap_qty: string
  wip_value: string
  produced_cost: string
  date: string
  status: string
}
export const Production = {
  boms: () => api.get<Bom[]>('/boms').then((r) => r.data),
  createBom: (b: {
    product_id: number
    output_qty: number
    lines: { component_product_id: number; qty: number }[]
  }) => api.post('/boms', b).then((r) => r.data),
  orders: () => api.get<ProductionOrder[]>('/production-orders').then((r) => r.data),
  createOrder: (b: { product_id: number; bom_id?: number; warehouse_id: number; planned_qty: number }) =>
    api.post('/production-orders', b).then((r) => r.data),
  issue: (id: number) => api.post(`/production-orders/${id}/issue`, {}).then((r) => r.data),
  receive: (id: number, qty: number, scrap_qty = 0) =>
    api.post(`/production-orders/${id}/receive`, { qty, scrap_qty }).then((r) => r.data),
}

export interface AssetCategory { id: number; code: string; name: string; useful_life_months: number }
export interface FixedAsset {
  id: number
  asset_no: string
  name: string
  cost: string
  accumulated_depreciation: string
  book_value: string
  status: string
}
export const FixedAssets = {
  categories: () => api.get<AssetCategory[]>('/asset-categories').then((r) => r.data),
  createCategory: (b: Record<string, string>) => api.post('/asset-categories', b).then((r) => r.data),
  assets: async (): Promise<FixedAsset[]> => {
    const { data } = await api.get<{ data: FixedAsset[] }>('/fixed-assets')
    return data.data ?? (data as unknown as FixedAsset[])
  },
  acquire: (b: Record<string, string | number>) => api.post('/fixed-assets', b).then((r) => r.data),
  runDepreciation: () => api.post('/fixed-assets/run-depreciation', {}).then((r) => r.data),
  dispose: (id: number, b: { proceeds: number; received_account_id: number }) =>
    api.post(`/fixed-assets/${id}/dispose`, b).then((r) => r.data),
}

export interface ActivityLog {
  id: number
  event: string
  user: string | null
  auditable_type: string
  auditable_id: number | null
  ip_address: string | null
  created_at: string | null
}
export const System = {
  activityLogs: () => api.get<ActivityLog[]>('/security/activity-logs').then((r) => r.data),
  loginHistory: () => api.get<ActivityLog[]>('/security/login-history').then((r) => r.data),
  roles: () => api.get<string[]>('/security/roles').then((r) => r.data),
  permissions: () => api.get<string[]>('/security/permissions').then((r) => r.data),
}

export interface Me {
  id: number
  name: string
  email: string
  username: string | null
  roles?: string[]
  companies?: { id: number; code: string; name: string }[]
}
export const Profile = {
  me: () => api.get<Me>('/auth/me').then((r) => r.data),
  changePassword: (b: { current_password: string; password: string; password_confirmation: string }) =>
    api.post('/auth/change-password', b).then((r) => r.data),
}

export interface Contract { id: number; contract_no: string; customer_id: number; processing_rate: string; scrap_ownership: string }
export interface JobWorkOrderRow { id: number; order_no: string; customer_id: number; status: string }
export const JobWork = {
  contracts: () => api.get<Contract[]>('/job-work/contracts').then((r) => r.data),
  createContract: (b: Record<string, string>) => api.post('/job-work/contracts', b).then((r) => r.data),
  orders: () => api.get<JobWorkOrderRow[]>('/job-work/orders').then((r) => r.data),
  createOrder: (b: Record<string, string | number>) => api.post('/job-work/orders', b).then((r) => r.data),
  receiveMaterial: (orderId: number, lines: { product_id: number; qty: number }[]) =>
    api.post(`/job-work/orders/${orderId}/receive-material`, { lines }).then((r) => r.data),
  consumeMaterial: (orderId: number, lines: { product_id: number; qty: number }[]) =>
    api.post(`/job-work/orders/${orderId}/consume-material`, { lines }).then((r) => r.data),
  deliver: (orderId: number, lines: { product_id: number; qty: number }[]) =>
    api.post(`/job-work/orders/${orderId}/deliver`, { lines }).then((r) => r.data),
  processingInvoice: (b: { customer_id: number; job_work_order_id?: number; qty: number; rate: number }) =>
    api.post('/job-work/processing-invoices', b).then((r) => r.data),
  reconciliation: () =>
    api.get<{ rows: Record<string, string>[] }>('/job-work/material-reconciliation').then((r) => (r.data.rows ?? (r.data as unknown as Record<string, string>[]))),
}

export interface Approval {
  id: number
  type: string
  document_no: string | null
  amount: string | null
  status: 'pending' | 'approved' | 'rejected'
  note: string | null
  requested_by: string | null
  acted_by: string | null
  acted_at: string | null
}
export const Approvals = {
  list: (status: 'pending' | 'approved' | 'rejected' = 'pending') =>
    api.get<Approval[]>(`/approvals?status=${status}`).then((r) => r.data),
  approve: (id: number) => api.post(`/approvals/${id}/approve`, {}).then((r) => r.data),
  reject: (id: number, note?: string) => api.post(`/approvals/${id}/reject`, { note }).then((r) => r.data),
}

export const Settings = {
  get: <T = Record<string, unknown>>(group: string) =>
    api.get<{ group: string; payload: T }>(`/settings/${group}`).then((r) => r.data.payload),
  put: <T = Record<string, unknown>>(group: string, payload: T) =>
    api.put<{ group: string; payload: T }>(`/settings/${group}`, { payload }).then((r) => r.data.payload),
}

export interface DocRow {
  id: number
  title: string
  filename: string
  mime: string
  size: number
  category: string | null
  uploaded_by: string | null
  created_at: string | null
}
export const Documents = {
  list: () => api.get<DocRow[]>('/documents').then((r) => r.data),
  upload: (file: File, meta: { title?: string; category?: string }) => {
    const fd = new FormData()
    fd.append('file', file)
    if (meta.title) fd.append('title', meta.title)
    if (meta.category) fd.append('category', meta.category)
    return api.post('/documents', fd, { headers: { 'Content-Type': 'multipart/form-data' } }).then((r) => r.data)
  },
  async download(id: number, filename: string) {
    const res = await api.get(`/documents/${id}`, { responseType: 'blob' })
    const url = URL.createObjectURL(res.data as Blob)
    const a = document.createElement('a')
    a.href = url
    a.download = filename
    a.click()
    URL.revokeObjectURL(url)
  },
  remove: (id: number) => api.delete(`/documents/${id}`).then((r) => r.data),
}

export const Reports = {
  list: () => api.get<{ reports: string[] }>('/reports').then((r) => r.data.reports),
  json: (key: string) => api.get<ReportDef>(`/reports/${key}?format=json`).then((r) => r.data),
  async openOrDownload(key: string, format: 'pdf' | 'xlsx') {
    const res = await api.get(`/reports/${key}?format=${format}`, { responseType: 'blob' })
    const url = URL.createObjectURL(res.data as Blob)
    if (format === 'pdf') {
      window.open(url, '_blank')
    } else {
      const a = document.createElement('a')
      a.href = url
      a.download = `${key}.xlsx`
      a.click()
    }
    setTimeout(() => URL.revokeObjectURL(url), 10_000)
  },
}
