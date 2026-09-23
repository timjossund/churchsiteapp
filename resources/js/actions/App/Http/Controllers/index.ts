import SiteController from './SiteController'
import SiteBlockController from './SiteBlockController'
import Settings from './Settings'

const Controllers = {
    SiteController: Object.assign(SiteController, SiteController),
    SiteBlockController: Object.assign(SiteBlockController, SiteBlockController),
    Settings: Object.assign(Settings, Settings),
}

export default Controllers