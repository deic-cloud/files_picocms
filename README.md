# files_picocms — Pico CMS sites on Nextcloud

Serve static Markdown-based websites from a user's Nextcloud files, with
in-browser editing for owners and collaborators.

**Author:** Frederik Orellana, Technical University of Denmark (fror@dtu.dk)  
**License:** AGPL-3.0

---

## Overview

`files_picocms` exposes any directory in a user's NC files as a
[Pico CMS](https://picocms.org/) website at a stable public URL.  Pages are
Markdown files; metadata (title, date, author, access, …) lives in YAML
front-matter.  The app bundles a copy of Pico 2.x, Parsedown Extra, and a
blog-style Twig theme; no composer install or build step is required.

### URL scheme

```
/remote.php/files_picocms/sites/{name}[/{page}]    — named site (registered in DB)
/remote.php/files_picocms/users/{email}[/{page}]   — user public page
```

All requests are handled by `appinfo/serve.php`, which runs inside the
Nextcloud bootstrap environment and has full access to NC services.

### Pretty URLs

With rewrite rules in the web server, sites can be served at
`/sites/{name}` and `/users/{email}` directly. Two pieces:

1. Web server rewrite (Apache):

```apache
RewriteRule ^/?sites/(.*)$ /remote.php/sites/$1 [PT,L]
RewriteRule ^/?users/(.*)$ /remote.php/users/$1 [PT,L]
```

   or Caddy:

```caddy
@picocms path /sites /sites/* /users /users/*
rewrite @picocms /remote.php{uri}
```

   The target is `/remote.php/sites/…`, NOT `…/files_picocms/sites/…`:
   Nextcloud derives the remote *service* name from the original
   `REQUEST_URI` (which internal rewrites do not change), so the first path
   segment of the public URL must itself be a registered service. The app
   registers `sites` and `users` as remote services in `info.xml` for
   exactly this purpose (on manual installs make sure
   `occ config:app:get core remote_sites` returns
   `files_picocms/appinfo/serve.php`, likewise `remote_users` — the app
   installer writes these on install/update).

2. Tell the app to generate matching links, in `config.php`:

```php
'files_picocms.url_prefix' => '',
```

The parameter is the URL path prefix for all generated site/user links
(default `/remote.php/files_picocms`). Incoming requests are accepted in
both forms regardless of the setting, and redirects between nodes preserve
whichever form the visitor used — but set the parameter the same on every
node, and only together with the rewrite rules: with `''` and no rules,
every generated link 404s. Note that `/sites` and `/users` become reserved
top-level paths on the host.

---

## Site configuration

Place a `_config.md` file at the site root (or in any subdirectory for
sub-site config).  It is never rendered as a page (leading `_` is blocked).

Recognised front-matter keys:

| Key | Default | Description |
|-----|---------|-------------|
| `access` | `public` | `public` — anyone; `private` — NC session required |
| `theme` | `blog` | Theme directory name under `themes/` |
| `title` | site name | Overrides the DB-registered site name |
| `description` | — | Passed to Pico; used by the theme |
| `author` | — | Author name, available to themes |
| `EditLinks` | — | `yes` to show per-post Edit buttons |
| `icon` | — | Nav-bar icon, path relative to site root; also used as favicon if no `favicon` is set |
| `favicon` | — | Path to favicon relative to site root |

When a site is created through the wizard (`POST /create`), a commented
default `_config.md` is written to the site root, pre-populated with the
site name, chosen theme, and a detected or copied `img/data_icon.png`.

---

## Write access and in-browser editing

### Who can write

- **Site owner** — the NC user whose files contain the site directory.
- **Collaborator** — any NC user who has been granted update-permission
  share access to the site folder (see *Share detection* below).

### Share detection

`serve.php` runs two strategies to determine if the current user has write
access:

**Strategy 1 — local mount lookup (same silo)**  
If the collaborator is on the same NC instance as the owner, NC's
`IRootFolder::getById()` finds the shared node in the collaborator's mount
tree.  If the node reports `isUpdateable()`, write access is granted.  This
handles standard TYPE_USER local shares.

**Strategy 2 — outgoing federated shares (cross-silo)**  
In a sharded / trusted federation, a share from the owner to
`collaborator@master` is recorded in the owner's silo as a TYPE_REMOTE
(`share_type = 6`) outgoing share.  `serve.php` iterates the owner's
outgoing TYPE_REMOTE and TYPE_USER shares, resolves the `share_with` cloud ID
to a plain username, and compares it with the currently authenticated user's
UID.  This covers the case where the owner is on silo2 and the collaborator's
ghost account on silo2 (created by `files_sharding` exchange) matches the
share recipient.

### Write proxy

All file writes (new posts, edits, comments) go through `serve.php` itself
rather than NC's WebDAV endpoint.

**Why a proxy instead of WebDAV?**  
In a `files_sharding` trusted federation, federated shares between users on
different silos work correctly over WebDAV.  For example, when alice (silo2)
shares her blog directory with `bob@master`, and bob's home silo is silo1,
the federation mount appears in bob's file tree on silo1.  Bob can write to
alice's files on silo2 via silo1's WebDAV at
`https://silo1.example.org/remote.php/webdav/{alice_mount}/`.  The
federation mechanism itself is sound.

However, the blog page is served from silo2, while bob's federation mount
lives on silo1 — a different origin (different hostname or port).  A direct
WebDAV PUT from silo2 JS to silo1 WebDAV is a cross-origin request and
requires:

1. CORS headers on silo1's WebDAV endpoint  
   (`Access-Control-Allow-Origin`, `Access-Control-Allow-Credentials: true`)
2. `withCredentials: true` in all Ajax calls so the browser sends bob's
   silo1 session cookie
3. The JS knowing bob's home silo URL (from `files_sharding`)

This is achievable, but has two constraints worth noting:

- `Access-Control-Allow-Credentials: true` is required (so the browser sends
  the session cookie).  The CORS spec forbids combining this with a wildcard
  origin (`*`), so `Access-Control-Allow-Origin` must name the **exact**
  origin of each silo page — e.g. `https://silo2.example.org`.  In a
  multi-silo setup this means a per-origin rule in each node's reverse-proxy
  config (Caddy `header` directive or Apache `Header set` inside a
  `<Location /remote.php/webdav/>`).
- All nodes must share the same parent domain (e.g. `example.org`) so that
  domain-scoped NC cookies set on one node are sent by the browser to another.

The serve.php
proxy is self-contained: the JS writes to the **same URL it was loaded from**
(silo2, same origin, no CORS), `serve.php` checks write permission, then
writes through `IRootFolder` as the site owner.  No per-deployment CORS
config needed.

**Switching to native WebDAV** (if CORS is already configured):  
Revert `davUrl()` in `blog.js` to `host + '/remote.php/webdav' + path`,
restore `$ocPath` to the full WebDAV path (prepend `$contentRelative`),
set `$pico->ocUserHomeUrl` to the collaborator's home silo URL (via
`FilesSharding\Lib::getServerForUser($currentUid)`), and add
`xhrFields: {withCredentials: true}` to all Ajax calls.  Remove the
`PUT` / `GET?picocms_raw` handlers from `serve.php`.

#### Proxy endpoints (handled by serve.php before Pico runs)

| Method | Path | Description |
|--------|------|-------------|
| `PUT` | `/remote.php/files_picocms/sites/{name}/{file}.{ext}` | Write or create a file (pages and theme uploads) |
| `DELETE` | same path | Delete a file |
| `GET?picocms_raw=1` | `…/{file}.md` | Return raw Markdown for the inline editor |
| `GET?picocms_list_images=1` | site base | JSON list of images for the image picker |

Security constraints: extension whitelist (`md` plus media/document types:
`png jpg jpeg gif svg webp pdf mp4 webm`; raw read is `.md` only); path
traversal (`..`) rejected; write permission (`$writeGranted`) required.
Unhandled `PUT`/`DELETE`/`POST` requests get HTTP 405 — they never fall
through to page rendering. Asset serving is GET/HEAD-only so writes always
reach the proxies. `$sitePath` is `rawurldecode`d, so filenames with spaces
and non-ASCII characters work both for serving and through the proxies.

### Inline editor

The blog theme bundles [EasyMDE](https://github.com/Ionaru/easy-markdown-editor).
Clicking **Edit** on a post opens a full-page modal with a Markdown editor;
clicking **Write** on the index creates a new post file and opens the editor.
The editor uses `davGet` / `davPut` helpers in `themes/blog/js/blog.js`
(and `themes/team/js/team.js` for the team theme) which talk to the write
proxy above.

---

## Cross-silo SSO

When a visitor lands on a site served from silo2 without a local silo2
session, but they do have an NC session on the master (detected via the
`nc_username` cookie, which is scoped to the shared parent domain so all
nodes see it), `serve.php` redirects them through the `files_sharding`
SSO flow:

```
serve.php → master/sudo/confirm?silo=…&callback=…
         → silo2/apps/files_sharding/login?return=…    (exchange endpoint)
         → back to the original page URL on silo2
```

After the exchange, the visitor has a silo2 session and `$currentUid` is set.
This means alice's collaborators never need to know they are on silo2 — they
just log in at master and are transparently redirected.

This redirect only fires on silos (not on master itself) and only when the
visitor is not already authenticated on the silo.

---

## Private sites

Set `access: private` in `_config.md`.  Unauthenticated visitors receive HTTP
403.  If the site provides a `content/403.md`, it is rendered through Pico
(themed); otherwise a plain HTML page with a login link is shown.

---

## files_sharding integration

### Site registry: master is the source of truth

The master holds the authoritative copy of the site registry
(`oc_files_picocms`) so that any node can resolve `/sites/{name}` and
redirect to the node hosting the site. Silos keep a local copy of their own
users' rows for serving.

- **Mutations** (`SiteService::addSite` / `removeSite`, including renames)
  are written locally and forwarded to the master via files_sharding's
  `InterServerClient` (`internal/sites`, `internal/sites/delete`). Forward
  failures are logged (`master registry is now stale`), not fatal.
- **Name uniqueness is global**: before registering, a silo checks the name
  against the master registry (`internal/lookup`) and rejects names taken by
  users on other silos.
- **Serving** (`serve.php`): a local lookup miss on a silo triggers a master
  lookup; the response includes the owner's silo (`server_url`) and the
  visitor is redirected there with HTTP 307. On the master itself, a found
  site is redirected to the owner's silo via
  `ShardingService::getUserServer()` (owners without a silo assignment are
  homed on the master and served locally).
- All of this is guarded with `class_exists` / config checks — without
  files_sharding the app works standalone on its local registry.

So `https://cloud.example.org/remote.php/files_picocms/sites/myblog` (the
master) always works regardless of which silo hosts the owner — the master
307s to e.g. `https://silo1.example.org/...`.

### Twig URL variables

The `ocUserHomeUrl` Twig variable is set to the **current silo's base URL**
(not master) so that internal links and asset URLs remain correct on silos.
The `oc_cms_base` Twig variable holds the full picocms site URL and is used
by the JS as the write proxy base. `config.site_folder` holds the site's
folder path relative to the owner's files root (used by the team theme's
Manage button to link into the NC Files app).

---

## Theming

Themes live under `themes/` (either in the site directory or in the app's
`themes/` directory).  Bundled themes: `blog` (Bootstrap 3, CSS derived from
Clean Blog, EasyMDE), `team` (sidebar navigation, EasyMDE inline editing),
`documentation`, and `default`.  Theme CSS/JS files are named after the theme
(`css/blog.css`, `js/team.js`, …).

Theme files (`themes/…`) are served directly without running Pico, so CSS/JS
assets load even before the page is rendered.

### Sample sites

The team theme's sample content (`sample-content/team/`) is a complete
example **research-group site** aimed at group leaders: a public front page
with placeholder text in [brackets] and a self-explanatory "this is an
example site" banner, Research/People/Publications/Contact pages, a public
News section (posts with comments, auto-listed via `Index: true` in
`news/index.md`), and a private `internal/` section (member-only pages —
demonstrates `Access: private` with a friendly "Members only" login prompt).
The wizard fills in Date/Author/Site automatically.

Note: a folder **without** an `index.md` gets a Pico-generated listing that
defaults to private; public sections must carry their own `index.md` with
`Access: public`. Also note `Index: true` parses as a YAML boolean — the
index.twig listing condition compares strictly (`same as(true)`) because a
loose `true != "false"` comparison is false in PHP/Twig.

New sites no longer copy the app's `themes/` directory into the site folder
(`data-copy-themes="no"` in the wizard): sites track the app's themes, so
theme fixes reach all sites immediately, and site folders stay small. Users
who want to customise a theme can copy `themes/{name}` into their site folder
manually — site-local themes still take precedence.

### Self-contained assets — no CDNs

All JS, CSS and fonts are bundled inside each theme; pages load nothing from
external hosts:

- Google Fonts (Lora + Open Sans, latin/latin-ext woff2) are bundled as
  `css/fonts.css` + `fonts/google/` per theme (`fonts.css` at the theme root
  for the `default` theme).
- Font Awesome is bundled (`css/font-awesome.css` + `fonts/fa-*`); EasyMDE is
  initialised with `autoDownloadFontAwesome: false` so it never injects its
  CDN stylesheet.
- jQuery, Bootstrap and EasyMDE are theme-local files.
- MathJax 3.2.2 (`tex-mml-chtml` + woff-v2 fonts) is bundled once at the app
  level in `3rdparty/mathjax/` and served at `{site}/mathjax/…` by a dedicated
  serve.php branch — deliberately NOT under `themes/` so the site wizard's
  copy-themes option does not duplicate ~1.5 MB into every site folder. The
  blog, team and default templates load it with `$…$` and `\(…\)` inline-math
  delimiters; the documentation theme carries its own copy
  (`js/tex-chtml.js`).

The serve.php CSP is correspondingly strict — `'self'` only for styles,
scripts, fonts and images (plus `unsafe-inline`/`unsafe-eval` for the themes'
inline JS). Keep it that way: when adding theme features, copy the asset into
the theme instead of referencing a CDN, or the CSP will block it.

---

## 3rdparty libraries

All third-party code is bundled directly; no composer install is required.

| Library | Version | Location |
|---------|---------|----------|
| Pico CMS | 2.x | `3rdparty/Pico/` |
| Parsedown | 1.x | `3rdparty/Parsedown.php` |
| Parsedown Extra | 0.7 | `3rdparty/ParsedownExtra.php` |
| Twig | 1.x | `3rdparty/Twig/` |
| Symfony/Yaml | — | `3rdparty/Yaml/` |

---

## HTTP API

The app exposes three families of endpoints: the user-facing **OCS API**, the
shared-secret **internal inter-server API**, and the **write-proxy endpoints**
handled directly by `serve.php` (documented under *Write proxy* above).

### OCS API

Base URL:

```
/ocs/v2.php/apps/files_picocms/api/v1
```

Authentication: a logged-in Nextcloud session (with CSRF `requesttoken`) or
Basic auth with an app password. All requests need the header
`OCS-APIREQUEST: true`. Append `?format=json` for JSON instead of XML.
Responses below describe the `ocs.data` payload. All endpoints operate on the
**current user's** sites; `folder` parameters are paths relative to the
user's home, e.g. `/blog`.

Implementation: routes in `appinfo/routes.php`, logic in
`lib/Controller/ApiController.php` → `lib/Service/SiteService.php`.

#### `GET /sites` — list the current user's sites

No parameters. Returns an array of rows:

```json
[ { "uid": "alice", "site": "myblog", "path": "/blog", "gid": "" } ]
```

`site` is the URL slug (`/remote.php/files_picocms/sites/{site}`), `path` the
folder in the user's files, `gid` an optional group owning the site.

#### `POST /sites` — register an existing folder as a site

| Param | Required | Description |
|-------|----------|-------------|
| `folder` | yes | Folder path to serve |
| `name` | yes | URL slug |
| `group` | no | Group ID for group-owned sites (default `''`) |
| `rename` | no | `yes` = rename the existing registration for `folder` to `name` instead of creating a new one (default `no`) |

Returns `{"msg": "Added"}`, or HTTP 400 `{"error": "Name taken: …"}` if the
slug is already registered (or, with `rename=yes`, if no registration exists
for `folder`).

#### `DELETE /sites` — unregister a site

| Param | Required | Description |
|-------|----------|-------------|
| `folder` | yes | Folder path of the registered site |

Returns `{"msg": "Removed"}` or HTTP 404 `{"error": "Not found"}`. The folder
and its files are not touched — the site just stops being served.

#### `POST /create` — create a site from sample content (wizard backend)

| Param | Required | Description |
|-------|----------|-------------|
| `folder` | yes | Destination folder (created if missing) |
| `name` | no | URL slug; defaults to the folder's basename |
| `content` | no | Sample-content source, path relative to the app dir (e.g. `/sample-content/blog`) or a file for single-page sites |
| `destination` | no | Subfolder of `folder` to copy content into; empty = site root |
| `theme` | no | Theme to set in the generated `_config.md` |
| `copyThemes` | no | `yes` = copy the app's `themes/` into the site folder (default `no`) |

Copies the sample content (or the admin-configured sample folder, see
`/sample-folder`), registers the site, and writes a commented default
`_config.md` including a nav icon/favicon. Returns `{"site": "{name}"}`,
HTTP 400 `{"error": "Site name taken: …"}`, or HTTP 500
`{"error": "Failed to copy content"}`.

#### `GET /config` / `POST /config` — read & write a site's `_config.md`

Backs the **Manage** button in personal settings.

| Param | Required | Description |
|-------|----------|-------------|
| `folder` | yes | Folder path of the site |
| `content` | POST only | Full new `_config.md` content |

The config file is looked up first at `{folder}/content/_config.md`, then at
`{folder}/_config.md`; if neither exists, GET returns empty `content` and the
path where POST will create it (`content/_config.md` if a `content/`
subfolder exists, else the site root).

- GET returns `{"content": "---\ntitle: …", "file": "/blog/_config.md"}`
- POST returns `{"msg": "Saved"}` or HTTP 500 `{"error": "Save failed"}`

#### `GET /serve-public` / `POST /serve-public` — toggle the user public page

Controls whether `/remote.php/files_picocms/users/{email}` is served for the
current user.

- GET: no parameters, returns `{"serve": "yes"|"no"}`
- POST: parameter `serve` = `yes` or `no`, echoes `{"serve": "…"}`

#### `GET /help` — wizard defaults

No parameters. Returns the default folder suggestions used by the
personal-settings wizard:

```json
{ "team_folder": "/team", "blog_folder": "/blog", "doc_folder": "/documentation",
  "public_folder": "/public", "default_folder": "/website" }
```

#### `GET /sample-folder` / `POST /sample-folder` — admin only

Configure a Nextcloud folder whose contents override the bundled
`sample-content/` as the wizard source. Requires an admin session.

- GET: no parameters, returns `{"owner": "…", "path": "…"}`
- POST: parameters `owner` (NC uid) and `path`, returns `{"msg": "Saved"}`

### Internal inter-server API

Used by `files_sharding` silos to perform site operations against the master
(silos do not write the `oc_files_picocms` table locally). Called via
`InterServerClient::postDirect/getDirect`.

Base URL (plain JSON, not OCS):

```
/index.php/apps/files_picocms/internal
```

Authentication: `Authorization: Bearer {files_sharding_shared_secret}` — the
shared secret from `config.php`. All endpoints return HTTP 401
`{"error": "Unauthorized"}` on a missing/wrong token, and 401 always if the
secret is not configured. Implementation:
`lib/Controller/InternalController.php`.

These mirror the OCS endpoints but take an explicit `uid` (the acting user)
instead of using the session:

| Method | Path | Params | Returns |
|--------|------|--------|---------|
| GET | `/internal/sites` | `uid` | array of site rows (as OCS `GET /sites`) |
| POST | `/internal/sites` | `uid`, `folder`, `name`, `group=''`, `rename='no'` | `{"msg": "Added"}` / 400 |
| DELETE | `/internal/sites` | `uid`, `folder` | `{"msg": "Removed"}` / 404 |
| POST | `/internal/sites/delete` | `uid`, `folder` | alias for DELETE (InterServerClient cannot send DELETE) |
| GET | `/internal/lookup` | `site` (slug) | `{"uid", "site", "path", "gid", "server_id", "server_url"}` or `{}` if unknown — `server_url` is the owner's silo (empty = master-homed) |
| GET | `/internal/serve-public` | `uid` | `{"serve": "yes"\|"no"}` |
| POST | `/internal/serve-public` | `uid`, `serve` | `{"serve": "…"}` |
| GET | `/internal/sample-folder` | — | `{"owner", "path"}` |
| POST | `/internal/sample-folder` | `owner`, `path` | `{"msg": "Saved"}` |
| GET | `/internal/userid` | `email` | `{"uid": "…"\|null}` — resolves an email to a NC uid |

`/internal/lookup` is what `serve.php` on a silo uses to resolve a site slug
to its owner before redirecting to the owner's silo.

---

## Deployment

No build step, no composer install.  Copy the app directory into `apps/` on
every node and reload PHP so OPcache picks up the changes:

```bash
for HOST in cloud.example.org silo1.example.org silo2.example.org; do
  rsync -a files_picocms/ "$HOST:/var/www/nextcloud/apps/files_picocms/"
  ssh "$HOST" 'service php8.3-fpm reload'
done
```

In a single-node (non-sharded) installation just the one copy is needed.

---

## Known limitations / future work

- The write proxy (CORS workaround) can be replaced by native WebDAV if the
  reverse proxy (Caddy in test, Apache in production) adds
  `Access-Control-Allow-Origin` / `Access-Control-Allow-Credentials` headers
  to the `/remote.php/webdav/` path.  See *Switching to native WebDAV* above.
- Search page integration (`has_search_page`) assumes the blog theme.
- Comments require write access to the post/page file (they go through the
  serve.php write proxy). The comment form is hidden for everyone else; if
  visitor/reader comments are wanted, a dedicated sanitising
  comment-append endpoint is needed.
- When a user is **moved between silos**, the move tooling must delete the
  user's `oc_files_picocms` rows on the old silo. The master row plus the
  lookup-through fallback keep the site URLs working on the new silo without
  any local rows, but a stale row on the old silo would make direct URLs to
  that silo serve a 404 instead of redirecting.
