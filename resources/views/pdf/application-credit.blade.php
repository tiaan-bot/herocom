@extends('pdf.layout')

@section('title', 'Credit Reseller Application Form — Credit')

@section('content')
    @php($cgic = json_decode($application->cgic_payload ?? '{}', true) ?: [])
    @php($banking = $cgic['banking'] ?? [])

    <table class="kv"><tr><td class="k">Account type</td><td>Credit account</td></tr></table>

    @include('pdf.partials.company')

    <h2>Credit requested</h2>
    <table class="kv">
        <tr><td class="k">Credit limit requested</td><td>{{ $application->credit_limit_requested_currency }} {{ number_format((float) $application->credit_limit_requested, 2) }}</td></tr>
        <tr><td class="k">Payment terms</td><td>{{ $application->credit_terms_requested_days ? $application->credit_terms_requested_days.' days' : '—' }}</td></tr>
        <tr><td class="k">Annual turnover</td><td>{{ $application->annual_turnover_band ? str($application->annual_turnover_band->value)->headline() : '—' }}</td></tr>
    </table>

    <h2>Banking details</h2>
    <table class="kv">
        <tr><td class="k">Bank</td><td>{{ $banking['bank'] ?? '—' }}</td></tr>
        <tr><td class="k">Account name</td><td>{{ $banking['account_name'] ?? '—' }}</td></tr>
        <tr><td class="k">Account number</td><td>{{ $banking['account_number'] ?? '—' }}</td></tr>
        <tr><td class="k">Branch code</td><td>{{ $banking['branch_code'] ?? '—' }}</td></tr>
    </table>

    <h2>Principals &amp; sureties</h2>
    <table class="data">
        <thead>
            <tr><th>Name</th><th>ID number</th><th>Shareholding</th><th>Surety</th></tr>
        </thead>
        <tbody>
            @forelse ($application->principals as $principal)
                <tr>
                    <td>{{ $principal->full_name }} {{ $principal->surname }}</td>
                    <td>{{ $principal->id_number }}</td>
                    <td>{{ $principal->shareholding_percent !== null ? $principal->shareholding_percent.'%' : '—' }}</td>
                    <td>{{ $principal->is_surety ? 'Yes' : 'No' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">No principals captured.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="note">Each principal signs the Deed of Suretyship separately; the signature below is the applicant signatory's.</p>

    @include('pdf.partials.signature')

    @include('pdf.partials.terms')
@endsection
