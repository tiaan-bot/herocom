<h2>Signatories</h2>
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
