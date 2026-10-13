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
}
