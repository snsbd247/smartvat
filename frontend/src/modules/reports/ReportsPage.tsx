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

export type ReportCategory = 'all' | 'accounting' | 'inventory' | 'vat-vds' | 'sales' | 'purchase' | 'aging'

// Which report keys belong to each category menu. Categories without a dedicated
// report yet show a helpful empty state instead of the whole catalogue.
const CATEGORY: Record<ReportCategory, { title: string; subtitle: string; keys: string[] }> = {
  all: { title: 'Reports & MIS', subtitle: 'View in-app, open as PDF, or export to Excel', keys: Object.keys(LABELS) },
  accounting: { title: 'Accounting Reports', subtitle: 'Trial balance, profit & loss and balance sheet', keys: ['trial-balance', 'profit-loss', 'balance-sheet'] },
  inventory: { title: 'Inventory Reports', subtitle: 'Stock valuation and movement', keys: ['stock-valuation'] },
  'vat-vds': { title: 'VAT & VDS Reports', subtitle: 'Mushak note summaries and VAT registers', keys: ['vat-note-summary'] },
  sales: { title: 'Sales Reports', subtitle: 'Revenue and customer analytics', keys: [] },
  purchase: { title: 'Purchase Reports', subtitle: 'Procurement and supplier analytics', keys: [] },
  aging: { title: 'Receivable / Payable Aging', subtitle: 'Outstanding balances by age bucket', keys: [] },
}

export function ReportsPage({ category = 'all' }: { category?: ReportCategory }) {
  const { data: available } = useQuery({ queryKey: ['reports'], queryFn: Reports.list })
  const cfg = CATEGORY[category]
  // Only show reports that both belong to this category and are actually registered.
  const keys = (available ?? []).filter((k) => cfg.keys.includes(k))

  return (
    <div>
      <PageHeader title={cfg.title} subtitle={cfg.subtitle} />

      {cfg.keys.length === 0 ? (
        <div className="rounded-xl border border-slate-200 bg-white p-8 text-center">
          <p className="font-medium text-ink">No standard report in this group yet.</p>
          <p className="mt-1 text-sm text-muted">
            Use the live <Link to="/" className="font-medium text-brand hover:underline">dashboard</Link> for these KPIs, or the{' '}
            <Link to="/reports/builder" className="font-medium text-brand hover:underline">report hub</Link> for available reports.
          </p>
        </div>
      ) : (
        <Table head={['Report', 'Actions']}>
          {keys.map((key) => (
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
          {keys.length === 0 && (
            <tr><td colSpan={2} className="px-4 py-8 text-center text-muted">Loading…</td></tr>
          )}
        </Table>
      )}
    </div>
  )
}
