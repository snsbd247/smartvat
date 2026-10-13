import { useQuery } from '@tanstack/react-query'
import {
  Banknote,
  Boxes,
  CheckCircle2,
  Landmark,
  Receipt,
  TrendingUp,
  Wallet,
} from 'lucide-react'
import { fetchExecutive } from '../../shared/lib/api'

const money = (v: string | number | undefined) =>
  v == null ? '—' : Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })

export function DashboardPage() {
  const { data, isLoading, isError } = useQuery({
    queryKey: ['executive'],
    queryFn: fetchExecutive,
    refetchInterval: 60_000,
  })

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-xl font-semibold text-ink">Executive Dashboard</h2>
        <p className="text-sm text-muted">Live KPIs for the active company.</p>
      </div>

      {isError && (
        <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
          Could not load dashboard data.
        </div>
      )}

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Kpi title="Revenue" icon={<TrendingUp size={18} />} value={money(data?.revenue)} loading={isLoading} />
        <Kpi title="Net Profit" icon={<Banknote size={18} />} value={money(data?.net_profit)} loading={isLoading} />
        <Kpi title="Receivables" icon={<Wallet size={18} />} value={money(data?.receivables)} loading={isLoading} />
        <Kpi title="Payables" icon={<Receipt size={18} />} value={money(data?.payables)} loading={isLoading} />
        <Kpi title="Stock Value" icon={<Boxes size={18} />} value={money(data?.stock_value)} loading={isLoading} />
        <Kpi title="Cash & Bank" icon={<Landmark size={18} />} value={money(data?.cash_bank)} loading={isLoading} />
        <Kpi title="VDS Payable" icon={<Receipt size={18} />} value={money(data?.vds_payable)} loading={isLoading} />
        <Kpi
          title="Pending Approvals"
          icon={<CheckCircle2 size={18} />}
          value={isLoading ? '…' : String(data?.pending_approvals ?? 0)}
          loading={isLoading}
          accent={(data?.pending_approvals ?? 0) > 0}
        />
      </div>

      {(data?.vat_unmapped ?? 0) > 0 && (
        <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
          {data?.vat_unmapped} VAT transaction(s) are unmapped and require practitioner review.
        </div>
      )}
    </div>
  )
}

function Kpi({
  title,
  icon,
  value,
  loading,
  accent,
}: {
  title: string
  icon: React.ReactNode
  value: string
  loading?: boolean
  accent?: boolean
}) {
  return (
    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <div className="flex items-center gap-2 text-sm font-medium text-muted">
        {icon}
        {title}
      </div>
      <div
        className={[
          'mt-3 text-2xl font-semibold',
          accent ? 'text-amber-600' : 'text-ink',
        ].join(' ')}
      >
        {loading ? '…' : value}
      </div>
    </div>
  )
}
