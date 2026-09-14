@props(['organizer'])

<section id="organisateur-beneficiaire" class="scroll-mt-28 rounded-2xl border border-border bg-white p-6" aria-labelledby="public-parties-title">
    <h2 id="public-parties-title" class="text-2xl font-semibold text-ink">Organisateur et bénéficiaire</h2>
    <dl class="mt-5 grid gap-5 sm:grid-cols-2">
        <div>
            <dt class="text-sm font-semibold text-ink">Organisateur</dt>
            <dd class="mt-1 text-sm leading-6 text-muted">{{ $organizer }}</dd>
        </div>
        <div>
            <dt class="text-sm font-semibold text-ink">Bénéficiaire</dt>
            <dd class="mt-1 text-sm leading-6 text-muted">Bénéficiaire de la collecte</dd>
        </div>
    </dl>
</section>
