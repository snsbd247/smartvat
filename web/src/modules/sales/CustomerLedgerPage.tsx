import { PartyLedgerPage } from '../../shared/ui/PartyLedgerPage'
import { Customers, Sales } from '../../shared/lib/resources'

export function CustomerLedgerPage() {
  return (
    <PartyLedgerPage
      title="Customer Ledger"
      label="Customer"
      fetchParties={Customers.list}
      fetchLedger={(id) => Sales.ledger(id)}
    />
  )
}
