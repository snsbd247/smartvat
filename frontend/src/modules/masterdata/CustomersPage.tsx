import { ResourceListPage } from '../../shared/ui/ResourceListPage'
import { Customers, type Party } from '../../shared/lib/resources'

export function CustomersPage() {
  return (
    <ResourceListPage<Party>
      title="Customers"
      subtitle="Customer master for sales"
      queryKey="customers"
      fetchList={Customers.list}
      createFn={(b) => Customers.create(b)}
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
