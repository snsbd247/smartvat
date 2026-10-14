import { BookOpen, CheckCircle2, LifeBuoy, ShieldCheck } from 'lucide-react'
import { PageHeader } from '../../shared/ui/primitives'

const sections = [
  {
    icon: <BookOpen size={18} />,
    title: 'Getting started',
    items: [
      'Pick your active company from the top bar — all data is scoped to it.',
      'Available modules depend on your company’s business type (Manufacturing / Trading / Service).',
      'Master Data (products, customers, suppliers, chart of accounts) should be set up first.',
    ],
  },
  {
    icon: <CheckCircle2 size={18} />,
    title: 'Daily operations',
    items: [
      'Purchases: Order → Goods Receipt → Invoice → Payment. Sales: Invoice → Delivery Challan → Receipt.',
      'Documents that need sign-off appear in the Approval Center as draft, then approve/reject.',
      'Stock postings are concurrency-safe and never go negative; inventory stays reconciled with the GL.',
    ],
  },
  {
    icon: <ShieldCheck size={18} />,
    title: 'Compliance & data',
    items: [
      'VAT/Mushak and VDS are fully data-driven — unmapped transactions are flagged for review, never guessed.',
      'Accounting periods can be locked under Accounting → Period Closing; closed periods reject new postings.',
      'Encrypted backups run automatically every 20 minutes; check Backup & System Health for status.',
    ],
  },
]

export function HelpPage() {
  return (
    <div className="max-w-3xl">
      <PageHeader title="Help & Documentation" subtitle="A quick guide to working in PLASTEX ERP." />

      <div className="space-y-4">
        {sections.map((s) => (
          <div key={s.title} className="rounded-xl border border-slate-200 bg-white p-5">
            <div className="mb-3 flex items-center gap-2 font-semibold text-ink">
              <span className="grid h-8 w-8 place-items-center rounded-lg bg-brand/10 text-brand">{s.icon}</span>
              {s.title}
            </div>
            <ul className="space-y-2 pl-1">
              {s.items.map((it, i) => (
                <li key={i} className="flex gap-2 text-sm text-ink">
                  <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand" />
                  {it}
                </li>
              ))}
            </ul>
          </div>
        ))}

        <div className="flex items-start gap-3 rounded-xl border border-brand/20 bg-brand/5 p-5">
          <LifeBuoy className="mt-0.5 text-brand" size={20} />
          <div className="text-sm text-ink">
            <div className="font-semibold">Need more help?</div>
            Contact your system administrator, or reach support at{' '}
            <a className="font-medium text-brand underline" href="mailto:support@easyvat365.xyz">support@easyvat365.xyz</a>.
          </div>
        </div>
      </div>
    </div>
  )
}
