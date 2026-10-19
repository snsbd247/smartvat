import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { Accounts, OpeningBalances, Products, Warehouses } from '../../shared/lib/resources'
import { Banner, Button, Field, PageHeader, apiError, inputClass } from '../../shared/ui/primitives'

type Tab = 'stock' | 'accounts'

export function OpeningBalancesPage() {
  const qc = useQueryClient()
  const [tab, setTab] = useState<Tab>('stock')
  const [msg, setMsg] = useState<{ kind: 'error' | 'success'; text: string } | null>(null)

  const { data: products } = useQuery({ queryKey: ['products'], queryFn: Products.list })
  const { data: warehouses } = useQuery({ queryKey: ['warehouses'], queryFn: Warehouses.list })
  const { data: accounts } = useQuery({ queryKey: ['accounts'], queryFn: Accounts.list })

  // Stock state
  const [warehouse, setWarehouse] = useState('')
  const [sLines, setSLines] = useState([{ product_id: '', qty: '', unit_cost: '' }])
  // Account state
  const [aLines, setALines] = useState([{ account_id: '', side: 'debit', amount: '' }])

  const invalidate = () => { qc.invalidateQueries({ queryKey: ['stock'] }); qc.invalidateQueries({ queryKey: ['dashboard'] }) }

  const postStock = useMutation({
    mutationFn: () => OpeningBalances.stock({
      warehouse_id: Number(warehouse),
      lines: sLines.filter((l) => l.product_id && l.qty).map((l) => ({ product_id: Number(l.product_id), qty: Number(l.qty), unit_cost: Number(l.unit_cost || 0) })),
    }),
    onSuccess: () => { setMsg({ kind: 'success', text: 'Opening stock posted (Dr Inventory / Cr Opening Balance Equity).' }); setSLines([{ product_id: '', qty: '', unit_cost: '' }]); invalidate() },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })

  const postAccounts = useMutation({
    mutationFn: () => OpeningBalances.accounts({
      lines: aLines.filter((l) => l.account_id && l.amount).map((l) => ({ account_id: Number(l.account_id), [l.side]: Number(l.amount) })),
    }),
    onSuccess: (r: { posted: number }) => { setMsg({ kind: 'success', text: `${r.posted} opening account balance(s) posted.` }); setALines([{ account_id: '', side: 'debit', amount: '' }]); invalidate() },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })

  return (
    <div>
      <PageHeader title="Opening Balances" subtitle="One-time setup when going live — bring in current stock and account balances (booked to Opening Balance Equity)." />
      {msg && <Banner kind={msg.kind}>{msg.text}</Banner>}

      <div className="mb-4 flex gap-1 rounded-lg border border-slate-200 bg-workspace p-1 w-fit">
        {(['stock', 'accounts'] as Tab[]).map((t) => (
          <button key={t} onClick={() => setTab(t)} className={`rounded-md px-4 py-1.5 text-sm font-medium capitalize transition ${tab === t ? 'bg-white text-ink shadow-sm' : 'text-muted hover:text-ink'}`}>
            {t === 'stock' ? 'Opening Stock' : 'Opening Accounts'}
          </button>
        ))}
      </div>

      {tab === 'stock' ? (
        <form onSubmit={(e) => { e.preventDefault(); setMsg(null); postStock.mutate() }} className="space-y-5 rounded-xl border border-slate-200 bg-white p-5">
          <Field label="Warehouse">
            <select className={`${inputClass} sm:max-w-sm`} value={warehouse} onChange={(e) => setWarehouse(e.target.value)} required>
              <option value="">Select warehouse…</option>
              {warehouses?.map((w) => <option key={w.id} value={w.id}>{w.name}</option>)}
            </select>
          </Field>
          <div className="space-y-2">
            <div className="text-sm font-medium text-ink">Products</div>
            {sLines.map((l, i) => (
              <div key={i} className="grid grid-cols-12 items-center gap-2">
                <select className={`${inputClass} col-span-6`} value={l.product_id} onChange={(e) => setSLines((s) => s.map((x, idx) => idx === i ? { ...x, product_id: e.target.value } : x))} required>
                  <option value="">Product…</option>
                  {products?.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                </select>
                <input className={`${inputClass} col-span-2`} placeholder="Qty" type="number" step="any" value={l.qty} onChange={(e) => setSLines((s) => s.map((x, idx) => idx === i ? { ...x, qty: e.target.value } : x))} required />
                <input className={`${inputClass} col-span-3`} placeholder="Unit cost" type="number" step="any" value={l.unit_cost} onChange={(e) => setSLines((s) => s.map((x, idx) => idx === i ? { ...x, unit_cost: e.target.value } : x))} required />
                <button type="button" className="col-span-1 grid place-items-center rounded-lg border border-slate-300 text-muted hover:bg-slate-50" onClick={() => setSLines((s) => s.filter((_, idx) => idx !== i))}><Trash2 size={15} /></button>
              </div>
            ))}
            <Button variant="ghost" onClick={() => setSLines((s) => [...s, { product_id: '', qty: '', unit_cost: '' }])}><Plus size={15} /> Add product</Button>
          </div>
          <div className="flex justify-end"><Button type="submit" disabled={postStock.isPending}>{postStock.isPending ? 'Posting…' : 'Post Opening Stock'}</Button></div>
        </form>
      ) : (
        <form onSubmit={(e) => { e.preventDefault(); setMsg(null); postAccounts.mutate() }} className="space-y-5 rounded-xl border border-slate-200 bg-white p-5">
          <div className="space-y-2">
            <div className="text-sm font-medium text-ink">Account opening balances</div>
            {aLines.map((l, i) => (
              <div key={i} className="grid grid-cols-12 items-center gap-2">
                <select className={`${inputClass} col-span-6`} value={l.account_id} onChange={(e) => setALines((s) => s.map((x, idx) => idx === i ? { ...x, account_id: e.target.value } : x))} required>
                  <option value="">Account…</option>
                  {(accounts ?? []).map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>)}
                </select>
                <select className={`${inputClass} col-span-3`} value={l.side} onChange={(e) => setALines((s) => s.map((x, idx) => idx === i ? { ...x, side: e.target.value } : x))}>
                  <option value="debit">Debit</option>
                  <option value="credit">Credit</option>
                </select>
                <input className={`${inputClass} col-span-2`} placeholder="Amount" type="number" step="any" value={l.amount} onChange={(e) => setALines((s) => s.map((x, idx) => idx === i ? { ...x, amount: e.target.value } : x))} required />
                <button type="button" className="col-span-1 grid place-items-center rounded-lg border border-slate-300 text-muted hover:bg-slate-50" onClick={() => setALines((s) => s.filter((_, idx) => idx !== i))}><Trash2 size={15} /></button>
              </div>
            ))}
            <Button variant="ghost" onClick={() => setALines((s) => [...s, { account_id: '', side: 'debit', amount: '' }])}><Plus size={15} /> Add account</Button>
          </div>
          <p className="text-xs text-muted">Each balance posts against Opening Balance Equity, which the accountant later clears to capital / retained earnings.</p>
          <div className="flex justify-end"><Button type="submit" disabled={postAccounts.isPending}>{postAccounts.isPending ? 'Posting…' : 'Post Opening Balances'}</Button></div>
        </form>
      )}
    </div>
  )
}
