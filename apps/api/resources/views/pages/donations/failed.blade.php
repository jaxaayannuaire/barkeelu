@extends('layouts.public')
@section('title', 'Paiement non finalisé — Barkeelu')
@section('robots', 'noindex,nofollow')
@section('content')
<section class="mx-auto max-w-md px-5 py-10">
    <p class="text-sm font-semibold text-danger">Paiement non finalisé</p>
    <h1 class="mt-3 text-3xl font-semibold">Votre paiement n’a pas été finalisé</h1>
    <p class="mt-5 text-muted">Le serveur a confirmé l’échec de cette tentative.</p>
    <form class="mt-6" method="post" action="{{ route('donations.retry.checkout', [$checkout->campaign->slug, $checkout->public_id]) }}">
        @csrf
        <button class="min-h-11 w-full rounded-[10px] bg-brand-primary px-4 font-semibold text-white">Réessayer le paiement</button>
    </form>
</section>
@endsection
