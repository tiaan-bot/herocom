<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title inertia>{{ config('app.name', 'Herocom Distribution') }}</title>

    <link rel="icon" type="image/png" href="/images/brand/nexus-mark.png">
    <link rel="apple-touch-icon" href="/images/brand/nexus-mark.png">


    {{-- Preload the marketing hero display font (Smooth Circulars). Aspira + the
         rest are self-hosted via @font-face in the marketing CSS scope. --}}
    <link rel="preload" href="/fonts/marketing/smooth-circulars.otf" as="font" type="font/otf" crossorigin>

    @vite(['resources/css/app.css', 'resources/js/app.ts'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
