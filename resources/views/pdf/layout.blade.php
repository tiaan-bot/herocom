<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: "DejaVu Sans", sans-serif; }
        body { color: #191320; font-size: 11px; margin: 0; }
        .wrap { padding: 0 6px 40px; }
        header { border-bottom: 2px solid #733DA0; padding-bottom: 8px; margin-bottom: 12px; }
        header .mark { height: 32px; }
        header .name { font-size: 15px; font-weight: bold; color: #733DA0; }
        h1 { font-size: 15px; color: #733DA0; margin: 8px 0 0; }
        .meta { color: #79747f; font-size: 10px; margin-top: 2px; }
        h2 { font-size: 11px; color: #733DA0; border-bottom: 1px solid #e9e6ee; padding-bottom: 3px; margin: 14px 0 5px; text-transform: uppercase; letter-spacing: .04em; }
        table.kv { width: 100%; border-collapse: collapse; }
        table.kv td { padding: 2px 6px; vertical-align: top; }
        table.kv td.k { color: #56505d; width: 36%; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.data th, table.data td { border: 1px solid #e9e6ee; padding: 4px 6px; text-align: left; font-size: 10px; }
        table.data th { background: #f3f1f6; color: #56505d; }
        .sig { margin-top: 6px; }
        .sig__img { height: 72px; border-bottom: 1px solid #191320; display: inline-block; padding: 0 12px; }
        .sig__line { border-bottom: 1px solid #191320; width: 240px; height: 72px; }
        .muted { color: #79747f; }
        .note { color: #79747f; font-style: italic; }
        footer { position: fixed; bottom: -16px; left: 0; right: 0; font-size: 8px; color: #79747f; border-top: 1px solid #e9e6ee; padding-top: 4px; }

        /* Standard Terms & Conditions of Sale — starts on a fresh page. */
        .terms { page-break-before: always; }
        .terms__intro { color: #79747f; font-size: 9.5px; margin: 0 0 8px; }
        .terms .clause { margin-top: 9px; }
        .terms .clause > .h { font-weight: bold; color: #191320; }
        .terms .clause p { margin: 3px 0; line-height: 1.4; text-align: justify; }
        .terms .sub { margin: 2px 0 2px 16px; line-height: 1.4; text-align: justify; }
        .terms .sub .n { display: inline-block; min-width: 26px; color: #56505d; }
        .terms .subh { font-weight: bold; margin: 5px 0 2px 16px; }
        .terms ul { margin: 3px 0 3px 34px; padding: 0; }
        .terms li { line-height: 1.4; margin: 2px 0; text-align: justify; }

        /* Consent blocks */
        .consent p { margin: 3px 0; line-height: 1.4; text-align: justify; font-size: 10px; }
        .consent ul { margin: 3px 0 3px 22px; }
        .consent li { line-height: 1.4; margin: 2px 0; text-align: justify; font-size: 10px; }
    </style>
</head>
<body>
    <footer>
        Herocom Distribution (Pty) Ltd &middot; 087 551 1485 &middot; sales@herocom.co.za &middot; 15 Desi Street, Middelburg, 1050<br>
        Your information is processed in line with POPIA.
    </footer>

    <div class="wrap">
        <header>
            @if (! empty($wordmark))
                <img class="mark" src="{{ $wordmark }}" alt="Herocom Distribution">
            @else
                <span class="name">Herocom Distribution</span>
            @endif
            <h1>@yield('title')</h1>
            <div class="meta">Reference: {{ $application->uuid }} &middot; Submitted {{ optional($application->submitted_at)->format('d F Y') ?? '—' }}</div>
        </header>

        @yield('content')
    </div>
</body>
</html>
