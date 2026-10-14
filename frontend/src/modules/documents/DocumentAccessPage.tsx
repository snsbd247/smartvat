import { useQuery } from '@tanstack/react-query'
import { Eye, ShieldCheck } from 'lucide-react'
import { System } from '../../shared/lib/resources'
import { PageHeader, Table } from '../../shared/ui/primitives'

export function DocumentAccessPage() {
  const { data: roles } = useQuery({ queryKey: ['roles'], queryFn: System.roles })

  const perms = [
    { icon: <Eye size={16} />, perm: 'document.view', desc: 'List and download documents in the repository.' },
    { icon: <ShieldCheck size={16} />, perm: 'document.manage', desc: 'Upload new documents and delete existing ones.' },
  ]

  return (
    <div className="max-w-2xl">
      <PageHeader title="Document Access Control" subtitle="Document access is governed by per-company roles and permissions." />

      <div className="mb-6 space-y-3">
        {perms.map((p) => (
          <div key={p.perm} className="flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-4">
            <span className="grid h-8 w-8 place-items-center rounded-lg bg-brand/10 text-brand">{p.icon}</span>
            <div>
              <code className="text-sm font-semibold text-ink">{p.perm}</code>
              <p className="text-sm text-muted">{p.desc}</p>
            </div>
          </div>
        ))}
      </div>

      <h3 className="mb-2 text-sm font-semibold text-ink">Roles in this company</h3>
      <Table head={['Role', 'Note']}>
        {(roles ?? []).map((r) => (
          <tr key={r} className="text-ink">
            <td className="px-4 py-2.5 font-medium capitalize">{r.replace(/-/g, ' ')}</td>
            <td className="px-4 py-2.5 text-muted">{r === 'super-admin' ? 'Full access to all documents' : r === 'viewer' ? 'View & download only' : 'View, upload & manage'}</td>
          </tr>
        ))}
        {(roles ?? []).length === 0 && (
          <tr><td colSpan={2} className="px-4 py-8 text-center text-muted">No roles found.</td></tr>
        )}
      </Table>
    </div>
  )
}
