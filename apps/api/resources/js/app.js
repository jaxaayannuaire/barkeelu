import './bootstrap';

document.querySelectorAll('[data-share]').forEach((button) => {
    button.addEventListener('click', async () => {
        const status = document.querySelector('[data-share-status]');
        const shareData = { title: document.title, url: button.dataset.shareUrl || window.location.href };

        try {
            if (navigator.share) {
                await navigator.share(shareData);
                if (status) status.textContent = 'Lien partagé.';
                return;
            }

            if (navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(shareData.url);
            } else {
                const input = document.createElement('textarea');
                input.value = shareData.url;
                input.setAttribute('readonly', '');
                input.className = 'sr-only';
                document.body.append(input);
                input.select();
                document.execCommand('copy');
                input.remove();
            }
            if (status) status.textContent = 'Lien copié dans le presse-papiers.';
        } catch (error) {
            if (status) status.textContent = 'Le partage n’a pas pu être effectué.';
        }
    });
});
