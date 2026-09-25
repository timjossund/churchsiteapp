import SiteController from './SiteController';
import SiteMediaController from './SiteMediaController';
import SiteBlockController from './SiteBlockController';
import Settings from './Settings';

const Controllers = {
    SiteController: Object.assign(SiteController, SiteController),
    SiteMediaController: Object.assign(
        SiteMediaController,
        SiteMediaController,
    ),
    SiteBlockController: Object.assign(
        SiteBlockController,
        SiteBlockController,
    ),
    Settings: Object.assign(Settings, Settings),
};

export default Controllers;
