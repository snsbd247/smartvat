import { ResourceListPage } from '../../shared/ui/ResourceListPage'
import { Suppliers, type Party } from '../../shared/lib/resources'

export function SuppliersPage() {
  return (
    <ResourceListPage<Party>
      title="Suppliers"
      subtitle="Vendor master for purchases"
      queryKey="suppliers"
      fetchList={Suppliers.list}
      createFn={(b) => Suppliers.create(b)}
      columns={[
        { key: 'code', label: 'Code' },
        { key: 'name', label: 'Name' },
        { key: 'status', label: 'Status' },
      ]}
      fields={[
        { name: 'code', label: 'Code', required: true },
        { name: 'name', label: 'Name', required: true },
        { name: 'phone', label: 'Phone' },
        { name: 'email', label: 'Email' },
      ]}
    />
  )
}
