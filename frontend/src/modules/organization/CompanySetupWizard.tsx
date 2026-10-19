import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, ArrowRight, CheckCircle2 } from 'lucide-react'
import { useState } from 'react'
import { Company } from '../../shared/lib/resources'
import { Banner, Button, PageHeader, apiError } from '../../shared/ui/primitives'

const BUSINESS_TYPES = [
  { key: 'manufacturing', label: 'Manufacturing', desc: 'Buy materials, produce, then sell' },
  { key: 'trading', label: 'Trading', desc: 'Buy finished goods, declare price, then sell' },
  { key: 'service', label: 'Service', desc: 'Sell services, no inventory or production' },
]
const TEMPLATES = [
  { key: '', label: 'None (use business-type defaults)' },
  { key: 'plastic_manufacturing', label: 'Plastic Manufacturing' },
  { key: 'general_trading', label: 'General Trading / Distribution' },
  { key: 'professional_services', label: 'Professional / IT Services' },
  { key: 'manufacturing_trading', label: 'Manufacturing + Trading' },
]
// Mirrors backend config/erp.php (modules enabled per business type).
const CORE = ['Dashboard', 'Users', 'Master Data', 'Purchase', 'Sales', 'Accounting', 'VAT', 'VDS', 'Fixed Assets', 'Reports', 'Documents', 'Trade', 'Backup', 'Approvals', 'Settings']
const TYPE_MODULES: Record<string, string[]> = {
  manufacturing: ['Inventory', 'Production', 'Job Work'],
  trading: ['Inventory'],
  service: [],
}

export function CompanySetupWizard() {
  const qc = useQueryClient()
  const { data: cfg } = useQuery({ queryKey: ['company-config'], queryFn: Company.config })
  const [step, setStep] = useState(1)
  const [types, setTypes] = useState<string[]>([])
  const [primary, setPrimary] = useState('')
  const [template, setTemplate] = useState('')
  const [done, setDone] = useState(false)
  const [msg, setMsg] = useState<string | null>(null)

  // Seed from current config once.
  if (!types.length && cfg?.business_types?.length && step === 1 && primary === '') {
    setTypes(cfg.business_types)
    setPrimary(cfg.primary_business_type ?? cfg.business_types[0])
  }

  const enabledModules = Array.from(new Set([...CORE, ...types.flatMap((t) => TYPE_MODULES[t] ?? [])]))

  const apply = useMutation({
    mutationFn: () => Company.update({ business_types: types, primary_business_type: primary || types[0], industry_template: template || undefined }),
    onSuccess: () => { setDone(true); qc.invalidateQueries({ queryKey: ['company-config'] }); qc.invalidateQueries({ queryKey: ['company-modules'] }) },
    onError: (e) => setMsg(apiError(e)),
  })

  const toggle = (t: string) => setTypes((s) => {
    const next = s.includes(t) ? s.filter((x) => x !== t) : [...s, t]
    if (!next.includes(primary)) setPrimary(next[0] ?? '')
    return next
  })

  if (done) {
    return (
      <div className="max-w-xl">
        <PageHeader title="Company Setup" subtitle="Configuration applied." />
        <div className="flex flex-col items-center rounded-xl border border-emerald-200 bg-emerald-50 p-10 text-center">
          <CheckCircle2 className="text-emerald-500" size={44} />
          <p className="mt-3 font-semibold text-ink">Setup complete</p>
          <p className="text-sm text-muted">Modules have been enabled for the selected business type(s). The sidebar updates on your next navigation.</p>
          <div className="mt-4 flex flex-wrap justify-center gap-2">
            {enabledModules.map((m) => <span key={m} className="rounded-full bg-white px-2.5 py-0.5 text-xs font-medium text-ink">{m}</span>)}
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="max-w-2xl">
      <PageHeader title="Company Setup Wizard" subtitle={`Step ${step} of 3 — configure business type and modules.`} />
      {msg && <Banner kind="error">{msg}</Banner>}

      <div className="mb-5 flex gap-2">
        {[1, 2, 3].map((s) => <div key={s} className={`h-1.5 flex-1 rounded-full ${s <= step ? 'bg-brand' : 'bg-slate-200'}`} />)}
      </div>

      <div className="rounded-xl border border-slate-200 bg-white p-6">
        {step === 1 && (
          <>
            <h3 className="mb-1 font-semibold text-ink">Business type</h3>
            <p className="mb-4 text-sm text-muted">Select one or more. This drives which modules are active (enforced in the backend).</p>
            <div className="space-y-2">
              {BUSINESS_TYPES.map((b) => (
                <label key={b.key} className={`flex cursor-pointer items-start gap-3 rounded-lg border p-3 ${types.includes(b.key) ? 'border-brand bg-brand/5' : 'border-slate-200'}`}>
                  <input type="checkbox" className="mt-1 h-4 w-4 accent-[color:var(--color-brand)]" checked={types.includes(b.key)} onChange={() => toggle(b.key)} />
                  <div>
                    <div className="font-medium text-ink">{b.label}</div>
                    <div className="text-xs text-muted">{b.desc}</div>
                  </div>
                  {types.length > 1 && types.includes(b.key) && (
                    <label className="ml-auto flex items-center gap-1 text-xs text-muted">
                      <input type="radio" name="primary" checked={primary === b.key} onChange={() => setPrimary(b.key)} /> primary
                    </label>
                  )}
                </label>
              ))}
            </div>
          </>
        )}

        {step === 2 && (
          <>
            <h3 className="mb-1 font-semibold text-ink">Industry template (optional)</h3>
            <p className="mb-4 text-sm text-muted">A preset for common industries. Leave as “None” to use the defaults.</p>
            <div className="space-y-2">
              {TEMPLATES.map((t) => (
                <label key={t.key} className={`flex cursor-pointer items-center gap-3 rounded-lg border p-3 ${template === t.key ? 'border-brand bg-brand/5' : 'border-slate-200'}`}>
                  <input type="radio" name="template" className="h-4 w-4 accent-[color:var(--color-brand)]" checked={template === t.key} onChange={() => setTemplate(t.key)} />
                  <span className="text-sm text-ink">{t.label}</span>
                </label>
              ))}
            </div>
          </>
        )}

        {step === 3 && (
          <>
            <h3 className="mb-1 font-semibold text-ink">Review</h3>
            <p className="mb-4 text-sm text-muted">These modules will be enabled for this company.</p>
            <div className="mb-4 space-y-1 text-sm">
              <div><span className="text-muted">Business types: </span><strong className="text-ink">{types.join(', ') || '—'}</strong></div>
              <div><span className="text-muted">Primary: </span><strong className="text-ink">{primary || types[0] || '—'}</strong></div>
              <div><span className="text-muted">Template: </span><strong className="text-ink">{TEMPLATES.find((t) => t.key === template)?.label}</strong></div>
            </div>
            <div className="flex flex-wrap gap-2">
              {enabledModules.map((m) => <span key={m} className="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-ink">{m}</span>)}
            </div>
          </>
        )}

        <div className="mt-6 flex justify-between">
          <Button variant="ghost" onClick={() => setStep((s) => Math.max(1, s - 1))} disabled={step === 1}><ArrowLeft size={16} /> Back</Button>
          {step < 3 ? (
            <Button onClick={() => setStep((s) => s + 1)} disabled={step === 1 && types.length === 0}>Next <ArrowRight size={16} /></Button>
          ) : (
            <Button onClick={() => { setMsg(null); apply.mutate() }} disabled={apply.isPending || types.length === 0}>{apply.isPending ? 'Applying…' : 'Apply Setup'}</Button>
          )}
        </div>
      </div>
    </div>
  )
}
