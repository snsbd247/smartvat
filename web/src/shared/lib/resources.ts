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
}
export const Sales = {
  invoice: (body: {
    customer_id: number
    lines: { product_id: number; qty: number; unit_price: number; line_total: number }[]
  }) => api.post('/sales-invoices', body).then((r) => r.data),
  challan: (body: {
    customer_id: number
    warehouse_id: number
    lines: { product_id: number; qty: number }[]
  }) => api.post('/delivery-challans', body).then((r) => r.data),
  receipt: (body: { customer_id: number; amount: number; received_in_account_id: number }) =>
    api.post('/customer-receipts', body).then((r) => r.data),
  ledger: (customerId: number) => api.get(`/customers/${customerId}/ledger`).then((r) => r.data),
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
}
export const Journals = {
  create: (body: {
    date: string
    narration?: string
    lines: { account_id: number; debit?: number; credit?: number }[]
  }) => api.post('/accounting/journals', body).then((r) => r.data),
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
  ledger: (supplierId: number) => api.get(`/suppliers/${supplierId}/ledger`).then((r) => r.data),
}

export interface ReportDef {
  title: string
  subtitle?: string | null
  columns: { key: string; label: string; align?: string }[]
  rows: Record<string, string | number>[]
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
