@props(['collected', 'goal'])

@php
    if (! is_int($collected) || ! is_int($goal) || $collected < 0 || $goal <= 0) {
        throw new InvalidArgumentException('La progression requiert des montants entiers non négatifs et un objectif strictement positif.');
    }

    $percentage = intdiv($collected, $goal) * 100 + intdiv(($collected % $goal) * 100, $goal);
    $visualPercentage = min($percentage, 100);
    $ariaMax = max($percentage, 100);
    $formatAmount = static fn (int $amount): string => number_format($amount, 0, ',', ' ') . ' FCFA';
@endphp

<div {{ $attributes->class('space-y-2') }}>
    <div class="flex items-baseline justify-between gap-3 text-sm">
        <p class="font-semibold text-ink">{{ $formatAmount($collected) }}</p>
        <p class="text-muted">Objectif : {{ $formatAmount($goal) }}</p>
    </div>
    <div
        class="h-2 overflow-hidden rounded-full bg-brand-primary-soft"
        role="progressbar"
        aria-label="Progression de la collecte"
        aria-valuemin="0"
        aria-valuemax="{{ $ariaMax }}"
        aria-valuenow="{{ $percentage }}"
        aria-valuetext="{{ $percentage }} % de l’objectif"
    >
        <div class="h-full rounded-full bg-brand-primary" style="width: {{ $visualPercentage }}%"></div>
    </div>
    <p class="text-sm text-muted"><span class="font-semibold text-ink">{{ $percentage }} %</span> de l’objectif</p>
</div>
