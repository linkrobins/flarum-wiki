A wiki / knowledge-base extension for Flarum 2. Members write public articles with full edit history; every change is captured as an immutable revision.

## Features

- **Public articles.** Anyone can read articles. Members with the permission can create them and edit collaboratively.
- **Categories.** Admin-configurable, each with name, slug, description, color, icon, and position. Articles can be filed under one category (or none).
- **Revision history.** Every save that changes an article's title or body records a revision — a snapshot of the title and content at that point, attributed to the editor. The history is read-only and viewable from the article page.
- **Markdown content.** Article bodies run through Flarum's formatter, so Markdown/BBCode and format extensions (mentions, emoji) work the same as in discussions. The rendered HTML is produced on demand at serialize time, so format extensions apply retroactively to older articles.
- **Moderation.** Editors can soft-delete and restore articles; soft-deleted articles stay visible to editors (with a "deleted" treatment) and hidden from everyone else. Permanent deletion is admin-only and requires the article to be soft-deleted first, then cascades to its revisions.
- **File attachments.** Optional integration with `fof/upload`.

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

- `linkrobins-wiki.createArticle` — start new articles.
- `linkrobins-wiki.editArticles` — edit and moderate (soft-delete / restore) any article, not just one's own.

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

[upl-image-preview uuid=5064b37a-358f-4c4c-ba41-a485c94eb521 url=https://cdn.discuss.flarum.org/2026-06-23/1782173503-462941-image.png alt=image.png thumbnail_url=https://cdn.discuss.flarum.org/2026-06-23/1782173503-462941-image-thumb.webp]
[upl-image-preview uuid=526e8a44-6cb6-4438-9f69-4c9de492bc8e url=https://cdn.discuss.flarum.org/2026-06-23/1782173517-703183-image.png alt=image.png thumbnail_url=https://cdn.discuss.flarum.org/2026-06-23/1782173517-703183-image-thumb.webp]
[upl-image-preview uuid=67733ce6-19cd-4c7a-b0cb-ef203ba17a31 url=https://cdn.discuss.flarum.org/2026-06-23/1782173596-932886-image.png alt=image.png thumbnail_url=https://cdn.discuss.flarum.org/2026-06-23/1782173596-932886-image-thumb.webp]
[upl-image-preview uuid=8395ba3a-0ba8-4a1e-9bfb-287537d9ffac url=https://cdn.discuss.flarum.org/2026-06-23/1782173607-766841-image.png alt=image.png thumbnail_url=https://cdn.discuss.flarum.org/2026-06-23/1782173607-766841-image-thumb.webp]
[upl-image-preview uuid=bbf33f77-0531-45b9-8c4e-255fd5775dc0 url=https://cdn.discuss.flarum.org/2026-06-23/1782173631-134794-image.png alt=image.png thumbnail_url=https://cdn.discuss.flarum.org/2026-06-23/1782173631-134794-image-thumb.webp]
