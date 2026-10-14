import { PartyLedgerPage } from '../../shared/ui/PartyLedgerPage'
import { PurchaseApi, Suppliers } from '../../shared/lib/resources'

export function SupplierLedgerPage() {
  return (
    <PartyLedgerPage
      title="Supplier Ledger"
      label="Supplier"
      fetchParties={Suppliers.list}
      fetchLedger={(id) => PurchaseApi.ledger(id)}
    />
  )
}
