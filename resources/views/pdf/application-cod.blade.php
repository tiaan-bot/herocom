@extends('pdf.layout')

@section('title', 'Reseller Application Form — COD')

@section('content')
    <table class="kv"><tr><td class="k">Account type</td><td>COD (pay upfront)</td></tr></table>

    @include('pdf.partials.company')

    @include('pdf.partials.cod-consent')

    @include('pdf.partials.signature')

    @include('pdf.partials.terms')
@endsection
