@extends('layouts.public')
@section('title', 'Merci — Barkeelu')
@section('robots', 'noindex,nofollow')
@section('content')
<section class="mx-auto max-w-md px-5 py-10">
    <p class="text-sm font-semibold text-success">Paiement confirmé</p>
    <h1 class="mt-3 text-3xl font-semibold">Merci pour votre soutien</h1>
    <p class="mt-5 text-muted">Votre don de {{ number_format($donation->nominal_amount, 0, ',', ' ') }} FCFA a été confirmé par le serveur.</p>
    <a class="mt-7 inline-flex min-h-11 items-center rounded-[10px] bg-brand-primary px-4 font-semibold text-white" href="{{ route('campaigns.show', $checkout->campaign->slug) }}">Retour vers la collecte</a>
</section>
@endsection
