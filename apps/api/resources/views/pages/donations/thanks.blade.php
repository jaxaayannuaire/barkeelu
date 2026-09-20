@extends('layouts.public')
@section('title', 'Merci — Barkeelu')
@section('robots', 'noindex,nofollow')
@section('content')
<section class="mx-auto max-w-md px-5 py-10">
    <p class="text-sm font-semibold text-success">Paiement confirmé</p>
    <h1 class="mt-3 text-3xl font-semibold">Merci pour votre soutien</h1>
    <p class="mt-5 text-muted">Votre don a été confirmé par le serveur.</p>
    <dl class="mt-6 space-y-3 rounded-2xl border border-border p-5 tabular-nums">
        <div class="flex justify-between"><dt>Montant du don</dt><dd>{{ number_format($donation->nominal_amount, 0, ',', ' ') }} FCFA</dd></div>
        @foreach (($checkout->fee_snapshot['fees'] ?? []) as $fee)
            <div class="flex justify-between"><dt>{{ str_replace('_', ' ', $fee['fee_type']) }}</dt><dd>{{ number_format((int) $fee['calculated_amount'], 0, ',', ' ') }} FCFA</dd></div>
        @endforeach
        <div class="flex justify-between border-t border-border pt-3 font-semibold"><dt>Total payé</dt><dd>{{ number_format((int) $checkout->total_payable_amount, 0, ',', ' ') }} FCFA</dd></div>
    </dl>
    @if ($payment)
        <p class="mt-5 text-sm text-muted">Référence de paiement : {{ $payment->public_id }}</p>
        @if ($payment->paid_at)
            <p class="mt-2 text-sm text-muted">Confirmé le {{ $payment->paid_at->format('d/m/Y H:i') }}.</p>
        @endif
    @endif
    <a class="mt-7 inline-flex min-h-11 items-center rounded-[10px] bg-brand-primary px-4 font-semibold text-white" href="{{ route('campaigns.show', $checkout->campaign->slug) }}">Retour vers la collecte</a>
</section>
@endsection
