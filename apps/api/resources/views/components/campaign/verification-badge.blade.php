@props(['status' => 'unverified'])

@php
    $statuses = [
        'verified' => ['label' => 'Identité vérifiée', 'classes' => 'bg-brand-primary-soft text-brand-primary'],
        'pending' => ['label' => 'Examen en cours', 'classes' => 'bg-brand-secondary-soft text-[#8a5100]'],
        'incomplete' => ['label' => 'Informations à compléter', 'classes' => 'bg-surface-muted text-muted'],
        'unverified' => ['label' => 'Non vérifié', 'classes' => 'bg-surface-muted text-muted'],
    ];
    $badge = $statuses[$status] ?? $statuses['unverified'];
@endphp

<span {{ $attributes->class("inline-flex min-h-7 items-center rounded-full px-3 text-xs font-semibold {$badge['classes']}") }}>
    {{ $badge['label'] }}
</span>
