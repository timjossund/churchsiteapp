/** Shared drawer behavior for the editor preview and the published header. */
export function mountSiteMenu(root: HTMLElement) {
    const trigger = root.querySelector<HTMLButtonElement>('[data-menu-open]');
    const dialog = root.querySelector<HTMLDialogElement>('[data-menu-dialog]');
    const dismiss = root.querySelector<HTMLButtonElement>('[data-menu-close]');
    if (!trigger || !dialog || !dismiss) return () => {};

    const desktop = window.matchMedia('(min-width: 640px)');
    let previousOverflow: string | undefined;
    const restore = () => {
        if (dialog.open) return;
        trigger.setAttribute('aria-expanded', 'false');
        if (previousOverflow !== undefined) {
            document.body.style.overflow = previousOverflow;
            previousOverflow = undefined;
        }
    };
    const open = () => {
        if (dialog.open || desktop.matches) return;
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        dialog.showModal();
        trigger.setAttribute('aria-expanded', 'true');
    };
    const close = () => {
        dialog.close();
        restore();
    };
    const cancel = (event: Event) => {
        event.preventDefault();
        close();
    };
    const resize = () => {
        if (desktop.matches) close();
    };
    const backdrop = (event: MouseEvent) => {
        if (event.target !== dialog) return;
        const bounds = dialog.getBoundingClientRect();
        if (
            event.clientX < bounds.left ||
            event.clientX > bounds.right ||
            event.clientY < bounds.top ||
            event.clientY > bounds.bottom
        )
            close();
    };
    trigger.addEventListener('click', open);
    dismiss.addEventListener('click', close);
    dialog.addEventListener('close', restore);
    dialog.addEventListener('cancel', cancel);
    dialog.addEventListener('click', backdrop);
    desktop.addEventListener('change', resize);

    return () => {
        close();
        restore();
        trigger.removeEventListener('click', open);
        dismiss.removeEventListener('click', close);
        dialog.removeEventListener('close', restore);
        dialog.removeEventListener('cancel', cancel);
        dialog.removeEventListener('click', backdrop);
        desktop.removeEventListener('change', resize);
    };
}
