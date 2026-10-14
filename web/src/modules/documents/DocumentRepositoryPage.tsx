import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Download, FileText, Trash2, Upload } from 'lucide-react'
import { useRef, useState } from 'react'
import { Documents } from '../../shared/lib/resources'
import { Banner, Button, Field, Modal, PageHeader, Table, apiError, inputClass } from '../../shared/ui/primitives'

const fmtSize = (n: number) => (n > 1048576 ? (n / 1048576).toFixed(1) + ' MB' : n > 1024 ? (n / 1024).toFixed(0) + ' KB' : n + ' B')

export function DocumentRepositoryPage() {
  const qc = useQueryClient()
  const fileRef = useRef<HTMLInputElement>(null)
  const [open, setOpen] = useState(false)
  const [title, setTitle] = useState('')
  const [category, setCategory] = useState('')
  const [file, setFile] = useState<File | null>(null)
  const [msg, setMsg] = useState<string | null>(null)
  const [err, setErr] = useState<string | null>(null)

  const { data: docs } = useQuery({ queryKey: ['documents'], queryFn: Documents.list })

  const upload = useMutation({
    mutationFn: () => Documents.upload(file!, { title: title || undefined, category: category || undefined }),
    onSuccess: () => {
      setErr(null); setMsg('Document uploaded.'); setOpen(false)
      setTitle(''); setCategory(''); setFile(null)
      qc.invalidateQueries({ queryKey: ['documents'] })
    },
    onError: (e) => setErr(apiError(e)),
  })

  const remove = useMutation({
    mutationFn: (id: number) => Documents.remove(id),
    onSuccess: () => { setMsg('Document deleted.'); qc.invalidateQueries({ queryKey: ['documents'] }) },
    onError: (e) => setErr(apiError(e)),
  })

  return (
    <div>
      <PageHeader
        title="Document Repository"
        subtitle="Private, tenant-scoped file storage. Downloads stream through the app with authorization."
        action={<Button onClick={() => { setOpen(true); setErr(null); setMsg(null) }}><Upload size={16} /> Upload</Button>}
      />
      {msg && <Banner kind="success">{msg}</Banner>}
      {err && <Banner kind="error">{err}</Banner>}

      <Table head={['Title', 'File', 'Category', 'Size', 'Uploaded by', 'When', '']}>
        {(docs ?? []).map((d) => (
          <tr key={d.id} className="text-ink">
            <td className="px-4 py-2.5 font-medium">
              <span className="inline-flex items-center gap-2"><FileText size={15} className="text-muted" />{d.title}</span>
            </td>
            <td className="px-4 py-2.5 text-muted">{d.filename}</td>
            <td className="px-4 py-2.5">{d.category ?? '—'}</td>
            <td className="px-4 py-2.5">{fmtSize(d.size)}</td>
            <td className="px-4 py-2.5">{d.uploaded_by ?? '—'}</td>
            <td className="px-4 py-2.5">{d.created_at ? new Date(d.created_at).toLocaleDateString() : '—'}</td>
            <td className="px-4 py-2.5">
              <div className="flex justify-end gap-1">
                <button type="button" onClick={() => Documents.download(d.id, d.filename)} className="rounded p-1.5 text-muted hover:bg-slate-100 hover:text-brand" title="Download">
                  <Download size={16} />
                </button>
                <button type="button" onClick={() => { if (confirm('Delete this document?')) remove.mutate(d.id) }} className="rounded p-1.5 text-muted hover:bg-rose-50 hover:text-rose-600" title="Delete">
                  <Trash2 size={16} />
                </button>
              </div>
            </td>
          </tr>
        ))}
        {(docs ?? []).length === 0 && (
          <tr><td colSpan={7} className="px-4 py-8 text-center text-muted">No documents uploaded yet.</td></tr>
        )}
      </Table>

      {open && (
        <Modal title="Upload Document" onClose={() => setOpen(false)}>
          {err && <Banner kind="error">{err}</Banner>}
          <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); if (file) upload.mutate() }}>
            <Field label="File (max 20 MB)">
              <input
                ref={fileRef}
                type="file"
                className={inputClass}
                onChange={(e) => {
                  const f = e.target.files?.[0] ?? null
                  setFile(f)
                  if (f && !title) setTitle(f.name)
                }}
                required
              />
            </Field>
            <Field label="Title">
              <input className={inputClass} value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Document title" />
            </Field>
            <Field label="Category (optional)">
              <input className={inputClass} value={category} onChange={(e) => setCategory(e.target.value)} placeholder="e.g. Invoice, Contract, License" />
            </Field>
            <div className="flex justify-end gap-2">
              <Button variant="ghost" onClick={() => setOpen(false)}>Cancel</Button>
              <Button type="submit" disabled={upload.isPending || !file}>{upload.isPending ? 'Uploading…' : 'Upload'}</Button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  )
}
