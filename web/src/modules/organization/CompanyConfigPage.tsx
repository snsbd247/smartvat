import { type FormEvent, useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Banner, Button, Field, PageHeader, Table, inputClass, apiError } from '../../shared/ui/primitives'
import { Company } from '../../shared/lib/resources'

const TYPES = ['manufacturing', 'trading', 'service']

export function CompanyConfigPage() {
  const qc = useQueryClient()
  const { data: config } = useQuery({ queryKey: ['company-config'], queryFn: Company.config })
  const [selected, setSelected] = useState<string[]>([])
  const [primary, setPrimary] = useState('')
  const [msg, setMsg] = useState<{ kind: 'error' | 'success'; text: string } | null>(null)

  useEffect(() => {
    if (config) {
      setSelected(config.business_types)
      setPrimary(config.primary_business_type ?? config.business_types[0] ?? '')
    }
  }, [config])

  const save = useMutation({
    mutationFn: () => Company.update({ business_types: selected, primary_business_type: primary }),
    onSuccess: () => {
      setMsg({ kind: 'success', text: 'Configuration updated. Enabled modules refreshed.' })
      qc.invalidateQueries({ queryKey: ['company-config'] })
      qc.invalidateQueries({ queryKey: ['modules'] })
    },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })

  const toggle = (t: string) =>
    setSelected((s) => (s.includes(t) ? s.filter((x) => x !== t) : [...s, t]))

  return (
    <div>
      <PageHeader title="Company Configuration" subtitle="Business types drive which modules are enabled (enforced in the backend)" />
      {msg && <Banner kind={msg.kind}>{msg.text}</Banner>}

      <form
        onSubmit={(e: FormEvent) => { e.preventDefault(); setMsg(null); save.mutate() }}
        className="mb-6 max-w-xl space-y-4 rounded-xl border border-slate-200 bg-white p-5"
      >
        <div>
          <div className="mb-2 text-sm font-medium text-ink">Business Types</div>
          <div className="flex flex-wrap gap-3">
            {TYPES.map((t) => (
              <label key={t} className="flex items-center gap-2 text-sm text-ink">
                <input type="checkbox" checked={selected.includes(t)} onChange={() => toggle(t)} />
                <span className="capitalize">{t}</span>
              </label>
            ))}
          </div>
        </div>
        <Field label="Primary Business Type">
          <select className={inputClass} value={primary} onChange={(e) => setPrimary(e.target.value)} required>
            {selected.map((t) => <option key={t} value={t} className="capitalize">{t}</option>)}
          </select>
        </Field>
        <div className="flex justify-end">
          <Button type="submit" disabled={save.isPending || selected.length === 0}>
            {save.isPending ? 'Saving…' : 'Save Configuration'}
          </Button>
        </div>
      </form>

      <div className="mb-2 text-sm font-semibold text-ink">Enabled Modules</div>
      <Table head={['Module', 'Enabled']}>
        {(config?.modules ?? []).map((m) => (
          <tr key={m.module_key} className="text-ink">
            <td className="px-4 py-2.5">{m.module_key.replace(/_/g, ' ')}</td>
            <td className="px-4 py-2.5">
              <span className={m.enabled ? 'text-emerald-600' : 'text-muted'}>{m.enabled ? 'Yes' : 'No'}</span>
            </td>
          </tr>
        ))}
      </Table>
    </div>
  )
}
