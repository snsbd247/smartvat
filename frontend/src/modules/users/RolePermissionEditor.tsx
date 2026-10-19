import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, Save, ShieldAlert } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import { System } from '../../shared/lib/resources'
import { useAuth } from '../../shared/auth/AuthContext'
import { Banner, Button, PageHeader, apiError, inputClass } from '../../shared/ui/primitives'

export function RolePermissionEditor() {
  const qc = useQueryClient()
  const { data } = useQuery({ queryKey: ['roles-matrix'], queryFn: System.rolesMatrix })
  const { data: me } = useQuery({ queryKey: ['me'], queryFn: () => import('../../shared/lib/resources').then((m) => m.Profile.me()) })
  useAuth()

  const [role, setRole] = useState('')
  const [checked, setChecked] = useState<Set<string>>(new Set())
  const [newRole, setNewRole] = useState('')
  const [msg, setMsg] = useState<{ kind: 'error' | 'success'; text: string } | null>(null)

  const isSuperAdmin = (me?.roles ?? []).includes('super-admin')
  const current = data?.roles.find((r) => r.name === role)
  const editable = isSuperAdmin && role !== 'super-admin'

  useEffect(() => {
    if (!role && data?.roles.length) setRole(data.roles.find((r) => r.name !== 'super-admin')?.name ?? data.roles[0].name)
  }, [data, role])
  useEffect(() => {
    if (current) setChecked(new Set(current.permissions))
  }, [role, current])

  // Group permissions by module prefix (before the dot).
  const groups = useMemo(() => {
    const g: Record<string, string[]> = {}
    for (const p of data?.permissions ?? []) {
      const key = p.includes('.') ? p.split('.')[0] : 'other'
      ;(g[key] ??= []).push(p)
    }
    return g
  }, [data])

  const save = useMutation({
    mutationFn: () => System.setRolePermissions(role, [...checked]),
    onSuccess: () => { setMsg({ kind: 'success', text: 'Role permissions saved.' }); qc.invalidateQueries({ queryKey: ['roles-matrix'] }) },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })
  const create = useMutation({
    mutationFn: () => System.createRole(newRole.trim()),
    onSuccess: () => { setMsg({ kind: 'success', text: `Role "${newRole}" created.` }); setNewRole(''); qc.invalidateQueries({ queryKey: ['roles-matrix'] }) },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })

  const toggle = (p: string) => setChecked((s) => { const n = new Set(s); n.has(p) ? n.delete(p) : n.add(p); return n })
  const toggleGroup = (mods: string[], on: boolean) => setChecked((s) => { const n = new Set(s); mods.forEach((m) => on ? n.add(m) : n.delete(m)); return n })

  return (
    <div>
      <PageHeader title="Roles & Permissions" subtitle="Set which permissions each role has. Editing requires a super-admin." />
      {msg && <Banner kind={msg.kind}>{msg.text}</Banner>}
      {!isSuperAdmin && (
        <div className="mb-4 flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-700">
          <ShieldAlert size={15} /> View-only — only a super-admin can change role permissions.
        </div>
      )}

      <div className="flex flex-wrap items-center gap-2">
        <div className="flex flex-wrap gap-1 rounded-lg border border-slate-200 bg-workspace p-1">
          {(data?.roles ?? []).map((r) => (
            <button key={r.name} onClick={() => setRole(r.name)} className={`rounded-md px-3 py-1.5 text-sm font-medium capitalize transition ${role === r.name ? 'bg-white text-ink shadow-sm' : 'text-muted hover:text-ink'}`}>
              {r.name.replace(/-/g, ' ')}
            </button>
          ))}
        </div>
        {isSuperAdmin && (
          <div className="ml-auto flex items-center gap-2">
            <input className={`${inputClass} w-40`} placeholder="new-role-name" value={newRole} onChange={(e) => setNewRole(e.target.value)} />
            <Button variant="ghost" onClick={() => create.mutate()} disabled={!newRole.trim() || create.isPending}><Plus size={15} /> Add role</Button>
          </div>
        )}
      </div>

      {role === 'super-admin' ? (
        <div className="mt-4 rounded-xl border border-slate-200 bg-white p-6 text-sm text-muted">The super-admin role always has every permission and cannot be edited.</div>
      ) : (
        <>
          <div className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {Object.entries(groups).map(([mod, perms]) => {
              const allOn = perms.every((p) => checked.has(p))
              return (
                <div key={mod} className="rounded-xl border border-slate-200 bg-white p-4">
                  <div className="mb-2 flex items-center justify-between">
                    <div className="font-semibold capitalize text-ink">{mod.replace(/_/g, ' ')}</div>
                    {editable && (
                      <button className="text-xs font-medium text-brand hover:underline" onClick={() => toggleGroup(perms, !allOn)}>{allOn ? 'none' : 'all'}</button>
                    )}
                  </div>
                  <div className="space-y-1.5">
                    {perms.map((p) => (
                      <label key={p} className="flex items-center gap-2 text-sm text-ink">
                        <input type="checkbox" className="h-4 w-4 accent-[color:var(--color-brand)] disabled:opacity-50" checked={checked.has(p)} disabled={!editable} onChange={() => toggle(p)} />
                        {p.split('.')[1] ?? p}
                      </label>
                    ))}
                  </div>
                </div>
              )
            })}
          </div>

          {editable && (
            <div className="mt-5 flex justify-end">
              <Button onClick={() => save.mutate()} disabled={save.isPending}><Save size={16} /> {save.isPending ? 'Saving…' : 'Save Permissions'}</Button>
            </div>
          )}
        </>
      )}
    </div>
  )
}
