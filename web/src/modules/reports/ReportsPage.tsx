import { useQuery } from '@tanstack/react-query'
import { Download, Eye, FileText } from 'lucide-react'
import { Link } from 'react-router-dom'
import { Button, PageHeader, Table } from '../../shared/ui/primitives'
import { Reports } from '../../shared/lib/resources'

const LABELS: Record<string, string> = {
  'trial-balance': 'Trial Balance',
  'profit-loss': 'Profit & Loss',
  'balance-sheet': 'Balance Sheet',
  'stock-valuation': 'Stock Valuation',
  'vat-note-summary': 'Mushak 9.1 — Note Summary',
}

export function ReportsPage() {
  const { data: keys } = useQuery({ queryKey: ['reports'], queryFn: Reports.list })

  return (
    <div>
      <PageHeader title="Reports & MIS" subtitle="View in-app, open as PDF, or export to Excel" />
      <Table head={['Report', 'Actions']}>
        {(keys ?? []).map((key) => (
          <tr key={key} className="text-ink">
            <td className="px-4 py-3 font-medium">{LABELS[key] ?? key}</td>
            <td className="px-4 py-3">
              <div className="flex gap-2">
                <Link to={`/reports/view/${key}`}>
                  <Button variant="ghost"><Eye size={15} /> View</Button>
                </Link>
                <Button variant="ghost" onClick={() => Reports.openOrDownload(key, 'pdf')}>
                  <FileText size={15} /> PDF
                </Button>
                <Button variant="ghost" onClick={() => Reports.openOrDownload(key, 'xlsx')}>
                  <Download size={15} /> Excel
                </Button>
              </div>
            </td>
          </tr>
        ))}
        {(keys ?? []).length === 0 && (
          <tr><td colSpan={2} className="px-4 py-8 text-center text-muted">Loading…</td></tr>
        )}
      </Table>
    </div>
  )
}
