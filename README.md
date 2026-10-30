# apLists

[**Buy one year of updates and support — $59, one site**](https://artistprowp.com/checkout/?product=aplists&plan=annual&sites=1) · [All plans](https://artistprowp.com/aplists/) · [Get help](https://artistprowp.com/support/)

First-party WordPress subscriber lists with consent records.

- Double opt-in signup and immediate unsubscribe handling
- Subscriber segments and suppression protection
- CSV import and complete, batched exports

## Requirements

WordPress 6.4 or later; PHP 8.0 or later. Tested on WordPress 6.4 and 7.1.3 with PHP 8.3. The production retail deployment uses PHP 8.4.

## Installation

Use a packaged release ZIP, not GitHub's source-code ZIP. Back up your site first. If an older community or paid apLists edition is active, deactivate it before activating this community edition. The community package uses the `artistpro-lists` plugin folder; older community and paid packages use `aplists`, so WordPress may install it as a separate plugin rather than offer to replace the old one. Keep the site database and verify your lists and subscribers after activation. Community users do not need a license key. See the [getting-started guide](https://artistprowp.com/docs/aplists-getting-started/).

The community distribution does not require a license key and does not contact a private licensing or update service. The retail distribution includes the commercial update client; activate its service key under apLists → License.

## Open source and paid services

The plugin is GPLv2 or later. Paid plans at [artistproWP](https://artistprowp.com/aplists/) provide updates and support for the covered sites. Installed functionality remains available after paid service expires. Purchasing support is optional for community users. Community releases here can be updated manually by installing the latest release ZIP. A paid plan provides the retail package with its automatic update client and one year of support; use the service key for the number of sites purchased. This does not restrict your open-source software rights. WordPress.org availability will be announced after directory review; it is not yet approved.

## Moving from the community edition to a paid plan

After purchasing, download the retail ZIP and find your service key in [My Account](https://artistprowp.com/my-account/). Back up your site and database. Deactivate the community edition before activating the retail edition; they share classes and cannot run together. Upload the retail ZIP through Plugins → Add New. If an older `aplists` folder is already installed, WordPress may offer to replace that older folder; it will not replace the differently named `artistpro-lists` folder. Activate the retail plugin and then your key under apLists → License. Your existing lists and subscribers remain in the database; verify them after the switch. [See support instructions](https://artistprowp.com/support/).

## Help and contributions

Use GitHub issues for reproducible public bug reports. Never include license keys, passwords, subscriber data, or private site access. Paid customer assistance is available through [support](https://artistprowp.com/support/). See CONTRIBUTING.md and SECURITY.md.

## License

[GNU General Public License, version 2 or later](LICENSE.txt).
