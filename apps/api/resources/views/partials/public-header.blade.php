<header class="border-b border-border bg-white">
    <div class="mx-auto flex min-h-16 max-w-[1200px] items-center justify-between gap-4 px-6">
        <a class="hidden min-h-11 items-center rounded-[10px] focus:outline-none focus:ring-4 focus:ring-brand-primary-soft sm:flex" href="{{ url('/') }}">
            <x-brand.logo variant="horizontal" class="h-10 w-auto" />
            <span class="sr-only">Accueil Barkeelu</span>
        </a>
        <a class="flex min-h-11 items-center rounded-[10px] focus:outline-none focus:ring-4 focus:ring-brand-primary-soft sm:hidden" href="{{ url('/') }}">
            <x-brand.logo variant="square" class="h-10 w-10" />
            <span class="sr-only">Accueil Barkeelu</span>
        </a>
        <nav aria-label="Navigation principale" class="flex items-center gap-1 sm:gap-4">
            <a class="flex min-h-11 items-center rounded-[10px] px-3 text-sm text-muted hover:text-brand-primary focus:outline-none focus:ring-4 focus:ring-brand-primary-soft" href="{{ url('/#comment-ca-marche') }}">Comment ça marche</a>
            <a class="flex min-h-11 items-center rounded-[10px] px-3 text-sm text-muted hover:text-brand-primary focus:outline-none focus:ring-4 focus:ring-brand-primary-soft" href="{{ url('/#a-propos') }}">À propos</a>
        </nav>
    </div>
</header>
