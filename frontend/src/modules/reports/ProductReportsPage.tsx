import { useQuery } from '@tanstack/react-query'
import { ProductReports } from '../../shared/lib/resources'
import { PageHeader, Table } from '../../shared/ui/primitives'

const money = (v: string) => '৳ ' + Number(v).toLocaleString(undefined, { maximumFractionDigits: 0 })
const qty = (v: string) => Number(v).toLocaleString(undefined, { maximumFractionDigits: 2 })

export function ProfitByProductPage() {
  const { data: rows } = useQuery({ queryKey: ['profit-by-product'], queryFn: ProductReports.profit })
  return (
    <div>
      <PageHeader title="Profit by Product" subtitle="Revenue vs estimated cost (quantity sold × current average cost) per product." />
      <Table head={['Product', 'Qty sold', 'Revenue', 'Est. cost', 'Profit', 'Margin %']}>
        {(rows ?? []).map((r, i) => (
          <tr key={i} className="text-ink">
            <td className="px-4 py-2.5 font-medium">{r.product}</td>
            <td className="px-4 py-2.5">{qty(r.qty_sold)}</td>
            <td className="px-4 py-2.5">{money(r.revenue)}</td>
            <td className="px-4 py-2.5">{money(r.est_cost)}</td>
            <td className={`px-4 py-2.5 font-medium ${Number(r.profit) < 0 ? 'text-rose-600' : 'text-emerald-600'}`}>{money(r.profit)}</td>
            <td className="px-4 py-2.5">{Number(r.margin_pct).toFixed(1)}%</td>
          </tr>
        ))}
        {(rows ?? []).length === 0 && <tr><td colSpan={6} className="px-4 py-8 text-center text-muted">No sales yet.</td></tr>}
      </Table>
    </div>
  )
}

export function StockMovementPage() {
  const { data: rows } = useQuery({ queryKey: ['stock-movement'], queryFn: ProductReports.movement })
  return (
    <div>
      <PageHeader title="Stock Movement Report" subtitle="Total quantity and value in / out per product (company-owned stock)." />
      <Table head={['Product', 'In qty', 'In value', 'Out qty', 'Out value', 'Net qty']}>
        {(rows ?? []).map((r, i) => (
          <tr key={i} className="text-ink">
            <td className="px-4 py-2.5 font-medium">{r.product}</td>
            <td className="px-4 py-2.5">{qty(r.in_qty)}</td>
            <td className="px-4 py-2.5">{money(r.in_value)}</td>
            <td className="px-4 py-2.5">{qty(r.out_qty)}</td>
            <td className="px-4 py-2.5">{money(r.out_value)}</td>
            <td className="px-4 py-2.5 font-medium">{qty(r.net_qty)}</td>
          </tr>
        ))}
        {(rows ?? []).length === 0 && <tr><td colSpan={6} className="px-4 py-8 text-center text-muted">No stock movement yet.</td></tr>}
      </Table>
    </div>
  )
}
