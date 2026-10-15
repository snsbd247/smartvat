import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Accounts, FixedAssets, type FixedAsset } from '../../shared/lib/resources'
import { Banner, Button, Field, Modal, PageHeader, Table, apiError, inputClass } from '../../shared/ui/primitives'

const money = (v: string) => '৳ ' + Number(v).toLocaleString(undefined, { maximumFractionDigits: 2 })

export type AssetAction = 'transfer' | 'revaluation' | 'maintenance'

const META: Record<AssetAction, { title: string; subtitle: string; button: string }> = {
  transfer: { title: 'Asset Transfer', subtitle: 'Move an asset to a new location or custodian (no financial impact).', button: 'Transfer' },
  revaluation: { title: 'Asset Revaluation', subtitle: 'Revalue to fair value — upward to reserve, downward to P&L.', button: 'Revalue' },
  maintenance: { title: 'Asset Maintenance', subtitle: 'Record a repair/maintenance expense (never capitalised).', button: 'Maintain' },
}

export function AssetLifecyclePage({ action }: { action: AssetAction }) {
  const meta = META[action]
  const qc = useQueryClient()
  const { data: assets } = useQuery({ queryKey: ['fixed-assets'], queryFn: FixedAssets.assets })
  const { data: accounts } = useQuery({ queryKey: ['accounts'], queryFn: Accounts.list })
  const [target, setTarget] = useState<FixedAsset | null>(null)
  const [form, setForm] = useState<Record<string, string>>({})
  const [msg, setMsg] = useState<{ kind: 'error' | 'success'; text: string } | null>(null)

  const refresh = () => { qc.invalidateQueries({ queryKey: ['fixed-assets'] }); qc.invalidateQueries({ queryKey: ['dashboard'] }) }

  const run = useMutation({
    mutationFn: () => {
      const id = target!.id
      if (action === 'transfer') return FixedAssets.transfer(id, { to_location: form.to_location, to_custodian: form.to_custodian, note: form.note })
      if (action === 'revaluation') return FixedAssets.revalue(id, { book_value: Number(form.book_value) })
      return FixedAssets.maintenance(id, { amount: Number(form.amount), paid_from_account_id: Number(form.paid_from_account_id), description: form.description })
    },
    onSuccess: () => { setMsg({ kind: 'success', text: `${meta.button} recorded for ${target!.asset_no}.` }); setTarget(null); setForm({}); refresh() },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })

  const open = (a: FixedAsset) => {
    setTarget(a); setMsg(null)
    setForm(action === 'revaluation' ? { book_value: a.book_value } : {})
  }
  const set = (k: string, v: string) => setForm((s) => ({ ...s, [k]: v }))

  return (
    <div>
      <PageHeader title={meta.title} subtitle={meta.subtitle} />
      {msg && <Banner kind={msg.kind}>{msg.text}</Banner>}

      <Table head={['Asset No', 'Name', 'Cost', 'Book Value', 'Status', 'Action']}>
        {(assets ?? []).map((a) => (
          <tr key={a.id} className="text-ink">
            <td className="px-4 py-2.5 font-medium">{a.asset_no}</td>
            <td className="px-4 py-2.5">{a.name}</td>
            <td className="px-4 py-2.5">{money(a.cost)}</td>
            <td className="px-4 py-2.5">{money(a.book_value)}</td>
            <td className="px-4 py-2.5 capitalize">{a.status}</td>
            <td className="px-4 py-2.5">
              {a.status === 'active' ? (
                <Button variant="ghost" onClick={() => open(a)}>{meta.button}</Button>
              ) : <span className="text-xs text-muted capitalize">{a.status}</span>}
            </td>
          </tr>
        ))}
        {(assets ?? []).length === 0 && <tr><td colSpan={6} className="px-4 py-8 text-center text-muted">No assets registered.</td></tr>}
      </Table>

      {target && (
        <Modal title={`${meta.button} — ${target.asset_no}`} onClose={() => setTarget(null)}>
          {msg?.kind === 'error' && <Banner kind="error">{msg.text}</Banner>}
          <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); run.mutate() }}>
            {action === 'transfer' && (
              <>
                <Field label="New location"><input className={inputClass} value={form.to_location ?? ''} onChange={(e) => set('to_location', e.target.value)} placeholder="e.g. Plant B" /></Field>
                <Field label="New custodian"><input className={inputClass} value={form.to_custodian ?? ''} onChange={(e) => set('to_custodian', e.target.value)} placeholder="Person responsible" /></Field>
                <Field label="Note"><input className={inputClass} value={form.note ?? ''} onChange={(e) => set('note', e.target.value)} /></Field>
              </>
            )}
            {action === 'revaluation' && (
              <>
                <p className="text-sm text-muted">Current book value <strong className="text-ink">{money(target.book_value)}</strong>.</p>
                <Field label="New book value (৳)"><input className={inputClass} type="number" step="0.01" min="0" value={form.book_value ?? ''} onChange={(e) => set('book_value', e.target.value)} required /></Field>
              </>
            )}
            {action === 'maintenance' && (
              <>
                <Field label="Amount (৳)"><input className={inputClass} type="number" step="0.01" min="0" value={form.amount ?? ''} onChange={(e) => set('amount', e.target.value)} required /></Field>
                <Field label="Paid from account">
                  <select className={inputClass} value={form.paid_from_account_id ?? ''} onChange={(e) => set('paid_from_account_id', e.target.value)} required>
                    <option value="">Select account…</option>
                    {(accounts ?? []).map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>)}
                  </select>
                </Field>
                <Field label="Description"><input className={inputClass} value={form.description ?? ''} onChange={(e) => set('description', e.target.value)} /></Field>
              </>
            )}
            <div className="flex justify-end gap-2">
              <Button variant="ghost" onClick={() => setTarget(null)}>Cancel</Button>
              <Button type="submit" disabled={run.isPending}>{run.isPending ? 'Saving…' : meta.button}</Button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  )
}
