@props(['compact' => false, 'shareUrl' => null])

<div {{ $attributes->class($compact ? 'grid grid-cols-3 gap-2' : 'grid gap-3 sm:grid-cols-3') }}>
    <x-ui.button disabled aria-describedby="support-unavailable">Je soutiens</x-ui.button>
    <button type="button" data-share @if ($shareUrl) data-share-url="{{ $shareUrl }}" @endif class="inline-flex min-h-11 items-center justify-center rounded-[10px] border border-brand-primary bg-white px-4 text-sm font-semibold text-brand-primary transition-colors hover:bg-brand-primary-soft focus:outline-none focus:ring-4 focus:ring-brand-primary-soft">Partager</button>
    <x-ui.button variant="secondary" disabled aria-describedby="follow-unavailable">Suivre</x-ui.button>
</div>
