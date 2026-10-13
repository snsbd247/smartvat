import { type FormEvent, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, RefreshCw } from 'lucide-react'
import { Banner, Button, Field, Modal, PageHeader, Table, apiError, inputClass } from '../../shared/ui/primitives'
import { Accounts, FixedAssets } from '../../shared/lib/resources'

const money = (v: string) => Number(v).toLocaleString(undefined, { maximumFractionDigits: 2 })

export function FixedAssetsPage() {
  const qc = useQueryClient()
  const { data: assets } = useQuery({ queryKey: ['fixed-assets'], queryFn: FixedAssets.assets })
  const { data: cats } = useQuery({ queryKey: ['asset-categories'], queryFn: FixedAssets.categories })
  const { data: accounts } = useQuery({ queryKey: ['accounts'], queryFn: Accounts.list })
  const assetAcc = (accounts ?? []).filter((a) => a.type === 'asset')

  const [open, setOpen] = useState(false)
  const [msg, setMsg] = useState<{ kind: 'error' | 'success'; text: string } | null>(null)
  const [form, setForm] = useState<Record<string, string>>({ acquisition_date: new Date().toISOString().slice(0, 10) })
  const [disposeId, setDisposeId] = useState<number | null>(null)
  const [disposeForm, setDisposeForm] = useState<{ proceeds: string; account: string }>({ proceeds: '', account: '' })

  const dispose = useMutation({
    mutationFn: () => FixedAssets.dispose(disposeId!, { proceeds: Number(disposeForm.proceeds), received_account_id: Number(disposeForm.account) }),
    onSuccess: () => { setMsg({ kind: 'success', text: 'Asset disposed — gain/loss recognised.' }); setDisposeId(null); setDisposeForm({ proceeds: '', account: '' }); invalidate() },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })

  const invalidate = () => { qc.invalidateQueries({ queryKey: ['fixed-assets'] }); qc.invalidateQueries({ queryKey: ['dashboard'] }) }
  const acquire = useMutation({
    mutationFn: () => FixedAssets.acquire({
      asset_category_id: Number(form.asset_category_id),
      name: form.name,
      acquisition_date: form.acquisition_date,
      cost: Number(form.cost),
      credit_account_id: Number(form.credit_account_id),
    }),
    onSuccess: () => { invalidate(); setOpen(false); setForm({ acquisition_date: new Date().toISOString().slice(0, 10) }) },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })
  const depreciate = useMutation({
    mutationFn: () => FixedAssets.runDepreciation(),
    onSuccess: (r: unknown) => { setMsg({ kind: 'success', text: `Depreciation posted for ${(r as { depreciated_assets?: number }).depreciated_assets ?? 0} asset(s).` }); invalidate() },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })
  const set = (k: string, v: string) => setForm((s) => ({ ...s, [k]: v }))

  return (
    <div>
      <PageHeader title="Fixed Assets" subtitle="Asset register — acquire and depreciate (straight-line)"
        action={
          <div className="flex gap-2">
            <Button variant="ghost" onClick={() => depreciate.mutate()} disabled={depreciate.isPending}><RefreshCw size={15} /> Run Depreciation</Button>
            <Button onClick={() => { setOpen(true); setMsg(null) }}><Plus size={16} /> Acquire Asset</Button>
          </div>
        } />
      {msg && <Banner kind={msg.kind}>{msg.text}</Banner>}

      <Table head={['Asset No', 'Name', 'Cost', 'Accum. Dep.', 'Book Value', 'Status', '']}>
        {(assets ?? []).map((a) => (
          <tr key={a.id} className="text-ink">
            <td className="px-4 py-2.5">{a.asset_no}</td>
            <td className="px-4 py-2.5">{a.name}</td>
            <td className="px-4 py-2.5 text-right">{money(a.cost)}</td>
            <td className="px-4 py-2.5 text-right">{money(a.accumulated_depreciation)}</td>
            <td className="px-4 py-2.5 text-right">{money(a.book_value)}</td>
            <td className="px-4 py-2.5">{a.status}</td>
            <td className="px-4 py-2.5">
              {a.status === 'active' && (
                <Button variant="ghost" onClick={() => { setDisposeId(a.id); setMsg(null) }}>Dispose</Button>
              )}
            </td>
          </tr>
        ))}
        {(assets ?? []).length === 0 && <tr><td colSpan={7} className="px-4 py-8 text-center text-muted">No assets yet.</td></tr>}
      </Table>

      {disposeId !== null && (
        <Modal title="Dispose Asset" onClose={() => setDisposeId(null)}>
          <form onSubmit={(e: FormEvent) => { e.preventDefault(); setMsg(null); dispose.mutate() }} className="grid gap-4">
            <Field label="Proceeds (sale amount)">
              <input className={inputClass} type="number" step="any" value={disposeForm.proceeds} onChange={(e) => setDisposeForm((s) => ({ ...s, proceeds: e.target.value }))} required />
            </Field>
            <Field label="Received in (Cash/Bank)">
              <select className={inputClass} value={disposeForm.account} onChange={(e) => setDisposeForm((s) => ({ ...s, account: e.target.value }))} required>
                <option value="">Select account…</option>
                {assetAcc.map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>)}
              </select>
            </Field>
            <div className="flex justify-end gap-2">
              <Button variant="ghost" onClick={() => setDisposeId(null)}>Cancel</Button>
              <Button type="submit" disabled={dispose.isPending}>{dispose.isPending ? 'Posting…' : 'Dispose'}</Button>
            </div>
          </form>
        </Modal>
      )}

      {open && (
        <Modal title="Acquire Asset" onClose={() => setOpen(false)}>
          <form onSubmit={(e: FormEvent) => { e.preventDefault(); setMsg(null); acquire.mutate() }} className="grid gap-4 sm:grid-cols-2">
            <Field label="Category">
              <select className={inputClass} value={form.asset_category_id ?? ''} onChange={(e) => set('asset_category_id', e.target.value)} required>
                <option value="">Select…</option>
                {cats?.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
              </select>
            </Field>
            <Field label="Name"><input className={inputClass} value={form.name ?? ''} onChange={(e) => set('name', e.target.value)} required /></Field>
            <Field label="Acquisition Date"><input className={inputClass} type="date" value={form.acquisition_date ?? ''} onChange={(e) => set('acquisition_date', e.target.value)} required /></Field>
            <Field label="Cost"><input className={inputClass} type="number" step="any" value={form.cost ?? ''} onChange={(e) => set('cost', e.target.value)} required /></Field>
            <Field label="Paid from / Payable (credit)">
              <select className={inputClass} value={form.credit_account_id ?? ''} onChange={(e) => set('credit_account_id', e.target.value)} required>
                <option value="">Select…</option>
                {assetAcc.map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>)}
              </select>
            </Field>
            <div className="col-span-full mt-2 flex justify-end gap-2">
              <Button variant="ghost" onClick={() => setOpen(false)}>Cancel</Button>
              <Button type="submit" disabled={acquire.isPending}>{acquire.isPending ? 'Saving…' : 'Acquire'}</Button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  )
}
