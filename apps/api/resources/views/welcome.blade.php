@extends('layouts.public')

@section('title', 'Barkeelu — Plateforme solidaire')
@section('description', 'Barkeelu prépare une plateforme solidaire pour le Sénégal et sa diaspora.')

@section('content')
    <section class="bg-surface-muted">
        <div class="mx-auto max-w-[1200px] px-6 py-16 sm:py-24">
            <p class="text-sm font-semibold text-brand-primary">Barkeelu.com</p>
            <h1 class="mt-4 max-w-3xl text-4xl font-semibold tracking-tight text-ink sm:text-5xl">Une fondation publique, claire et accessible.</h1>
            <p class="mt-6 max-w-2xl text-base leading-7 text-muted">Cette page présente les bases visuelles de Barkeelu. Les parcours de collecte et de don ne sont pas encore disponibles.</p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                <x-ui.button href="#comment-ca-marche">Comment ça marche</x-ui.button>
                <x-ui.button href="#a-propos" variant="secondary">À propos de Barkeelu</x-ui.button>
            </div>
        </div>
    </section>
    <section id="comment-ca-marche" class="mx-auto max-w-[1200px] px-6 py-16 sm:py-24" aria-labelledby="how-it-works-title">
        <div class="max-w-2xl rounded-2xl border border-border bg-white p-6 sm:p-8">
            <h2 id="how-it-works-title" class="text-2xl font-semibold text-ink">Comment ça marche</h2>
            <p class="mt-4 leading-7 text-muted">Barkeelu construit progressivement son interface publique. Les fonctions liées aux campagnes et aux paiements seront présentées seulement lorsqu’elles seront réellement disponibles.</p>
        </div>
    </section>
    <section id="a-propos" class="border-y border-border bg-brand-primary-soft" aria-labelledby="about-title">
        <div class="mx-auto max-w-[1200px] px-6 py-16 sm:py-24">
            <h2 id="about-title" class="text-2xl font-semibold text-ink">À propos</h2>
            <p class="mt-4 max-w-2xl leading-7 text-muted">Barkeelu.com est une plateforme de fundraising, de crowdfunding, de dons, de solidarité et d’impact social, pensée pour le Sénégal et sa diaspora.</p>
        </div>
    </section>
@endsection
