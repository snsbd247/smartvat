import { useQuery } from '@tanstack/react-query'
import { ResourceListPage } from '../../shared/ui/ResourceListPage'
import { Products, Settings, type Product } from '../../shared/lib/resources'

const FALLBACK_UOM = ['PCS', 'KG', 'GM', 'LTR', 'MTR', 'BOX', 'SET', 'DOZ']

export function ProductsPage() {
  // Units of measure come from the Master Data → Units of Measure list when set.
  const { data: uomPayload } = useQuery({ queryKey: ['settings', 'master.uom'], queryFn: () => Settings.get<{ items?: { code?: string; name?: string }[] }>('master.uom') })
  const uomItems = (uomPayload as { items?: { code?: string; name?: string }[] })?.items ?? []
  const codes = uomItems.map((i) => i.code).filter(Boolean) as string[]
  const uomOptions = (codes.length ? codes : FALLBACK_UOM).map((c) => ({ value: c, label: c }))

  return (
    <ResourceListPage<Product>
      title="Products"
      subtitle="Products & SKUs for the active company"
      queryKey="products"
      fetchList={Products.list}
      createFn={(b) => Products.create(b)}
      columns={[
        { key: 'code', label: 'Code' },
        { key: 'name', label: 'Name' },
        { key: 'type', label: 'Type' },
        { key: 'base_uom', label: 'UoM' },
        { key: 'costing_method', label: 'Costing' },
        { key: 'status', label: 'Status' },
      ]}
      fields={[
        { name: 'code', label: 'Code', required: true },
        { name: 'name', label: 'Name', required: true },
        {
          name: 'type',
          label: 'Type',
          type: 'select',
          default: 'raw',
          options: ['raw', 'finished', 'packaging', 'consumable', 'spare', 'service'].map((v) => ({ value: v, label: v })),
        },
        { name: 'base_uom', label: 'Base UoM', type: 'select', default: uomOptions[0]?.value ?? 'PCS', options: uomOptions },
        {
          name: 'costing_method',
          label: 'Costing',
          type: 'select',
          default: 'weighted_average',
          options: [{ value: 'weighted_average', label: 'Weighted Average' }],
        },
      ]}
    />
  )
}
