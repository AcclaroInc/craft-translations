# Frequently Asked Questions

## Plugin Support

<details><summary>What fields, elements, and plugins are supported?</summary>

### Craft CMS Elements
- [Categories](https://craftcms.com/docs/5.x/reference/element-types/categories.html)
- [Entries](https://craftcms.com/docs/5.x/reference/element-types/entries.html)
- [Assets](https://craftcms.com/docs/5.x/reference/element-types/assets.html)
- [Globals](https://craftcms.com/docs/5.x/reference/element-types/globals.html)

### Craft CMS Fields
- [Assets](https://craftcms.com/docs/5.x/reference/field-types/assets.html)
- [Categories](https://craftcms.com/docs/5.x/reference/field-types/categories.html)
- [Checkboxes](https://craftcms.com/docs/5.x/reference/field-types/checkboxes.html)
- [Dropdown](https://craftcms.com/docs/5.x/reference/field-types/dropdown.html)
- [Entries](https://craftcms.com/docs/5.x/reference/field-types/entries.html)
- [Matrix](https://craftcms.com/docs/5.x/reference/field-types/matrix.html)
- [Multi-select](https://craftcms.com/docs/5.x/reference/field-types/multi-select.html)
- [Number](https://craftcms.com/docs/5.x/reference/field-types/number.html)
- [Plain Text](https://craftcms.com/docs/5.x/reference/field-types/plain-text.html)
- [Radio Buttons](https://craftcms.com/docs/5.x/reference/field-types/radio-buttons.html)
- [Table](https://craftcms.com/docs/5.x/reference/field-types/table.html)
- [Tags](https://craftcms.com/docs/5.x/reference/element-types/tags.html)

### 3rd Party Plugins
- [Craft Commerce](https://plugins.craftcms.com/commerce?craft5)
  - Commerce Products
  - Commerce Variants
- [CKEditor](https://plugins.craftcms.com/ckeditor?craft5)
- [SEOmatic](https://plugins.craftcms.com/seomatic?craft5)
- [Ether SEO](https://plugins.craftcms.com/seo?craft5)
- [Neo](https://plugins.craftcms.com/neo?craft5)
- [Hyper](https://plugins.craftcms.com/hyper?craft5)
- [LinkIt](https://plugins.craftcms.com/linkit?craft5)
- [Visy](https://plugins.craftcms.com/vizy?craft5)
- [Navigation](https://plugins.craftcms.com/navigation?craft5)
- [Super Dynamic Fields](https://plugins.craftcms.com/super-dynamic-fields?craft5)
  - Dropdown
  - Radio Buttons
  - Checkboxes
  - Multi-select
- [Super Table](https://plugins.craftcms.com/super-table?craft5)

*Need support for a specific plugin? [Open an issue](https://github.com/AcclaroInc/craft-translations/issues/new).*

</details>

## Translation Services

<details><summary>Does the purchase of the plugin include Translation Services?</summary>

The purchase of the Translations plugin is for the software only. For Acclaro's Professional Translation Services, please contact sales@acclaro.com.

</details>

## Best Practices

<details><summary>Should I use a staging site to manage my translations?</summary>

Yes, we recommend testing the Translation plugin and localization workflow on a staging site to ensure everything works as expected before applying changes to your live production site.

</details>

## Technical Issues

<details><summary>Why are my static translations being overwritten in production?</summary>

Sometimes, production deployments use **atomic, zero-downtime** techniques that replace the entire application instead of applying incremental updates. As a result, your static translation files (for example, `/translations/es/Site.php`) may be overwritten during deployment.

To ensure that your static translations persist across deployments, follow these steps:

1. **Create a Persistent Directory**  
   Start a new shell session on your production server and create a shared directory:
   ```bash
   mkdir -p /var/www/shared/translations
   ```

2. **Set Folder Permissions**  
   Ensure that the user running your application (e.g., `www-data` for Craft) has access to the shared directory:
   ```bash
   chown -R www-data:www-data /var/www/shared
   chmod -R 755 /var/www/shared
   ```

3. **Configure the Translations Path**  
   In your `web/index.php`, update the environment variable to point to the persistent translations directory:
   ```php
   define('CRAFT_TRANSLATIONS_PATH', '/var/www/shared/translations');
   ```

4. **Upload Your Static Translation Files**  
   Move your static translation files into the new persistent directory and confirm they are in place:
   ```bash
   ls -la /var/www/shared/translations
   ```

Following these steps ensures that updates to your static translations will persist across deployments.

</details>

<details><summary>How do I display translated values for dropdown, multi-select, and radio fields?</summary>

To display translated string values for these fields, use the Twig `|t` filter with the 'translations' category in your templates. For example: `'some string'|t('translations')`.

</details>

<details><summary>My site has translatable fields within "non-translatable" blocks such as Matrix, Neo, Super Table. How can I fix issues with content resetting?</summary>

Ensure that field propagation settings store blocks on a per-site basis and that all fields requiring translation have their translatable settings enabled, including nested fields. Create new test orders and review the translations to see if the issue is resolved. For more details, refer to the [Craft CMS issue](https://github.com/craftcms/cms/issues/5503) and [Neo issue](https://github.com/spicywebau/craft-neo/issues/355).

</details>

## Order Processing

<details><summary>I'm not getting redirected from the "Thank You" page after submitting my order. What can I do?</summary>

Order processing time depends on the number of Entries and target Sites. For larger orders, Craft's background task manager is used. Check the job status by clicking on the queue manager if processing takes longer than expected.

</details>

---

For further assistance, please contact our support team at support@acclaro.com.
