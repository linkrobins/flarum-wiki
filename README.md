A wiki / knowledge-base extension for Flarum 2. Members write public articles with full edit history; every change is captured as an immutable revision.

## Features

- **Public articles.** Anyone can read articles. Members with the permission can create them and edit collaboratively.
- **Categories.** Admin-configurable, each with name, slug, description, color, icon, and position. Articles can be filed under one category (or none).
- **Readable addresses.** Each article gets an address built from its title, such as `/wiki/getting-started`, and the author can set their own. Renaming an article keeps its old addresses working, so links people have already shared never break. Numeric `/wiki/12` links keep working too.
- **Manual ordering.** Give articles a position to order them within a category, lowest first. Articles without one sit after the ordered ones, newest first.
- **Drafts.** Save an article as a draft to keep working on it. A draft is visible only to its author and to wiki editors until it is published.
- **FAQs.** Add questions and answers to any article. They show as a collapsible list under the article, and answers support Markdown.
- **Table of contents.** Articles with enough headings get a contents panel beside them that follows along as the reader scrolls. It can be turned off, or set to need more headings, in the admin settings.
- **Related articles.** Articles filed in a category list a few of their siblings underneath. The number shown is configurable, and the section can be turned off.
- **Comments.** Readers with the permission can discuss an article underneath it. Authors can edit and delete their own comments, and editors can moderate any of them.
- **Revision history.** Every save that changes an article's title or body records a revision: a snapshot of the title and content at that point, attributed to the editor. The history is read-only, shows what changed between versions, and is viewable from the article page. Admins can limit who sees it.
- **Reports.** Members can report an article as inaccurate, out of date, off topic, a duplicate, or something else, with an optional note. Reports collect on the extension's admin page, where they can be resolved; resolved reports are kept as history.
- **Markdown content.** Article bodies run through Flarum's formatter, so Markdown/BBCode and format extensions (mentions, emoji) work the same as in discussions. The rendered HTML is produced on demand at serialize time, so format extensions apply retroactively to older articles.
- **Moderation.** Editors can soft-delete and restore articles; soft-deleted articles stay visible to editors (with a "deleted" treatment) and hidden from everyone else. Permanent deletion is admin-only and requires the article to be soft-deleted first, then cascades to its revisions.
- **Customizable home page.** Build the `/wiki` landing page from shortcodes (article lists, single articles, the category list, headings, links), or leave it blank to list every article.
- **Layout options.** Choose where the Wiki link sits in the forum sidebar (or hide it), and optionally let wiki pages use the full page width.
- **File attachments.** Optional integration with `fof/upload`.
- **Search.** A search box beside the wiki's title finds articles by title or text as you type, title matches first. A link with `?q=` opens straight to a search. Articles also appear in the forum's own search dropdown, alongside discussions and users.
- **Audit log.** When `flarum/audit` is installed, reports and article changes (created, edited, deleted, restored) are recorded in it.

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

- `lr-wiki.createArticle`: start new articles.
- `lr-wiki.editArticles`: edit and moderate (soft-delete / restore) any article and any comment, not just one's own, and see every draft.
- `lr-wiki.comment`: comment on articles. Not granted to any group out of the box, so only admins can comment until you grant it.
- `lr-wiki.viewHistory`: view an article's revision history. Granted to guests out of the box, which means everyone; remove it there and grant it to specific groups to restrict history.
- `lr-wiki.reportArticle`: report an article. Granted to members out of the box; guests can never report.

Authors can always edit their own articles. Admins bypass every check. Permanent deletion is admin-only.

## Forum UI

- `/wiki`: the wiki home page (every article, or the layout set in admin), with a category filter in the sidebar.
- `/wiki/new`: write a new article (title, address, position, category, body, FAQ, draft).
- `/wiki/:slug`: the article page, with the rendered body, contents panel, FAQ, related articles, comments, edit/moderation controls, and the revision history. The article's id works here as well.
- `/wiki/:slug/edit`: edit an existing article.

## Admin UI

Settings live at admin → Extensions → LR Wiki:

- **Wiki home page:** the shortcode layout for `/wiki`.
- **Layout:** the Wiki link's place in the sidebar, and full-width pages.
- **Table of contents:** on or off, and the minimum number of headings.
- **Related articles:** on or off, and how many to show.
- **Wiki categories:** create, edit, order and delete categories.
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
