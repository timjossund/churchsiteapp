import { mountHeroMotion } from './lib/hero-motion';
import { mountSiteMenu } from './lib/site-menu';

const header = document.querySelector<HTMLElement>('[data-site-header]');
if (header) mountSiteMenu(header);

for (const image of document.querySelectorAll<HTMLImageElement>(
    '.site-hero-image',
)) {
    const fallback = () => {
        image
            .closest<HTMLElement>('.site-hero')
            ?.removeAttribute('data-has-image');
        image.hidden = true;
    };
    image.addEventListener('error', fallback, { once: true });
    if (image.complete && image.naturalWidth === 0) fallback();
}

mountHeroMotion(document);
