@php($company = $application->company)
<h2>Company details</h2>
<table class="kv">
    <tr><td class="k">Legal / registered name</td><td>{{ $company->legal_name }}</td></tr>
    <tr><td class="k">Trading name</td><td>{{ $company->trading_name ?: '—' }}</td></tr>
    <tr><td class="k">Entity type</td><td>{{ str($company->entity_type->value)->headline() }}</td></tr>
    <tr><td class="k">Registration number</td><td>{{ $company->registration_number ?: '—' }}</td></tr>
    <tr><td class="k">VAT number</td><td>{{ $company->vat_number ?: '—' }}</td></tr>
    <tr><td class="k">Nature of business</td><td>{{ $company->nature_of_business ?: '—' }}</td></tr>
    <tr><td class="k">Physical address</td><td>
        {{ $company->address_line1 }}@if ($company->address_line2), {{ $company->address_line2 }}@endif,
        {{ $company->city }}, {{ $company->province }}, {{ $company->postal_code }}, {{ $company->country_code }}
    </td></tr>
</table>

<h2>Applicant / account owner</h2>
<table class="kv">
    <tr><td class="k">Contact name</td><td>{{ $application->contact_name }}</td></tr>
    <tr><td class="k">Email</td><td>{{ $application->contact_email }}</td></tr>
    <tr><td class="k">Phone</td><td>{{ $application->contact_phone }}</td></tr>
    <tr><td class="k">Premises</td><td>{{ $application->premises_owned ? 'Owned' : 'Leased' }}</td></tr>
    @unless ($application->premises_owned)
        <tr><td class="k">Landlord</td><td>{{ $application->landlord_name ?: '—' }} &middot; {{ $application->landlord_tel ?: '—' }}</td></tr>
    @endunless
</table>
