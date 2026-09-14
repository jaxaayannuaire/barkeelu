@props(['placeholder', 'ratio' => '16:9'])

@php
    $ratios = ['16:9' => 'aspect-video', '9:16' => 'aspect-[9/16]'];

    if (! array_key_exists($ratio, $ratios)) {
        throw new InvalidArgumentException("Format galerie non pris en charge : {$ratio}");
    }
@endphp

<section class="rounded-2xl border border-border bg-white p-5" aria-labelledby="gallery-title">
    <h2 id="gallery-title" class="text-xl font-semibold text-ink">Galerie</h2>
    <div class="mt-4 {{ $ratios[$ratio] }} overflow-hidden rounded-xl bg-surface-muted">
        <img class="h-full w-full object-cover opacity-70" src="{{ asset($placeholder) }}" alt="" aria-hidden="true">
    </div>
    <p class="mt-3 text-sm leading-6 text-muted">Galerie non disponible : aucun média de campagne n’est encore publié.</p>
</section>
