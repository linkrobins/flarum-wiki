import Page from 'flarum/common/components/Page';
import Button from 'flarum/common/components/Button';
import extractText from 'flarum/common/utils/extractText';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import PageStructure from 'flarum/forum/components/PageStructure';
import WikiIndexSidebar from './WikiIndexSidebar';
import { tr } from '../utils/translate';
import {
  basePath,
  BASE_PATH,
  articleHref,
  formatDate,
  safeNavigate,
  readForumAttribute,
  fullWidth,
  pageClassName,
  emptySidebar,
  executeContentScripts,
} from '../utils/helpers';
import { canCreateWikiArticle } from '../utils/permissions';
import { loadArticles, loadArticle, loadCategories } from '../utils/api';
import { parseIndexLayout, WikiBlock } from '../utils/indexLayout';

export default class WikiIndexPage extends Page {
  loading = true;
  error: any = null;
  articles: any[] = [];
  category: string | null = null;

  // Search state. `query` is what the list was loaded with (from the route);
  // `queryDraft` is what is currently in the box, so typing doesn't refetch.
  query = '';
  queryDraft = '';

  // Custom-layout state.
  layout: string = '';
  blocks: WikiBlock[] = [];
  categories: any[] = [];
  blockData: Record<number, any> = {};

  oninit(vnode: any) {
    super.oninit(vnode);
    try {
      app.setTitle(tr('nav', 'Wiki'));
    } catch (e) {}
    this._init();
  }

  onbeforeupdate(vnode: any) {
    const next = m.route.param('category') || null;
    const nextQuery = (m.route.param('q') || '').toString();
    if (next !== this.category || nextQuery !== this.query) {
      Promise.resolve().then(() => this._init());
    }
    return true;
  }

  _init() {
    this.category = m.route.param('category') || null;
    this.query = (m.route.param('q') || '').toString();
    this.queryDraft = this.query;
    this.error = null;
    this.blockData = {};

    // A search always shows results, whatever the custom layout says, for the
    // same reason a category filter does.
    if (this.query.trim()) {
      this.blocks = [];
      this._loadList();
      return;
    }

    // A category filter (from the sidebar) always shows that category's list,
    // regardless of any custom homepage layout.
    if (this.category) {
      this.blocks = [];
      this._loadList();
      return;
    }

    this.layout = (readForumAttribute('linkrobinsWikiIndexLayout') || '').toString();
    if (this.layout.trim()) {
      this.loading = false;
      this.blocks = parseIndexLayout(this.layout);
      // Load categories once (for slug resolution + the [categories] block),
      // then fetch each dynamic block's data.
      loadCategories()
        .then((cats: any[]) => {
          this.categories = cats || [];
          this._fetchBlocks();
        })
        .catch(() => this._fetchBlocks());
      m.redraw();
      return;
    }

    // No custom layout -> default list of all articles.
    this.blocks = [];
    this._loadList();
  }

  _loadList() {
    this.loading = true;
    m.redraw();
    const params: any = { page: { limit: 25 }, filter: {} };
    if (this.category) {
      params.filter.categoryId = this.category;
      // A category is the unit people arrange by hand, so its listing leads
      // with the manual order and falls back to recency for the rest.
      params.sort = 'position,-lastEditedAt';
    }
    if (this.query.trim()) {
      params.filter.q = this.query.trim();
      // The default sort stays: the fulltext filter adds its title-first
      // ordering before the sort is applied, so title matches lead and recency
      // breaks ties.
    }
    loadArticles(params)
      .then((articles: any[]) => {
        this.articles = articles || [];
        this.loading = false;
        m.redraw();
      })
      .catch((err: any) => {
        this.error = err;
        this.loading = false;
        console.error('[linkrobins/wiki] index load failed:', err);
        m.redraw();
      });
  }

  _resolveCategoryId(value: string): string | null {
    if (!value) return null;
    if (/^\d+$/.test(value)) return value;
    const found = this.categories.find((c: any) => c.slug && c.slug() === value);
    return found ? String(found.id()) : null;
  }

  _fetchBlocks() {
    this.blocks.forEach((block, i) => {
      if (block.type === 'articles') {
        const params: any = { page: { limit: parseInt(block.attrs.limit, 10) || 25 } };
        const catId = block.attrs.category ? this._resolveCategoryId(block.attrs.category) : null;
        if (catId) {
          params.filter = { categoryId: catId };
          // Same rule as the category listing page: a block that names a
          // category shows it in the order it was arranged by hand. A block
          // with no category stays on recency, which is what "[articles
          // limit=5 title=Recent]" is for.
          params.sort = 'position,-lastEditedAt';
        }
        loadArticles(params)
          .then((arts: any[]) => {
            this.blockData[i] = arts || [];
            m.redraw();
          })
          .catch(() => {
            this.blockData[i] = [];
            m.redraw();
          });
      } else if (block.type === 'article' && block.attrs.id) {
        loadArticle(block.attrs.id)
          .then((a: any) => {
            this.blockData[i] = a;
            m.redraw();
          })
          .catch(() => {
            this.blockData[i] = null;
            m.redraw();
          });
      }
    });
    m.redraw();
  }

  view() {
    return m(
      PageStructure,
      {
        className: pageClassName('IndexPage LinkRobinsWiki-page'),
        // In full-width mode the real sidebar is never built, so its category
        // request never fires; see emptySidebar() for why it is not just null.
        sidebar: fullWidth() ? emptySidebar : () => this._renderSidebar(),
      },
      m('div', { className: 'LinkRobinsWiki-container' }, this._renderBody())
    );
  }

  _renderSidebar() {
    try {
      return m(WikiIndexSidebar, { className: 'LinkRobinsWiki-sidebar' });
    } catch (e) {
      console.error('[linkrobins/wiki] sidebar render failed:', e);
    }
    return null;
  }

  _renderBody() {
    // Custom homepage layout (only when no category filter or search is on).
    if (!this.category && !this.query.trim() && this.blocks.length) {
      return [
        this._renderSearch(),
        m(
          'div',
          { className: 'LinkRobinsWiki-home' },
          this.blocks.map((b, i) => this._renderBlock(b, i))
        ),
      ];
    }

    // A plain listing when the reader has already narrowed things themselves
    // (a search or a category); grouped when this is the index of everything.
    const body = this.category || this.query.trim() ? this._renderList(this.articles) : this._renderGrouped(this.articles);

    return [this._renderSearch(), this._renderHeader(), body];
  }

  // The wiki's own search box. Submitting puts the term in the URL (?q=), so a
  // result page can be linked, bookmarked and gone back to.
  _renderSearch() {
    const submit = (e?: any) => {
      if (e) e.preventDefault();
      const value = (this.queryDraft || '').trim();
      const params: any = {};
      if (value) params.q = value;
      if (this.category) params.category = this.category;
      m.route.set(basePath() + BASE_PATH, params);
    };

    return m('form', { className: 'LinkRobinsWiki-search', onsubmit: submit, role: 'search' }, [
      m('input', {
        className: 'FormControl LinkRobinsWiki-search-input',
        type: 'search',
        value: this.queryDraft,
        placeholder: extractText(tr('search.placeholder', 'Search the wiki')),
        'aria-label': extractText(tr('search.placeholder', 'Search the wiki')),
        oninput: (e: any) => {
          this.queryDraft = e.target.value;
        },
      }),
      m(Button, { className: 'Button LinkRobinsWiki-search-go', type: 'submit', icon: 'fas fa-search' }, tr('search.button', 'Search')),
      this.query.trim()
        ? m(
            Button,
            {
              className: 'Button Button--link LinkRobinsWiki-search-clear',
              onclick: () => {
                this.queryDraft = '';
                submit();
              },
            },
            tr('search.clear', 'Clear')
          )
        : null,
    ]);
  }

  _renderHeader() {
    const cat = this.category ? this.categories.find((c: any) => String(c.id()) === String(this.category)) : null;
    if (this.query.trim()) {
      return m('header', { className: 'LinkRobinsWiki-header' }, [
        m('h1', { className: 'LinkRobinsWiki-title' }, [
          m('i', { className: 'fas fa-search' }),
          ' ',
          tr('search.results_heading', 'Results for "{query}"', { query: this.query.trim() }),
        ]),
      ]);
    }
    const label = cat ? cat.name() : tr('nav', 'Wiki');
    return m('header', { className: 'LinkRobinsWiki-header' }, [
      m('h1', { className: 'LinkRobinsWiki-title' }, [m('i', { className: 'fas fa-book' }), ' ', label]),
    ]);
  }

  // --- Block rendering --------------------------------------------------

  _renderBlock(block: WikiBlock, i: number) {
    switch (block.type) {
      case 'prose':
        return this._renderProse(block);
      case 'articles':
        return m('section', { className: 'LinkRobinsWiki-homeBlock' }, [
          block.attrs.title ? m('h2', { className: 'LinkRobinsWiki-homeBlock-title' }, block.attrs.title) : null,
          this.blockData[i] === undefined ? m(LoadingIndicator, { display: 'inline' }) : this._renderList(this.blockData[i]),
        ]);
      case 'article':
        return m('section', { className: 'LinkRobinsWiki-homeBlock' }, this._renderArticleLink(this.blockData[i]));
      case 'categories':
        return m('section', { className: 'LinkRobinsWiki-homeBlock' }, [
          block.attrs.title ? m('h2', { className: 'LinkRobinsWiki-homeBlock-title' }, block.attrs.title) : null,
          this._renderCategories(),
        ]);
      case 'html':
        return this._renderHtml(block);
      default:
        return null;
    }
  }

  // A raw [html] block from the layout setting. m.trust never runs embedded
  // <script> tags, so widgets that boot themselves would silently do nothing;
  // executeContentScripts re-creates them the way core does for post content.
  _renderHtml(block: WikiBlock) {
    const html = (block.lines || []).join('\n');
    if (!html.trim()) return null;

    return m(
      'div',
      {
        className: 'LinkRobinsWiki-homeBlock LinkRobinsWiki-html',
        oncreate: (vnode: any) => executeContentScripts(vnode.dom, html),
        onupdate: (vnode: any) => executeContentScripts(vnode.dom, html),
      },
      m.trust(html)
    );
  }

  _renderProse(block: WikiBlock) {
    const out: any[] = [];
    (block.lines || []).forEach((line, idx) => {
      const t = line.trim();
      if (t === '') return;
      if (t.indexOf('### ') === 0) out.push(m('h3', { key: idx }, this._renderInline(t.slice(4))));
      else if (t.indexOf('## ') === 0) out.push(m('h2', { key: idx }, this._renderInline(t.slice(3))));
      else if (t.indexOf('# ') === 0) out.push(m('h1', { className: 'LinkRobinsWiki-title', key: idx }, this._renderInline(t.slice(2))));
      else out.push(m('p', { key: idx }, this._renderInline(t)));
    });
    return m('div', { className: 'LinkRobinsWiki-prose' }, out);
  }

  /**
   * Links inside prose. `[label](url)` and bare http(s) URLs become anchors;
   * everything else stays a text node, so nothing an admin types can inject
   * markup. Only http(s) and site-relative targets qualify: a `javascript:`
   * address fails the scheme test and is left as the text it arrived as.
   */
  _renderInline(text: string): any[] {
    const out: any[] = [];
    const re = /\[([^\]\n]+)\]\(((?:https?:\/\/|\/)[^\s)]+)\)|https?:\/\/[^\s<>]+/g;
    let last = 0;
    let match: RegExpExecArray | null;

    while ((match = re.exec(text))) {
      let label: string;
      let href: string;
      let consumed = match[0].length;

      if (match[1] !== undefined) {
        label = match[1];
        href = match[2];
      } else {
        // A bare URL keeps its trailing punctuation as prose, not address.
        const stripped = match[0].replace(/[.,!?;:'"\)\]]+$/, '');
        consumed = stripped.length;
        label = href = stripped;
      }

      if (match.index > last) out.push(text.slice(last, match.index));
      out.push(this._inlineLink(href, label));
      last = match.index + consumed;
      re.lastIndex = last;
    }

    if (last < text.length) out.push(text.slice(last));
    return out;
  }

  _inlineLink(href: string, label: string) {
    // Site-relative targets stay in the SPA. The href is kept exactly as the
    // admin typed it: safeNavigate strips the forum's base path when present,
    // so both `/wiki/getting-started` and `/forum/wiki/getting-started` route,
    // and guessing at a prefix here would double it for one of them.
    if (href.charAt(0) === '/') {
      return m('a', { href, onclick: (e: any) => safeNavigate(href, e) }, label);
    }
    return m('a', { href, target: '_blank', rel: 'noopener nofollow ugc' }, label);
  }

  _renderArticleLink(article: any) {
    if (article === undefined) return m(LoadingIndicator, { display: 'inline' });
    if (!article) return null;
    return this._renderList([article]);
  }

  _renderCategories() {
    if (!this.categories.length) {
      return m('div', { className: 'LinkRobinsWiki-empty' }, tr('index.no_categories', 'No categories yet.'));
    }
    return m(
      'div',
      { className: 'LinkRobinsWiki-categoryCards' },
      this.categories.map((cat: any) => {
        const href = basePath() + BASE_PATH + '?category=' + encodeURIComponent(cat.id());
        return m(
          'a',
          {
            href,
            className: 'LinkRobinsWiki-categoryCard',
            key: 'cat-' + cat.id(),
            onclick: (e: any) => safeNavigate(href, e),
          },
          [
            m('i', {
              className: (cat.icon() || 'fas fa-folder') + ' LinkRobinsWiki-categoryCard-icon',
              style: 'color: ' + (cat.color() || 'inherit'),
            }),
            m('span', { className: 'LinkRobinsWiki-categoryCard-name' }, cat.name()),
            cat.description && cat.description() ? m('span', { className: 'LinkRobinsWiki-categoryCard-desc' }, cat.description()) : null,
          ]
        );
      })
    );
  }

  // --- Shared list / row ------------------------------------------------

  _renderList(articles: any[], opts: { hideCategory?: boolean } = {}) {
    if (this.loading) {
      return m(LoadingIndicator);
    }
    if (this.error) {
      return m('div', { className: 'LinkRobinsWiki-empty' }, tr('errors.load_articles', 'Could not load articles.'));
    }
    if (!articles || !articles.length) {
      // "Nothing yet, write one" is the wrong prompt when a search simply
      // found nothing.
      if (this.query.trim()) {
        return m('div', { className: 'LinkRobinsWiki-empty' }, tr('search.empty', 'No articles match "{query}".', { query: this.query.trim() }));
      }
      return m(
        'div',
        { className: 'LinkRobinsWiki-empty' },
        canCreateWikiArticle()
          ? tr('index.empty_own', 'No articles yet. Click "New article" to write one.')
          : tr('index.empty', 'No articles to show.')
      );
    }
    return m(
      'div',
      { className: 'LinkRobinsWiki-list' },
      articles.map((a: any) => this._renderRow(a, opts))
    );
  }

  /**
   * The default index, grouped under one heading per category.
   *
   * A flat listing repeats the category on every card, which on a wiki is the
   * one thing a run of articles already has in common. The heading says it
   * once and the cards get the room back for what each article is about.
   */
  _renderGrouped(articles: any[]) {
    if (this.loading || this.error || !articles || !articles.length) {
      return this._renderList(articles);
    }

    const groups: { key: string; cat: any; items: any[] }[] = [];
    const seen: Record<string, number> = {};

    articles.forEach((a: any) => {
      const cat = a.category && a.category();
      const key = cat ? 'c' + cat.id() : 'none';

      if (seen[key] === undefined) {
        seen[key] = groups.length;
        groups.push({ key, cat, items: [] });
      }

      groups[seen[key]].items.push(a);
    });

    // One group is not a grouping: an uncategorised wiki gets the plain
    // listing rather than a lone heading sitting over everything it owns.
    if (groups.length < 2) {
      return this._renderList(articles);
    }

    return groups.map((g) =>
      m('section', { className: 'LinkRobinsWiki-group', key: 'group-' + g.key }, [
        m(
          'h2',
          {
            className: 'LinkRobinsWiki-group-title',
            style: g.cat && g.cat.color() ? 'color: ' + g.cat.color() : undefined,
          },
          g.cat ? g.cat.name() : tr('index.uncategorised', 'Other')
        ),
        this._renderList(g.items, { hideCategory: true }),
      ])
    );
  }

  _renderRow(article: any, opts: { hideCategory?: boolean } = {}) {
    const cat = article.category && article.category();
    const excerpt = (article.excerpt && article.excerpt()) || '';
    const href = articleHref(article);
    const isDeleted = !!(article.isDeleted && article.isDeleted());
    const isDraft = !!(article.isDraft && article.isDraft());

    return m(
      'a',
      {
        href,
        className: 'LinkRobinsWiki-row' + (isDeleted ? ' LinkRobinsWiki-row--deleted' : '') + (isDraft ? ' LinkRobinsWiki-row--draft' : ''),
        onclick: (e: any) => safeNavigate(href, e),
        key: 'article-' + article.id(),
      },
      [
        m('div', { className: 'LinkRobinsWiki-row-main' }, [
          m('div', { className: 'LinkRobinsWiki-row-subject' }, [
            article.title() || tr('index.untitled', 'Untitled'),
            isDeleted ? m('span', { className: 'LinkRobinsWiki-row-deletedBadge' }, tr('index.deleted_badge', 'Deleted')) : null,
            isDraft ? m('span', { className: 'LinkRobinsWiki-row-draftBadge' }, tr('index.draft_badge', 'Draft')) : null,
          ]),
          excerpt ? m('div', { className: 'LinkRobinsWiki-row-excerpt' }, excerpt) : null,
          m('div', { className: 'LinkRobinsWiki-row-meta' }, [
            // The author is the one field on a wiki card nobody navigates by,
            // and it was a third of what stretched every row. The category
            // goes too wherever a heading above already says it.
            cat && !opts.hideCategory
              ? m('span', { className: 'LinkRobinsWiki-row-cat', style: 'color: ' + (cat.color() || 'inherit') }, cat.name())
              : null,
            m('span', { className: 'LinkRobinsWiki-row-date' }, formatDate(article.lastEditedAt() || article.createdAt())),
          ]),
        ]),
      ]
    );
  }
}
