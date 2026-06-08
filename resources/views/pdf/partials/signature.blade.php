<h2>Declaration &amp; signature</h2>
<p class="muted">
    The signatory declares that the information in this application is true and complete, accepts the Standard Terms
    &amp; Conditions of Sale (version {{ $application->terms_version }}), and consents to processing in line with POPIA.
</p>
<table class="kv">
    <tr><td class="k">Terms accepted</td><td>{{ optional($application->terms_accepted_at)->format('d F Y') ?? '—' }}</td></tr>
    <tr><td class="k">POPIA consent</td><td>{{ optional($application->popia_consent_at)->format('d F Y') ?? '—' }}</td></tr>
    @if ($application->credit_enquiry_consent_at)
        <tr><td class="k">Credit enquiry consent</td><td>{{ $application->credit_enquiry_consent_at->format('d F Y') }}</td></tr>
    @endif
</table>

<div class="sig">
    @if (! empty($signature))
        <img class="sig__img" src="{{ $signature }}" alt="Signature">
    @else
        <div class="sig__line"></div>
    @endif
    <table class="kv" style="margin-top:4px">
        <tr><td class="k">Signed by</td><td>{{ $application->signed_by_name ?: '—' }}</td></tr>
        <tr><td class="k">Capacity</td><td>{{ $application->signed_by_capacity ?: '—' }}</td></tr>
        <tr><td class="k">Date</td><td>{{ optional($application->signed_at)->format('d F Y') ?? '—' }}</td></tr>
    </table>
</div>
