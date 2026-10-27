/* Shared pages: toggles the right-hand navigation drawer. */

(function () {
    const drawer = document.getElementById('rightNav');
    const toggle = document.getElementById('navToggle');
    const closeBtn = document.getElementById('navClose');
    const overlay = document.getElementById('navOverlay');
    let lastFocused = null;

    const isOpen = () => drawer.classList.contains('is-open');

    function openNav() {
        lastFocused = document.activeElement;
        drawer.classList.add('is-open');
        // Next frame so the transition runs from the hidden state.
        requestAnimationFrame(() => overlay.classList.add('is-visible'));
        drawer.removeAttribute('inert');
        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-label', 'Close navigation menu');
        document.body.classList.add('nav-open');
        closeBtn.focus();
    }

    function closeNav() {
        drawer.classList.remove('is-open');
        overlay.classList.remove('is-visible');
        drawer.setAttribute('inert', '');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Open navigation menu');
        document.body.classList.remove('nav-open');

        if (lastFocused instanceof HTMLElement) {
            lastFocused.focus();
        } else {
            toggle.focus();
        }
    }

    toggle.addEventListener('click', () => {
        isOpen() ? closeNav() : openNav();
    });

    closeBtn.addEventListener('click', closeNav);
    overlay.addEventListener('click', closeNav);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen()) {
            closeNav();
        }
    });

    // Keep keyboard focus inside the drawer while it is open.
    drawer.addEventListener('keydown', (event) => {
        if (event.key !== 'Tab' || !isOpen()) {
            return;
        }

        const focusable = drawer.querySelectorAll('a[href], button:not([disabled])');
        if (focusable.length === 0) {
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    // Close the drawer after a link is used.
    drawer.querySelectorAll('a[href]').forEach((link) => {
        link.addEventListener('click', () => {
            if (isOpen()) {
                closeNav();
            }
        });
    });
})();
