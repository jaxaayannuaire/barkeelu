@extends('layouts.public')

@section('title', $campaign->title . ' — Barkeelu')
@section('description', $metaDescription)
@section('canonical', $canonical)
@section('robots', $robots)
@section('og_title', $campaign->title . ' — Barkeelu')
@section('og_description', $metaDescription)

@section('content')
    <article class="bg-white pb-36 lg:pb-20">
        <div class="mx-auto max-w-[1200px] px-6 py-8 sm:py-12">
            <a class="inline-flex min-h-11 items-center rounded-[10px] text-sm font-semibold text-brand-primary focus:outline-none focus:ring-4 focus:ring-brand-primary-soft" href="{{ url('/#collectes') }}">← Retour vers les collectes</a>
            <p class="mt-6 text-sm font-semibold text-brand-primary">{{ $fundraisingLabel }}</p>
            <h1 class="mt-3 max-w-4xl text-3xl font-semibold leading-tight text-ink sm:text-4xl">{{ $campaign->title }}</h1>
            <p class="mt-4 text-sm text-muted">{{ $organizerLabel }} · Bénéficiaire de la collecte</p>

            <div class="mt-8 grid gap-8 lg:grid-cols-[minmax(0,65fr)_minmax(280px,35fr)] lg:items-start">
                <div class="space-y-6">
                    <x-campaign.media-player poster="images/placeholders/campaign-community.svg" />
                    <x-campaign.gallery placeholder="images/placeholders/campaign-education.svg" />
                </div>

                <aside class="rounded-2xl border border-border bg-white p-6 shadow-sm lg:sticky lg:top-6" aria-labelledby="support-title">
                    <h2 id="support-title" class="text-xl font-semibold text-ink">Soutenir cette collecte</h2>
                    <div class="mt-5"><x-campaign.progress :collected="$campaign->net_collected_nominal" :goal="$campaign->goal_amount" :currency="$campaign->currency" /></div>
                    <dl class="mt-6 grid grid-cols-2 gap-4 text-sm tabular-nums">
                        <div><dt class="text-muted">Contributions</dt><dd class="mt-1 font-semibold text-ink">{{ number_format($campaign->donation_count, 0, ',', ' ') }}</dd></div>
                        <div><dt class="text-muted">Soutiens distincts</dt><dd class="mt-1 font-semibold text-ink">{{ number_format($campaign->distinct_donor_count, 0, ',', ' ') }}</dd></div>
                    </dl>
                    @if (! $supportUrl)
                        <p id="support-unavailable" class="mt-5 text-sm leading-6 text-muted">{{ $fundraisingLabel }}.</p>
                    @endif
                    <p id="follow-unavailable" class="sr-only">Fonctionnalité à venir.</p>
                    <p class="sr-only" data-share-status aria-live="polite"></p>
                    <div class="mt-5"><x-campaign.action-bar :share-url="$canonical" :support-url="$supportUrl" /></div>
                </aside>
            </div>

            <nav class="mt-10 overflow-x-auto border-y border-border" aria-label="Navigation de la collecte">
                <ul class="flex min-w-max gap-6 py-4 text-sm font-semibold text-muted">
                    <li><a class="rounded-sm text-brand-primary focus:outline-none focus:ring-4 focus:ring-brand-primary-soft" href="#a-propos">À propos</a></li>
                    <li aria-disabled="true">Dons — À venir</li>
                    <li aria-disabled="true">Commentaires — À venir</li>
                    <li aria-disabled="true">Mises à jour — À venir</li>
                    <li><a class="rounded-sm text-brand-primary focus:outline-none focus:ring-4 focus:ring-brand-primary-soft" href="#organisateur-beneficiaire">Organisateur et bénéficiaire</a></li>
                </ul>
            </nav>

            <div class="mt-10 grid gap-10 lg:grid-cols-[minmax(0,65fr)_minmax(280px,35fr)]">
                <section id="a-propos" class="scroll-mt-28" aria-labelledby="about-title">
                    <h2 id="about-title" class="text-3xl font-semibold text-ink">À propos de cette collecte</h2>
                    <div class="mt-5 space-y-5 text-base leading-8 text-muted">
                        @forelse ($descriptionParagraphs as $paragraph)
                            <p>{{ $paragraph }}</p>
                        @empty
                            <p>Aucun récit public n’a encore été communiqué.</p>
                        @endforelse
                    </div>
                </section>
                <x-campaign.public-parties :organizer="$organizerLabel" />
            </div>
        </div>
    </article>

    <aside class="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-white p-3 shadow-lg lg:hidden" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom))" aria-label="Actions de collecte">
        <div class="mx-auto max-w-[1200px]">
            <p class="mb-2 text-center text-xs tabular-nums text-muted">{{ number_format($campaign->net_collected_nominal, 0, ',', ' ') }} FCFA sur {{ number_format($campaign->goal_amount, 0, ',', ' ') }} FCFA</p>
            <x-campaign.action-bar compact :share-url="$canonical" :support-url="$supportUrl" />
        </div>
    </aside>
@endsection
