import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { useState } from 'react'
import { Trade, type TradeRow } from '../../shared/lib/resources'
import { Banner, Button, Field, Modal, PageHeader, Table, apiError, inputClass } from '../../shared/ui/primitives'

type Kind = 'finance' | 'shipments' | 'export-docs'
interface FieldDef { key: string; label: string; type?: 'text' | 'number' | 'date' | 'select'; options?: { value: string; label: string }[]; required?: boolean }
interface Cfg {
  title: string; subtitle: string; api: typeof Trade.finance
  noField: string; columns: { key: string; label: string }[]
  fields: FieldDef[]; statuses: string[]
}

const CONFIG: Record<Kind, Cfg> = {
  finance: {
    title: 'LC / TT (Trade Finance)', subtitle: 'Letters of Credit and Telegraphic Transfers for import payments.',
    api: Trade.finance, noField: 'tf_no',
    columns: [{ key: 'tf_no', label: 'No.' }, { key: 'kind', label: 'Type' }, { key: 'party', label: 'Beneficiary' }, { key: 'bank', label: 'Bank' }, { key: 'currency', label: 'Ccy' }, { key: 'amount', label: 'Amount' }, { key: 'expiry_date', label: 'Expiry' }],
    fields: [
      { key: 'kind', label: 'Type', type: 'select', options: [{ value: 'lc', label: 'Letter of Credit' }, { value: 'tt', label: 'Telegraphic Transfer' }], required: true },
      { key: 'party', label: 'Beneficiary / Supplier' }, { key: 'bank', label: 'Bank' }, { key: 'ref_number', label: 'LC / TT reference' },
      { key: 'currency', label: 'Currency' }, { key: 'amount', label: 'Amount', type: 'number', required: true },
      { key: 'issue_date', label: 'Issue date', type: 'date' }, { key: 'expiry_date', label: 'Expiry date', type: 'date' },
    ],
    statuses: ['open', 'partial', 'settled', 'expired', 'cancelled'],
  },
  shipments: {
    title: 'Shipment Tracking', subtitle: 'Import and export consignments and their status.',
    api: Trade.shipments, noField: 'shipment_no',
    columns: [{ key: 'shipment_no', label: 'No.' }, { key: 'direction', label: 'Direction' }, { key: 'transport_ref', label: 'BL / AWB' }, { key: 'carrier', label: 'Carrier' }, { key: 'origin', label: 'Origin' }, { key: 'destination', label: 'Destination' }, { key: 'eta', label: 'ETA' }],
    fields: [
      { key: 'direction', label: 'Direction', type: 'select', options: [{ value: 'import', label: 'Import' }, { value: 'export', label: 'Export' }], required: true },
      { key: 'transport_ref', label: 'BL / AWB number' }, { key: 'carrier', label: 'Carrier' },
      { key: 'origin', label: 'Origin' }, { key: 'destination', label: 'Destination' },
      { key: 'etd', label: 'ETD', type: 'date' }, { key: 'eta', label: 'ETA', type: 'date' },
    ],
    statuses: ['booked', 'in_transit', 'arrived', 'cleared', 'delivered'],
  },
  'export-docs': {
    title: 'Export Documents', subtitle: 'EXP forms, Certificates of Origin and related export paperwork.',
    api: Trade.exportDocs, noField: 'doc_no',
    columns: [{ key: 'doc_no', label: 'No.' }, { key: 'doc_type', label: 'Type' }, { key: 'party', label: 'Buyer' }, { key: 'reference', label: 'Reference' }, { key: 'date', label: 'Date' }],
    fields: [
      { key: 'doc_type', label: 'Document type', type: 'select', options: [{ value: 'exp', label: 'EXP Form' }, { value: 'coo', label: 'Certificate of Origin' }, { value: 'commercial_invoice', label: 'Commercial Invoice' }, { value: 'packing_list', label: 'Packing List' }], required: true },
      { key: 'party', label: 'Buyer' }, { key: 'reference', label: 'Reference (invoice, order…)' }, { key: 'date', label: 'Date', type: 'date' },
    ],
    statuses: ['draft', 'issued', 'submitted'],
  },
}

const STATUS_STYLE = (s: string) =>
  ['settled', 'delivered', 'cleared', 'submitted', 'issued'].includes(s) ? 'bg-emerald-50 text-emerald-700'
    : ['cancelled', 'expired'].includes(s) ? 'bg-rose-50 text-rose-700'
      : 'bg-amber-50 text-amber-700'

export function TradePage({ kind }: { kind: Kind }) {
  const cfg = CONFIG[kind]
  const qc = useQueryClient()
  const { data: rows } = useQuery({ queryKey: ['trade', kind], queryFn: cfg.api.list })
  const [open, setOpen] = useState(false)
  const [form, setForm] = useState<Record<string, string>>({})
  const [msg, setMsg] = useState<{ kind: 'error' | 'success'; text: string } | null>(null)

  const refresh = () => qc.invalidateQueries({ queryKey: ['trade', kind] })

  const create = useMutation({
    mutationFn: () => cfg.api.create(Object.fromEntries(Object.entries(form).filter(([, v]) => v !== ''))),
    onSuccess: () => { setMsg({ kind: 'success', text: `${cfg.title} record created.` }); setOpen(false); setForm({}); refresh() },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })
  const setStatus = useMutation({
    mutationFn: ({ id, status }: { id: number; status: string }) => cfg.api.status(id, status),
    onSuccess: () => refresh(),
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })

  const set = (k: string, v: string) => setForm((s) => ({ ...s, [k]: v }))
  const fmt = (v: string | number | null) => (v === null || v === '' ? '—' : String(v).length > 10 && String(v).includes('T') ? new Date(String(v)).toLocaleDateString() : String(v))

  return (
    <div>
      <PageHeader title={cfg.title} subtitle={cfg.subtitle}
        action={<Button onClick={() => { setOpen(true); setMsg(null) }}><Plus size={16} /> New</Button>} />
      {msg && <Banner kind={msg.kind}>{msg.text}</Banner>}

      <Table head={[...cfg.columns.map((c) => c.label), 'Status']}>
        {(rows ?? []).map((r: TradeRow) => (
          <tr key={r.id} className="text-ink">
            {cfg.columns.map((c) => (
              <td key={c.key} className="px-4 py-2.5 capitalize">{fmt(r[c.key] ?? null)}</td>
            ))}
            <td className="px-4 py-2.5">
              <select
                value={r.status}
                onChange={(e) => setStatus.mutate({ id: r.id, status: e.target.value })}
                className={`rounded-full border-0 px-2 py-0.5 text-xs font-medium capitalize ${STATUS_STYLE(r.status)}`}
              >
                {cfg.statuses.map((s) => <option key={s} value={s}>{s.replace(/_/g, ' ')}</option>)}
              </select>
            </td>
          </tr>
        ))}
        {(rows ?? []).length === 0 && <tr><td colSpan={cfg.columns.length + 1} className="px-4 py-8 text-center text-muted">No records yet.</td></tr>}
      </Table>

      {open && (
        <Modal title={`New — ${cfg.title}`} onClose={() => setOpen(false)}>
          {msg?.kind === 'error' && <Banner kind="error">{msg.text}</Banner>}
          <form className="grid gap-4 sm:grid-cols-2" onSubmit={(e) => { e.preventDefault(); create.mutate() }}>
            {cfg.fields.map((f) => (
              <Field key={f.key} label={f.label}>
                {f.type === 'select' ? (
                  <select className={inputClass} value={form[f.key] ?? ''} onChange={(e) => set(f.key, e.target.value)} required={f.required}>
                    <option value="">Select…</option>
                    {f.options?.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                  </select>
                ) : (
                  <input className={inputClass} type={f.type === 'number' ? 'number' : f.type === 'date' ? 'date' : 'text'} step={f.type === 'number' ? 'any' : undefined}
                    value={form[f.key] ?? ''} onChange={(e) => set(f.key, e.target.value)} required={f.required} />
                )}
              </Field>
            ))}
            <div className="col-span-full flex justify-end gap-2">
              <Button variant="ghost" onClick={() => setOpen(false)}>Cancel</Button>
              <Button type="submit" disabled={create.isPending}>{create.isPending ? 'Saving…' : 'Create'}</Button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  )
}
