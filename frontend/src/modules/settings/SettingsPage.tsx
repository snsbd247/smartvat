import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { Settings } from '../../shared/lib/resources'
import { Banner, Button, Field, PageHeader, apiError, inputClass } from '../../shared/ui/primitives'

type FieldType = 'text' | 'textarea' | 'number' | 'toggle' | 'select'
interface SettingField {
  key: string
  label: string
  type?: FieldType
  placeholder?: string
  options?: string[]
  help?: string
}
interface SettingSchema {
  group: string
  title: string
  subtitle: string
  fields: SettingField[]
}

const SCHEMAS: Record<string, SettingSchema> = {
  general: {
    group: 'general',
    title: 'General Settings',
    subtitle: 'Organisation-wide preferences applied across the app.',
    fields: [
      { key: 'legal_name', label: 'Legal name', placeholder: 'As registered with authorities' },
      { key: 'address', label: 'Address', type: 'textarea' },
      { key: 'phone', label: 'Phone' },
      { key: 'bin', label: 'BIN (Business Identification No.)' },
      { key: 'date_format', label: 'Date format', type: 'select', options: ['DD/MM/YYYY', 'YYYY-MM-DD', 'MM/DD/YYYY'] },
      { key: 'currency_symbol', label: 'Currency symbol', placeholder: '৳' },
    ],
  },
  numbering: {
    group: 'numbering',
    title: 'Document Numbering',
    subtitle: 'Prefixes used when generating document numbers. Sequences stay per-company and atomic.',
    fields: [
      { key: 'purchase_order', label: 'Purchase Order prefix', placeholder: 'PO-' },
      { key: 'sales_invoice', label: 'Sales Invoice prefix', placeholder: 'INV-' },
      { key: 'delivery_challan', label: 'Delivery Challan prefix', placeholder: 'DC-' },
      { key: 'journal', label: 'Journal Entry prefix', placeholder: 'JV-' },
      { key: 'production_order', label: 'Production Order prefix', placeholder: 'PRD-' },
      { key: 'reset_yearly', label: 'Reset sequence each fiscal year', type: 'toggle' },
    ],
  },
  notifications: {
    group: 'notifications',
    title: 'Notification Settings',
    subtitle: 'Choose which events raise in-app notifications.',
    fields: [
      { key: 'notify_pending_approvals', label: 'Pending approvals', type: 'toggle' },
      { key: 'notify_vat_review', label: 'VAT transactions needing review', type: 'toggle' },
      { key: 'notify_backup_failures', label: 'Backup failures', type: 'toggle' },
      { key: 'notify_low_stock', label: 'Low stock alerts', type: 'toggle' },
    ],
  },
  email: {
    group: 'email',
    title: 'Email Configuration',
    subtitle: 'SMTP settings for outgoing mail. Leave blank to keep logging mail only.',
    fields: [
      { key: 'host', label: 'SMTP host', placeholder: 'smtp.example.com' },
      { key: 'port', label: 'Port', type: 'number', placeholder: '587' },
      { key: 'username', label: 'Username' },
      { key: 'from_address', label: 'From address', placeholder: 'no-reply@easyvat365.xyz' },
      { key: 'from_name', label: 'From name', placeholder: 'PLASTEX ERP' },
      { key: 'encryption', label: 'Encryption', type: 'select', options: ['tls', 'ssl', 'none'] },
    ],
  },
  'audit-policy': {
    group: 'audit-policy',
    title: 'Audit Policy',
    subtitle: 'Controls for the audit trail and data retention.',
    fields: [
      { key: 'log_logins', label: 'Log login activity', type: 'toggle' },
      { key: 'log_data_changes', label: 'Log data changes', type: 'toggle' },
      { key: 'retention_days', label: 'Audit log retention (days)', type: 'number', placeholder: '365' },
    ],
  },
}

export function SettingsPage({ group }: { group: keyof typeof SCHEMAS }) {
  const schema = SCHEMAS[group]
  const qc = useQueryClient()
  const [form, setForm] = useState<Record<string, unknown>>({})
  const [msg, setMsg] = useState<string | null>(null)
  const [err, setErr] = useState<string | null>(null)

  const { data, isLoading } = useQuery({ queryKey: ['settings', group], queryFn: () => Settings.get(group) })

  useEffect(() => {
    if (data) setForm(data as Record<string, unknown>)
  }, [data])

  const save = useMutation({
    mutationFn: () => Settings.put(group, form),
    onSuccess: () => {
      setErr(null)
      setMsg('Settings saved.')
      qc.invalidateQueries({ queryKey: ['settings', group] })
    },
    onError: (e) => { setMsg(null); setErr(apiError(e)) },
  })

  const set = (k: string, v: unknown) => setForm((f) => ({ ...f, [k]: v }))

  return (
    <div className="max-w-2xl">
      <PageHeader title={schema.title} subtitle={schema.subtitle} />
      {msg && <Banner kind="success">{msg}</Banner>}
      {err && <Banner kind="error">{err}</Banner>}

      <form
        className="space-y-4 rounded-xl border border-slate-200 bg-white p-6"
        onSubmit={(e) => { e.preventDefault(); save.mutate() }}
      >
        {schema.fields.map((f) => {
          const val = form[f.key]
          if (f.type === 'toggle') {
            return (
              <label key={f.key} className="flex items-center justify-between gap-4 py-1">
                <span className="text-sm font-medium text-ink">{f.label}</span>
                <input type="checkbox" className="h-5 w-5 accent-[color:var(--color-brand)]" checked={Boolean(val)} onChange={(e) => set(f.key, e.target.checked)} />
              </label>
            )
          }
          return (
            <Field key={f.key} label={f.label}>
              {f.type === 'textarea' ? (
                <textarea className={inputClass} rows={3} value={String(val ?? '')} placeholder={f.placeholder} onChange={(e) => set(f.key, e.target.value)} />
              ) : f.type === 'select' ? (
                <select className={inputClass} value={String(val ?? '')} onChange={(e) => set(f.key, e.target.value)}>
                  <option value="">Select…</option>
                  {f.options?.map((o) => <option key={o} value={o}>{o}</option>)}
                </select>
              ) : (
                <input className={inputClass} type={f.type === 'number' ? 'number' : 'text'} value={String(val ?? '')} placeholder={f.placeholder} onChange={(e) => set(f.key, f.type === 'number' ? e.target.value : e.target.value)} />
              )}
              {f.help && <span className="mt-1 block text-xs text-muted">{f.help}</span>}
            </Field>
          )
        })}
        <Button type="submit" disabled={save.isPending || isLoading}>
          {save.isPending ? 'Saving…' : 'Save Settings'}
        </Button>
      </form>
    </div>
  )
}
