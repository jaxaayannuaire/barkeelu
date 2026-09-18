@extends('layouts.public')
@section('title', 'Vérification du paiement — Barkeelu')
@section('robots', 'noindex,nofollow')
@section('content')
<section class="mx-auto max-w-md px-5 py-10" data-payment-status data-status-url="{{ route('donations.status.checkout', [$checkout->campaign->slug, $checkout->public_id]) }}" data-success-url="{{ route('donations.thanks.checkout', [$checkout->campaign->slug, $checkout->public_id]) }}" data-error-url="{{ route('donations.failed.checkout', [$checkout->campaign->slug, $checkout->public_id]) }}">
    <p class="text-sm font-semibold text-brand-primary">Vérification serveur · Sécurisé</p>
    <h1 class="mt-3 text-3xl font-semibold">Vérification du paiement</h1>
    @if ($payment->status->value === 'UNKNOWN')
        <p class="mt-5 text-muted" aria-live="polite">Vérification du paiement en cours. Ne relancez pas le paiement tant que cet état n’est pas résolu.</p>
        <form class="mt-6" method="post" action="{{ route('donations.verify.checkout', [$checkout->campaign->slug, $checkout->public_id]) }}">
            @csrf
            <button class="min-h-11 w-full rounded-[10px] border border-border px-4 font-semibold">Vérifier le statut</button>
        </form>
    @elseif ($payment->status->value === 'PAID')
        <p class="mt-5 text-muted">Paiement confirmé par le serveur.</p>
        <a class="mt-6 inline-flex min-h-11 items-center rounded-[10px] bg-brand-primary px-4 font-semibold text-white" href="{{ route('donations.thanks.checkout', [$checkout->campaign->slug, $checkout->public_id]) }}">Voir la confirmation</a>
    @elseif ($payment->status->value === 'FAILED')
        <p class="mt-5 text-muted">Le paiement a échoué.</p>
        <a class="mt-6 inline-flex min-h-11 items-center rounded-[10px] bg-brand-primary px-4 font-semibold text-white" href="{{ route('donations.failed.checkout', [$checkout->campaign->slug, $checkout->public_id]) }}">Voir les options</a>
    @else
        <p class="mt-5 text-muted" aria-live="polite">Le paiement est en cours de vérification par le serveur.</p>
    @endif
    <p class="mt-5 text-sm tabular-nums text-muted">État : {{ $checkout->status->value }}</p>
</section>
@endsection
