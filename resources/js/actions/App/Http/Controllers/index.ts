import SiteController from './SiteController'
import Settings from './Settings'

const Controllers = {
    SiteController: Object.assign(SiteController, SiteController),
    Settings: Object.assign(Settings, Settings),
}

export default Controllers