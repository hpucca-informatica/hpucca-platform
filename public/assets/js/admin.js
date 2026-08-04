(() => {
    const shell = document.querySelector('[data-admin-shell]');
    const toggle = document.querySelector('[data-menu-toggle]');

    if (shell && toggle) {
        toggle.addEventListener('click', () => {
            shell.classList.toggle('is-menu-open');
        });
    }

    const slugify = (value) => value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/\u00e7/g, 'c')
        .replace(/\u00c7/g, 'c')
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '')
        .slice(0, 100)
        .replace(/-$/g, '');

    window.HPuccaAdmin = window.HPuccaAdmin || {};
    window.HPuccaAdmin.slugifyIntegrationSourceName = slugify;

    document.querySelectorAll('[data-slug-source]').forEach((nameInput) => {
        const form = nameInput.closest('form');
        const slugInput = form ? form.querySelector('[data-slug-target]') : null;

        if (!slugInput) {
            return;
        }

        let manualSlug = slugInput.value.trim() !== '';

        slugInput.addEventListener('input', () => {
            manualSlug = true;
        });

        nameInput.addEventListener('input', () => {
            if (manualSlug) {
                return;
            }

            slugInput.value = slugify(nameInput.value);
        });
    });

    const copyText = async (value) => {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            await navigator.clipboard.writeText(value);
            return;
        }

        const temporaryInput = document.createElement('textarea');
        temporaryInput.value = value;
        temporaryInput.setAttribute('readonly', 'readonly');
        temporaryInput.style.position = 'fixed';
        temporaryInput.style.left = '-9999px';
        document.body.appendChild(temporaryInput);
        temporaryInput.select();
        document.execCommand('copy');
        document.body.removeChild(temporaryInput);
    };

    document.querySelectorAll('[data-copy-target], [data-copy-value]').forEach((button) => {
        const originalLabel = button.textContent || 'Copiar';
        const feedback = button.dataset.copyFeedback || 'Copiado';
        const container = button.closest('.copyable-text, .api-key-copy, .json-viewer') || button.parentElement;
        const status = container ? container.querySelector('[data-copy-status]') : null;

        button.addEventListener('click', async () => {
            const targetSelector = button.dataset.copyTarget || '';
            const target = targetSelector ? document.querySelector(targetSelector) : null;
            const value = button.dataset.copyValue || (target ? ('value' in target ? target.value : target.textContent) : '');

            if (!value) {
                return;
            }

            await copyText(value);
            button.textContent = feedback;

            if (status) {
                status.textContent = feedback;
            }

            setTimeout(() => {
                button.textContent = originalLabel;

                if (status) {
                    status.textContent = '';
                }
            }, 2000);
        });
    });

    document.querySelectorAll('[data-dismiss-flash]').forEach((button) => {
        button.addEventListener('click', () => {
            button.closest('.flash-message')?.remove();
        });
    });
})();
