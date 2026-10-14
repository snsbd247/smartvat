import { ResourceListPage } from '../../shared/ui/ResourceListPage'
import { Accounts, type Account } from '../../shared/lib/resources'

export function ChartOfAccountsPage() {
  return (
    <ResourceListPage<Account>
      title="Chart of Accounts"
      subtitle="General ledger accounts"
      queryKey="accounts"
      fetchList={Accounts.list}
      createFn={(b) => Accounts.create(b)}
      columns={[
        { key: 'code', label: 'Code' },
        { key: 'name', label: 'Name' },
        { key: 'type', label: 'Type' },
        { key: 'normal_balance', label: 'Normal' },
      ]}
      fields={[
        { name: 'code', label: 'Code', required: true },
        { name: 'name', label: 'Name', required: true },
        {
          name: 'type',
          label: 'Type',
          type: 'select',
          required: true,
          options: ['asset', 'liability', 'equity', 'income', 'expense'].map((v) => ({ value: v, label: v })),
        },
      ]}
    />
  )
}
