# The Form Builder — a developer tutorial

This is a hands-on guide to how the **Account Custom Fields** form builder works. It's
written for a developer who is new to this plugin (and maybe fairly new to WordPress
plugins). Read it once from top to bottom, then keep the **Recipes** section handy.

By the end you'll understand:

- what the plugin actually does, in plain terms;
- the full journey of a field, from "admin drags it onto a canvas" to "value shown on a
  public author page";
- what every file is responsible for;
- how to add a new field type yourself;
- the security rules you must never break.

---

## 1. What is this thing?

WordPress has a base plugin called **Classified Listing** (a classifieds/directory
plugin). Logged-in users get an **Account Details** page where they edit their profile.

Our plugin is an **add-on**. It lets a site admin invent *extra* fields — "Company name",
"WhatsApp number", "Upload your CV", etc. — without writing any code. Those fields then:

1. appear on the front-end **Account Details** form, and on the WordPress **user profile**
   admin screens;
2. get validated and saved when the user submits;
3. optionally show up on the user's **public author page**.

The admin designs these fields using a drag-and-drop **form builder** (a small React app).

> **Mental model:** the admin *designs a form* → the plugin *renders that form for every
> user* → users *fill it in* → the plugin *validates, saves, and displays* the answers.

---

## 2. Two halves you must keep separate in your head

The whole plugin splits cleanly into two worlds. Confusing them is the #1 source of bugs,
so let's name them up front.

```
┌─────────────────────────────┐        ┌──────────────────────────────┐
│  A) THE BUILDER              │        │  B) THE RUNTIME              │
│  (admin designs the form)    │        │  (users fill the form)       │
│                              │        │                              │
│  React app in src/           │        │  PHP in includes/            │
│  Runs in wp-admin only       │        │  Runs on the front-end +     │
│  Talks to a REST API         │        │  wp-admin profile screens    │
│                              │        │                              │
│  Produces: FIELD DEFINITIONS │──────▶ │  Consumes: FIELD DEFINITIONS │
│  (the "shape" of the form)   │  saved │  Produces: USER VALUES       │
└─────────────────────────────┘  to DB └──────────────────────────────┘
```

- **A) The builder** decides *what fields exist and how they look*. Its output is a list of
  **field definitions**. A definition is just a small array like
  `{ id, label, type, options, required, ... }`. No user answers here — just the design.
- **B) The runtime** reads those definitions and renders real `<input>`s for the end user,
  then stores the user's answers.

They meet in one place: a WordPress **option** in the database (see §4).

---

## 3. Where everything lives (the map)

```
rtcl-account-fields/
├─ rtcl-account-fields.php   ← plugin entry / bootstrap
├─ includes/                               ← B) THE RUNTIME (+ the REST API)
│  ├─ class-fields.php     ← the brain: definitions, sanitize, validate
│  ├─ class-rest.php       ← REST API the builder talks to
│  ├─ class-admin.php      ← loads the React app in wp-admin
│  ├─ class-frontend.php   ← renders/validates/saves on the front-end + author page
│  └─ class-profile.php    ← same, for the wp-admin user profile screens
├─ src/                                     ← A) THE BUILDER (React source)
│  ├─ index.js             ← mounts the React app
│  ├─ app.js               ← all the builder's state + logic
│  ├─ api.js               ← wraps the REST calls
│  ├─ icons.js             ← inline SVG icons + the type→icon map
│  └─ components/          ← palette, canvas, sortable-field, settings-panel, toolbar
├─ build/                   ← COMPILED React (this is what WordPress actually loads)
└─ assets/css/frontend.css  ← front-end styling (plain CSS, not compiled)
```

> **Golden rule #1:** `src/` is the *source*. `build/` is the *compiled output* and is what
> runs in the browser. **Editing `src/` does nothing until you rebuild** (see §9.3).

---

## 4. Where the data is stored

Two completely different things, in two different places. Keep them straight:

| Thing | Stored as | Key | Set by | Example |
| --- | --- | --- | --- | --- |
| **Field definitions** (the form design) | one `wp_options` row | `rtcl_acf_fields` | the builder (admin) | `[{id:'company', type:'text', ...}, ...]` |
| **User values** (the answers) | `wp_usermeta` rows, one per field per user | `_rtcl_acf_<field_id>` | the runtime (each user) | user 42's `company` = `"Acme Ltd"` |

Both keys are defined as constants in `class-fields.php`:

```php
const OPTION      = 'rtcl_acf_fields';   // the whole form design, one option
const META_PREFIX = '_rtcl_acf_';        // + field id = one user's answer
```

> **Golden rule #2:** a field's `id` **is** its storage key. If an admin renames a field's
> id, its old saved answers are orphaned (a new meta key starts fresh). This is intentional
> and called out in the builder UI.

---

## 5. The life of a field (the whole flow, start to finish)

Follow one field — say a "Company name" text field — through its entire life.

### Step 1 — Admin opens the builder

`class-admin.php` registers an admin page (**Classified Listing → Account Fields**) and,
on that page only, enqueues the compiled React app from `build/`. It also injects a
JavaScript object called `window.rtclAcf` that hands the app its starting data:

```php
window.rtclAcf = {
  restNamespace: 'rtcl-acf/v1',
  fields:   [ ...existing field definitions... ],
  types:    { text: 'Text', textarea: 'Textarea', ... },
  defaults: { id:'', label:'', type:'text', required:false, ... },
  visibility: { all:'All', seller:'Seller', buyer:'Buyer' },
};
```

The React entry point `src/index.js` finds the `<div id="rtcl-acf-app">` and mounts
`<App>` (`src/app.js`) into it.

### Step 2 — Admin designs the form (all client-side)

Inside `app.js`, the builder is just a React state problem. The key piece of state is:

```js
const [ fields, setFields ] = useState(...);   // the working list of field definitions
```

Everything the admin does is a function that edits that array:

- **Palette** (`components/palette.js`) — the left column of draggable field types.
- **Canvas** (`components/canvas.js` + `sortable-field.js`) — the middle, showing the
  current fields as cards. Uses [`@dnd-kit`](https://dndkit.com/) for drag/drop + reorder.
- **Settings panel** (`components/settings-panel.js`) — the right column: edit the selected
  field's label, key, options, required, etc.
- **Toolbar** (`components/toolbar.js`) — the Save button + "Unsaved changes" / "Saved" badges.

Each field in the working list carries a **client-only** `_uid` (a throwaway id used as a
React key and for drag/drop). It is *not* saved — `api.js` strips it before sending.

Nothing has touched the server yet. The admin could refresh and lose it all — that's what
the "Unsaved changes" badge warns about.

### Step 3 — Admin clicks Save

`app.js#onSave` calls `saveFields()` in `src/api.js`, which POSTs the whole list to the
REST API:

```js
apiFetch({ path: '/rtcl-acf/v1/fields', method: 'POST', data: { fields: [...] } });
```

`apiFetch` is WordPress's fetch wrapper. It automatically attaches the security nonce, so
the request is authenticated as the logged-in admin. (More on why that matters in §8.)

### Step 4 — The server cleans and stores the definitions

`class-rest.php#save_fields` receives the POST. It does **not** trust the incoming data. It
runs it through `Fields::normalize_collection()` (in `class-fields.php`), which:

- drops any field with no label;
- forces `type` to be one of the known types (unknown → `text`);
- turns the `id` into a safe storage key (`sanitize_key`) and makes it unique;
- cleans options, description, placeholder, and the `visible_for` setting.

Then `Fields::save()` writes the cleaned list to the `rtcl_acf_fields` option. The endpoint
responds with the *normalized* list, and the React app replaces its working copy with it —
so what you see after saving is exactly what's stored.

**The builder's job is now done.** The rest is the runtime.

### Step 5 — A user opens their Account Details page

`class-frontend.php` hooked into the base plugin's account form:

```php
add_action( 'rtcl_edit_account_form', [ $this, 'render_account_fields' ], 99 );
```

`render_account_fields()` loads the definitions with `Fields::all()`, filters out fields
this user's account type shouldn't see (`Fields::field_visible_for_user()`), and renders a
real `<input>` for each. The per-type markup lives in a big `switch` in
`render_account_field()`.

The same fields also render on the wp-admin profile screens via `class-profile.php` (which
hooks `show_user_profile` / `edit_user_profile`).

### Step 6 — The user submits

The base plugin owns the account form's submit + nonce. It fires hooks; we listen:

```php
add_action( 'rtcl_my_account_validate_user_data', [ $this, 'validate' ] );  // check
add_filter( 'rtcl_update_user_account_data',       [ $this, 'save' ], 10, 2 ); // store
```

- **Validate** (`validate()`) loops the definitions and calls
  `Fields::validate_value()` for each. If anything is wrong (required-but-empty, bad email,
  etc.) it adds a message and the base plugin blocks the save.
- **Save** (`save()`) loops again, calls `Fields::sanitize_value()` to clean each answer,
  and hands the meta back to the base plugin to persist as `_rtcl_acf_<id>` user meta.

### Step 7 — The value appears on the public author page

`class-frontend.php` also hooks the author template:

```php
add_action( 'rtcl_author_details_after_meta', [ $this, 'render_author_fields' ] );
```

For each field flagged **Show on author page** (and visible to that account type) with a
non-empty value, it prints a label + value. Crucially, the value is escaped **per type** in
`format_author_value()` — a URL becomes a safe link, an email is anti-spam encoded, a
textarea gets `nl2br(esc_html())`, and so on.

That's the whole loop: **design → save definitions → render → validate → sanitize → store →
display.**

---

## 6. `class-fields.php` — the brain (read this file first)

Almost everything important goes through this one class. It's all `static` methods (it's a
utility/repository, not an object you instantiate). The parts you'll touch most:

| Method | What it does |
| --- | --- |
| `types()` | The master list of field types → human labels. **The source of truth for "what types exist."** |
| `defaults()` | The default shape of a single field definition. |
| `all()` | Load + normalize all definitions from the DB. |
| `save()` | Persist the definition list. |
| `normalize_collection()` | Clean raw definitions coming from the builder (trust boundary #1). |
| `sanitize_value()` | Clean one submitted **answer** by type (trust boundary #2, write path). |
| `validate_value()` | Check one submitted answer by type; returns an error string or `''`. |
| `validate_file()` / `save_file_field()` | The file-upload equivalents (uploads need `$_FILES`, so they're separate). |
| `field_visible_for_user()` | Should this field show for this user's account type? |

> **Golden rule #3:** `sanitize_value()` and `validate_value()` **unslash the value once
> internally** (`wp_unslash`). So callers must pass the **raw** `$_POST` value — do *not*
> `wp_unslash()` it first, or you double-unslash and corrupt values containing backslashes.

---

## 7. The supported field types

Defined in `Fields::types()`:

| Type | Renders as | Stored value | Notes |
| --- | --- | --- | --- |
| `text` | single-line input | string | the default/fallback |
| `textarea` | multi-line box | string | |
| `number` | number input | numeric string | |
| `email` | email input | sanitized email | validated with `is_email()` |
| `url` | url input | cleaned URL | validated + `esc_url` on display |
| `tel` | phone input | `"+CC local"` | reuses the base plugin's country-code widget; default/allowed countries come from **Settings → Misc → Phone** |
| `select` | dropdown | one option | value must be one of the configured options |
| `radio` | radio group | one option | " |
| `checkbox` | one or many checkboxes | `'1'` **or** an array | with options → multi-value array; without → single yes/no |
| `switch` | on/off toggle | `'1'` or `''` | boolean; behaves like a single checkbox |
| `file` | file/image upload | attachment id | stored in the Media Library |

Some types are grouped by helper methods you'll reuse:

- `option_types()` → `['select','radio','checkbox']` (types that have an options list).
- `is_multi($field)` → true only for a checkbox *with* options (stores an array).

---

## 8. Security — the rules you must not break

This plugin follows a strict **"never trust input, always escape output"** discipline.
When you add code, keep it that way.

1. **Definitions are cleaned on save.** Everything from the builder passes through
   `normalize_collection()`. Field `id`s become `sanitize_key` (safe as both a meta key and
   an HTML `name`). Never store raw builder input.
2. **Answers are cleaned + validated per type.** Every user answer goes through
   `sanitize_value()` (write) and `validate_value()` (check). `select`/`radio` only accept a
   value that's actually one of the configured options — you can't inject arbitrary values.
3. **Output is escaped at every sink.** `esc_attr` / `esc_html` / `esc_textarea` / `esc_url`
   in the form markup; the author page escapes **per type** in `format_author_value()`.
   If you add markup, escape it — no exceptions.
4. **Capabilities are checked everywhere.**
   - Builder REST API + admin page → `manage_options` (admins only).
   - Profile screens → `current_user_can('edit_user', $user_id)` + our own nonce.
   - Front-end save/validate → run only inside the base plugin's nonce-verified flow, and
     only ever write the current/target user's own meta.
5. **REST is CSRF-safe for free.** Because the app is enqueued with `wp-api-fetch`,
   WordPress attaches a nonce to every request; a request without a valid nonce is treated
   as logged-out and fails the `manage_options` check.
6. **File uploads are locked down.** `media_handle_upload` with an explicit mime allow-list,
   `wp_check_filetype_and_ext` (blocks disguised extensions), and size limits.

---

## 9. Recipes

### 9.1 Add a new field type (worked example: a "Switch")

This is the most common change, and it touches several files because a "type" has to be
understood by both halves of the plugin. Here's the complete checklist. (The `switch` type
already exists — it's used here as the reference example.)

**Backend — `includes/class-fields.php`:**

1. **Register it** in `types()`:
   ```php
   'switch' => __( 'Switch', 'rtcl-account-fields' ),
   ```
2. **Sanitize it** in `sanitize_value()` (a switch is a boolean, so it shares the single
   checkbox branch):
   ```php
   case 'switch':
   case 'checkbox':
       return '' === $raw ? '' : '1';
   ```
3. **Validate it** in `validate_value()` — only if it needs type-specific rules. A switch
   doesn't: the generic "required means non-empty" check already covers it, so no case is
   needed.

**Backend — render it on both surfaces:**

4. Add a `case 'switch':` to the `switch` in `class-frontend.php#render_account_field()`
   (front-end form) **and** in `class-profile.php#render_row()` (wp-admin profile). If you
   skip one, the field silently won't render there.
5. If it should appear on the public author page, add a `case` in
   `class-frontend.php#format_author_value()` (the switch returns "Yes").

**Frontend builder — `src/`:**

6. **Give it an icon** in `src/icons.js`: write an `IconSwitch` component and add it to the
   `TYPE_ICONS` map (and a one-liner in `TYPE_HINTS`). The palette and canvas cards read
   these maps, so the new type appears automatically.
7. **Adjust the settings panel** in `src/components/settings-panel.js` only if the type has
   special settings. A switch has no placeholder, so it's added to the `NO_PLACEHOLDER`
   list. Option-based types would go in `OPTION_TYPES` instead.

**Styling + build:**

8. Add any CSS (e.g. the toggle look) — front-end styles go in `assets/css/frontend.css`.
9. **Rebuild** (`npm run build`) so `build/` picks up the `src/` changes.

> **Tip:** grep for an existing similar type before you start. To add a type *like* a
> checkbox, search `grep -rn "'checkbox'" includes/ src/` and mirror every hit.

### 9.2 Add a new per-field setting (e.g. a "read only" flag)

1. Add the key + default to `Fields::defaults()`.
2. Clean it in `Fields::normalize_collection()` (cast/whitelist it — never store it raw).
3. Add a control for it in `src/components/settings-panel.js` that calls
   `onChange({ your_key: value })`.
4. Use it wherever it applies (render/validate/save).
5. Rebuild.

### 9.3 Build & deploy

```bash
npm install      # once, to get dependencies
npm run start    # watch mode: rebuilds build/ as you edit src/
npm run build    # one-off production build (do this before committing)
npm run zip      # production build + package a distributable plugin .zip
```

`build/` is committed to the repo because it's what WordPress loads at runtime. **A `src/`
change is invisible until you rebuild.** Plain CSS in `assets/css/` is *not* compiled — edit
it directly (but bump the cache, or hard-refresh, since it's versioned by plugin version).

### 9.4 Debugging checklist

- **My new field type doesn't show up in the builder.** You edited `src/` but didn't
  rebuild, or you forgot to add it to `TYPE_ICONS`/`types()`. Run `npm run build` and hard-refresh.
- **The field shows in the builder but not on the front-end.** You added the `case` to the
  frontend/profile render `switch`? Is the field's **Visible for** hiding it from your
  account type?
- **Saved value looks mangled (missing backslashes/quotes).** You probably double-unslashed —
  see Golden rule #3. Pass raw `$_POST` to the `Fields` helpers.
- **"Builder assets are not compiled" notice.** `build/` is missing — run `npm run build`.
- **REST save returns 403.** The nonce is missing/expired (e.g. you're not really logged in
  as an admin, or the page was open a long time). Reload the admin page.
- **Value not saving on the front-end.** Check the field passed `validate()` (a required
  field elsewhere on the form can block the whole save), and that `submitted()` sees your
  input group.

---

## 10. Boot & the hard dependency (one more thing to know)

The entry file `rtcl-account-fields.php` boots on `plugins_loaded` at
**priority 20** — after the base Classified Listing plugin (priority 10) — so the base's
functions are guaranteed available.

If the base plugin is **not active**, this add-on loads *nothing*: no classes, no hooks,
just a single admin notice. So if you're adding new startup code, put it inside
`rtcl_acf_bootstrap()` — never register hooks at the top level, or you'll break the "stays
dormant without the base plugin" guarantee.

---

## Quick reference

```
Field DEFINITION lives in:  wp_options → 'rtcl_acf_fields'
Field VALUE lives in:       wp_usermeta → '_rtcl_acf_<field_id>'
Type registry:              Fields::types()          (class-fields.php)
Clean definitions:          Fields::normalize_collection()
Clean an answer:            Fields::sanitize_value()
Check an answer:            Fields::validate_value()
Builder REST API:           /rtcl-acf/v1/fields      (class-rest.php)
Builder React root:         #rtcl-acf-app            (src/index.js → src/app.js)
Rebuild after src/ edits:   npm run build
```
