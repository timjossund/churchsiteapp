import { mountSiteMenu } from './lib/site-menu';

const header = document.querySelector<HTMLElement>('[data-site-header]');
if (header) mountSiteMenu(header);
