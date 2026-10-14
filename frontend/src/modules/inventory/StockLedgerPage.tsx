import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Field, PageHeader, Table, inputClass } from '../../shared/ui/primitives'
import { Inventory, Products } from '../../shared/lib/resources'

const n = (v: string) => Number(v).toLocaleString(undefined, { maximumFractionDigits: 2 })

export function StockLedgerPage() {
  const { data: products } = useQuery({ queryKey: ['products'], queryFn: Products.list })
  const [product, setProduct] = useState('')
  const { data: rows } = useQuery({
    queryKey: ['stock-ledger', product],
    queryFn: () => Inventory.ledger(product ? { product_id: Number(product) } : {}),
  })

  return (
    <div>
      <PageHeader title="Stock Ledger" subtitle="Append-only movement history" />
      <div className="mb-4 max-w-sm">
        <Field label="Filter by product">
          <select className={inputClass} value={product} onChange={(e) => setProduct(e.target.value)}>
            <option value="">All products</option>
            {products?.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
          </select>
        </Field>
      </div>
      <Table head={['Date', 'Product', 'Ref', 'Qty', 'Unit Cost', 'Value', 'Balance Qty']}>
        {(rows ?? []).map((r, i) => (
          <tr key={i} className="text-ink">
            <td className="px-4 py-2.5">{String(r.moved_at ?? '').slice(0, 10)}</td>
            <td className="px-4 py-2.5">{products?.find((p) => p.id === Number(r.product_id))?.name ?? r.product_id}</td>
            <td className="px-4 py-2.5">{r.reference ?? '—'}</td>
            <td className="px-4 py-2.5 text-right">{n(r.qty)}</td>
            <td className="px-4 py-2.5 text-right">{n(r.unit_cost)}</td>
            <td className="px-4 py-2.5 text-right">{n(r.value)}</td>
            <td className="px-4 py-2.5 text-right">{n(r.balance_qty)}</td>
          </tr>
        ))}
        {(rows ?? []).length === 0 && <tr><td colSpan={7} className="px-4 py-8 text-center text-muted">No movements.</td></tr>}
      </Table>
    </div>
  )
}
