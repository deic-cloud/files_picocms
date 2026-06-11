---

Title: Anatomy
Description: How this site works
Access: public
Theme: default

---

## Files become pages

Every `.md` file in the site folder is a page; subfolders become URL paths:

    index.md            →  /sites/{name}
    anatomy.md          →  /sites/{name}/anatomy
    projects/alpha.md   →  /sites/{name}/projects/alpha

`index.md` is the landing page of its folder. `403.md` and `404.md`, if
present, are shown for denied respectively missing pages. Anything else you
put in the folder (images, PDFs, …) is served as-is and can be referenced
relatively: `![plot](img/plot.png)`.

## The page header

Each page starts with a header between `---` lines:

    Title:        shown in the navigation and browser tab
    Description:  one-liner, used in HTML meta tags
    Date:         used for sorting where themes list pages
    Author:       a Nextcloud user id; themes show the display name
    Access:       public (anyone) or private (requires login + folder access)
    Theme:        per-page theme override
    Template:     which template of the theme renders this page
    Labels:       comma-separated labels (used by the blog and team themes)
    Comments:     on/off (used by the blog and team themes)

Useful placeholders in page text: `%email%` (your registered email),
`%user%` (the logged-in visitor), `%base_url%` (this site's address).

## Site-wide configuration

`_config.md` at the site root sets site-wide values (`title`, `theme`,
`access`, `icon`, `favicon`, …) — edit it via **Manage** in the Websites
app, where your sites are listed. A `_config.md` in a subfolder overrides
these for that part of the site.

## Themes

Built-in themes: `default` (this one), `blog`, `team`, `documentation`. Set
per site in `_config.md` or per page in the header. For full control, copy a
theme from the app's `themes/` directory into a `themes/` folder inside your
site and modify it — site-local themes take precedence. Templates are Twig;
Markdown is rendered with Parsedown Extra.

## Editing

This theme has no in-browser editor by design: edit the files in the
Nextcloud Files app (the *Show file* button on each page takes you there),
over WebDAV, or with any editor and sync client. A `content/` subfolder, if
it contains `index.md`, is used as the page root — handy for keeping pages
separate from a site-local `themes/` folder.
