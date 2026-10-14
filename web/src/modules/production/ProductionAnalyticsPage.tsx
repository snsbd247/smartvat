import { useQuery } from '@tanstack/react-query'
import { Production, Products, type ProductionOrder } from '../../shared/lib/resources'
import { PageHeader, Table } from '../../shared/ui/primitives'

const money = (v: string | number) => '৳ ' + Number(v).toLocaleString(undefined, { maximumFractionDigits: 2 })
const qty = (v: string | number) => Number(v).toLocaleString(undefined, { maximumFractionDigits: 4 })

function useOrders() {
  const { data: orders } = useQuery({ queryKey: ['production-orders'], queryFn: Production.orders })
  const { data: products } = useQuery({ queryKey: ['products'], queryFn: Products.list })
  const nameOf = (id: number) => products?.find((p) => p.id === id)?.name ?? `#${id}`
  return { orders: orders ?? [], nameOf }
}

function StatCards({ items }: { items: { label: string; value: string; tone?: string }[] }) {
  return (
    <div className="mb-6 grid gap-4 sm:grid-cols-3">
      {items.map((c) => (
        <div key={c.label} className="rounded-xl border border-slate-200 bg-white p-4">
          <div className="text-xs font-medium text-muted">{c.label}</div>
          <div className={`mt-1 text-2xl font-bold ${c.tone ?? 'text-ink'}`}>{c.value}</div>
        </div>
      ))}
    </div>
  )
}

/** Work in Progress: orders with material issued but not yet completed. */
export function ProductionWipPage() {
  const { orders, nameOf } = useOrders()
  const wip = orders.filter((o: ProductionOrder) => Number(o.wip_value) > 0 || o.status === 'released')
  const totalWip = wip.reduce((s, o) => s + Number(o.wip_value || 0), 0)

  return (
    <div>
      <PageHeader title="Work in Progress" subtitle="Open production orders carrying accumulated WIP cost (Dr WIP)." />
      <StatCards items={[
        { label: 'Open WIP orders', value: String(wip.length) },
        { label: 'Total WIP value', value: money(totalWip), tone: 'text-brand' },
      ]} />
      <Table head={['Order', 'Product', 'Planned', 'Produced', 'WIP value', 'Status']}>
        {wip.map((o) => (
          <tr key={o.id} className="text-ink">
            <td className="px-4 py-2.5 font-medium">{o.order_no}</td>
            <td className="px-4 py-2.5">{nameOf(o.product_id)}</td>
            <td className="px-4 py-2.5">{qty(o.planned_qty)}</td>
            <td className="px-4 py-2.5">{qty(o.produced_qty)}</td>
            <td className="px-4 py-2.5">{money(o.wip_value)}</td>
            <td className="px-4 py-2.5 capitalize">{o.status}</td>
          </tr>
        ))}
        {wip.length === 0 && <tr><td colSpan={6} className="px-4 py-8 text-center text-muted">Nothing in progress.</td></tr>}
      </Table>
    </div>
  )
}

/** Production Costing: completed orders with total and unit cost of finished goods. */
export function ProductionCostingPage() {
  const { orders, nameOf } = useOrders()
  const done = orders.filter((o: ProductionOrder) => o.status === 'completed')
  const totalCost = done.reduce((s, o) => s + Number(o.produced_cost || 0), 0)

  return (
    <div>
      <PageHeader title="Production Costing" subtitle="Finished-goods cost at receipt (WIP ÷ produced qty = actual unit cost)." />
      <StatCards items={[
        { label: 'Completed orders', value: String(done.length) },
        { label: 'Total FG cost', value: money(totalCost), tone: 'text-emerald-600' },
      ]} />
      <Table head={['Order', 'Product', 'Produced', 'Scrap', 'Total cost', 'Unit cost']}>
        {done.map((o) => {
          const produced = Number(o.produced_qty)
          const unit = produced > 0 ? Number(o.produced_cost) / produced : 0
          return (
            <tr key={o.id} className="text-ink">
              <td className="px-4 py-2.5 font-medium">{o.order_no}</td>
              <td className="px-4 py-2.5">{nameOf(o.product_id)}</td>
              <td className="px-4 py-2.5">{qty(o.produced_qty)}</td>
              <td className="px-4 py-2.5">{qty(o.scrap_qty)}</td>
              <td className="px-4 py-2.5">{money(o.produced_cost)}</td>
              <td className="px-4 py-2.5 font-medium">{money(unit)}</td>
            </tr>
          )
        })}
        {done.length === 0 && <tr><td colSpan={6} className="px-4 py-8 text-center text-muted">No completed orders yet.</td></tr>}
      </Table>
    </div>
  )
}

/** Variance Analysis: planned vs produced quantity, scrap and yield. */
export function ProductionVariancePage() {
  const { orders, nameOf } = useOrders()
  const rows = orders.filter((o: ProductionOrder) => Number(o.produced_qty) > 0 || o.status === 'completed')

  return (
    <div>
      <PageHeader title="Variance Analysis" subtitle="Planned vs actual output, scrap and yield per production order." />
      <Table head={['Order', 'Product', 'Planned', 'Produced', 'Scrap', 'Qty variance', 'Yield %']}>
        {rows.map((o) => {
          const planned = Number(o.planned_qty)
          const produced = Number(o.produced_qty)
          const scrap = Number(o.scrap_qty)
          const variance = produced - planned
          const yieldPct = planned > 0 ? (produced / planned) * 100 : 0
          return (
            <tr key={o.id} className="text-ink">
              <td className="px-4 py-2.5 font-medium">{o.order_no}</td>
              <td className="px-4 py-2.5">{nameOf(o.product_id)}</td>
              <td className="px-4 py-2.5">{qty(planned)}</td>
              <td className="px-4 py-2.5">{qty(produced)}</td>
              <td className="px-4 py-2.5">{qty(scrap)}</td>
              <td className={`px-4 py-2.5 font-medium ${variance < 0 ? 'text-rose-600' : 'text-emerald-600'}`}>
                {variance > 0 ? '+' : ''}{qty(variance)}
              </td>
              <td className="px-4 py-2.5">{yieldPct.toFixed(1)}%</td>
            </tr>
          )
        })}
        {rows.length === 0 && <tr><td colSpan={7} className="px-4 py-8 text-center text-muted">No output recorded yet.</td></tr>}
      </Table>
    </div>
  )
}
