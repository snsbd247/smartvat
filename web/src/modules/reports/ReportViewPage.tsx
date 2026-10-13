import { useQuery } from '@tanstack/react-query'
import { Download, FileText } from 'lucide-react'
import { Button, PageHeader, Table } from '../../shared/ui/primitives'
import { Reports } from '../../shared/lib/resources'

const fmt = (v: unknown) =>
  typeof v === 'number' || (typeof v === 'string' && v !== '' && !isNaN(Number(v)))
    ? Number(v).toLocaleString(undefined, { maximumFractionDigits: 2 })
    : String(v ?? '')

export function ReportViewPage({ reportKey }: { reportKey: string }) {
  const { data, isLoading } = useQuery({ queryKey: ['report', reportKey], queryFn: () => Reports.json(reportKey) })

  return (
    <div>
      <PageHeader
        title={data?.title ?? 'Report'}
        subtitle={data?.subtitle ?? undefined}
        action={
          <div className="flex gap-2">
            <Button variant="ghost" onClick={() => Reports.openOrDownload(reportKey, 'pdf')}>
              <FileText size={16} /> PDF
            </Button>
            <Button variant="ghost" onClick={() => Reports.openOrDownload(reportKey, 'xlsx')}>
              <Download size={16} /> Excel
            </Button>
          </div>
        }
      />
      {isLoading ? (
        <div className="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-muted">Loading…</div>
      ) : (
        <Table head={(data?.columns ?? []).map((c) => c.label)}>
          {(data?.rows ?? []).map((row, i) => (
            <tr key={i} className="text-ink">
              {(data?.columns ?? []).map((c) => (
                <td key={c.key} className={`px-4 py-2.5 ${c.align === 'right' ? 'text-right' : ''}`}>
                  {c.align === 'right' ? fmt(row[c.key]) : String(row[c.key] ?? '')}
                </td>
              ))}
            </tr>
          ))}
          {(data?.rows ?? []).length === 0 && (
            <tr><td colSpan={(data?.columns ?? []).length || 1} className="px-4 py-8 text-center text-muted">No data.</td></tr>
          )}
        </Table>
      )}
    </div>
  )
}
