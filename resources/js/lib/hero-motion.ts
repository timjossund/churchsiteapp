/** Enhance static hero images without changing the scroll behavior of their content. */
export function mountHeroMotion(root: ParentNode): () => void {
    const preference = window.matchMedia('(prefers-reduced-motion: reduce)');
    const heroes = Array.from(root.querySelectorAll<HTMLElement>('.site-hero'));
    const entries = heroes.flatMap((hero) => {
        const image = hero.querySelector<HTMLImageElement>('.site-hero-image');
        if (!image) return [];
        let viewport: HTMLElement | null = hero.parentElement;
        while (viewport && viewport !== document.body) {
            if (
                /(auto|scroll|overlay)/.test(
                    getComputedStyle(viewport).overflowY,
                )
            )
                break;
            viewport = viewport.parentElement;
        }
        return [
            {
                hero,
                image,
                viewport: viewport === document.body ? null : viewport,
            },
        ];
    });
    let frame: number | undefined;
    let disposed = false;
    const reset = (image: HTMLImageElement) => {
        image.style.removeProperty('height');
        image.style.removeProperty('transform');
    };
    const update = () => {
        frame = undefined;
        if (disposed) return;
        for (const { hero, image, viewport } of entries) {
            const factor =
                hero.dataset.motion === 'fixed'
                    ? 1
                    : hero.dataset.motion === 'half'
                      ? 0.5
                      : 0;
            if (
                preference.matches ||
                !factor ||
                image.hidden ||
                hero.dataset.hasImage !== 'true'
            ) {
                reset(image);
                continue;
            }
            const rect = hero.getBoundingClientRect();
            const viewportTop = viewport
                ? viewport.getBoundingClientRect().top + viewport.clientTop
                : 0;
            const viewportHeight = viewport
                ? viewport.clientHeight
                : window.innerHeight;
            const top = rect.top - viewportTop;
            // The larger canvas covers the visible part of the hero throughout its passage.
            const imageHeight = Math.max(rect.height, viewportHeight);
            const translation =
                (rect.height - imageHeight) / 2 -
                factor * (top - (viewportHeight - rect.height) / 2);
            image.style.height = `${imageHeight}px`;
            image.style.transform = `translate3d(0, ${translation}px, 0)`;
        }
    };
    const schedule = () => {
        if (!disposed && frame === undefined)
            frame = window.requestAnimationFrame(update);
    };
    const observer = new ResizeObserver(schedule);
    for (const { hero, image, viewport } of entries) {
        observer.observe(hero);
        if (viewport) observer.observe(viewport);
        image.addEventListener('load', schedule);
        image.addEventListener('error', schedule);
    }
    // Capture also catches scrolling inside an editor scroll container.
    window.addEventListener('scroll', schedule, {
        capture: true,
        passive: true,
    });
    window.addEventListener('resize', schedule);
    preference.addEventListener('change', schedule);
    update();
    return () => {
        disposed = true;
        if (frame !== undefined) window.cancelAnimationFrame(frame);
        observer.disconnect();
        window.removeEventListener('scroll', schedule, true);
        window.removeEventListener('resize', schedule);
        preference.removeEventListener('change', schedule);
        for (const { image } of entries) {
            image.removeEventListener('load', schedule);
            image.removeEventListener('error', schedule);
            reset(image);
        }
    };
}
