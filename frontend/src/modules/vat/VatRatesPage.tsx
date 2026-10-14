import { ResourceListPage } from '../../shared/ui/ResourceListPage'
import { Vat, type VatRate } from '../../shared/lib/resources'

export function VatRatesPage() {
  return (
    <ResourceListPage<VatRate>
      title="VAT Rates"
      subtitle="Configurable VAT rate master (verified per NBR rules before use)"
      queryKey="vat-rates"
      fetchList={Vat.rates}
      createFn={(b) => Vat.createRate(b)}
      columns={[
        { key: 'code', label: 'Code' },
        { key: 'rate', label: 'Rate %' },
        { key: 'effective_from', label: 'Effective From' },
      ]}
      fields={[
        { name: 'code', label: 'Code (e.g. STD15)', required: true },
        { name: 'rate', label: 'Rate %', required: true },
        { name: 'effective_from', label: 'Effective From (YYYY-MM-DD)' },
      ]}
    />
  )
}
