import SiteBillingWebhookController from './SiteBillingWebhookController'
import PublishedSiteController from './PublishedSiteController'
import CustomHostnameController from './CustomHostnameController'
import SiteBillingController from './SiteBillingController'
import SiteController from './SiteController'
import SitePageController from './SitePageController'
import SiteMediaController from './SiteMediaController'
import SiteBlockController from './SiteBlockController'
import SitePublishingController from './SitePublishingController'
import Settings from './Settings'
import DomainProxyController from './DomainProxyController'

const Controllers = {
    SiteBillingWebhookController: Object.assign(SiteBillingWebhookController, SiteBillingWebhookController),
    PublishedSiteController: Object.assign(PublishedSiteController, PublishedSiteController),
    CustomHostnameController: Object.assign(CustomHostnameController, CustomHostnameController),
    SiteBillingController: Object.assign(SiteBillingController, SiteBillingController),
    SiteController: Object.assign(SiteController, SiteController),
    SitePageController: Object.assign(SitePageController, SitePageController),
    SiteMediaController: Object.assign(SiteMediaController, SiteMediaController),
    SiteBlockController: Object.assign(SiteBlockController, SiteBlockController),
    SitePublishingController: Object.assign(SitePublishingController, SitePublishingController),
    Settings: Object.assign(Settings, Settings),
    DomainProxyController: Object.assign(DomainProxyController, DomainProxyController),
}

export default Controllers