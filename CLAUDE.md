# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A WordPress **add-on plugin** for the base **Classified Listing** plugin. It lets admins define extra custom fields (via a React drag-and-drop builder) that render on the front-end **Account Details** form, the WordPress **user-profile** screens, and the public **author page**.

Text domain: `rtcl-account-fields`. PHP namespace: `RtclAcf`. All constants/options/meta are prefixed `RTCL_ACF_` / `rtcl_acf_` / `_rtcl_acf_`.

## Build

The admin builder is a React app compiled with `@wordpress/scripts`. **The compiled `build/` directory is what the plugin loads at runtime and is committed to the repo — always rebuild after changing anything in `src/`.**

```bash
npm install          # once
npm run build        # production build → build/
npm run start        # watch mode
npm run lint:js      # ESLint over src/
npm run format       # format src/
npm run zip          # production build + package classified-listing-account-fields.zip
```

There is no PHP or JS test suite. Verify PHP changes against a live WordPress install with the base plugin active.

## Architecture

### Boot & hard dependency (`classified-listing-account-fields.php`)
Boots on `plugins_loaded` at **priority 20** (base plugin boots at 10) so `rtcl()`/`RTCL_VERSION` are guaranteed present. If the base plugin is absent, the add-on stays **completely dormant** — no classes load, no hooks register — and only shows an admin notice. Preserve this: never register hooks or load classes outside `rtcl_acf_bootstrap()`.

### `includes/class-fields.php` — the core
Static repository + the single source of truth for field shape, sanitization, and validation. Everything else calls into it. Key points:
- Field **definitions** are stored as one array in the `rtcl_acf_fields` wp_option. **Values** are stored per user in user meta keyed `_rtcl_acf_<field_id>` (`Fields::meta_key()`).
- The field `id` **is** the storage key — changing an existing field's id orphans its stored values.
- `normalize_collection()` is the trust boundary for builder input; `sanitize_value()` / `validate_value()` / `validate_file()` are the trust boundary for submitted user data. **The server never trusts client input** — always route new field logic through these per-type switches rather than handling values inline.
- Field types: `text, textarea, number, email, url, tel, select, radio, checkbox, file`. `checkbox` with options is **multi-value** (stored as an array); without options it's a single yes/no. `select`/`radio`/`checkbox` are the "option types". `file` stores an attachment id and is handled separately (uploads need `$_FILES`, so it bypasses the normal value pipeline).
- `visible_for` (`all`/`seller`/`buyer`) gates a field by the user's Classified Listing account type (`_rtcl_user_type`). Unknown type shows everything (fail-open). Always guard render/validate/save with `field_visible_for_user()` so hidden fields are never validated or wiped.

### Integration surfaces (each a separate class, all instantiated in bootstrap)
- **`class-admin.php`** — registers the submenu under `rtcl-admin`, enqueues the built React bundle on its page only, and injects the `window.rtclAcf` bootstrap object (fields, types, defaults, visibility labels, REST namespace). Themes the builder with the base plugin's primary color via inline CSS vars.
- **`class-rest.php`** — `rtcl-acf/v1/fields` GET/POST, `manage_options` only. POST replaces the **whole** collection with a normalized copy (bulk save; there is no per-field endpoint).
- **`class-frontend.php`** — front-end account form + public author page. Integrates purely through **base-plugin hooks** (see table below), so it adds nothing when the base is inactive. Both `validate()` and `save()` gate on `submitted()` so a theme that drops the form section can't cause required-field errors or wipe stored values.
- **`class-profile.php`** — the same fields on `profile.php` / `user-edit.php`, using standard WP profile hooks. This path is **nonce-protected** (`rtcl_acf_profile`); the front-end path relies on the base plugin's own nonce/auth.

### Base-plugin hooks used (front-end)
| Purpose | Hook |
| --- | --- |
| Render on account form | `rtcl_edit_account_form` (action) |
| Validate submitted values | `rtcl_my_account_validate_user_data` (action) |
| Sanitize & save values | `rtcl_update_user_account_data` (filter) |
| Display on author page | `rtcl_author_details_after_meta` (action) |

`rtcl_author_details_after_meta` is a **single additive hook that had to be added to the base plugin** (`templates/listing/author-content.php`) — it's the only base-plugin change. A theme overriding that template must re-add the `do_action()` line for author-page display to work.

### React builder (`src/`)
`index.js` mounts `App` into `#rtcl-acf-app`. `app.js` holds all state: a working copy of fields each with a client-only `_uid` (stripped before save in `api.js#toServer`). Uses `@dnd-kit` for the palette→canvas drop and reorder. Components: `palette`, `canvas`, `sortable-field`, `settings-panel`, `toolbar`. Save posts the full collection and re-hydrates from the server response, so ids are always server-normalized after save.

## Conventions
- Follow WordPress coding standards (tabs, Yoda conditions, `phpcs` directives inline where escaping/nonce checks are intentionally handled elsewhere). Match the existing per-type `switch` structure when adding a field type — a new type generally touches `Fields::types()`, the render switches in **both** `class-frontend.php` and `class-profile.php`, and the sanitize/validate switches in `class-fields.php`.
- All user-facing strings use `__()`/`esc_html__()` with the `rtcl-account-fields` domain.
- Author-page output is escaped **per type** in `format_author_value()`, not at the echo site — keep that pattern.
