# Translations for Craft Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 1.9.4 - 2021-01-18

## Added
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

## Added
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
- Added `acclaro/UdpateReviewFileUrls` background task

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
