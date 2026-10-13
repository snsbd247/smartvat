import { ResourceListPage } from '../../shared/ui/ResourceListPage'
import { Warehouses, type Warehouse } from '../../shared/lib/resources'

export function WarehousesPage() {
  return (
    <ResourceListPage<Warehouse>
      title="Warehouses"
      subtitle="Storage locations (requires the inventory module)"
      queryKey="warehouses"
      fetchList={Warehouses.list}
      createFn={(b) => Warehouses.create(b)}
      columns={[
        { key: 'code', label: 'Code' },
        { key: 'name', label: 'Name' },
      ]}
      fields={[
        { name: 'code', label: 'Code', required: true },
        { name: 'name', label: 'Name', required: true },
        {
          name: 'type',
          label: 'Type',
          type: 'select',
          default: 'general',
          options: ['general', 'raw', 'finished'].map((v) => ({ value: v, label: v })),
        },
      ]}
    />
  )
}
