{{-- COD consent — verbatim from CONSENT_BLOCK_TEXT.md. --}}
<h2>Consent</h2>
<div class="consent">
    <p>I/We warrant that all information provided in this application is true and correct. I/We further confirm that this application is signed of my/our own free will, with full knowledge and understanding of its contents, and that I/We am/are duly authorized to do so.</p>
    <p>I/We acknowledge and accept that all transactions undertaken with the company shall be strictly and exclusively subject to Herocom Distribution's Terms and Conditions of Sale. By my/our signature(s), I/We confirm that I/We have reviewed, understood, and accepted the terms and conditions, and that such terms and conditions form an integral part of this application.</p>
    <table class="kv">
        <tr><td class="k">Terms accepted</td><td>{{ optional($application->terms_accepted_at)->format('d F Y') ?? '—' }} (version {{ $application->terms_version }})</td></tr>
        <tr><td class="k">POPIA consent</td><td>{{ optional($application->popia_consent_at)->format('d F Y') ?? '—' }}</td></tr>
    </table>
</div>
