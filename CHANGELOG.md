# Translations for Craft Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 3.2.5 - 2023-04-15

### Fixed
- An issue where merging changes to draft throwing error when commerce product/variant are referenced inside another commerce product/variant.

## 3.2.4 - 2023-04-05

### Fixed
- An issue where order listing page was taking too long to load.

## 3.2.3 - 2023-03-10

### Fixed
- An issue where delivery files failed to sync back from acclaro.
- An issue where clicking `save draft` button in detail page of an existing order draft was creating new draft.
- An issue where download log files was not working because of different date formats in different time zones.
- An issue where zip files delivered from acclaro failed to upload.

## 3.2.2 - 2023-02-27

### Fixed
- An issue where delivered files failed to merge into draft ([AcclaroInc#421](https://github.com/AcclaroInc/craft-translations/issues/421))
- An issue where log files failed to download with invalid date error.

## 3.2.1 - 2023-02-06

### Fixed
- An issue where order unable to create draft due to tag id mismatch. ([AcclaroInc#410](https://github.com/AcclaroInc/craft-translations/issues/410))

### Chore
- Code refactor (draft creation & publish)

## 3.2.0 - 2023-01-12

### Added
- Support for `Google machine translations`. ([AcclaroInc#409](https://github.com/AcclaroInc/craft-translations/pull/409))

### Chore
- Code refactoring and cleanup.

## 3.1.1 - 2022-12-29

### Added
- Dedicated `Add product` button on order detail page if commerce is installed. ([AcclaroInc#394](https://github.com/AcclaroInc/craft-translations/issues/394#issuecomment-1333708317))

### Fixed
- An issue where delivered translated content from api was not reflecting in draft. ([AcclaroInc#412](https://github.com/AcclaroInc/craft-translations/pull/412))

## 3.1.0 - 2022-11-14

### Added
- Support for craft commerce products/variants. ([AcclaroInc#394](https://github.com/AcclaroInc/craft-translations/issues/394))
- TM alignment files can now also be downloaded in XML & JSON format. ([AcclaroInc#406](https://github.com/AcclaroInc/craft-translations/pull/406))

### Updated
- Plugins control panel message & logging format. ([AcclaroInc#407](https://github.com/AcclaroInc/craft-translations/pull/407))

## 3.0.3 - 2022-10-12

### Fixed
- Error On Submitting Order ([AcclaroInc#400](https://github.com/AcclaroInc/craft-translations/issues/400))

## 3.0.2 - 2022-10-04

### Added
- Date selector in send logs page ([AcclaroInc#397](https://github.com/AcclaroInc/craft-translations/pull/397))
- New `getting quotes` & `needs approval` status filters on order index page sidebar ([AcclaroInc#395](https://github.com/AcclaroInc/craft-translations/pull/395))

### Updated
- UX for save button in `settings > config-options`([AcclaroInc#393](https://github.com/AcclaroInc/craft-translations/pull/393))
- Error opening order details when source entry has been deleted ([AcclaroInc#396](https://github.com/AcclaroInc/craft-translations/pull/396))

### Fixed
- Plugin settings url throwing error ([AcclaroInc#401](https://github.com/AcclaroInc/craft-translations/pull/401))

## 3.0.1 - 2022-09-06

### Fixed
- Unhandled exception on plugin installation ([AcclaroInc#387](https://github.com/AcclaroInc/craft-translations/issues/387))
- Exception opening plugin dashboard when acclaro feed is unavailable.

## 3.0.0 - 2022-08-24

### Fixed
- Track target content changes alerts ([AcclaroInc#488](https://github.com/AcclaroInc/pm-craft-translations/issues/488))
- An issue where order details page loads without order id when order is being submitted to api translator from queue.
- An issue where deleting draft from `globalsetdraft/assetdraft` detail page was removing order file.

### Updated
- Requires Craft `4.0.0+`
- Requires PHP `8.0.2+`
- Category draft's custom logic with craft's native code. (Craft now supports category drafts)
- Download TM files action disabled by default.
- Entry draft live-preview feature.

### Added
- Entry quick-edit feature ([AcclaroInc#490](https://github.com/AcclaroInc/pm-craft-translations/issues/490))
- Request a quote feature ([AcclaroInc#11](https://github.com/AcclaroInc/pm-craft-translations/issues/11))
- Logging in webhook flow for api orders.

### Removed
- Removed unsused `editDraftAssets`.
- Removed `translations_categorydrafts` table.

### Chore
- Code refactoring and cleanup.


## 2.2.3 - 2022-08-01

### Fixed
- An issue where target content was missing after merging into draft ([AcclaroInc#358](https://github.com/AcclaroInc/craft-translations/pull/358))
- An issue where exported redactor links were missing some required data ([AccclaroInc#364](https://github.com/AcclaroInc/craft-translations/pull/364))
- An issue where entry was missing assets fields after merging into draft ([AcclaroInc#362](https://github.com/AcclaroInc/craft-translations/pull/362))
- An issue where seomatic image field missing after merge into draft ([AcclaroInc#372](https://github.com/AcclaroInc/craft-translations/pull/372))
- A bug where global config settings for track source/target changes was interfering with submitted orders track changes light switch.
- An issue where category drafts were missing content from non-localized blocks and draft titles were updated but not saved ([AcclaroInc#375](https://github.com/AcclaroInc/craft-translations/pull/375)).
- An issue where category drafts were not editable and leading to error when saved or published from draft detail page.

### Updated
- To prevent target entry sync for old orders which were created before `track target content` feature was introduced.
- The disable logging behaviour changed from only disabling api calls to completely disabled plugin logs ([AcclaroInc#373](https://github.com/AcclaroInc/craft-translations/pull/373))
- Download/Sync TM files option only available when user can see alert icon for target mismatch.

## 3.0.0-beta.1 - 2022-07-14

### Fixed
- An issue where `New translations` button on entry index page remains active on switching entry groups.
- An issue where deleting draft from `globalsetdraft/assetdraft` detail page was removing order file.

### Updated
- Craft minimum version from 3.7.33 to 4.0.0
- Requires PHP ^8.0.2
- Category draft's custom logic with craft's native code. (Craft now supports category drafts)
- Download TM files action disabled by default.

### Added
- Support for Craft 4.
- Logging in webhook flow for api orders.

### Removed
- Removed unsused `editDraftAssets`.
- Removed `translations_categorydrafts` table.

### Chore
- Code refactoring and cleanup.

## 2.2.2 - 2022-07-06

### Fixed
- An issue where order fails to open when `previewUrl` length exceeds 255 chars. (#341)

### Updated
- Removed the use of `filetype` param in API order sync call.

## 2.2.1 - 2022-06-24

### Fixed

- TM alignment error parsing special characters ([AcclaroInc/#483](https://github.com/AcclaroInc/pm-craft-translations/issues/483))
- Track target content notice bug ([AcclaroInc/#484](https://github.com/AcclaroInc/pm-craft-translations/issues/484))
- TM alignment file export bug ([AcclaroInc/485](https://github.com/AcclaroInc/pm-craft-translations/issues/485))

### Updated

- `craftcms/cms` minimum version from `3.7.33` to `3.7.36` due to dependency vulnerabilities
- `guzzlehttp/guzzle` minimum version from `6.5.7` to `6.5.8` due to dependency vulnerabilities

## 2.2.0 - 2022-06-21

### Fixed
- Missing translation draft edits after applying ([AcclaroInc/#477](https://github.com/AcclaroInc/pm-craft-translations/issues/477))
- Duplicate resnames in exported XML ([AcclaroInc/#472](https://github.com/AcclaroInc/pm-craft-translations/issues/472))
- Issue with duplicated blocks and overwritten values in multiple working drafts
- Issue with localized matrix fields when order submitted with non-primary source site ([AcclaroInc/#467](https://github.com/AcclaroInc/pm-craft-translations/issues/467))
- Duplicate "Modified" Order status (#299)
- Acclaro orders sync issue (#334)
- Draft deletion on Order removal
- Draft application error with JSON and CSV formats
- Issue with order page auto-refresh on queue job completion

### Added
- Translation memory alignment ([AcclaroInc/#442](https://github.com/AcclaroInc/pm-craft-translations/issues/442))
- Enhanced UX for Order preview links ([AcclaroInc/#448](https://github.com/AcclaroInc/pm-craft-translations/issues/448))
- Vizy plugin support ([AcclaroInc/#305](https://github.com/AcclaroInc/pm-craft-translations/issues/305))
- Acclaro API `createOrder` request param `type` as `Website` ([AcclaroInc/#476](https://github.com/AcclaroInc/pm-craft-translations/issues/476))
- Allow file actions for "Applied" Orders ([AcclaroInc/#431](https://github.com/AcclaroInc/pm-craft-translations/issues/431))
- A confirmation warning when clearing orders from settings page

### Updated
- Craft minimum version from `3.7.14` to `3.7.33`

## 2.1.4 - 2022-03-14

### Updated
- ISO alias mapping API endpoint (https://github.com/AcclaroInc/craft-translations/pull/315)

### Fixed
- Plugin uninstallation bug (https://github.com/AcclaroInc/craft-translations/pull/313)

## 2.1.3 - 2022-02-21

### Fixed

- Entry relation for disabled target reference ([AcclaroInc/#455](https://github.com/AcclaroInc/pm-craft-translations/issues/455))
- `$order->siteId` to `$order->sourceSite` (https://github.com/AcclaroInc/craft-translations/issues/305)
- Nested Supertable bug ([AcclaroInc/#451](https://github.com/AcclaroInc/pm-craft-translations/issues/451))
- Missing title on "Merge into draft" bug ([AcclaroInc/#437](https://github.com/AcclaroInc/pm-craft-translations/issues/437))

## 2.1.2 - 2022-01-21

### Updated
- Order activity log text format ([AcclaroInc/#371](https://github.com/AcclaroInc/pm-craft-translations/issues/371))
- Activity log status format ([craft-translations/#290](https://github.com/AcclaroInc/craft-translations/pull/290))

### Fixed
- ISO alias mapping bug ([craft-translations/#289](https://github.com/AcclaroInc/craft-translations/issues/289))
- Exclude non-localized Element titles ([craft-translations/#294](https://github.com/AcclaroInc/craft-translations/issues/294))
- Tag label bug ([craft-translations/#292](https://github.com/AcclaroInc/craft-translations/pull/292))
- Source site selection bug ([craft-translations/#297](https://github.com/AcclaroInc/craft-translations/pull/297))
- Preview URL generation bug ([craft-translations/#298](https://github.com/AcclaroInc/craft-translations/pull/298))

## 2.1.1 - 2022-01-07

### Fixed
- Guzzle dependency conflict

## 2.1.0 - 2022-01-07

### Added
- Ability to track source content changes ([AcclaroInc/#359](https://github.com/AcclaroInc/pm-craft-translations/issues/359))
- Fetch ISO `$aliases` dynamically via GraphQL endpoint ([AcclaroInc/#40](https://github.com/AcclaroInc/pm-craft-translations/issues/40))
- Compatibility with Guzzle 7 (#281)
- Target sites meta to order sidebar ([AcclaroInc/#416](https://github.com/AcclaroInc/pm-craft-translations/issues/416))

### Updated
- Acclaro API to `v2.0` ([AcclaroInc/#400](https://github.com/AcclaroInc/pm-craft-translations/issues/400))
- Desktop & mobile UX for "Review changes" action ([AcclaroInc/#415](https://github.com/AcclaroInc/pm-craft-translations/issues/415))
- Refactor entries version mapping ([AcclaroInc/#427](https://github.com/AcclaroInc/pm-craft-translations/issues/427))

### Fixed
- Site "enabled" issue with custom propagation settings (#260)
- Misc. bugs ([AcclaroInc/#423](https://github.com/AcclaroInc/pm-craft-translations/issues/423)), ([AcclaroInc/#419](https://github.com/AcclaroInc/pm-craft-translations/issues/419)), ([AcclaroInc/#414](https://github.com/AcclaroInc/pm-craft-translations/issues/414)), ([AcclaroInc/#425](https://github.com/AcclaroInc/pm-craft-translations/issues/425)), ([AcclaroInc/#426](https://github.com/AcclaroInc/pm-craft-translations/issues/426)), ([AcclaroInc/#430](https://github.com/AcclaroInc/pm-craft-translations/issues/430)), ([AcclaroInc/#366](https://github.com/AcclaroInc/pm-craft-translations/issues/366))

### Chore
- Code refactoring and cleanup ([AcclaroInc/#399](https://github.com/AcclaroInc/pm-craft-translations/issues/399))

## 2.0.5 - 2021-11-16

### Fixed
- Issue with 'Rebuild draft preview'
- ISO string case sensitive matching error

## 2.0.4 - 2021-11-12

### Fixed
- 'Array to String Conversion' error (https://github.com/AcclaroInc/craft-translations/issues/255)
- 'Variable `webUrls` doesn't exist' error (https://github.com/AcclaroInc/craft-translations/issues/256)

## 2.0.3 - 2021-10-28

### Fixed

- `m210922_095949_add_ready_for_review_status` PostgreSQL migration issue (#231)
- Use `$file->source` instead of `$file->target` for "Modified Source Entries" source html
- Added margin to 'updates available' button on Translation dashboard

## 2.0.2 - 2021-10-25

### Added

- 'Review changes' modal UI/UX updates ([AcclaroInc/#382](https://github.com/AcclaroInc/pm-craft-translations/issues/382))
- 'New & Modified Source Entries' dashboard widget UI/UX updates ([AcclaroInc/#147](https://github.com/AcclaroInc/pm-craft-translations/issues/147))
- Download file action for API orders
- Support for custom preview target URIs ([AcclaroInc/#383](https://github.com/AcclaroInc/pm-craft-translations/issues/383))

### Updated

- `applyDrafts` logic to create and apply drafts individually v.s in bulk ([AcclaroInc/#387](https://github.com/AcclaroInc/pm-craft-translations/issues/387)), ([AcclaroInc/#388](https://github.com/AcclaroInc/pm-craft-translations/issues/388))

### Fixed

- Use the block's `canonicalId` instead of `typeId` (#237)
- PostgreSQL migration exception (#231)
- Resname mismatch issue ([AcclaroInc/#387](https://github.com/AcclaroInc/pm-craft-translations/issues/387))
- Misc. bug fixes ([AcclaroInc/#391](https://github.com/AcclaroInc/pm-craft-translations/issues/391)), ([AcclaroInc/#394](https://github.com/AcclaroInc/pm-craft-translations/issues/394)), ([AcclaroInc/#395](https://github.com/AcclaroInc/pm-craft-translations/issues/395))
## 2.0.1 - 2021-09-30

### Fixed

- Bug triggered when applying drafts
- Display notice for "Sync order" action

### Changed

- Display author name and image on create order form

## 2.0.0 - 2021-09-29

### Added

- Support for Craft 3.7.9+ ([AcclaroInc/#306](https://github.com/AcclaroInc/pm-craft-translations/issues/306))
- Enhanced "Review and publish" workflow with simplified source and target comparison, "Ready for review" statuses, copy text to clipboard, and more intuitive publishing actions. ([AcclaroInc/#351](https://github.com/AcclaroInc/pm-craft-translations/issues/351))
- Ability to select specific Entry Drafts for translation ([AcclaroInc/#298](https://github.com/AcclaroInc/pm-craft-translations/issues/298))
- Support for selecting Asset Elements for translations ([#168](https://github.com/AcclaroInc/craft-translations/issues/168)), ([AcclaroInc/#297](https://github.com/AcclaroInc/pm-craft-translations/issues/297))
- Orders now support JSON and CSV filetypes for download and upload actions
- Added "Update order" and "Create new order" actions making post-submission order updates (i.e., adding a file, language, etc.) much easier
- It's now possible to export Order Elements (CSV, JSON, and XML) ([AcclaroInc/#233](https://github.com/AcclaroInc/pm-craft-translations/issues/233))
- API orders now support manual file uploads ([AcclaroInc/#377](https://github.com/AcclaroInc/pm-craft-translations/issues/377))
- For API orders, it's now possible to cancel specific files or the entire order
- API orders now support custom tags ([AcclaroInc/#320](https://github.com/AcclaroInc/pm-craft-translations/issues/320))
- Include Order ID parameters in XML meta tags ([AcclaroInc/#352](https://github.com/AcclaroInc/pm-craft-translations/issues/352))

### Changed

- Translation drafts are now created when translations are approved and applied instead of order creation, reducing the likelihood of outdated drafts
- Orders now use a familiar single-page view for creating, reviewing, updating, and applying translations ([AcclaroInc/#10](https://github.com/AcclaroInc/pm-craft-translations/issues/10))

### Updated

- Translator archive and details pages now use a more familiar layout ([AcclaroInc/#322](https://github.com/AcclaroInc/pm-craft-translations/issues/322))
- "Recent Orders" widget now sorts by `dateUpdated`
- Acclaro API order-endpoint URL pattern ([AcclaroInc/#317](https://github.com/AcclaroInc/pm-craft-translations/issues/317))
- Application information indicators (i.e., app version, support links, etc.)

### Fixed
- Matrix block merge issue 'Attempting to merge source changes for a draft in an unsupported site.' ([AcclaroInc/#329](https://github.com/AcclaroInc/pm-craft-translations/issues/329))
- Matrix block merge issues 'Attempting to duplicate/save an element in an unsupported site.' ([AcclaroInc/#321](https://github.com/AcclaroInc/pm-craft-translations/issues/321))
- Matrix block merge issue 'Invalid owner ID' ([#211](https://github.com/AcclaroInc/craft-translations/issues/211))
- Neo block merge issue 'Invalid owner ID' ([AcclaroInc/#330](https://github.com/AcclaroInc/pm-craft-translations/issues/330))
- Other nested fields (Matrix, Neo, Super Table) draft merging issues introduced in Craft 3.7.9+ ([AcclaroInc/#215](https://github.com/AcclaroInc/pm-craft-translations/issues/215))
- Deprecated `getSourceId()` method. Now using `getCanonicalId()` ([#202](https://github.com/AcclaroInc/craft-translations/issues/202)), ([AcclaroInc/#319](https://github.com/AcclaroInc/pm-craft-translations/issues/319))
- "Modified Source Entries" widget loading issue ([AcclaroInc/#334](https://github.com/AcclaroInc/pm-craft-translations/issues/334))
- Order status not updating for Categories ([AcclaroInc/#308](https://github.com/AcclaroInc/pm-craft-translations/issues/308))
- Misc. bugs ([AcclaroInc/#314](https://github.com/AcclaroInc/pm-craft-translations/issues/314)), ([AcclaroInc/#335](https://github.com/AcclaroInc/pm-craft-translations/issues/335)), ([AcclaroInc/#284](https://github.com/AcclaroInc/pm-craft-translations/issues/284)), ([AcclaroInc/#349](https://github.com/AcclaroInc/pm-craft-translations/issues/349)), ([AcclaroInc/#324](https://github.com/AcclaroInc/pm-craft-translations/issues/324)), ([AcclaroInc/#345](https://github.com/AcclaroInc/pm-craft-translations/issues/345))

### Removed
- Deletion of auto-propagated drafts when applying translation drafts as it is no longer necessary as of Craft 3.7.9+

## 1.10.6 - 2021-06-24

### Fixed
- orderDueDate in install.php migration

## 1.10.5 - 2021-06-11

### Added
- Due date sync and display feature https://github.com/AcclaroInc/pm-craft-translations/issues/120
- Support for section propagation method "custom" https://github.com/AcclaroInc/craft-translations/issues/167
- Source Entry 'Edit' warning message https://github.com/AcclaroInc/pm-craft-translations/issues/287
- New release dashboard notification https://github.com/AcclaroInc/pm-craft-translations/issues/282

### Updated
- Discord notification action

### Fixed
- Asset titles bug https://github.com/AcclaroInc/craft-translations/issues/170
- Dashboard widget layout bug (Firefox) https://github.com/AcclaroInc/pm-craft-translations/issues/307

## 1.10.4 - 2021-05-03

### Added
- Queue manager performance enhancements

### Updated
- Require Composer `^2.0.13` due to [potential security vulnerability](https://github.com/advisories/GHSA-h5h8-pc6h-jvvx)

## 1.10.3 - 2021-04-06

### Updated
- Use `UrlHelper::baseSiteUrl()` instead of deprecated `App::env('SITE_URL')` for generating Order URLs

### Fixed
- Issue when applying drafts via Queue Manager

## 1.10.2 - 2021-03-11

### Fixed
- Resolved issue with CHANGELOG.md
- Disabled default Craft publishing on Translation drafts

## 1.10.1 - 2021-03-11

### Updated
- Translation Setting requirements

## 1.10.0 - 2021-03-11

### Added
- NSM Email field support [`newism\fields\fields\Email`](https://github.com/newism/craft3-fields/blob/master/src/fields/Email.php) (#139)
- NSM Telephone field support [`newism\fields\fields\Telephone`](https://github.com/newism/craft3-fields/blob/master/src/fields/Telephone.php) (#139)
- NSM Address field support [`newism\fields\fields\Address`](https://github.com/newism/craft3-fields/blob/master/src/fields/Address.php) (#139)
- NSM Embed field support [`newism\fields\fields\Embed`](https://github.com/newism/craft3-fields/blob/master/src/fields/Embed.php) (#139)
- Ether, SEO field support [`ether\seo\fields\SeoField`](https://github.com/ethercreative/seo/blob/v3/src/fields/SeoField.php#L24) (#136)
- Bi-direction Sync for Acclaro Order name updates (#134)
- Support for [Craft 3.6](https://github.com/craftcms/cms/blob/3.6.0/CHANGELOG.md#360---2021-01-26) (#144)
- Improved Order navigation (#149)

### Fixed
- Non-localized field interference within localized 'nested' blocks (#140)
- Source lang is included as target lang when selecting 'All' target languages   (#134)
- Adding Entries to a 'Saved' Order triggers new Order (#137)
- Prevent translation drafts from getting published via Craft (fd0bc80)
- Translation Draft URI structures (#145)

## 1.9.4 - 2021-01-18

### Added
- Custom Twig RegEx Filters for Static Translations https://github.com/AcclaroInc/craft-translations/pull/130
- Spinner icon to individual "Apply Translation" buttons https://github.com/AcclaroInc/craft-translations/pull/131
- `zh-Hans-CN` mapping to `SiteRepository.php` https://github.com/AcclaroInc/craft-translations/commit/acfc84dde21318bfb04a4e8970680aed12021699

### Fixed
- [BUG] - Target Site draft preview URI structures https://github.com/AcclaroInc/craft-translations/commit/be7bbe219730e5586477729996c1c5ecad84055d
- [BUG] - Disable the "Publish Changes" button within Entry detail screen https://github.com/AcclaroInc/craft-translations/pull/129
- [BUG] - Order status not updating to "Applied" https://github.com/AcclaroInc/craft-translations/pull/128

## 1.9.3 - 2020-12-16

### Fixed
- `loadTranslations()` issue for unavailable `targetSites`

### Added
- Improved plugin logging

## 1.9.2 - 2020-12-11

### Fixed
- `getFiles()` FileRecord query typo in `FileRepository.php`

## 1.9.1 - 2020-12-11

### Fixed
- `dateDeleted` bug in installation script

## 1.9.0 - 2020-12-08
### Added
- Support for Typed Link Field [2.0-beta](https://github.com/sebastian-lenz/craft-linkfield/tree/2.0.0-beta.10)
- Manual file import for Acclaro orders
- XML check to fail file imports with mismatched resnames
- Initial support for translation file soft-deletes

### Updated
- Required Craft version to `3.5.9` to resolve Yii2 security issue
- Improved activity log messaging for failed imports and applied draft

### Fixed
- Resolve medium security issues
- Minor bugs

## 1.8.3 - 2020-09-11

### Fixed
- Re-added deleteAutoPropagatedDrafts() when applying drafts

## 1.8.2 - 2020-08-13

### Fixed
- Issue with null $site in _includeGlobalSetResources()

## 1.8.1 - 2020-08-11

### Added
- Option to select all sites in target sites selection

### Fixed
- Unrendered HTML display issue in Craft 3.5+
- Typecast `$elementId` as an integer for `getElementById()` in `actionOrderDetail()`

### Updated
- Use the site ID instead of the site handle in `getDraftsByGlobalSetId()`

## 1.8.0 - 2020-08-06

### Added
- Support for [Category Entries](https://docs.craftcms.com/api/v3/craft-elements-category.html)
- Support for [Asset](https://docs.craftcms.com/api/v3/craft-elements-asset.html) fields
- Source v.s. translated content visual comparison
- Support for "New Translation" action buttons for recent versions of Craft
- Additional flexibility for bulk publishing actions

### Updated
- Matrix, SuperTable, and Neo fieldTranslator `toTranslationSource()` block indexing

### Fixed
- Minor bug fixes

## 1.7.2 - 2020-07-24

### Added
- Support for selecting Upload Volumes


## 1.7.1 - 2020-07-14

### Changed
- Moved `chkDuplicateEntries` setting from projectConfig to Plugin Settings

## 1.7.0 - 2020-05-14

### Added
- Support for managing static translations
- Minor UI fixes

## 1.6.0 - 2020-04-13

### Added
- Support links to About page
- Setting to disable duplicate message warning
- In Review status to API orders
- User-friendly exported ZIP filenames
- Support for Thai th-TH ISO code

### Updated
- Filenames for API orders to ensure they are unique
- Logic for adding & removing entries from new order screen

### Fixed
- Namespacing issue with static translations
- Craft 3.4+ incompatability with Globals translation drafts
- Resolved issue with pre-selected target sites on previously saved orders
- Redirect for failed orders due to unsupported ISO codes

## 1.5.1 - 2020-03-10

### Added
- Support for all propagation method options in nested field settings
- Ability to add entries directly from the create order form
- Basic handling of Acclaro "In Review" orders

### Updated
- Switch to word count vs entry count for task processing
- Minor bug fixes & additional updates

## 1.5.0 - 2020-02-25

### Added
- Support for Craft 3.4
- Alternative to background tasks for small requests
- Allow source entries to be added to existing orders
- Duplicate entry warning

### Fixed
- Minor bug fixes

### Updated
- Composer dependencies

## 1.4.3 - 2020-02-13

### Updated
- Filter out console requests for _onDeleteElement() event

## 1.4.2 - 2020-02-04

### Fixed
- Load static translations for radio, dropdown, and multiselect field types"

## 1.4.1 - 2020-01-30

### Update
- MultiOptionsFieldTranslator class to MultiSelectFieldTranslator

## 1.4.0 - 2019-10-18

### Added
- New source entries widget
- Improved errors for failed API requests
- Additional XML import error reporting
- Bug fixes

### Updated
- Updated order detail page
    - Compare file changes
- API request methods

## 1.3.0 - 2019-10-18

### Added
- [CodeMirror](https://github.com/luwes/craft3-codemirror) support
- Granular user permissions for translations
- Settings Page
    - Translation settings check for system requirements, propagation methods, and supported field types
    - Send logs to Acclaro
    - Clear translation orders
- Small UI improvements

### Fixed
- PostgreSQL `translations_translations` indexing issue

### Removed
- Translator Sites requirement and options

## 1.2.5 - 2019-10-01

### Fixed
- Added class declaration for Acclaro translator

## 1.2.4 - 2019-09-25

### Fixed
- SSL error in RSS News widget

## 1.2.3 - 2019-09-13

### Added
- Support for Craft 3.2+ draft publishing
- Ability to publish site-specific drafts without overwriting content in other sites
- Deletion of auto-propagated drafts
- Support for Craft 3.3+ global HTML markup
- Additional logic to prevent duplicate files within orders

### Changed
- Changed `job\UpdateEntries` to `job\ApplyDrafts`

### Fixed
- Bug with regenerating preview URLs for previously published drafts
- Typo in order detail page
- Bug related to GlobalSetDrafts element
- File import bug related to translatable dropdown fields

## 1.2.2 - 2019-08-22

### Fixed
- Fixed draft->id vs draft->sourceId discrepancy

## 1.2.1 - 2019-08-21

### Added
- Added `RegeneratePreviewUrls` background task
- Added `acclaro/UpdateReviewFileUrls` background task

## 1.2.0 - 2019-08-16

> {warning} Due to a fundamental [change in Crafts internal draft service](https://github.com/craftcms/cms/blob/master/CHANGELOG-v3.md#320---2019-07-09) in 3.2+, we are unable to retain previously created translation drafts. If you are upgrading from Craft 3.1 or below to Craft 3.2+ please refer to our [upgrade guide](https://github.com/AcclaroInc/craft-translations/wiki/Translations-Upgrade-Workflow-(Craft-3.1-to-3.2-)) for important upgrade information.

### Added
- Added `CreateDrafts` background task
- Added `DeleteDrafts` background task
- Added `ImportFiles` background task
- Added `SyncOrders` background task
- Added `UpdateEntries` background task
- Added support for nested fields stored on global basis
- Increased elementIds db char limit
- Update new order GET request to POST
- Delete drafts on uninstall
- Delete drafts on hard-deleted Orders
- Added soft-fail for file import

### Updated
- Updated `DraftRepository` to use new Craft draft service
- Better Entry updating UI

### Removed
- Removed `UpdateDraftFromXML`
- Truncate `translations_files` and `translations_orders` tables to be compliant with new Craft draft service

## 1.1.1 - 2019-07-03
### Added
- Added support for empty Entry URIs

## 1.1.0 - 2019-06-25
### Changed
- Added support for PostgreSQL
- Support Element soft-delete
- Minor UI updates
- Update actionUrl to token based
- Fixed bug for non-primary sites as source

## 1.0.2 - 2019-04-01
### Changed
- Added support `EntryDraft` types in `elements` table

## 1.0.1 - 2019-03-25
### Changed
- Fixed an issue with deeply nested Super Table fields

## 1.0.0 - 2019-03-19
### Added
- Initial release
