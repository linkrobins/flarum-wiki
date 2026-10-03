A wiki / knowledge-base extension for Flarum 2. Members write public articles with full edit history; every change is captured as an immutable revision.

## Features

- **Public articles.** Anyone can read articles. Members with the permission can create them and edit collaboratively.
- **Categories.** Admin-configurable, each with name, slug, description, color, icon, and position. Articles can be filed under one category (or none).
- **Revision history.** Every save that changes an article's title or body records a revision — a snapshot of the title and content at that point, attributed to the editor. The history is read-only and viewable from the article page.
- **Markdown content.** Article bodies run through Flarum's formatter, so Markdown/BBCode and format extensions (mentions, emoji) work the same as in discussions. The rendered HTML is produced on demand at serialize time, so format extensions apply retroactively to older articles.
- **Moderation.** Editors can soft-delete and restore articles; soft-deleted articles stay visible to editors (with a "deleted" treatment) and hidden from everyone else. Permanent deletion is admin-only and requires the article to be soft-deleted first, then cascades to its revisions.
- **File attachments.** Optional integration with `fof/upload`.

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

## Installation

```
composer require linkrobins/wiki
php flarum migrate
php flarum cache:clear
```

Then enable the extension in admin → Extensions.

## Permissions

- `lr-wiki.createArticle` — start new articles.
- `lr-wiki.editArticles` — edit and moderate (soft-delete / restore) any article, not just one's own.

Authors can always edit their own articles. Admins bypass every check.
Permanent deletion is admin-only.

## Forum UI

- `/wiki` — the article list, with a category filter in the sidebar.
- `/wiki/new` — write a new article (title, optional category, body).
- `/wiki/:id` — the article page, with the rendered body, edit/moderation controls, and the revision history.
- `/wiki/:id/edit` — edit an existing article.

## Admin UI

Settings live at admin → Extensions → Link Robins Wiki: full CRUD for article categories.

## Data model

Three tables:

- `linkrobins_wiki_categories` — name, slug, description, color, icon, position.
- `linkrobins_wiki_articles` — category_id, user_id (author), last_edited_by_user_id, title, content (parsed-source), last_edited_at, deleted_at.
- `linkrobins_wiki_revisions` — article_id, user_id (editor), title, content, summary.

## License

MIT.
