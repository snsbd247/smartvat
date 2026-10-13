import { ResourceListPage } from '../../shared/ui/ResourceListPage'
import { Factories, type Factory } from '../../shared/lib/resources'

export function FactoriesPage() {
  return (
    <ResourceListPage<Factory>
      title="Factories"
      subtitle="Factories & branches for the active company"
      queryKey="factories"
      fetchList={Factories.list}
      createFn={(b) => Factories.create(b)}
      columns={[
        { key: 'code', label: 'Code' },
        { key: 'name', label: 'Name' },
        { key: 'address', label: 'Address' },
        { key: 'status', label: 'Status' },
      ]}
      fields={[
        { name: 'code', label: 'Code', required: true },
        { name: 'name', label: 'Name', required: true },
        { name: 'address', label: 'Address' },
      ]}
    />
  )
}
