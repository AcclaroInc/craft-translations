# User Guide for Translations Plugin

Welcome to the Translations Plugin User Guide. This guide will walk you through the essential steps to set up and use the Translations plugin for Craft CMS effectively.

## 🔔 Pre-Installation Reminder

Before installing the plugin, back up your site. We recommend testing multisite settings and configuration changes on a staging site before implementing them in a production environment.

## 1. System Requirements

Ensure your site meets the following requirements:
- **Craft CMS:** Version 5.0.0 or later
- **PHP:** Version 8.2 or higher
- **PHP DOM Extension:** Required
- **Craft Multi-site Configuration:** Required
- **Section Propagation:** Set to "Propagate entries across all enabled sites"
- **Field Translation Method:** Recommended to "Translate for each language"
- **Nested Blocks:** Use "Only save blocks to the site they were created in" for better translation management

## 2. Setting Up Translators

### Local Export/Import
- **Purpose:** Allows you to export content as XML, JSON, or CSV files for manual translation.
- **Setup:** A default Export/Import translator is created upon installation.

### Acclaro Translation Services
- **Purpose:** Provides professional translation services through Acclaro’s human translators.
- **Setup:** Requires a MyAcclaro API token. [Register here](https://info.acclaro.com/my-acclaro-registration) to obtain a token.

### Google MT Translator
- **Purpose:** Enables machine translation using Google's translation services.
- **Setup:** Requires a Google API Token. [Learn how to obtain a Google API Token](https://cloud.google.com/translate/docs/setup).
  
To create a new translator, navigate to "Translators" in the sidebar and click "New translator". For Google MT, select "Google MT" as your service, enter your Google API Token, and authenticate.

## 3. Creating a Translation Order

1. **Navigate to Orders:** Click "Orders" in the sidebar, then "New order".
2. **Order Details:**
   - Add a name for the order.
   - Select the content to translate.
   - Choose a source site and target sites (languages).
   - Select a translator.
3. **Review & Submit:** Ensure all details are correct before submitting the order.

## 4. Publishing Translations

- **Individual Publishing:** Click on the "Target Site(s)" link for each language to review and publish drafts.
- **Bulk Publishing:** Select entries and click "Apply Translations Drafts" to publish multiple entries at once.

## 5. Translation Readiness Check

Before starting translation projects, perform the following checks:
- **Database Backup:** Ensure you have a backup.
- **Plugin Installation:** Install on a staging site first to check for errors.
- **Settings Verification:** Confirm field and section translation settings are correct.
- **3rd Party Plugin Support:** Verify compatibility with installed plugins.

## 6. Local Export/Import Workflow

- **Export Content:** Navigate to your order dashboard, select an order, and click "Export" to download content.
- **Import Translations:** Once translated, upload the XML files via the "Import" button.

## 7. Acclaro Sync Workflow

- **Automatic Handling:** The Acclaro API manages exporting and importing translations.
- **Sync Translations:** Click "Sync" on the order dashboard to retrieve translated content. The status will update to "Ready to Publish" once complete.

## Additional Information

For more detailed setup instructions, recommended workflows, and FAQs, please refer to the [User Guide](./user-guide.md).

---

For further assistance, please contact our support team at support@acclaro.com.