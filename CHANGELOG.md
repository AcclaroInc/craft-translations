# Translations for Craft Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

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
