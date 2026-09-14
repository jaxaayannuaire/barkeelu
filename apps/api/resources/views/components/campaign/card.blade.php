@props([
    'title',
    'summary',
    'organizer',
    'image',
    'collected',
    'goal',
    'contributions',
    'verificationStatus' => null,
    'cause',
    'demoLabel' => 'Exemple fictif',
    'isDemo' => false,
    'currency' => 'XOF',
    'url' => null,
])

@php
    if (! is_int($contributions) || $contributions < 0) {
        throw new InvalidArgumentException('Le nombre de contributions doit être un entier non négatif.');
    }
@endphp

<article {{ $attributes->class('overflow-hidden rounded-2xl border border-border bg-white shadow-sm') }}>
    <div class="aspect-video bg-surface-muted">
        <img class="h-full w-full object-cover" src="{{ asset($image) }}" alt="" aria-hidden="true">
    </div>
    <div class="p-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs font-semibold text-brand-primary">{{ $cause }}</p>
            @if ($isDemo)
                <span class="rounded-full bg-brand-secondary-soft px-3 py-1 text-xs font-semibold text-[#8a5100]">{{ $demoLabel }}</span>
            @endif
        </div>
        <h3 class="mt-4 text-xl font-semibold text-ink">
            @if (! $isDemo && $url)
                <a class="rounded-sm focus:outline-none focus:ring-4 focus:ring-brand-primary-soft" href="{{ $url }}" aria-label="Voir la collecte {{ $title }}">{{ $title }}</a>
            @else
                {{ $title }}
            @endif
        </h3>
        <p class="mt-2 text-sm leading-6 text-muted">{{ $summary }}</p>
        <p class="mt-4 text-sm text-muted">Organisateur : {{ $organizer }}</p>
        @if ($verificationStatus !== null)
            <div class="mt-4"><x-campaign.verification-badge :status="$verificationStatus" /></div>
        @endif
        <div class="mt-5"><x-campaign.progress :collected="$collected" :goal="$goal" :currency="$currency" /></div>
        <p class="mt-4 text-sm text-muted">{{ number_format($contributions, 0, ',', ' ') }} contributions{{ $isDemo ? ' fictives' : '' }}</p>
    </div>
</article>
