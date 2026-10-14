import { type FormEvent, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { Banner, Button, Field, Modal, PageHeader, Table, apiError, inputClass } from '../../shared/ui/primitives'
import { Users } from '../../shared/lib/resources'

export function UsersPage() {
  const qc = useQueryClient()
  const { data: users } = useQuery({ queryKey: ['users'], queryFn: Users.list })
  const [open, setOpen] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [form, setForm] = useState<Record<string, string>>({ role: 'viewer' })

  const create = useMutation({
    mutationFn: () => Users.create(form),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['users'] }); setOpen(false); setForm({ role: 'viewer' }) },
    onError: (e) => setError(apiError(e)),
  })
  const set = (k: string, v: string) => setForm((s) => ({ ...s, [k]: v }))

  return (
    <div>
      <PageHeader title="Users" subtitle="Users with access to the active company"
        action={<Button onClick={() => { setOpen(true); setError(null) }}><Plus size={16} /> New User</Button>} />
      <Table head={['Name', 'Email', 'Username', 'Status']}>
        {(users ?? []).map((u) => (
          <tr key={u.id} className="text-ink">
            <td className="px-4 py-2.5">{u.name}</td>
            <td className="px-4 py-2.5">{u.email}</td>
            <td className="px-4 py-2.5">{u.username ?? '—'}</td>
            <td className="px-4 py-2.5">{u.status}</td>
          </tr>
        ))}
        {(users ?? []).length === 0 && <tr><td colSpan={4} className="px-4 py-8 text-center text-muted">No users yet.</td></tr>}
      </Table>

      {open && (
        <Modal title="New User" onClose={() => setOpen(false)}>
          {error && <Banner kind="error">{error}</Banner>}
          <form onSubmit={(e: FormEvent) => { e.preventDefault(); setError(null); create.mutate() }} className="grid gap-4 sm:grid-cols-2">
            <Field label="Full Name"><input className={inputClass} value={form.name ?? ''} onChange={(e) => set('name', e.target.value)} required /></Field>
            <Field label="Email"><input className={inputClass} type="email" value={form.email ?? ''} onChange={(e) => set('email', e.target.value)} required /></Field>
            <Field label="Username"><input className={inputClass} value={form.username ?? ''} onChange={(e) => set('username', e.target.value)} /></Field>
            <Field label="Mobile"><input className={inputClass} value={form.mobile ?? ''} onChange={(e) => set('mobile', e.target.value)} /></Field>
            <Field label="Password"><input className={inputClass} type="password" value={form.password ?? ''} onChange={(e) => set('password', e.target.value)} required /></Field>
            <Field label="NID (encrypted)"><input className={inputClass} value={form.nid ?? ''} onChange={(e) => set('nid', e.target.value)} /></Field>
            <Field label="Role">
              <select className={inputClass} value={form.role ?? 'viewer'} onChange={(e) => set('role', e.target.value)}>
                <option value="viewer">Viewer</option>
                <option value="admin">Admin</option>
              </select>
            </Field>
            <div className="col-span-full mt-2 flex justify-end gap-2">
              <Button variant="ghost" onClick={() => setOpen(false)}>Cancel</Button>
              <Button type="submit" disabled={create.isPending}>{create.isPending ? 'Saving…' : 'Create User'}</Button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  )
}
