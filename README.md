# Link Robins Wiki

![License](https://img.shields.io/badge/license-MIT-blue.svg) [![Latest Stable Version](https://img.shields.io/packagist/v/linkrobins/wiki.svg)](https://packagist.org/packages/linkrobins/wiki)

A wiki / knowledge-base extension for Flarum 2. Members write public articles with full edit history; every change is captured as an immutable revision.

## Features

- **Public articles.** Anyone can read articles, guests included. Members with the permission can create them. The author can always edit their own article, and members with the editor permission can edit anyone's, so articles can be written collaboratively.
- **Categories.** Admin-configurable, each with name, slug, description, color, icon, and position. Articles can be filed under one category (or none). Deleting a category does not delete its articles; they become uncategorized.
- **Readable addresses.** Each article gets an address built from its title, such as `/wiki/getting-started`, and the author can set their own. Renaming an article keeps its old addresses working, so links people have already shared never break. Numeric `/wiki/12` links keep working too.
- **Manual ordering.** Give articles a position to order them within a category, lowest first. Articles without one sit after the ordered ones, newest first.
- **Drafts.** Save an article as a draft to keep working on it. A draft is visible only to its author and to wiki editors until it is published.
- **FAQs.** Add questions and answers to any article, up to 50 per article (questions up to 500 characters). They show as a collapsible list under the article, and answers support Markdown.
- **Table of contents.** Articles with enough headings (`#`, `##` and `###`) get a contents panel beside them that follows along as the reader scrolls. It can be turned off, or set to need more headings, in the admin settings.
- **Related articles.** Articles filed in a category list a few of their siblings underneath. The number shown is configurable, and the section can be turned off.
- **Comments.** Anyone can read the comments under an article. Members with the permission can add their own, and 25 load at a time. Authors can edit and soft-delete their own comments, editors can edit, delete and restore any of them, and only admins can delete one permanently (after it is soft-deleted).
- **Revision history.** Every save that changes an article's title or body records a revision: a snapshot of the title and content at that point, attributed to the editor. The history is read-only, shows what changed between versions (title and body), loads 25 revisions at a time, and is viewable from the article page. Public by default; admins can limit who sees it with the history permission.
- **Reports.** Members can report an article as inaccurate, out of date, off topic, a duplicate, or something else, with an optional note of up to 1,000 characters. A member can have only one open report per article. Reports collect on the extension's admin page, where they can be resolved; resolved reports are kept as history.
- **Markdown content.** Article bodies run through Flarum's formatter, so Markdown/BBCode and format extensions (mentions, emoji) work the same as in discussions. The rendered HTML is produced on demand at serialize time, so format extensions apply retroactively to older articles.
- **Moderation.** Editors can soft-delete and restore articles; soft-deleted articles stay visible to editors (with a "deleted" treatment) and hidden from everyone else. Permanent deletion is admin-only and requires the article to be soft-deleted first, then cascades to its revisions.
- **Home page by category.** `/wiki` lists every category in the order you set, each with its first few articles (positioned ones first) and a "See all" link when it has more, so no category drops off the page however large the wiki grows. Articles with no category come last, under "Other". How many each category shows is a setting.
- **Customizable home page.** Or build the `/wiki` landing page yourself from shortcodes, one per line (see below). Leave the layout blank and the page lists every category with its articles.
- **Layout options.** Choose where the Wiki link sits in the forum sidebar (top of the list, below All Discussions, with the section links, bottom, or hidden entirely; the wiki stays open at `/wiki` when hidden), and optionally let wiki pages drop the forum sidebar and use the full page width.
- **File attachments.** Optional integration with [`fof/upload`](https://packagist.org/packages/fof/upload). When it is enabled, the article editor gets FoF Upload's own Attach files button, paste and drag-and-drop, and the plain-text editor gets an Attach files button of its own for anyone allowed to upload. Uploaded files appear with FoF Upload's download button. Those buttons work in articles, FAQ answers and comments (not just in discussion posts), and a reader's download permission is still checked on the server. Nothing needs configuring here, and without FoF Upload nothing changes.
- **Search.** A search box beside the wiki's title finds articles by title or text as you type, title matches first. A link with `?q=` opens straight to a search. Articles also appear in the forum's own search dropdown, alongside discussions and users.
- **Audit log.** When `flarum/audit` is installed, reports and article changes (created, edited, deleted, restored) are recorded in it.
- **Link Preview.** With `datlechin/flarum-link-preview` 2.1 or later, links to articles and to `/wiki` turn into cards, and links inside articles, FAQ answers and comments get previews.

## Home page shortcodes

In **Wiki home page** in the admin settings, put one shortcode per line. Lines that are not shortcodes show as text, and lines starting with `#`, `##` or `###` become headings.

```
[articles]                                all articles
[articles category="guides" limit="5"]    the 5 most recent in a category (slug or id)
[articles category="guides" title="Guides"]   with a heading above the list
[article id="12"]                         a link to one article
[categories]                              the category list
[html] ... [/html]                        raw HTML between the tags, left exactly as written
```

Bare web addresses become links.

## Search engines and sitemaps

Wiki pages are built to be found by Google and other search engines:

- **Every article is a real page.** Its title, a description from the article, a canonical link to its address, and the full text are sent with the page itself, not loaded afterwards, so search engines read the article even though Flarum is a JavaScript app. Links shared on social media and chat apps show the article's title and description.
- **Works on forums that block `/api` for crawlers.** The article and the first page of the wiki index arrive with the page, so nothing a search engine needs depends on `/api`, which fof/sitemap's robots.txt disallows.
- **Missing, deleted and draft articles return a real "not found"**, and the new and edit pages are marked not to be indexed.
- **fof/sitemap:** when it is installed, every published article (at its address) and the wiki index are added to the forum's sitemap, with each article's last edit as its date.
- **fof/seo:** when it is installed, it supplies the article's search and social metadata, including schema.org article data, without anything appearing twice.

Neither fof extension is required; without them the wiki adds its own basic tags.

## Requirements

- Flarum 2.0.0+
- PHP 8.3+

### Optional integrations

None of these is required, and each switches on by itself when the extension is enabled.

| Extension | What the wiki adds |
| --- | --- |
| [`fof/upload`](https://packagist.org/packages/fof/upload) | Attach files in articles, FAQ answers and comments, with working download buttons. |
| [`fof/seo`](https://packagist.org/packages/fof/seo) | Article search and social metadata, including schema.org article data. |
| [`fof/sitemap`](https://packagist.org/packages/fof/sitemap) | Every published article and the wiki index in the sitemap. |
| [`flarum/audit`](https://packagist.org/packages/flarum/audit) | Reports and article changes in the audit log. |
| [`datlechin/flarum-link-preview`](https://packagist.org/packages/datlechin/flarum-link-preview) 2.1+ | Preview cards for wiki links, and previews inside articles, FAQ answers and comments. |

## Installation

```sh
composer require linkrobins/wiki
```

Then switch the extension on in the admin panel's Extensions page. Its database tables are created when you do.

## Updating

```sh
composer update linkrobins/wiki
php flarum migrate
php flarum cache:clear
```

## Permissions

Each appears in the admin panel's Permissions page.

- `lr-wiki.createArticle` ("Create wiki articles"): start new articles.
- `lr-wiki.editArticles` ("Edit and moderate any wiki article"): edit and moderate (soft-delete / restore) any article and any comment, not just one's own, and see every draft.
- `lr-wiki.comment` ("Comment on wiki articles"): comment on articles. Granted to members out of the box.
- `lr-wiki.viewHistory` ("View wiki article revision history"): view an article's revision history. Granted to guests out of the box, which means everyone; remove it there and grant it to specific groups to restrict history.
- `lr-wiki.reportArticle` ("Report wiki articles"): report an article. Granted to members out of the box; guests can never report.

Authors can always edit their own articles and see their own drafts. Admins bypass every check. Permanent deletion of articles and comments is admin-only, and so is managing categories. Creating articles is not granted to anyone on install, so until you grant it only admins can write.

## Forum UI

- `/wiki`: the wiki home page (every category with its first articles, or the layout set in admin), with a category filter in the sidebar.
- `/wiki?category=:id`: every article in one category, with "Load more" at the end. `?category=none` lists the articles with no category.
- `/wiki/new`: write a new article (title, address, position, category, body, FAQ, draft).
- `/wiki/:slug`: the article page, with the rendered body, contents panel, FAQ, related articles, comments, edit/moderation controls, and the revision history. The article's id works here as well.
- `/wiki/:slug/edit`: edit an existing article.

## Admin UI

Settings live at admin → Extensions → LR Wiki:

- **Wiki home page:** the shortcode layout for `/wiki`, and how many articles each category shows when there is no layout (default 5, from 1 to 50).
- **Layout:** the Wiki link's place in the sidebar (default: with the section links), and full-width pages (default off).
- **Table of contents:** on or off (default on), and the minimum number of headings (default 2).
- **Related articles:** on or off (default on), and how many to show (default 5, from 1 to 20).
- **Wiki categories:** create, edit, order and delete categories, each with a name, address, description, color, Font Awesome icon and position.
- **Reports:** the queue of reported articles, with resolve and reopen.

## Data model

Six tables:

- `linkrobins_wiki_categories`: name, slug, description, color, icon, position.
- `linkrobins_wiki_articles`: category_id, user_id (author), last_edited_by_user_id, title, slug, content (parsed-source), faq, position, is_draft, last_edited_at, deleted_at.
- `linkrobins_wiki_article_slugs`: earlier addresses of each article, so old links keep working.
- `linkrobins_wiki_revisions`: article_id, user_id (editor), title, content, summary.
- `linkrobins_wiki_comments`: article_id, user_id, content, deleted_at.
- `linkrobins_wiki_reports`: article_id, user_id (reporter), reason, detail, resolved_at, resolved_by_user_id.

## License

MIT.
