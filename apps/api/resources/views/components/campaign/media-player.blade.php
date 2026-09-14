@props(['poster', 'ratio' => '16:9'])

@php
    $ratios = ['16:9' => 'aspect-video', '9:16' => 'aspect-[9/16]'];

    if (! array_key_exists($ratio, $ratios)) {
        throw new InvalidArgumentException("Format média non pris en charge : {$ratio}");
    }
@endphp

<section class="overflow-hidden rounded-2xl border border-border bg-surface-muted" aria-labelledby="media-title">
    <img class="{{ $ratios[$ratio] }} w-full object-cover" src="{{ asset($poster) }}" alt="" aria-hidden="true">
    <div class="border-t border-border bg-white px-5 py-4">
        <h2 id="media-title" class="text-base font-semibold text-ink">Vidéo de présentation non disponible</h2>
        <p class="mt-1 text-sm leading-6 text-muted">Aucun média de campagne n’est encore publié pour cette collecte.</p>
    </div>
</section>
