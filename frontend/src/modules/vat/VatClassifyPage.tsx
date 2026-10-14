import { type FormEvent, useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import { Banner, Button, Field, PageHeader, apiError, inputClass } from '../../shared/ui/primitives'
import { Vat } from '../../shared/lib/resources'

const TX = ['local_sale', 'export_sale', 'local_purchase', 'import_purchase', 'service_purchase']
const TREAT = ['standard', 'zero_rated', 'exempt']

export function VatClassifyPage({ title = 'VAT Classification', subtitle = 'Classify a transaction to a verified Mushak 9.1 Note (never guessed)' }: { title?: string; subtitle?: string }) {
  const [form, setForm] = useState<Record<string, string>>({})
  const [result, setResult] = useState<{ status: string; note?: number | null } | null>(null)
  const [error, setError] = useState<string | null>(null)

  const m = useMutation({
    mutationFn: () =>
      Vat.classify({
        transaction_type: form.transaction_type,
        tax_treatment: form.tax_treatment,
        vat_rate: form.vat_rate || undefined,
        taxable_amount: Number(form.taxable_amount || 0),
        vat_amount: Number(form.vat_amount || 0),
      }),
    onSuccess: (r: unknown) => {
      const res = r as { status: string; vat_return_note_id?: number | null }
      setResult({ status: res.status, note: res.vat_return_note_id })
    },
    onError: (e) => setError(apiError(e)),
  })
  const set = (k: string, v: string) => setForm((s) => ({ ...s, [k]: v }))

  return (
    <div>
      <PageHeader title={title} subtitle={subtitle} />
      {error && <Banner kind="error">{error}</Banner>}
      {result && (
        <Banner kind={result.status === 'classified' ? 'success' : 'error'}>
          {result.status === 'classified'
            ? `Classified → Note id ${result.note}`
            : 'Review required — no verified mapping applies. A practitioner must configure/approve a mapping.'}
        </Banner>
      )}
      <form onSubmit={(e: FormEvent) => { e.preventDefault(); setError(null); setResult(null); m.mutate() }} className="grid max-w-2xl gap-4 rounded-xl border border-slate-200 bg-white p-5 sm:grid-cols-2">
        <Field label="Transaction Type">
          <select className={inputClass} value={form.transaction_type ?? ''} onChange={(e) => set('transaction_type', e.target.value)} required>
            <option value="">Select…</option>
            {TX.map((t) => <option key={t} value={t}>{t}</option>)}
          </select>
        </Field>
        <Field label="Tax Treatment">
          <select className={inputClass} value={form.tax_treatment ?? ''} onChange={(e) => set('tax_treatment', e.target.value)} required>
            <option value="">Select…</option>
            {TREAT.map((t) => <option key={t} value={t}>{t}</option>)}
          </select>
        </Field>
        <Field label="VAT Rate code (optional)">
          <input className={inputClass} value={form.vat_rate ?? ''} onChange={(e) => set('vat_rate', e.target.value)} />
        </Field>
        <Field label="Taxable amount">
          <input className={inputClass} type="number" step="any" value={form.taxable_amount ?? ''} onChange={(e) => set('taxable_amount', e.target.value)} />
        </Field>
        <Field label="VAT amount">
          <input className={inputClass} type="number" step="any" value={form.vat_amount ?? ''} onChange={(e) => set('vat_amount', e.target.value)} />
        </Field>
        <div className="col-span-full flex justify-end">
          <Button type="submit" disabled={m.isPending}>{m.isPending ? 'Classifying…' : 'Classify'}</Button>
        </div>
      </form>
    </div>
  )
}
