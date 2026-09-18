import './bootstrap';

document.querySelectorAll('[data-amount]').forEach((button) => button.addEventListener('click', () => { document.querySelector('#nominal_amount').value = button.dataset.amount; }));
document.querySelectorAll('[data-payment-status]').forEach((element) => {
    let delay = 3000;
    let attempts = 0;
    const poll = async () => {
        if (++attempts > 20) return;
        try {
            const response = await fetch(element.dataset.statusUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            const data = await response.json();
            if (data.checkout_status === 'PAID' || data.payment_status === 'PAID') location.assign(element.dataset.successUrl);
            else if (['FAILED', 'CANCELLED', 'EXPIRED'].includes(data.checkout_status) || ['FAILED', 'CANCELLED', 'EXPIRED'].includes(data.payment_status)) location.assign(element.dataset.errorUrl);
            else { delay = Math.min(delay * 2, 30000); setTimeout(poll, delay); }
        } catch { setTimeout(poll, delay); }
    };
    if (!matchMedia('(prefers-reduced-motion: reduce)').matches) setTimeout(poll, delay);
});

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
