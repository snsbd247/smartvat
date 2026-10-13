import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { FileCheck } from 'lucide-react'
import { Banner, Button, PageHeader, Table } from '../../shared/ui/primitives'
import { Vds } from '../../shared/lib/resources'

const money = (v: string) => Number(v).toLocaleString(undefined, { maximumFractionDigits: 2 })

export function VdsRegisterPage() {
  const qc = useQueryClient()
  const { data: rows } = useQuery({ queryKey: ['vds-deductions'], queryFn: Vds.deductions })
  const [msg, setMsg] = useState<string | null>(null)

  const cert = useMutation({
    mutationFn: (id: number) => Vds.certificate(id),
    onSuccess: (r: unknown) => { setMsg(`Certificate ${(r as { certificate_no?: string }).certificate_no ?? ''} issued.`); qc.invalidateQueries({ queryKey: ['vds-deductions'] }) },
  })

  return (
    <div>
      <PageHeader title="VDS Register" subtitle="All deductions — issue certificates" />
      {msg && <Banner kind="success">{msg}</Banner>}
      <Table head={['Deduction No', 'Date', 'Category', 'Base', 'Rate %', 'VDS', 'Status', '']}>
        {(rows ?? []).map((d) => (
          <tr key={d.id} className="text-ink">
            <td className="px-4 py-2.5">{d.deduction_no}</td>
            <td className="px-4 py-2.5">{d.date}</td>
            <td className="px-4 py-2.5">{d.service_category}</td>
            <td className="px-4 py-2.5 text-right">{money(d.base_amount)}</td>
            <td className="px-4 py-2.5 text-right">{d.rate}</td>
            <td className="px-4 py-2.5 text-right">{money(d.vds_amount)}</td>
            <td className="px-4 py-2.5">{d.status}</td>
            <td className="px-4 py-2.5">
              <Button variant="ghost" onClick={() => cert.mutate(d.id)}><FileCheck size={14} /> Certificate</Button>
            </td>
          </tr>
        ))}
        {(rows ?? []).length === 0 && <tr><td colSpan={8} className="px-4 py-8 text-center text-muted">No deductions yet.</td></tr>}
      </Table>
    </div>
  )
}
