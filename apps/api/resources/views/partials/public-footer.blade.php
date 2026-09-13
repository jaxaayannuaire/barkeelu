<footer class="bg-ink text-white">
    <div class="mx-auto max-w-[1200px] px-6 py-12">
        <div class="flex flex-col gap-8 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <x-brand.logo variant="horizontal" class="h-9 w-auto" />
                <p class="mt-4 max-w-md text-sm leading-6 text-white/75">Plateforme solidaire pour le Sénégal et sa diaspora.</p>
            </div>
            <div aria-label="Informations Barkeelu">
                <p class="text-sm font-semibold">Informations</p>
                <ul class="mt-4 grid gap-3 text-sm text-white/75 sm:grid-cols-2 sm:gap-x-8">
                    <li><a class="rounded-[10px] hover:text-white focus:outline-none focus:ring-4 focus:ring-brand-secondary" href="{{ url('/#a-propos') }}">À propos</a></li>
                    <li><a class="rounded-[10px] hover:text-white focus:outline-none focus:ring-4 focus:ring-brand-secondary" href="{{ url('/#comment-ca-marche') }}">Comment ça marche</a></li>
                    <li>Sécurité et vérification</li><li>Conditions générales</li><li>Confidentialité</li><li>Mentions légales</li><li>Aide en ligne</li>
                </ul>
            </div>
        </div>
        <p class="mt-10 border-t border-white/20 pt-6 text-sm text-white/60">© {{ now()->year }} Barkeelu.com</p>
    </div>
</footer>
