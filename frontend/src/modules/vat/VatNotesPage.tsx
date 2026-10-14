import { ResourceListPage } from '../../shared/ui/ResourceListPage'
import { Vat, type VatNote } from '../../shared/lib/resources'

export function VatNotesPage() {
  return (
    <ResourceListPage<VatNote>
      title="Mushak 9.1 Note Numbers"
      subtitle="Statutory note catalogue — verify codes against the official Mushak 9.1 form"
      queryKey="vat-notes"
      fetchList={Vat.notes}
      createFn={(b) => Vat.createNote(b)}
      columns={[
        { key: 'code', label: 'Note' },
        { key: 'title', label: 'Title' },
        { key: 'subform', label: 'Subform' },
        { key: 'verification_status', label: 'Verification' },
      ]}
      fields={[
        { name: 'code', label: 'Note Number (e.g. 6.3)', required: true },
        { name: 'title', label: 'Title', required: true },
        { name: 'subform', label: 'Subform' },
        {
          name: 'verification_status',
          label: 'Verification',
          type: 'select',
          default: 'requires_review',
          options: [
            { value: 'requires_review', label: 'Requires review' },
            { value: 'verified', label: 'Verified (by practitioner)' },
          ],
        },
      ]}
    />
  )
}
