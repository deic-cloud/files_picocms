# files_picocms — Pico CMS sites on Nextcloud

Serve static Markdown-based websites from a user's Nextcloud files, with
in-browser editing for owners and collaborators.

**Author:** Frederik Orellana, Technical University of Denmark (fror@dtu.dk) — developed for the ScienceData cloud platform.  
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
| `EditLinks` | — | `yes` to show per-post Edit buttons |
| `favicon` | — | Path to favicon relative to site root |

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
`https://silo1/remote.php/webdav/{alice_mount}/`.  The federation mechanism
itself is sound.

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
  origin of each silo page — e.g. `https://kube.sciencedata.dk:2005`.  In a
  multi-silo setup this means a per-origin rule in each node's reverse-proxy
  config (Caddy `header` directive or Apache `Header set` inside a
  `<Location /remote.php/webdav/>`).
- All nodes must share the same public hostname (same domain / subdomain) so
  that NC session cookies set on one node are sent by the browser to another.

The serve.php
proxy is self-contained: the JS writes to the **same URL it was loaded from**
(silo2, same origin, no CORS), `serve.php` checks write permission, then
writes through `IRootFolder` as the site owner.  No per-deployment CORS
config needed.

**Switching to native WebDAV** (if CORS is already configured):  
Revert `davUrl()` in `clean-blog.js` to `host + '/remote.php/webdav' + path`,
restore `$ocPath` to the full WebDAV path (prepend `$contentRelative`),
set `$pico->ocUserHomeUrl` to the collaborator's home silo URL (via
`FilesSharding\Lib::getServerForUser($currentUid)`), and add
`xhrFields: {withCredentials: true}` to all Ajax calls.  Remove the
`PUT` / `GET?picocms_raw` handlers from `serve.php`.

#### Proxy endpoints (handled by serve.php before Pico runs)

| Method | Path | Description |
|--------|------|-------------|
| `PUT` | `/remote.php/files_picocms/sites/{name}/{file}.md` | Write or create a Markdown file |
| `GET?picocms_raw=1` | same path | Return raw Markdown for the inline editor |

Security constraints: only `.md` extension allowed; path traversal (`..`) is
rejected; write permission (`$writeGranted`) must be true.

### Inline editor

The blog theme bundles [EasyMDE](https://github.com/Ionaru/easy-markdown-editor).
Clicking **Edit** on a post opens a full-page modal with a Markdown editor;
clicking **Write** on the index creates a new post file and opens the editor.
The editor uses `davGet` / `davPut` helpers in `themes/blog/js/clean-blog.js`
which talk to the write proxy above.

---

## Cross-silo SSO

When a visitor lands on a site served from silo2 without a local silo2
session, but they do have an NC session on the master (detected via the
`nc_username` cookie, which is shared across ports on the same hostname),
`serve.php` redirects them through the `files_sharding` SSO flow:

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

The app detects `files_sharding` and, if the site owner is not on the current
node, redirects to the correct silo:

```php
if (!\OCA\FilesSharding\Lib::onServerForUser($uid)) {
    header('Location: ' . getServerForUser($uid) . $_SERVER['REQUEST_URI']);
}
```

The `ocUserHomeUrl` Twig variable is set to the **current silo's base URL**
(not master) so that internal links and asset URLs remain correct on silos.
The `oc_cms_base` Twig variable holds the full picocms site URL and is used
by the JS as the write proxy base.

---

## Theming

Themes live under `themes/` (either in the site directory or in the app's
`themes/` directory).  The bundled `blog` theme uses Bootstrap 3,
Clean Blog CSS, and EasyMDE.

Theme files (`themes/…`) are served directly without running Pico, so CSS/JS
assets load even before the page is rendered.

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

## Deployment

No build step.  Edit PHP/Twig/JS/CSS files directly, then copy to all nodes:

```bash
for TREE in /home/claude/code/nextcloud \
            /home/claude/code/silo1/nextcloud \
            /home/claude/code/silo2/nextcloud; do
  rsync -a /home/claude/code/nextcloud/apps/files_picocms/ \
        "$TREE/apps/files_picocms/"
done
# Reload OPcache on all pods
sshpass -p secret ssh root@10.2.164.63 'service php8.3-fpm reload'
sshpass -p secret ssh root@10.2.24.238  'service php8.3-fpm reload'
sshpass -p secret ssh root@10.2.164.64  'service php8.3-fpm reload'
```

---

## Known limitations / future work

- The write proxy (CORS workaround) can be replaced by native WebDAV if the
  reverse proxy (Caddy in test, Apache in production) adds
  `Access-Control-Allow-Origin` / `Access-Control-Allow-Credentials` headers
  to the `/remote.php/webdav/` path.  See *Switching to native WebDAV* above.
- The wiki theme does not yet have an inline editor.
- Search page integration (`has_search_page`) assumes the blog theme.
