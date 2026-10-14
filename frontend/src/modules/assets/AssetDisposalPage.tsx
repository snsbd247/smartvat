import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Accounts, FixedAssets, type FixedAsset } from '../../shared/lib/resources'
import { Banner, Button, Field, Modal, PageHeader, Table, apiError, inputClass } from '../../shared/ui/primitives'

const money = (v: string) => '৳ ' + Number(v).toLocaleString(undefined, { maximumFractionDigits: 2 })

export function AssetDisposalPage() {
  const qc = useQueryClient()
  const [msg, setMsg] = useState<string | null>(null)
  const [err, setErr] = useState<string | null>(null)
  const [target, setTarget] = useState<FixedAsset | null>(null)
  const [proceeds, setProceeds] = useState('0')
  const [accountId, setAccountId] = useState('')

  const { data: assets } = useQuery({ queryKey: ['fixed-assets'], queryFn: FixedAssets.assets })
  const { data: accounts } = useQuery({ queryKey: ['accounts'], queryFn: Accounts.list })

  const dispose = useMutation({
    mutationFn: () => FixedAssets.dispose(target!.id, { proceeds: Number(proceeds), received_account_id: Number(accountId) }),
    onSuccess: () => {
      setErr(null)
      setMsg(`Asset ${target!.asset_no} disposed. Gain/loss posted to the ledger.`)
      setTarget(null)
      qc.invalidateQueries({ queryKey: ['fixed-assets'] })
    },
    onError: (e) => setErr(apiError(e)),
  })

  const active = (assets ?? []).filter((a) => a.status !== 'disposed')

  return (
    <div>
      <PageHeader title="Asset Disposal" subtitle="Dispose of a fixed asset; book value is cleared and gain/loss is posted automatically." />
      {msg && <Banner kind="success">{msg}</Banner>}
      {err && <Banner kind="error">{err}</Banner>}

      <Table head={['Asset No', 'Name', 'Cost', 'Accum. Deprec.', 'Book Value', 'Status', 'Action']}>
        {(assets ?? []).map((a) => (
          <tr key={a.id} className="text-ink">
            <td className="px-4 py-2.5 font-medium">{a.asset_no}</td>
            <td className="px-4 py-2.5">{a.name}</td>
            <td className="px-4 py-2.5">{money(a.cost)}</td>
            <td className="px-4 py-2.5">{money(a.accumulated_depreciation)}</td>
            <td className="px-4 py-2.5">{money(a.book_value)}</td>
            <td className="px-4 py-2.5 capitalize">{a.status}</td>
            <td className="px-4 py-2.5">
              {a.status !== 'disposed' ? (
                <Button variant="danger" onClick={() => { setTarget(a); setProceeds('0'); setErr(null); setMsg(null) }}>
                  Dispose
                </Button>
              ) : (
                <span className="text-xs text-muted">Disposed</span>
              )}
            </td>
          </tr>
        ))}
        {active.length === 0 && (assets ?? []).length === 0 && (
          <tr><td colSpan={7} className="px-4 py-8 text-center text-muted">No assets registered.</td></tr>
        )}
      </Table>

      {target && (
        <Modal title={`Dispose ${target.asset_no}`} onClose={() => setTarget(null)}>
          {err && <Banner kind="error">{err}</Banner>}
          <form
            onSubmit={(e) => { e.preventDefault(); dispose.mutate() }}
            className="space-y-4"
          >
            <p className="text-sm text-muted">
              Book value <strong className="text-ink">{money(target.book_value)}</strong>. Sale proceeds above book value post a
              gain; below book value post a loss.
            </p>
            <Field label="Sale proceeds (৳)">
              <input className={inputClass} type="number" step="0.01" min="0" value={proceeds} onChange={(e) => setProceeds(e.target.value)} />
            </Field>
            <Field label="Proceeds received into account">
              <select className={inputClass} value={accountId} onChange={(e) => setAccountId(e.target.value)} required>
                <option value="">Select account…</option>
                {(accounts ?? []).map((a) => (
                  <option key={a.id} value={a.id}>{a.code} — {a.name}</option>
                ))}
              </select>
            </Field>
            <div className="flex justify-end gap-2">
              <Button variant="ghost" onClick={() => setTarget(null)}>Cancel</Button>
              <Button type="submit" variant="danger" disabled={dispose.isPending}>
                {dispose.isPending ? 'Disposing…' : 'Confirm Disposal'}
              </Button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  )
}
