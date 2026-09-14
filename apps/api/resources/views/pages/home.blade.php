@extends('layouts.public')

@section('title', 'Barkeelu — Ensemble, donnons vie aux projets qui comptent')
@section('description', 'Découvrez la maquette publique de Barkeelu, pensée pour le Sénégal et sa diaspora.')

@section('content')
    <section class="bg-gradient-to-b from-brand-primary-soft to-white" aria-labelledby="home-title">
        <div class="mx-auto max-w-[1200px] px-6 py-16 text-center sm:py-24">
            @if ($demoMode)
                <p class="inline-flex rounded-full bg-white px-4 py-2 text-xs font-semibold text-brand-primary shadow-sm">Maquette — contenus et données fictifs</p>
            @endif
            <h1 id="home-title" class="mx-auto mt-6 max-w-3xl text-4xl font-semibold tracking-tight text-ink sm:text-5xl lg:text-6xl">Ensemble, donnons vie aux projets qui comptent.</h1>
            <p class="mx-auto mt-6 max-w-2xl text-base leading-7 text-muted">Barkeelu prépare un espace solidaire pour découvrir des initiatives et suivre leur présentation publique.</p>
            <div class="mx-auto mt-8 max-w-2xl text-left">
                <label class="text-sm font-semibold text-ink" for="campaign-search">Rechercher une collecte</label>
                <input id="campaign-search" class="mt-2 min-h-11 w-full rounded-[10px] border border-border bg-white px-4 text-sm text-muted disabled:cursor-not-allowed disabled:opacity-80" type="search" placeholder="Recherche bientôt disponible" disabled aria-describedby="campaign-search-help">
                <p id="campaign-search-help" class="mt-2 text-sm text-muted">La recherche sera disponible avec les collectes publiques.</p>
            </div>
            <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                <x-ui.button href="#collectes">Découvrir les exemples</x-ui.button>
                <x-ui.button variant="secondary" disabled>Lancer une collecte — bientôt disponible</x-ui.button>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-[1200px] px-6 py-16 sm:py-24" aria-labelledby="causes-title">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-semibold text-brand-primary">Explorer par thématique</p>
                <h2 id="causes-title" class="mt-2 text-3xl font-semibold text-ink">Domaines d’action</h2>
            </div>
            <p class="max-w-md text-sm leading-6 text-muted">Représentation visuelle provisoire — la taxonomie des causes reste à confirmer.</p>
        </div>
        <ul class="mt-8 flex flex-wrap gap-3" aria-label="Exemples de domaines d’action">
            @foreach (['Santé et soins', 'Éducation', 'Solidarité familiale', 'Projets communautaires'] as $cause)
                <li class="min-h-11 rounded-full border border-border bg-white px-4 py-3 text-sm text-muted">{{ $cause }}</li>
            @endforeach
        </ul>
    </section>

    <section id="collectes" class="bg-surface-muted" aria-labelledby="campaigns-title">
        <div class="mx-auto max-w-[1200px] px-6 py-16 sm:py-24">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-brand-primary">{{ $demoMode ? 'Sélection de démonstration' : 'Collectes publiques' }}</p>
                    <h2 id="campaigns-title" class="mt-2 text-3xl font-semibold text-ink">Collectes à soutenir</h2>
                </div>
                <p class="text-sm text-muted">{{ $demoMode ? 'Exemples fictifs, sans lien vers un parcours de don.' : 'Collectes publiques, sans lien vers un parcours de don.' }}</p>
            </div>
            <div class="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($campaigns as $campaign)
                    <x-campaign.card
                        :title="$campaign['title']"
                        :summary="$campaign['summary']"
                        :organizer="$campaign['organizer']"
                        :image="$campaign['image']"
                        :collected="$campaign['collected']"
                        :goal="$campaign['goal']"
                        :contributions="$campaign['contributions']"
                        :verification-status="$campaign['verificationStatus']"
                        :cause="$campaign['cause']"
                        :demo-label="$campaign['demoLabel']"
                        :is-demo="$campaign['isDemo'] ?? $demoMode"
                        :currency="$campaign['currency'] ?? 'XOF'"
                        :url="$campaign['url'] ?? null"
                    />
                @endforeach
            </div>
        </div>
    </section>

    <section id="comment-ca-marche" class="mx-auto max-w-[1200px] px-6 py-16 sm:py-24" aria-labelledby="how-it-works-title">
        <div class="mx-auto max-w-2xl text-center">
            <p class="text-sm font-semibold text-brand-primary">Simplicité, clarté, proximité</p>
            <h2 id="how-it-works-title" class="mt-2 text-3xl font-semibold text-ink">Comment ça marche ?</h2>
            <p class="mt-4 text-muted">Une présentation des étapes prévues, sans promesse de disponibilité ou de délai.</p>
        </div>
        <ol class="mt-10 grid gap-5 md:grid-cols-3">
            @foreach ([['1', 'Découvrir une initiative', 'Lire les informations publiques lorsqu’elles sont disponibles.'], ['2', 'Mobiliser une communauté', 'Partager une cause de manière responsable avec son entourage.'], ['3', 'Suivre les contrôles applicables', 'Les étapes de vérification seront communiquées avec clarté.']] as [$number, $title, $description])
                <li class="rounded-2xl border border-border bg-white p-6">
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-[10px] bg-brand-primary-soft font-semibold text-brand-primary">{{ $number }}</span>
                    <h3 class="mt-5 text-xl font-semibold text-ink">{{ $title }}</h3>
                    <p class="mt-3 leading-7 text-muted">{{ $description }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="bg-brand-primary-soft" aria-labelledby="trust-title">
        <div class="mx-auto grid max-w-[1200px] gap-8 px-6 py-16 sm:py-24 lg:grid-cols-2 lg:items-center">
            <div>
                <p class="text-sm font-semibold text-brand-primary">Engagement et conformité</p>
                <h2 id="trust-title" class="mt-2 text-3xl font-semibold text-ink">Une démarche d’examen rigoureuse</h2>
                <p class="mt-5 leading-7 text-muted">Barkeelu distingue les informations publiques des données soumises aux contrôles applicables.</p>
            </div>
            <ul class="space-y-4 rounded-2xl border border-white bg-white p-6 text-sm leading-6 text-muted">
                <li><span class="font-semibold text-ink">Campagnes examinées avant publication.</span> Cette formulation décrit l’objectif du processus.</li>
                <li><span class="font-semibold text-ink">Frais présentés avant confirmation.</span> Les éléments applicables seront explicités au moment concerné.</li>
                <li><span class="font-semibold text-ink">Décaissement soumis aux contrôles applicables.</span> Il ne constitue pas une promesse de disponibilité.</li>
            </ul>
        </div>
    </section>

    <section id="a-propos" class="mx-auto max-w-[1200px] px-6 py-16 sm:py-24" aria-labelledby="impact-title">
        <div class="grid gap-8 rounded-3xl bg-surface-muted p-6 sm:p-10 lg:grid-cols-2 lg:items-center">
            <img class="aspect-video w-full rounded-2xl object-cover" src="{{ asset('images/placeholders/campaign-community.svg') }}" alt="" aria-hidden="true">
            <div>
                <p class="text-sm font-semibold text-brand-primary">Exemple de récit d’impact — démonstration fictive</p>
                <h2 id="impact-title" class="mt-3 text-3xl font-semibold text-ink">Une initiative communautaire pour une maternité rurale</h2>
                <p class="mt-5 leading-7 text-muted">Ce récit fictif illustre uniquement la manière dont un projet pourrait être présenté. Il ne décrit ni une personne, ni une organisation, ni une collecte réelle.</p>
            </div>
        </div>
    </section>

    <section class="px-6 pb-16 sm:pb-24" aria-labelledby="final-cta-title">
        <div class="mx-auto max-w-[1200px] rounded-3xl bg-brand-primary px-6 py-12 text-center text-white sm:px-10">
            <h2 id="final-cta-title" class="text-3xl font-semibold">Prêt à faire connaître une initiative ?</h2>
            <p class="mx-auto mt-4 max-w-xl text-white/85">La création web n’est pas encore disponible. Cette page présente les fondations de l’expérience publique.</p>
            <div class="mt-8"><x-ui.button variant="secondary" disabled>Création web bientôt disponible</x-ui.button></div>
        </div>
    </section>
@endsection
