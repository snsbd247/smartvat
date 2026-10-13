import { ResourceListPage } from '../../shared/ui/ResourceListPage'
import { Products, type Product } from '../../shared/lib/resources'

export function ProductsPage() {
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
        { name: 'base_uom', label: 'Base UoM', default: 'PCS' },
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
