import { type ReactNode } from 'react'
import { useQuery } from '@tanstack/react-query'
import {
  Bar,
  CartesianGrid,
  Cell,
  ComposedChart,
  Legend,
  Line,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
  BarChart,
} from 'recharts'
import {
  Banknote,
  Boxes,
  Building2,
  Factory,
  Landmark,
  ShoppingCart,
  TrendingUp,
  Users,
} from 'lucide-react'
import { fetchDashboard } from '../../shared/lib/api'

const money = (v: string | number | undefined | null) =>
  v == null || v === '' ? '—' : '৳ ' + Number(v).toLocaleString(undefined, { maximumFractionDigits: 0 })
const num = (v: string | number | undefined) => Number(v ?? 0)
const CAT_COLORS = ['#2563eb', '#16a34a', '#f59e0b', '#ef4444', '#8b5cf6', '#64748b']

const TYPE_LABEL: Record<string, string> = {
  manufacturing: 'Manufacturing',
  trading: 'Trading',
  service: 'Service Provider',
}
const TYPE_SUB: Record<string, string> = {
  manufacturing: 'Real-time overview of your manufacturing operations',
  trading: 'Overview of your trading operations',
  service: 'Overview of your service operations',
}

export function DashboardPage() {
  const { data, isLoading, isError } = useQuery({
    queryKey: ['dashboard'],
    queryFn: fetchDashboard,
    refetchInterval: 60_000,
  })

  // The dashboard variant follows the company's business type — no manual switch.
  const biz = data?.business_type ?? 'manufacturing'
  const isMfg = biz === 'manufacturing'
  const isService = biz === 'service'
  const hasStock = biz === 'manufacturing' || biz === 'trading'
  const k = data?.kpis

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-2xl font-bold text-ink">Dashboard — {TYPE_LABEL[biz]}</h2>
          <p className="text-sm text-muted">{TYPE_SUB[biz]}</p>
        </div>
      </div>

      {isError && (
        <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
          Could not load dashboard data.
        </div>
      )}

      {/* KPI cards — tailored to the business type */}
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Kpi title={isService ? 'Total Service Revenue' : 'Total Sales'} value={money(k?.total_sales)} icon={<ShoppingCart size={18} />} tint="emerald"
          sub={isService ? undefined : `Local ${money(k?.local_sales)} · Export ${money(k?.export_sales)}`} loading={isLoading} />
        {!isService && (
          <Kpi title={isMfg ? 'Total Purchase (RM)' : 'Total Purchase'} value={money(k?.total_purchase)} icon={<Boxes size={18} />} tint="blue"
            sub={`Local ${money(k?.local_purchase)} · Import ${money(k?.import_purchase)}`} loading={isLoading} />
        )}
        {isMfg && (
          <Kpi title="Production Output" value={num(k?.production_output).toLocaleString() + ' units'} icon={<Factory size={18} />} tint="amber"
            sub="Produced quantity" loading={isLoading} />
        )}
        {hasStock && (
          <Kpi title={isMfg ? 'Stock Value (RM + FG)' : 'Stock Value'} value={money(k?.stock_value)} icon={<Boxes size={18} />} tint="violet" loading={isLoading} />
        )}
        <Kpi title={isService ? 'Net Profit / Profitability' : 'Net Profit'} value={money(k?.net_profit)} icon={<Banknote size={18} />} tint="rose" loading={isLoading} />
        <Kpi title="Receivables" value={money(k?.receivables)} icon={<Users size={18} />} tint="emerald" loading={isLoading} />
        <Kpi title="Payables" value={money(k?.payables)} icon={<Building2 size={18} />} tint="rose" loading={isLoading} />
        <Kpi title="Cash & Bank" value={money(k?.cash_bank)} icon={<Landmark size={18} />} tint="blue" loading={isLoading} />
      </div>

      {(() => {
        const CategoryPie = (
          <Card title="Sales by Category">
            {(data?.sales_by_category?.length ?? 0) > 0 ? (
              <ResponsiveContainer width="100%" height={280}>
                <PieChart>
                  <Pie data={data?.sales_by_category ?? []} dataKey={(d) => num((d as { amount: string }).amount)} nameKey="category" innerRadius={60} outerRadius={95} paddingAngle={2}>
                    {(data?.sales_by_category ?? []).map((_, i) => <Cell key={i} fill={CAT_COLORS[i % CAT_COLORS.length]} />)}
                  </Pie>
                  <Tooltip formatter={(v) => money(v as number)} />
                  <Legend />
                </PieChart>
              </ResponsiveContainer>
            ) : <div className="grid h-[280px] place-items-center text-sm text-muted">No sales yet</div>}
          </Card>
        )

        return (
          <>
            {/* Trend + secondary chart (varies by business type) */}
            <div className={isService ? '' : 'grid gap-4 lg:grid-cols-2'}>
              <Card title={isService ? 'Revenue Trend (Last 12 Months)' : 'Sales & Purchase Trend (Last 12 Months)'}>
                <ResponsiveContainer width="100%" height={280}>
                  <ComposedChart data={data?.sales_purchase_trend ?? []}>
                    <CartesianGrid strokeDasharray="3 3" stroke="#eef2f7" />
                    <XAxis dataKey="month" tick={{ fontSize: 12 }} />
                    <YAxis tick={{ fontSize: 11 }} width={48} />
                    <Tooltip formatter={(v) => money(v as number)} />
                    <Legend />
                    <Bar dataKey="sales" name={isService ? 'Revenue' : 'Sales'} fill="#2563eb" radius={[3, 3, 0, 0]} />
                    {!isService && <Bar dataKey="purchase" name="Purchase" fill="#16a34a" radius={[3, 3, 0, 0]} />}
                    <Line dataKey="sales" name={isService ? 'Revenue trend' : 'Sales trend'} stroke="#8b5cf6" strokeWidth={2} dot={false} />
                  </ComposedChart>
                </ResponsiveContainer>
              </Card>

              {isMfg && (
                <Card title="Production Output (This Month)">
                  <ResponsiveContainer width="100%" height={280}>
                    <BarChart data={data?.production_output ?? []}>
                      <CartesianGrid strokeDasharray="3 3" stroke="#eef2f7" />
                      <XAxis dataKey="product" tick={{ fontSize: 11 }} />
                      <YAxis tick={{ fontSize: 11 }} width={48} />
                      <Tooltip />
                      <Bar dataKey="qty" name="Qty" radius={[3, 3, 0, 0]}>
                        {(data?.production_output ?? []).map((_, i) => <Cell key={i} fill={CAT_COLORS[i % CAT_COLORS.length]} />)}
                      </Bar>
                    </BarChart>
                  </ResponsiveContainer>
                </Card>
              )}
              {biz === 'trading' && CategoryPie}
            </div>

            {/* Breakdown row */}
            <div className="grid gap-4 lg:grid-cols-3">
              {hasStock && (
                <Card title="Inventory Stock Value (Top 5)">
                  <SimpleTable head={['Item', 'Value', '%']} rows={(data?.inventory_top ?? []).map((r) => [r.item, money(r.value), num(r.pct).toFixed(1) + '%'])} empty="No stock yet" />
                </Card>
              )}
              {(isMfg || isService) && CategoryPie}
              <Card title="Top 5 Customers">
                <SimpleTable head={['Customer', 'Type', 'Amount']} rows={(data?.top_customers ?? []).map((r) => [r.name, r.type, money(r.amount)])} empty="No customers yet" />
              </Card>
            </div>
          </>
        )
      })()}

      {/* Recent transactions + pending approvals */}
      <div className="grid gap-4 lg:grid-cols-2">
        <Card title="Recent Transactions">
          <SimpleTable
            head={['Date', 'Type', 'Doc No', 'Amount', 'Status']}
            rows={(data?.recent_transactions ?? []).map((r) => [r.date, r.type, r.doc_no, money(r.amount), r.status])}
            empty="No transactions yet"
          />
        </Card>

        <Card title="Pending Approvals">
          <SimpleTable
            head={['Type', 'Number', 'Amount']}
            rows={(data?.pending_approvals ?? []).map((r) => [
              r.type.replace(/_/g, ' '),
              r.number,
              r.amount ? money(r.amount) : '—',
            ])}
            empty="Nothing pending"
          />
        </Card>
      </div>
    </div>
  )
}

const TINTS: Record<string, string> = {
  emerald: 'bg-emerald-50 text-emerald-600',
  blue: 'bg-blue-50 text-blue-600',
  amber: 'bg-amber-50 text-amber-600',
  violet: 'bg-violet-50 text-violet-600',
  rose: 'bg-rose-50 text-rose-600',
}

function Kpi({
  title,
  value,
  icon,
  tint,
  sub,
  loading,
}: {
  title: string
  value: string
  icon: ReactNode
  tint: keyof typeof TINTS | string
  sub?: string
  loading?: boolean
}) {
  return (
    <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
      <div className="flex items-start justify-between">
        <div>
          <div className="text-sm text-muted">{title}</div>
          <div className="mt-1 text-xl font-bold text-ink">{loading ? '…' : value}</div>
        </div>
        <span className={['grid h-9 w-9 place-items-center rounded-lg', TINTS[tint] ?? TINTS.blue].join(' ')}>
          {icon}
        </span>
      </div>
      {sub && <div className="mt-2 truncate text-[11px] text-muted">{sub}</div>}
    </div>
  )
}

function Card({ title, children }: { title: string; children: ReactNode }) {
  return (
    <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
      <div className="mb-3 flex items-center gap-2 text-sm font-semibold text-ink">
        <TrendingUp size={16} className="text-brand" />
        {title}
      </div>
      {children}
    </div>
  )
}

function SimpleTable({ head, rows, empty }: { head: string[]; rows: string[][]; empty: string }) {
  if (rows.length === 0) return <Empty>{empty}</Empty>
  return (
    <div className="overflow-x-auto">
      <table className="w-full text-sm">
        <thead>
          <tr className="text-left text-xs text-muted">
            {head.map((h) => (
              <th key={h} className="pb-2 font-medium">
                {h}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100">
          {rows.map((r, i) => (
            <tr key={i} className="text-ink">
              {r.map((c, j) => (
                <td key={j} className="py-2 pr-2">
                  {c}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

function Empty({ children }: { children: ReactNode }) {
  return <div className="grid h-24 place-items-center text-sm text-muted">{children}</div>
}
