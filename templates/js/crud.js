/* List pages: swipe-to-reveal actions, delete confirmation and modal reopening. */

(function () {
    const items = Array.from(document.querySelectorAll('[data-swipe-item]'));

    function closeItem(item) {
        const content = item.querySelector('[data-swipe-content]');
        if (content) {
            content.style.transform = 'translateX(0)';
        }
        item.classList.remove('is-open');
    }

    function closeAllExcept(keep) {
        items.forEach((item) => {
            if (item !== keep) {
                closeItem(item);
            }
        });
    }

    items.forEach((item) => {
        const content = item.querySelector('[data-swipe-content]');
        const actions = item.querySelector('[data-swipe-actions]');
        if (!content || !actions) {
            return;
        }

        let pointerId = null;
        let startX = 0;
        let startY = 0;
        let base = 0;
        let offset = 0;
        let horizontal = false;
        let moved = false;
        let wasOpen = false;
        let suppressClick = false;

        const actionWidth = () => actions.offsetWidth;

        const setOffset = (value) => {
            offset = Math.max(-actionWidth(), Math.min(0, value));
            content.style.transform = `translateX(${offset}px)`;
        };

        content.addEventListener('pointerdown', (event) => {
            if (event.pointerType === 'mouse' && event.button !== 0) {
                return;
            }

            closeAllExcept(item);
            pointerId = event.pointerId;
            startX = event.clientX;
            startY = event.clientY;
            wasOpen = item.classList.contains('is-open');
            base = wasOpen ? -actionWidth() : 0;
            offset = base;
            horizontal = false;
            moved = false;

            if (content.setPointerCapture) {
                content.setPointerCapture(pointerId);
            }
        });

        content.addEventListener('pointermove', (event) => {
            if (event.pointerId !== pointerId) {
                return;
            }

            const dx = event.clientX - startX;
            const dy = event.clientY - startY;

            if (!horizontal) {
                // Wait until the gesture direction is clear so vertical scrolling still works.
                if (Math.abs(dx) < 6 && Math.abs(dy) < 6) {
                    return;
                }

                if (Math.abs(dy) >= Math.abs(dx)) {
                    return;
                }

                horizontal = true;
                item.classList.add('is-dragging');
            }

            if (Math.abs(dx) > 6) {
                moved = true;
            }

            event.preventDefault();
            setOffset(base + dx);
        });

        const endDrag = (event) => {
            if (event.pointerId !== pointerId) {
                return;
            }

            if (content.releasePointerCapture) {
                content.releasePointerCapture(pointerId);
            }

            pointerId = null;
            item.classList.remove('is-dragging');

            if (horizontal) {
                const open = offset < -actionWidth() / 2;
                setOffset(open ? -actionWidth() : 0);
                item.classList.toggle('is-open', open);

                if (moved) {
                    suppressClick = true;
                }
            } else if (wasOpen && !moved) {
                // Tapping an open row closes it again.
                closeItem(item);
            }

            horizontal = false;
        };

        content.addEventListener('pointerup', endDrag);
        content.addEventListener('pointercancel', endDrag);

        // A drag should not also register as a tap on the row.
        content.addEventListener('click', (event) => {
            if (suppressClick) {
                event.preventDefault();
                event.stopPropagation();
                suppressClick = false;
            }
        }, true);

        // Keep the revealed actions reachable with a keyboard.
        actions.querySelectorAll('button, a').forEach((control) => {
            control.addEventListener('focus', () => {
                closeAllExcept(item);
                setOffset(-actionWidth());
                item.classList.add('is-open');
            });
        });
    });

    // Tapping outside an open row closes it.
    document.addEventListener('pointerdown', (event) => {
        items.forEach((item) => {
            if (item.classList.contains('is-open') && !item.contains(event.target)) {
                closeItem(item);
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            items.forEach(closeItem);
        }
    });

    // Confirm before deleting a record.
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm || 'Delete this record?')) {
                event.preventDefault();
            }
        });
    });

    // Re-open the modal when server-side validation failed.
    const openModalId = document.body.dataset.openModal;
    if (openModalId && window.bootstrap) {
        const modalElement = document.getElementById(openModalId);
        if (modalElement) {
            window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
        }
    }
})();
