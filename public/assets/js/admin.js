(() => {
    const shell = document.querySelector('[data-admin-shell]');
    const toggle = document.querySelector('[data-menu-toggle]');

    if (!shell || !toggle) {
        return;
    }

    toggle.addEventListener('click', () => {
        shell.classList.toggle('is-menu-open');
    });
})();
