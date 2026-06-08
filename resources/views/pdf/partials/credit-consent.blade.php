{{-- Credit consent — verbatim from CONSENT_BLOCK_TEXT.md. --}}
<h2>Consent</h2>
<div class="consent">
    <p>I/We warrant that all information provided in this application is true and correct. I/We confirm that this application is signed of my/our own free will, with full knowledge and understanding of its contents, and that I/We am/are duly authorized to do so.</p>
    <p>I/We voluntarily and unconditionally consent to the processing of the submitted personal information for all purposes related to this agreement, including credit enquiries. Such personal information may be processed by, or on behalf of, either party for the purposes set out in this application. Herocom Distribution undertakes to ensure that any personal information processed in the course of fulfilling its obligations under this agreement is handled in compliance with the Protection of Personal Information Act (POPI).</p>
    <p>I/We further acknowledge and accept that all transactions undertaken with the company shall be strictly and exclusively subject to Herocom Distribution's Terms and Conditions of Sale. By my/our signature(s), I/We confirm that I/We have reviewed, understood, and accepted the terms and conditions, and that these terms and conditions form an integral part of this application.</p>
    <p>I/We undertake to comply with the credit terms offered by Herocom Distribution and accept full responsibility for this commitment.</p>
    <p>The Customer specifically consents that Herocom Distribution:</p>
    <ul>
        <li>May conduct credit enquiries in respect of the Customer;</li>
        <li>May access the database of any Risk Information Agency prior to granting credit;</li>
        <li>May, where credit is granted, transmit details of the Customer's performance under the account to a Risk Information Agency, and share such information with other agencies for purposes including further credit assessments, debt tracing, debt collection, and fraud prevention;</li>
        <li>May record the Customer's default with a Risk Information Agency if the Customer fails to meet its financial commitments;</li>
        <li>May refer information relating to the Customer's credit performance to a Risk Information Agency for banking and credit assessment, statistical analysis, and credit scoring, and use such information to identify products (including third-party products) relevant to the Customer;</li>
        <li>Will charge interest on 30-day credit accounts not settled by the 1st of each month. Interest at 2.36% will be applied to finance accounts after 30 days;</li>
        <li>May record the existence of the Customer's account with Herocom Distribution at one or more Risk Information Agencies.</li>
    </ul>
    <table class="kv">
        <tr><td class="k">Terms accepted</td><td>{{ optional($application->terms_accepted_at)->format('d F Y') ?? '—' }} (version {{ $application->terms_version }})</td></tr>
        <tr><td class="k">POPIA consent</td><td>{{ optional($application->popia_consent_at)->format('d F Y') ?? '—' }}</td></tr>
        <tr><td class="k">Credit enquiry consent</td><td>{{ optional($application->credit_enquiry_consent_at)->format('d F Y') ?? '—' }}</td></tr>
    </table>
</div>
