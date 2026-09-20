@extends('layouts.public')
@section('title', 'Paiement non finalisé — Barkeelu')
@section('robots', 'noindex,nofollow')
@section('content')
<section class="mx-auto max-w-md px-5 py-10">
    @php
        $message = $checkout->status === \App\Enums\CheckoutStatus::EXPIRED
            ? 'Cette session de paiement a expiré.'
            : ($checkout->status === \App\Enums\CheckoutStatus::CANCELLED
                ? 'Cette session de paiement a été annulée.'
            : match ($payment?->status) {
            \App\Enums\PaymentStatus::EXPIRED => 'Cette session de paiement a expiré.',
            \App\Enums\PaymentStatus::CANCELLED => 'Cette session de paiement a été annulée.',
            default => 'Le serveur a confirmé l’échec de cette tentative.',
        });
    @endphp
    <p class="text-sm font-semibold text-danger">Paiement non finalisé</p>
    <h1 class="mt-3 text-3xl font-semibold">Votre paiement n’a pas été finalisé</h1>
    <p class="mt-5 text-muted">{{ $message }}</p>
    @if ($payment?->status === \App\Enums\PaymentStatus::FAILED)
        <form class="mt-6" method="post" action="{{ route('donations.retry.checkout', [$checkout->campaign->slug, $checkout->public_id]) }}">
            @csrf
            <button class="min-h-11 w-full rounded-[10px] bg-brand-primary px-4 font-semibold text-white">Réessayer le paiement</button>
        </form>
    @endif
</section>
@endsection
