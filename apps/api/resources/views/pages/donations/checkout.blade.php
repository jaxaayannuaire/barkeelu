@extends('layouts.public')
@section('title', 'Paiement Wave — Barkeelu')
@section('robots', 'noindex,nofollow')
@section('content')
<section class="mx-auto max-w-md px-5 py-8">
    <p class="text-sm font-semibold text-brand-primary">Étape 3 sur 3 · Sécurisé</p>
    <h1 class="mt-3 text-3xl font-semibold">Récapitulatif</h1>
    <p class="mt-2 text-sm text-muted">Parcours {{ $checkout->public_id }}</p>
    <dl class="mt-7 space-y-3 rounded-2xl border border-border p-5 tabular-nums">
        <div class="flex justify-between"><dt>Montant du don</dt><dd>{{ number_format($checkout->nominal_amount, 0, ',', ' ') }} FCFA</dd></div>
        <div class="flex justify-between border-t border-border pt-3 font-semibold"><dt>État du parcours</dt><dd>{{ $checkout->status->value }}</dd></div>
    </dl>
    <p class="mt-5 text-sm text-muted">Vous serez redirigé vers Wave. La confirmation sera affichée après vérification par le serveur.</p>
    <p class="mt-6 rounded-2xl bg-surface-muted p-4 text-sm text-muted">La quote serveur et la confirmation seront branchées dans l’étape suivante.</p>
</section>
@endsection
