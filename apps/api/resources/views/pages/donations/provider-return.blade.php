@extends('layouts.public')
@section('title', $state['title'].' — Barkeelu')
@section('robots', 'noindex,nofollow')
@section('content')
<section class="mx-auto max-w-md px-5 py-10" data-provider-return-state="{{ $state['name'] }}">
    <p class="text-sm font-semibold text-brand-primary">Barkeelu</p>
    <h1 class="mt-3 text-3xl font-semibold">{{ $state['title'] }}</h1>
    <p class="mt-5 text-muted">{{ $state['message'] }}</p>
    <a class="mt-7 inline-flex min-h-11 items-center rounded-[10px] bg-brand-primary px-4 font-semibold text-white" href="{{ route('campaigns.show', $campaign->slug) }}">Retour à la collecte</a>
</section>
@endsection
