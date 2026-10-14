import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, Trash2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Settings } from '../../shared/lib/resources'
import { Banner, Button, PageHeader, Table, apiError, inputClass } from '../../shared/ui/primitives'

interface Column { key: string; label: string; placeholder?: string }
interface ListConfig { group: string; title: string; subtitle: string; columns: Column[] }

const CONFIGS: Record<string, ListConfig> = {
  uom: {
    group: 'master.uom',
    title: 'Units of Measure',
    subtitle: 'Units used across products, inventory and production.',
    columns: [
      { key: 'code', label: 'Code', placeholder: 'KG' },
      { key: 'name', label: 'Name', placeholder: 'Kilogram' },
      { key: 'ratio', label: 'Base ratio', placeholder: '1' },
    ],
  },
  currencies: {
    group: 'master.currencies',
    title: 'Currencies & Exchange Rates',
    subtitle: 'Foreign currencies and their indicative rate to the base currency (BDT).',
    columns: [
      { key: 'code', label: 'Code', placeholder: 'USD' },
      { key: 'name', label: 'Name', placeholder: 'US Dollar' },
      { key: 'symbol', label: 'Symbol', placeholder: '$' },
      { key: 'rate', label: 'Rate to BDT', placeholder: '117.50' },
    ],
  },
  machines: {
    group: 'master.machines',
    title: 'Machines & Work Centres',
    subtitle: 'Production machines and work centres for routing and costing.',
    columns: [
      { key: 'code', label: 'Code', placeholder: 'INJ-01' },
      { key: 'name', label: 'Name', placeholder: 'Injection Moulder 1' },
      { key: 'capacity', label: 'Capacity / hr', placeholder: '120' },
      { key: 'location', label: 'Location', placeholder: 'Floor A' },
    ],
  },
}

type Row = Record<string, string>

export function ManagedListPage({ list }: { list: keyof typeof CONFIGS }) {
  const cfg = CONFIGS[list]
  const qc = useQueryClient()
  const [rows, setRows] = useState<Row[]>([])
  const [msg, setMsg] = useState<string | null>(null)
  const [err, setErr] = useState<string | null>(null)

  const { data } = useQuery({ queryKey: ['settings', cfg.group], queryFn: () => Settings.get<{ items?: Row[] }>(cfg.group) })

  useEffect(() => {
    const items = (data as { items?: Row[] })?.items
    if (Array.isArray(items)) setRows(items)
  }, [data])

  const save = useMutation({
    mutationFn: () => Settings.put(cfg.group, { items: rows.filter((r) => Object.values(r).some((v) => v)) }),
    onSuccess: () => {
      setErr(null)
      setMsg('Saved.')
      qc.invalidateQueries({ queryKey: ['settings', cfg.group] })
    },
    onError: (e) => { setMsg(null); setErr(apiError(e)) },
  })

  const addRow = () => setRows((r) => [...r, Object.fromEntries(cfg.columns.map((c) => [c.key, ''])) as Row])
  const removeRow = (i: number) => setRows((r) => r.filter((_, idx) => idx !== i))
  const update = (i: number, key: string, v: string) =>
    setRows((r) => r.map((row, idx) => (idx === i ? { ...row, [key]: v } : row)))

  return (
    <div>
      <PageHeader
        title={cfg.title}
        subtitle={cfg.subtitle}
        action={<Button onClick={() => save.mutate()} disabled={save.isPending}>{save.isPending ? 'Saving…' : 'Save'}</Button>}
      />
      {msg && <Banner kind="success">{msg}</Banner>}
      {err && <Banner kind="error">{err}</Banner>}

      <Table head={[...cfg.columns.map((c) => c.label), '']}>
        {rows.map((row, i) => (
          <tr key={i} className="text-ink">
            {cfg.columns.map((c) => (
              <td key={c.key} className="px-3 py-2">
                <input
                  className={inputClass}
                  value={row[c.key] ?? ''}
                  placeholder={c.placeholder}
                  onChange={(e) => update(i, c.key, e.target.value)}
                />
              </td>
            ))}
            <td className="px-3 py-2 text-right">
              <button type="button" onClick={() => removeRow(i)} className="rounded p-1.5 text-muted hover:bg-rose-50 hover:text-rose-600">
                <Trash2 size={16} />
              </button>
            </td>
          </tr>
        ))}
        {rows.length === 0 && (
          <tr><td colSpan={cfg.columns.length + 1} className="px-4 py-8 text-center text-muted">No entries yet. Add one below.</td></tr>
        )}
      </Table>

      <div className="mt-3">
        <Button variant="ghost" onClick={addRow}><Plus size={16} /> Add row</Button>
      </div>
    </div>
  )
}
