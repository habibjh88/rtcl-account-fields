# Classified Listing – Account Custom Fields

Add-on for the **Classified Listing** plugin. Lets an admin define extra custom
fields that are:

1. Appended to the front-end **Account Details** page (the my-account edit form), and
2. Displayed on the public **author page**, below the built-in contact details.

## Features

- Modern **React drag-and-drop form builder** under **Classified Listing → Account Fields**:
  drag field types from the palette onto the canvas, reorder by dragging, and edit each
  field in a live settings panel.
- Per field: label, description, type, placeholder, options (for select), *required*, and *show on author page*.
- Field types: text, textarea, number, email, URL, phone, select, checkbox.
- Fields also render, validate and save on the WordPress **user profile** screens
  (`profile.php` / `user-edit.php`).
- Full server-side **validation & sanitization** per type (never trusts client input).
- **Hard dependency** on the base plugin: if Classified Listing is deactivated, this
  add-on stays completely dormant (no hooks run) and shows an admin notice.

## Development / build

The admin builder is a React app compiled with [`@wordpress/scripts`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/).
The compiled bundle in `build/` is what the plugin loads at runtime, so **rebuild after
changing anything in `src/`**:

```bash
npm install      # once
npm run build    # production build → build/
npm run start    # watch mode while developing
npm run zip      # build + package a distributable plugin zip
```

`npm run zip` runs a production build and then bundles the plugin into
`rtcl-account-fields.zip` (runtime files only — `node_modules`,
`src`, and build config are excluded). The zip installs via **Plugins → Add New →
Upload Plugin**.

The React builder talks to a REST API (`rtcl-acf/v1/fields`, `manage_options` only)
that reads and bulk-saves the field definitions. Requires WordPress 6.6+ (for the
bundled React runtime).

## How it integrates (no core hacks required for the form/save path)

The add-on uses the base plugin's existing hooks:

| Purpose | Hook |
| --- | --- |
| Render fields on the account form | `rtcl_edit_account_form` (action) |
| Validate submitted values | `rtcl_my_account_validate_user_data` (action) |
| Sanitize & save values | `rtcl_update_user_account_data` (filter) |
| Display on author page | `rtcl_author_details_after_meta` (action) |

Field values are stored in user meta under the key `_rtcl_acf_<field_id>`.

## The one base-plugin change

The public author template had no extension point where the fields should appear.
A **single additive** action hook was added to the base plugin:

`templates/listing/author-content.php`

```php
do_action( 'rtcl_author_details_after_meta', $user_id );
```

It fires inside the author card, after the built-in meta and social links. This is
additive and backward-compatible. If your active theme overrides
`classified-listing/listing/author-content.php`, add the same `do_action(...)` line
to your override so the custom fields display there too.

## Install

Copy this folder into `wp-content/plugins/` and activate it (requires the
Classified Listing base plugin to be active).

## Changelog

### 1.0.0

- Initial release.
- React drag-and-drop form builder under **Classified Listing → Account Fields**.
- 11 field types: text, textarea, number, email, URL, phone, dropdown, radio, checkbox, switch, and file upload.
- Fields render, validate, and save on the front-end **Account Details** form, the **registration** form, and the WordPress **user profile** screens.
- Optional display of each field on the public **author page**, formatted per type.
- Per-field settings: label, description, placeholder, required, options, min/max, buyer/seller visibility, and file type/size limits.
- Full server-side validation and sanitization for every field type.
- License key + automatic updates via **Classified Listing → Tools → Licensing**.
