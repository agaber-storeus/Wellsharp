import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

const startAlpine = () => {
    if (!document.body || document.body.hasAttribute('data-alpine-started')) return;

    document.body.setAttribute('data-alpine-started', 'true');
    Alpine.start();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startAlpine, { once: true });
} else {
    startAlpine();
}
