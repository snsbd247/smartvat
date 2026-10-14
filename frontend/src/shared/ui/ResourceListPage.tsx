import { type FormEvent, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { Banner, Button, Field, Modal, PageHeader, Table, apiError, inputClass } from './primitives'

export interface FieldDef {
  name: string
  label: string
  type?: 'text' | 'select'
  options?: { value: string; label: string }[]
  required?: boolean
  default?: string
}
export interface ColumnDef<T> {
  key: keyof T
  label: string
}

export function ResourceListPage<T extends { id: number }>({
  title,
  subtitle,
  queryKey,
  fetchList,
  createFn,
  columns,
  fields,
}: {
  title: string
  subtitle?: string
  queryKey: string
  fetchList: () => Promise<T[]>
  createFn: (body: Record<string, string>) => Promise<unknown>
  columns: ColumnDef<T>[]
  fields: FieldDef[]
}) {
  const qc = useQueryClient()
  const [open, setOpen] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [form, setForm] = useState<Record<string, string>>({})

  const { data, isLoading } = useQuery({ queryKey: [queryKey], queryFn: fetchList })

  const mutation = useMutation({
    mutationFn: (body: Record<string, string>) => createFn(body),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: [queryKey] })
      qc.invalidateQueries({ queryKey: ['dashboard'] })
      setOpen(false)
      setForm({})
    },
    onError: (e) => setError(apiError(e)),
  })

  function onSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    const body: Record<string, string> = {}
    for (const f of fields) body[f.name] = form[f.name] ?? f.default ?? ''
    mutation.mutate(body)
  }

  return (
    <div>
      <PageHeader
        title={title}
        subtitle={subtitle}
        action={
          <Button onClick={() => { setOpen(true); setError(null) }}>
            <Plus size={16} /> New
          </Button>
        }
      />

      {isLoading ? (
        <div className="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-muted">Loading…</div>
      ) : (
        <Table head={columns.map((c) => c.label)}>
          {(data ?? []).map((row) => (
            <tr key={row.id} className="text-ink">
              {columns.map((c) => (
                <td key={String(c.key)} className="px-4 py-2.5">
                  {String(row[c.key] ?? '')}
                </td>
              ))}
            </tr>
          ))}
          {(data ?? []).length === 0 && (
            <tr>
              <td colSpan={columns.length} className="px-4 py-8 text-center text-muted">
                No records yet.
              </td>
            </tr>
          )}
        </Table>
      )}

      {open && (
        <Modal title={`New ${title.replace(/s$/, '')}`} onClose={() => setOpen(false)}>
          {error && <Banner kind="error">{error}</Banner>}
          <form onSubmit={onSubmit} className="grid gap-4 sm:grid-cols-2">
            {fields.map((f) => (
              <Field key={f.name} label={f.label}>
                {f.type === 'select' ? (
                  <select
                    className={inputClass}
                    value={form[f.name] ?? f.default ?? ''}
                    onChange={(e) => setForm((s) => ({ ...s, [f.name]: e.target.value }))}
                    required={f.required}
                  >
                    <option value="">Select…</option>
                    {f.options?.map((o) => (
                      <option key={o.value} value={o.value}>
                        {o.label}
                      </option>
                    ))}
                  </select>
                ) : (
                  <input
                    className={inputClass}
                    value={form[f.name] ?? f.default ?? ''}
                    onChange={(e) => setForm((s) => ({ ...s, [f.name]: e.target.value }))}
                    required={f.required}
                  />
                )}
              </Field>
            ))}
            <div className="col-span-full mt-2 flex justify-end gap-2">
              <Button variant="ghost" onClick={() => setOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" disabled={mutation.isPending}>
                {mutation.isPending ? 'Saving…' : 'Save'}
              </Button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  )
}
