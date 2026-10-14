import { ResourceListPage } from '../../shared/ui/ResourceListPage'
import { CostCenters, type CostCenter } from '../../shared/lib/resources'

export function CostCentersPage() {
  return (
    <ResourceListPage<CostCenter>
      title="Cost Centres"
      subtitle="Cost centres for accounting dimension tagging"
      queryKey="cost-centers"
      fetchList={CostCenters.list}
      createFn={(b) => CostCenters.create(b)}
      columns={[
        { key: 'code', label: 'Code' },
        { key: 'name', label: 'Name' },
      ]}
      fields={[
        { name: 'code', label: 'Code', required: true },
        { name: 'name', label: 'Name', required: true },
      ]}
    />
  )
}
