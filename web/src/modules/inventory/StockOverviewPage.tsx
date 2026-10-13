import { useQuery } from '@tanstack/react-query'
import { PageHeader, Table } from '../../shared/ui/primitives'
import { Inventory, Products, Warehouses } from '../../shared/lib/resources'

const n = (v: string) => Number(v).toLocaleString(undefined, { maximumFractionDigits: 2 })

export function StockOverviewPage() {
  const { data: stock } = useQuery({ queryKey: ['stock'], queryFn: Inventory.stock })
  const { data: products } = useQuery({ queryKey: ['products'], queryFn: Products.list })
  const { data: warehouses } = useQuery({ queryKey: ['warehouses'], queryFn: Warehouses.list })

  const pName = (id: number) => products?.find((p) => p.id === id)?.name ?? `#${id}`
  const wName = (id: number) => warehouses?.find((w) => w.id === id)?.name ?? `#${id}`
  const total = (stock ?? []).reduce((s, r) => s + Number(r.value), 0)

  return (
    <div>
      <PageHeader title="Stock Overview" subtitle="Company-owned inventory valuation" />
      <Table head={['Product', 'Warehouse', 'Qty', 'Avg Cost', 'Value']}>
        {(stock ?? []).map((r, i) => (
          <tr key={i} className="text-ink">
            <td className="px-4 py-2.5">{pName(r.product_id)}</td>
            <td className="px-4 py-2.5">{wName(r.warehouse_id)}</td>
            <td className="px-4 py-2.5">{n(r.qty)}</td>
            <td className="px-4 py-2.5">{n(r.avg_cost)}</td>
            <td className="px-4 py-2.5">৳ {n(r.value)}</td>
          </tr>
        ))}
        {(stock ?? []).length === 0 && (
          <tr><td colSpan={5} className="px-4 py-8 text-center text-muted">No stock yet.</td></tr>
        )}
        {(stock ?? []).length > 0 && (
          <tr className="bg-slate-50 font-semibold text-ink">
            <td className="px-4 py-2.5" colSpan={4}>Total</td>
            <td className="px-4 py-2.5">৳ {n(String(total))}</td>
          </tr>
        )}
      </Table>
    </div>
  )
}
