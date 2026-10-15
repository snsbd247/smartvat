import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { CheckCircle2, HardDrive, PlayCircle } from 'lucide-react'
import { Backup } from '../../shared/lib/resources'
import { Banner, Button, Field, PageHeader, apiError, inputClass } from '../../shared/ui/primitives'

export function DriveSettingsPage() {
  const qc = useQueryClient()
  const { data: cfg } = useQuery({ queryKey: ['drive-config'], queryFn: Backup.driveConfig })
  const [enabled, setEnabled] = useState(false)
  const [folderId, setFolderId] = useState('')
  const [creds, setCreds] = useState('')
  const [msg, setMsg] = useState<{ kind: 'error' | 'success'; text: string } | null>(null)

  useEffect(() => {
    if (cfg) { setEnabled(cfg.enabled); setFolderId(cfg.folder_id ?? '') }
  }, [cfg])

  const save = useMutation({
    mutationFn: () => Backup.saveDriveConfig({ enabled, folder_id: folderId || undefined, credentials_json: creds || undefined }),
    onSuccess: () => { setMsg({ kind: 'success', text: 'Google Drive backup settings saved.' }); setCreds(''); qc.invalidateQueries({ queryKey: ['drive-config'] }) },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })

  const test = useMutation({
    mutationFn: () => Backup.run(),
    onSuccess: () => { setMsg({ kind: 'success', text: 'Backup ran successfully — uploaded and verified to the configured target.' }); qc.invalidateQueries({ queryKey: ['backup-jobs'] }) },
    onError: (e) => setMsg({ kind: 'error', text: apiError(e) }),
  })

  return (
    <div className="max-w-2xl">
      <PageHeader title="Google Drive Backup" subtitle="Store encrypted backups off-site in a Google Drive folder." />
      {msg && <Banner kind={msg.kind}>{msg.text}</Banner>}

      <div className="mb-4 flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4">
        <span className={`grid h-9 w-9 place-items-center rounded-lg ${cfg?.enabled ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-muted'}`}>
          {cfg?.enabled ? <CheckCircle2 size={18} /> : <HardDrive size={18} />}
        </span>
        <div className="text-sm">
          <div className="font-semibold text-ink">{cfg?.enabled ? 'Enabled' : 'Disabled (backups stay on the server)'}</div>
          <div className="text-muted">{cfg?.credentials_set ? 'Service-account credentials are stored (encrypted).' : 'No credentials stored yet.'}</div>
        </div>
      </div>

      <form className="space-y-4 rounded-xl border border-slate-200 bg-white p-6" onSubmit={(e) => { e.preventDefault(); save.mutate() }}>
        <label className="flex items-center justify-between gap-4">
          <span className="text-sm font-medium text-ink">Enable Google Drive backup</span>
          <input type="checkbox" className="h-5 w-5 accent-[color:var(--color-brand)]" checked={enabled} onChange={(e) => setEnabled(e.target.checked)} />
        </label>

        <Field label="Drive folder ID">
          <input className={inputClass} value={folderId} onChange={(e) => setFolderId(e.target.value)} placeholder="e.g. 1A2b3C...   (share this folder with the service account email)" />
        </Field>

        <Field label={cfg?.credentials_set ? 'Service-account JSON (paste to replace; leave blank to keep current)' : 'Service-account JSON'}>
          <textarea className={`${inputClass} font-mono text-xs`} rows={7} value={creds} onChange={(e) => setCreds(e.target.value)}
            placeholder='{ "type": "service_account", "client_email": "...", "private_key": "...", ... }' />
          <span className="mt-1 block text-xs text-muted">Stored encrypted at rest and never returned to the browser.</span>
        </Field>

        <div className="flex items-center justify-between">
          <Button type="submit" disabled={save.isPending}>{save.isPending ? 'Saving…' : 'Save settings'}</Button>
          <Button variant="ghost" type="button" onClick={() => test.mutate()} disabled={test.isPending}>
            <PlayCircle size={16} /> {test.isPending ? 'Running…' : 'Run backup now'}
          </Button>
        </div>
      </form>

      <div className="mt-4 rounded-xl border border-slate-200 bg-workspace p-4 text-sm text-ink">
        <div className="mb-1 font-semibold">How to set up</div>
        <ol className="list-decimal space-y-1 pl-5 text-muted">
          <li>In Google Cloud, create a <strong>service account</strong> and download its JSON key.</li>
          <li>Enable the <strong>Google Drive API</strong> for that project.</li>
          <li>Create a Drive folder and <strong>share it</strong> with the service account's email (Editor).</li>
          <li>Paste the folder ID and the JSON above, tick “Enable”, Save, then “Run backup now” to verify.</li>
        </ol>
      </div>
    </div>
  )
}
