import PublishedSiteController from './PublishedSiteController';
import SiteController from './SiteController';
import SitePageController from './SitePageController';
import SiteBlockController from './SiteBlockController';
import SiteMediaController from './SiteMediaController';
import SitePublishingController from './SitePublishingController';
import Settings from './Settings';

const Controllers = {
    PublishedSiteController: Object.assign(
        PublishedSiteController,
        PublishedSiteController,
    ),
    SiteController: Object.assign(SiteController, SiteController),
    SitePageController: Object.assign(SitePageController, SitePageController),
    SiteBlockController: Object.assign(
        SiteBlockController,
        SiteBlockController,
    ),
    SiteMediaController: Object.assign(
        SiteMediaController,
        SiteMediaController,
    ),
    SitePublishingController: Object.assign(
        SitePublishingController,
        SitePublishingController,
    ),
    Settings: Object.assign(Settings, Settings),
};

export default Controllers;
