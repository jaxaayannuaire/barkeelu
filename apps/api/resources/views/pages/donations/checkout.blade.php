@extends('layouts.public')
@section('title', 'Récapitulatif du don — Barkeelu')
@section('robots', 'noindex,nofollow')
@section('content')
<section class="mx-auto max-w-md px-5 py-8">
    <p class="text-sm font-semibold text-brand-primary">Étape 3 sur 3 · Sécurisé</p>
    <h1 class="mt-3 text-3xl font-semibold">Récapitulatif</h1>
    <p class="mt-2 text-sm text-muted">Parcours {{ $checkout->public_id }}</p>
    <dl class="mt-7 space-y-3 rounded-2xl border border-border p-5 tabular-nums">
        <div class="flex justify-between"><dt>Montant du don</dt><dd>{{ number_format($checkout->nominal_amount, 0, ',', ' ') }} FCFA</dd></div>
        @foreach (($checkout->fee_snapshot['fees'] ?? []) as $fee)
            <div class="flex justify-between"><dt>{{ \App\Support\FeeLabel::for($fee['fee_type'] ?? null) }}</dt><dd>{{ number_format((int) $fee['calculated_amount'], 0, ',', ' ') }} FCFA</dd></div>
        @endforeach
        <div class="flex justify-between border-t border-border pt-3 font-semibold"><dt>Total à payer</dt><dd>{{ number_format((int) $checkout->total_payable_amount, 0, ',', ' ') }} FCFA</dd></div>
        <div class="flex justify-between border-t border-border pt-3"><dt>État du parcours</dt><dd>{{ $checkout->status->value }}</dd></div>
    </dl>
    @if ($checkout->quote_expires_at)
        <p class="mt-4 text-sm text-muted">Quote valable jusqu’au {{ $checkout->quote_expires_at->format('d/m/Y H:i') }}.</p>
    @endif
    @if ($checkout->status->value === 'QUOTED')
        <form class="mt-6" method="post" action="{{ route('donations.confirm', [$campaign->slug, $checkout->public_id]) }}">
            @csrf
            <button class="min-h-11 w-full rounded-[10px] bg-brand-primary px-4 font-semibold text-white">Confirmer le don</button>
        </form>
    @elseif ($checkout->status->value === 'CONFIRMED')
        <form class="mt-6" method="post" action="{{ route('donations.pay', [$campaign->slug, $checkout->public_id]) }}">
            @csrf
            <button class="min-h-11 w-full rounded-[10px] bg-brand-primary px-4 font-semibold text-white">Payer le don</button>
        </form>
    @elseif ($checkout->status->value === 'PAYMENT_PENDING' || $checkout->status->value === 'UNKNOWN')
        <a class="mt-6 inline-flex min-h-11 items-center rounded-[10px] border border-border px-4 font-semibold" href="{{ route('donations.waiting.checkout', [$campaign->slug, $checkout->public_id]) }}">Voir le statut du paiement</a>
    @else
        <p class="mt-6 rounded-2xl bg-surface-muted p-4 text-sm text-muted">Cette quote n’est plus confirmable. Revenez à l’étape coordonnées pour générer une nouvelle quote.</p>
    @endif
</section>
@endsection
