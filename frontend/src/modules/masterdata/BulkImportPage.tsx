import { useMutation, useQuery } from '@tanstack/react-query'
import { Upload } from 'lucide-react'
import { useState } from 'react'
import { BulkImport, type ImportResult } from '../../shared/lib/resources'
import { Banner, Button, PageHeader, Table, apiError, inputClass } from '../../shared/ui/primitives'

const ENTITIES = [
  { key: 'products', label: 'Products' },
  { key: 'customers', label: 'Customers' },
  { key: 'suppliers', label: 'Suppliers' },
]

export function BulkImportPage() {
  const [entity, setEntity] = useState('products')
  const [csv, setCsv] = useState('')
  const [result, setResult] = useState<ImportResult | null>(null)
  const [err, setErr] = useState<string | null>(null)

  const { data: header } = useQuery({ queryKey: ['import-template', entity], queryFn: () => BulkImport.template(entity) })

  const run = useMutation({
    mutationFn: () => BulkImport.run(entity, csv),
    onSuccess: (r) => { setErr(null); setResult(r) },
    onError: (e) => { setResult(null); setErr(apiError(e)) },
  })

  const onFile = (file: File) => {
    const reader = new FileReader()
    reader.onload = () => setCsv(String(reader.result ?? ''))
    reader.readAsText(file)
  }

  return (
    <div className="max-w-3xl">
      <PageHeader title="Bulk Import" subtitle="Import products, customers or suppliers from a CSV file. Existing codes are skipped." />
      {err && <Banner kind="error">{err}</Banner>}
      {result && (
        <Banner kind="success">
          {result.created} created, {result.skipped} skipped{result.errors.length ? `, ${result.errors.length} row error(s)` : ''}.
        </Banner>
      )}

      <div className="space-y-4 rounded-xl border border-slate-200 bg-white p-5">
        <div className="flex flex-wrap items-end gap-3">
          <label className="block">
            <span className="mb-1 block text-sm font-medium text-ink">Import type</span>
            <select className={inputClass} value={entity} onChange={(e) => { setEntity(e.target.value); setResult(null); setErr(null) }}>
              {ENTITIES.map((x) => <option key={x.key} value={x.key}>{x.label}</option>)}
            </select>
          </label>
          <label className="block">
            <span className="mb-1 block text-sm font-medium text-ink">Upload CSV file</span>
            <input type="file" accept=".csv,text/csv" className={inputClass} onChange={(e) => { const f = e.target.files?.[0]; if (f) onFile(f) }} />
          </label>
        </div>

        {header && (
          <div className="rounded-lg bg-workspace p-3 text-xs">
            <span className="text-muted">Required header (first row): </span>
            <code className="font-mono text-ink">{header}</code>
            <button type="button" className="ml-2 font-medium text-brand hover:underline" onClick={() => setCsv((c) => c || header + '\n')}>insert</button>
          </div>
        )}

        <label className="block">
          <span className="mb-1 block text-sm font-medium text-ink">CSV content</span>
          <textarea className={`${inputClass} font-mono text-xs`} rows={10} value={csv} onChange={(e) => setCsv(e.target.value)}
            placeholder={`${header ?? 'code,name,...'}\n...`} />
        </label>

        <div className="flex justify-end">
          <Button onClick={() => run.mutate()} disabled={run.isPending || !csv.trim()}><Upload size={16} /> {run.isPending ? 'Importing…' : 'Import'}</Button>
        </div>
      </div>

      {result && result.errors.length > 0 && (
        <div className="mt-4">
          <h3 className="mb-2 text-sm font-semibold text-ink">Row errors</h3>
          <Table head={['Row', 'Message']}>
            {result.errors.map((e, i) => (
              <tr key={i} className="text-ink"><td className="px-4 py-2">{e.row}</td><td className="px-4 py-2">{e.message}</td></tr>
            ))}
          </Table>
        </div>
      )}
    </div>
  )
}
