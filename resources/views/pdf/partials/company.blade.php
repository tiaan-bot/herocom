@php($company = $application->company)
<h2>Company details</h2>
<table class="kv">
    <tr><td class="k">Registered name</td><td>{{ $company->legal_name }}</td></tr>
    <tr><td class="k">Trading name</td><td>{{ $company->trading_name ?: '—' }}</td></tr>
    <tr><td class="k">Entity type</td><td>{{ str($company->entity_type->value)->headline() }}</td></tr>
    <tr><td class="k">Registration number</td><td>{{ $company->registration_number ?: '—' }}</td></tr>
    <tr><td class="k">Date of registration</td><td>{{ optional($company->date_of_registration)->format('d F Y') ?? '—' }}</td></tr>
    <tr><td class="k">VAT number</td><td>{{ $company->vat_number ?: '—' }}</td></tr>
    <tr><td class="k">Nature of business</td><td>{{ $company->nature_of_business ?: '—' }}</td></tr>
    <tr><td class="k">Physical address</td><td>{{ $company->address_line1 }}@if ($company->address_line2), {{ $company->address_line2 }}@endif</td></tr>
    <tr><td class="k">Physical &mdash; city / province</td><td>{{ $company->city }}, {{ $company->province }}</td></tr>
    <tr><td class="k">Physical &mdash; postal code</td><td>{{ $company->postal_code }}</td></tr>
    <tr><td class="k">Postal address</td><td>{{ $company->postal_address_line1 ?: '—' }}</td></tr>
    <tr><td class="k">Postal &mdash; province</td><td>{{ $company->postal_province ?: '—' }}</td></tr>
    <tr><td class="k">Postal &mdash; postal code</td><td>{{ $company->postal_postal_code ?: '—' }}</td></tr>
    <tr><td class="k">Telephone</td><td>{{ $company->telephone ?: '—' }}</td></tr>
    <tr><td class="k">Fax</td><td>{{ $company->fax ?: '—' }}</td></tr>
    <tr><td class="k">Main contact person</td><td>{{ $application->contact_name }}</td></tr>
    <tr><td class="k">Email</td><td>{{ $application->contact_email }}</td></tr>
    <tr><td class="k">Contact telephone</td><td>{{ $application->contact_phone ?: '—' }}</td></tr>
    <tr><td class="k">Premises owned</td><td>{{ $application->premises_owned ? 'Yes' : 'No' }}</td></tr>
    <tr><td class="k">Landlord name</td><td>{{ $application->landlord_name ?: '—' }}</td></tr>
    <tr><td class="k">Landlord address</td><td>{{ $application->landlord_address ?: '—' }}</td></tr>
    <tr><td class="k">Landlord telephone</td><td>{{ $application->landlord_tel ?: '—' }}</td></tr>
</table>
