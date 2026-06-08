@extends('pdf.layout')

@section('title', 'Credit Reseller Application Form — Credit')

@section('content')
    @php($cgic = json_decode($application->cgic_payload ?? '{}', true) ?: [])
    @php($banking = $cgic['banking'] ?? [])
    @php($disclosures = $cgic['disclosures'] ?? [])

    <table class="kv"><tr><td class="k">Account type</td><td>Credit account</td></tr></table>

    @include('pdf.partials.company')

    <h2>Proprietors / directors / members / partners</h2>
    @forelse ($application->principals as $i => $principal)
        <table class="kv" @if (! $loop->first) style="margin-top:6px" @endif>
            <tr><td class="k">{{ $i + 1 }}. Full name</td><td>{{ $principal->full_name }}</td></tr>
            <tr><td class="k">Surname</td><td>{{ $principal->surname }}</td></tr>
            <tr><td class="k">ID number</td><td>{{ $principal->id_number }}</td></tr>
            <tr><td class="k">Shareholding %</td><td>{{ $principal->shareholding_percent !== null ? $principal->shareholding_percent.'%' : '—' }}</td></tr>
            <tr><td class="k">Residential address</td><td>{{ $principal->residential_address_line1 ?: '—' }}@if ($principal->residential_address_line2), {{ $principal->residential_address_line2 }}@endif@if ($principal->residential_city), {{ $principal->residential_city }}@endif</td></tr>
            <tr><td class="k">Province</td><td>{{ $principal->residential_province ?: '—' }}</td></tr>
            <tr><td class="k">Postal code</td><td>{{ $principal->residential_postal_code ?: '—' }}</td></tr>
        </table>
    @empty
        <p class="muted">No principals captured.</p>
    @endforelse

    <h2>Banking details</h2>
    <table class="kv">
        <tr><td class="k">Financial institution</td><td>{{ $banking['bank'] ?? '—' }}</td></tr>
        <tr><td class="k">Date account opened</td><td>{{ ! empty($banking['date_opened']) ? \Illuminate\Support\Carbon::parse($banking['date_opened'])->format('d F Y') : '—' }}</td></tr>
        <tr><td class="k">Branch name</td><td>{{ $banking['branch_name'] ?? '—' }}</td></tr>
        <tr><td class="k">Branch code</td><td>{{ $banking['branch_code'] ?? '—' }}</td></tr>
        <tr><td class="k">Account type</td><td>{{ ! empty($banking['account_type']) ? str($banking['account_type'])->headline() : '—' }}</td></tr>
        <tr><td class="k">Account number</td><td>{{ $banking['account_number'] ?? '—' }}</td></tr>
        <tr><td class="k">Account name</td><td>{{ $banking['account_name'] ?? '—' }}</td></tr>
    </table>

    <h2>Trade references</h2>
    <table class="data">
        <thead>
            <tr><th>Company name</th><th>Credit limit</th><th>Account held</th><th>Terms</th></tr>
        </thead>
        <tbody>
            @forelse ($application->tradeReferences as $ref)
                <tr>
                    <td>{{ $ref->company_name }}</td>
                    <td>{{ $ref->credit_limit !== null ? 'R '.number_format((float) $ref->credit_limit, 2) : '—' }}</td>
                    <td>{{ str($ref->account_held->value)->upper() }}</td>
                    <td>{{ $ref->terms_days ? $ref->terms_days.' days' : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">No trade references captured.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Credit requirements</h2>
    <table class="kv">
        <tr><td class="k">Credit limit required</td><td>{{ $application->credit_limit_requested_currency }} {{ number_format((float) $application->credit_limit_requested, 2) }}</td></tr>
        <tr><td class="k">Payment terms</td><td>{{ $application->credit_terms_requested_days ? $application->credit_terms_requested_days.' days' : '—' }}</td></tr>
    </table>

    <h2>Company turnover</h2>
    <table class="kv">
        <tr><td class="k">Annual turnover</td><td>
            @switch(optional($application->annual_turnover_band)->value)
                @case('under_2m') Less than R2,000,000 @break
                @case('over_2m') More than R2,000,000 @break
                @default —
            @endswitch
        </td></tr>
    </table>

    <h2>Security / legal compliance</h2>
    <table class="kv">
        <tr><td class="k">Sureties, cessions or notarial bonds</td><td>{{ ($disclosures['sureties_cessions'] ?? false) ? 'Yes' : 'No' }}</td></tr>
        <tr><td class="k">Judgements against the business or principals</td><td>{{ ($disclosures['judgements'] ?? false) ? 'Yes' : 'No' }}</td></tr>
        <tr><td class="k">Liquidations / business rescue</td><td>{{ ($disclosures['liquidations'] ?? false) ? 'Yes' : 'No' }}</td></tr>
        <tr><td class="k">Current payment moratoriums</td><td>{{ ($disclosures['moratoriums'] ?? false) ? 'Yes' : 'No' }}</td></tr>
    </table>

    <h2>Account contact person</h2>
    <table class="kv">
        <tr><td class="k">Name</td><td>{{ $application->account_contact_name ?: $application->contact_name }}</td></tr>
        <tr><td class="k">Email</td><td>{{ $application->account_contact_email ?: $application->contact_email }}</td></tr>
        <tr><td class="k">Telephone</td><td>{{ $application->account_contact_phone ?: ($application->contact_phone ?: '—') }}</td></tr>
    </table>

    @include('pdf.partials.credit-consent')

    @include('pdf.partials.signature')

    @include('pdf.partials.terms')
@endsection
