=== apLists ===
Tags: email list, subscribers, newsletter, double opt-in, consent
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Own your WordPress subscriber lists with consent evidence, double opt-in, segmentation, suppression, CSV import/export, and one-click unsubscribe.

== Description ==

apLists is a first-party subscriber list workspace for WordPress. Build unlimited lists, collect permission through accessible signup forms, confirm subscribers, protect suppressions, create useful segments, and export portable data without renting a contact database.

* Unlimited subscriber lists and contacts
* Accessible public subscription forms
* Double opt-in confirmation
* Consent timestamps and privacy-preserving IP hashes
* Immediate one-click unsubscribe processing
* Permanent suppression protection
* Status, source, and email segments
* Safe CSV import with explicit consent handling
* Portable CSV export
* Bounce and complaint data foundation
* Optional apForms, apCampaign, and apCRM connections
* Free plugin functionality with optional paid implementation and support

== Installation ==

1. Upload the apLists ZIP through Plugins > Add New > Upload Plugin.
2. If another apLists edition is active, deactivate it before activating this edition. Your lists and subscribers remain in your database.
3. No license key is required for plugin functionality.
4. Open apLists > Getting Started.
5. Create a list and publish its shortcode on a page.

== Frequently Asked Questions ==

= Does apLists require apCampaign or a CRM? =

No. apLists runs independently on ordinary WordPress. Integrations are optional.

= Where is subscriber data stored? =

In dedicated tables in your WordPress database.

= Can a suppressed email be imported again? =

No. Suppression protection blocks accidental resubscription until an administrator deliberately resolves the record.

= What happens if I do not renew? =

Your installed plugin and subscriber data continue working. Renewal covers updates and support.

== Paid support ==

Optional implementation and support are available at https://artistprowp.com/support/. Purchasing support is not required to use the plugin. No license server or private update service is contacted by this directory build.

== Changelog ==

= 1.0.6 =
* Recover older installations with missing database tables while preserving existing data.

= 1.0.5 =
* Initialize network sites safely when apLists is network activated, and retry incomplete table creation.

= 1.0.4 =
* Improve request validation and input sanitization.

= 1.0.3 =
* Export all subscribers in bounded batches, including lists larger than 2,000.
* Keep concurrent new signups for the next export.

= 1.0.2 =
* Prepared WordPress.org distribution without a private updater or paid activation.
* Updated licensing metadata and release documentation.


= 1.0.1 =
* Corrected legacy-table upgrades and retained all 1.0 commercial features.

= 1.0.0 =
* Commercial launch with list management, public forms, double opt-in, consent evidence, subscribers, segments, CSV import/export, suppression, onboarding, licensing, and updates.
