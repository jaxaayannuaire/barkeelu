@extends('layouts.public')
@section('title', 'Faire un don — Barkeelu')
@section('robots', 'noindex,nofollow')
@section('content')
<section class="mx-auto max-w-md px-5 py-8">
    <p class="text-sm font-semibold text-brand-primary">Étape 1 sur 3 · Sécurisé</p>
    <h1 class="mt-3 text-3xl font-semibold">Votre don pour {{ $campaign->title }}</h1>
    <p class="mt-3 text-muted">{{ number_format($campaign->net_collected_nominal, 0, ',', ' ') }} FCFA collectés sur {{ number_format($campaign->goal_amount, 0, ',', ' ') }} FCFA.</p>
    <form class="mt-8 space-y-5" method="post">
        @csrf
        <div class="grid grid-cols-2 gap-3">
            @foreach ([2000, 5000, 10000, 25000] as $amount)
                <button class="min-h-11 rounded-[10px] border border-border font-semibold text-brand-primary" type="button" data-amount="{{ $amount }}">{{ number_format($amount, 0, ',', ' ') }} FCFA</button>
            @endforeach
        </div>
        <label class="block font-semibold" for="nominal_amount">Montant du don</label>
        <input class="mt-2 min-h-11 w-full rounded-[10px] border border-border px-3 tabular-nums" id="nominal_amount" name="nominal_amount" inputmode="numeric" value="{{ old('nominal_amount', $checkout?->nominal_amount ?? '') }}" required>
        @error('nominal_amount')<p class="text-danger">{{ $message }}</p>@enderror
        <p class="rounded-2xl bg-brand-primary-soft p-4 text-sm text-muted">Les frais applicables seront calculés par le serveur avant confirmation.</p>
        <button class="min-h-11 w-full rounded-[10px] bg-brand-primary px-4 font-semibold text-white">Continuer</button>
    </form>
</section>
@endsection
