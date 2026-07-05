import Page from 'flarum/common/components/Page';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Button from 'flarum/common/components/Button';
import Dropdown from 'flarum/common/components/Dropdown';
import PageStructure from 'flarum/forum/components/PageStructure';
import WikiIndexSidebar from './WikiIndexSidebar';
import WikiComments from './WikiComments';
import { tr, trText } from '../utils/translate';
import { basePath, BASE_PATH, formatDate, userLink, showError } from '../utils/helpers';
import { canEditWikiArticles } from '../utils/permissions';
import { loadArticle, loadRevisions, WIKI_PAGE_LIMIT } from '../utils/api';
import { lineDiff, foldContext, hasChanges, DiffLine } from '../utils/diff';
import { processWikiHeadings, scrollToAnchor, tocEnabled, tocMinHeadings, WikiTocEntry } from '../utils/toc';

export default class WikiShowPage extends Page {
  loading = true;
  error: any = null;
  article: any = null;

  historyOpen = false;
  revisions: any[] | null = null;
  revisionsLoading = false;
  revisionsLoadingMore = false;
  revisionsHasMore = false;
  expandedRevision: string | null = null;

  // Table of contents (sticky rail). Entries are derived from the rendered
  // article body; activeTocId tracks the section currently in view.
  tocEntries: WikiTocEntry[] = [];
  activeTocId: string | null = null;
  private _tocSig = '';
  private _tocHashHandled = false;
  private _boundScroll: (() => void) | null = null;
  private _spyRaf: number | null = null;

  oninit(vnode: any) {
    super.oninit(vnode);
    this._load();
  }

  oncreate(vnode: any) {
    super.oncreate(vnode);
    // Scroll-spy: highlight the contents entry for the section in view. Passive
    // + rAF-throttled so it stays off the scroll critical path.
    this._boundScroll = () => this._scheduleSpy();
    window.addEventListener('scroll', this._boundScroll, { passive: true });
  }

  onremove(vnode: any) {
    if (this._boundScroll) {
      window.removeEventListener('scroll', this._boundScroll);
      this._boundScroll = null;
    }
    if (this._spyRaf != null) {
      cancelAnimationFrame(this._spyRaf);
      this._spyRaf = null;
    }
    if (super.onremove) super.onremove(vnode);
  }

  onbeforeupdate(vnode: any) {
    const id = m.route.param('id');
    if (this.article && String(this.article.id()) !== String(id)) {
      this._load();
    }
    return true;
  }

  _load() {
    this.loading = true;
    this.error = null;
    this.revisions = null;
    this.revisionsHasMore = false;
    this.historyOpen = false;
    this.tocEntries = [];
    this.activeTocId = null;
    this._tocSig = '';
    this._tocHashHandled = false;
    m.redraw();

    loadArticle(m.route.param('id'))
      .then((article: any) => {
        this.article = article;
        this.loading = false;
        try {
          app.setTitle(article.title() || tr('nav', 'Wiki'));
        } catch (e) {}
        m.redraw();
      })
      .catch((err: any) => {
        this.error = err;
        this.loading = false;
        m.redraw();
      });
  }

  view() {
    return m(
      PageStructure,
      {
        className: 'IndexPage LinkRobinsWiki-page LinkRobinsWiki-page--show',
        sidebar: () => {
          try {
            const cat = this.article && this.article.category && this.article.category();
            return m(WikiIndexSidebar, { className: 'LinkRobinsWiki-sidebar', activeCategory: cat ? cat.id() : null });
          } catch (e) {
            return null;
          }
        },
      },
      m('div', { className: 'LinkRobinsWiki-container' }, this._renderContent())
    );
  }

  _renderContent() {
    if (this.loading) {
      return m(LoadingIndicator);
    }
    if (this.error || !this.article) {
      return m('div', { className: 'LinkRobinsWiki-empty' }, tr('errors.load_article', 'Could not load this article.'));
    }

    const article = this.article;
    const isDeleted = !!(article.isDeleted && article.isDeleted());

    return [
      isDeleted
        ? m('div', { className: 'LinkRobinsWiki-deletedNotice' }, tr('show.deleted_notice', 'This article is deleted. Only editors can see it.'))
        : null,

      m('div', { className: 'LinkRobinsWiki-articleLayout' }, [
        m('div', { className: 'LinkRobinsWiki-articleMain' }, [
          m('header', { className: 'LinkRobinsWiki-articleHeader' }, [
            this._renderControls(article),
            m('h1', { className: 'LinkRobinsWiki-articleTitle' }, article.title()),
            this._renderByline(article),
          ]),

          m(
            'div',
            {
              className: 'LinkRobinsWiki-articleBody Post-body',
              // The body is m.trust'd HTML, so headings only exist post-render:
              // instrument them once they're in the DOM (and again if the article
              // content changes, which remounts the trusted node).
              oncreate: (vnode: any) => this._processToc(vnode.dom),
              onupdate: (vnode: any) => this._processToc(vnode.dom),
            },
            m.trust(article.contentHtml() || '')
          ),

          this._renderHistory(article),

          m(WikiComments, { article }),
        ]),

        this._renderTocRail(),
      ]),
    ];
  }

  // --- Table of contents -------------------------------------------------

  _processToc(bodyEl: Element) {
    if (!tocEnabled()) return;

    const entries = processWikiHeadings(bodyEl);
    const sig = entries.map((e) => e.level + ':' + e.id).join('|');

    // Only redraw when the heading set actually changed. processWikiHeadings is
    // idempotent (ids are reused via data attributes), so the redraw it triggers
    // re-enters onupdate, recomputes the same signature, and stops -- no loop.
    if (sig !== this._tocSig) {
      this._tocSig = sig;
      this.tocEntries = entries;
      this._spy(false);
      m.redraw();
    }

    // Honor a deep link to a heading (#wiki-...) once the anchors exist. The
    // browser's own jump happened before ids were assigned, so we finish it.
    if (!this._tocHashHandled) {
      this._tocHashHandled = true;
      const hash = (window.location.hash || '').replace(/^#/, '');
      if (hash && entries.some((e) => e.id === hash)) {
        requestAnimationFrame(() => scrollToAnchor(hash));
      }
    }
  }

  _renderTocRail() {
    if (!tocEnabled()) return null;

    const entries = this.tocEntries;
    if (!entries || entries.length < tocMinHeadings()) return null;

    // Level 1 in the list is the shallowest heading actually present, so a
    // ##/### article still indents from a sensible baseline.
    let minLevel = Infinity;
    for (const e of entries) if (e.level < minLevel) minLevel = e.level;
    if (!isFinite(minLevel)) minLevel = 1;

    return m(
      'aside',
      { className: 'LinkRobinsWiki-tocRail' },
      m('nav', { className: 'LinkRobinsWiki-toc', 'aria-label': trText('show.toc_heading', 'Contents') }, [
        m('div', { className: 'LinkRobinsWiki-toc-title' }, tr('show.toc_heading', 'Contents')),
        m(
          'ol',
          { className: 'LinkRobinsWiki-toc-list' },
          entries.map((e) =>
            m(
              'li',
              {
                key: e.id,
                className:
                  'LinkRobinsWiki-toc-item LinkRobinsWiki-toc-item--level-' +
                  (e.level - minLevel + 1) +
                  (this.activeTocId === e.id ? ' is-active' : ''),
              },
              m(
                'a',
                {
                  className: 'LinkRobinsWiki-toc-link',
                  href: '#' + e.id,
                  onclick: (ev: Event) => {
                    ev.preventDefault();
                    scrollToAnchor(e.id);
                    this.activeTocId = e.id;
                    try {
                      window.history.replaceState(null, '', '#' + e.id);
                    } catch (err) {
                      // history API unavailable -- non-fatal, the scroll still happened.
                    }
                  },
                },
                e.text
              )
            )
          )
        ),
      ])
    );
  }

  _scheduleSpy() {
    if (this._spyRaf != null) return;
    this._spyRaf = requestAnimationFrame(() => {
      this._spyRaf = null;
      this._spy(true);
    });
  }

  // Pick the last heading whose top has scrolled above the header line; that's
  // the section the reader is currently in. Entries are in document order, so we
  // can stop at the first heading still below the line.
  _spy(allowRedraw: boolean) {
    if (!this.tocEntries.length) return;

    const header = document.querySelector('.App-header');
    const threshold = (header ? header.getBoundingClientRect().height : 0) + 24;

    let current: string | null = this.tocEntries[0].id;
    for (const e of this.tocEntries) {
      const el = document.getElementById(e.id);
      if (!el) continue;
      if (el.getBoundingClientRect().top - threshold <= 0) {
        current = e.id;
      } else {
        break;
      }
    }

    if (current !== this.activeTocId) {
      this.activeTocId = current;
      if (allowRedraw) m.redraw();
    }
  }

  _renderByline(article: any) {
    const author = article.user && article.user();
    const editor = article.lastEditedBy && article.lastEditedBy();
    const cat = article.category && article.category();

    const segments: any[] = [];
    if (cat) {
      segments.push(
        m(
          'a',
          {
            className: 'LinkRobinsWiki-byline-cat',
            href: basePath() + BASE_PATH + '?category=' + encodeURIComponent(cat.id()),
            style: 'color: ' + (cat.color() || 'inherit'),
          },
          cat.name()
        )
      );
    }
    if (author) {
      segments.push(m('span', { className: 'LinkRobinsWiki-byline-author' }, [tr('show.by', 'by '), userLink(author)]));
    }
    if (editor) {
      segments.push(
        m('span', { className: 'LinkRobinsWiki-byline-edited' }, [
          tr('show.last_edited', 'last edited by '),
          userLink(editor),
          ' ',
          formatDate(article.lastEditedAt() || article.createdAt()),
        ])
      );
    } else {
      segments.push(m('span', { className: 'LinkRobinsWiki-byline-edited' }, formatDate(article.createdAt())));
    }

    // Interleave with a middot separator so the segments stay on one tidy line
    // with consistent spacing (no run-together names, no oversized gaps).
    const out: any[] = [];
    segments.forEach((seg, i) => {
      if (i > 0) out.push(m('span', { className: 'LinkRobinsWiki-byline-sep' }, '·'));
      out.push(seg);
    });

    return m('div', { className: 'LinkRobinsWiki-byline' }, out);
  }

  _renderControls(article: any) {
    const canUpdate = !!(article.canUpdate && article.canUpdate());
    const canDelete = !!(article.canDelete && article.canDelete());
    const isEditor = canEditWikiArticles();
    const isDeleted = !!(article.isDeleted && article.isDeleted());

    if (!canUpdate && !canDelete && !isEditor) {
      return null;
    }

    const menu: any[] = [];
    if (isEditor && !isDeleted) {
      menu.push(m(Button, { icon: 'fas fa-trash', onclick: () => this._softDelete(article) }, tr('action.delete', 'Delete')));
    }
    if (isEditor && isDeleted) {
      menu.push(m(Button, { icon: 'fas fa-reply', onclick: () => this._restore(article) }, tr('action.restore', 'Restore')));
    }
    if (canDelete && isDeleted) {
      menu.push(m(Button, { icon: 'fas fa-times', onclick: () => this._deleteForever(article) }, tr('action.delete_forever', 'Delete forever')));
    }

    return m('div', { className: 'LinkRobinsWiki-articleControls' }, [
      canUpdate
        ? m(
            Button,
            {
              className: 'Button',
              icon: 'fas fa-pencil-alt',
              onclick: () => m.route.set(basePath() + BASE_PATH + '/' + encodeURIComponent(article.id()) + '/edit'),
            },
            tr('action.edit', 'Edit')
          )
        : null,
      menu.length ? m(Dropdown, { className: 'Dropdown--icon', icon: 'fas fa-ellipsis-h', buttonClassName: 'Button Button--icon' }, menu) : null,
    ]);
  }

  // --- Revision history --------------------------------------------------

  _renderHistory(article: any) {
    const count = article.revisionCount ? article.revisionCount() : 0;
    if (!count) return null;

    return m('section', { className: 'LinkRobinsWiki-history' }, [
      m(
        Button,
        {
          className: 'Button Button--text LinkRobinsWiki-history-toggle',
          icon: this.historyOpen ? 'fas fa-caret-down' : 'fas fa-caret-right',
          onclick: () => this._toggleHistory(article),
        },
        tr('show.history', 'History ({count})', { count })
      ),
      this.historyOpen ? this._renderRevisions(article) : null,
    ]);
  }

  _toggleHistory(article: any) {
    this.historyOpen = !this.historyOpen;
    if (this.historyOpen && this.revisions === null && !this.revisionsLoading) {
      this.revisionsLoading = true;
      loadRevisions(article.id())
        .then((revs: any[]) => {
          this.revisions = revs || [];
          this.revisionsHasMore = this.revisions.length >= WIKI_PAGE_LIMIT;
          this.revisionsLoading = false;
          m.redraw();
        })
        .catch(() => {
          this.revisions = [];
          this.revisionsLoading = false;
          m.redraw();
        });
    }
  }

  _loadMoreRevisions(article: any) {
    if (this.revisionsLoadingMore || !this.revisionsHasMore || this.revisions === null) return;
    this.revisionsLoadingMore = true;
    loadRevisions(article.id(), this.revisions.length)
      .then((revs: any[]) => {
        const page = revs || [];
        const seen = new Set((this.revisions || []).map((r: any) => String(r.id())));
        this.revisions = (this.revisions || []).concat(page.filter((r: any) => !seen.has(String(r.id()))));
        this.revisionsHasMore = page.length >= WIKI_PAGE_LIMIT;
        this.revisionsLoadingMore = false;
        m.redraw();
      })
      .catch(() => {
        this.revisionsLoadingMore = false;
        m.redraw();
      });
  }

  _renderRevisions(article: any) {
    if (this.revisionsLoading || this.revisions === null) {
      return m(LoadingIndicator, { display: 'inline' });
    }
    if (!this.revisions.length) {
      return m('div', { className: 'LinkRobinsWiki-empty' }, tr('show.no_history', 'No revisions yet.'));
    }
    return [
      m(
        'ul',
        { className: 'LinkRobinsWiki-revisions' },
        this.revisions.map((rev: any, idx: number) => this._renderRevision(rev, idx))
      ),
      this.revisionsHasMore
        ? m(
            'div',
            { className: 'LinkRobinsWiki-history-loadMore' },
            m(
              Button,
              {
                className: 'Button Button--text',
                loading: this.revisionsLoadingMore,
                onclick: () => this._loadMoreRevisions(article),
              },
              tr('show.load_more_history', 'Load older revisions')
            )
          )
        : null,
    ];
  }

  _renderRevision(rev: any, idx: number) {
    const editor = rev.user && rev.user();
    const id = String(rev.id());
    const expanded = this.expandedRevision === id;
    // Revisions are newest-first, so the older version is the next item.
    const prev = this.revisions ? this.revisions[idx + 1] : null;

    return m('li', { className: 'LinkRobinsWiki-revision', key: 'rev-' + id }, [
      m(
        'button',
        {
          type: 'button',
          className: 'LinkRobinsWiki-revision-head',
          onclick: () => {
            this.expandedRevision = expanded ? null : id;
          },
        },
        [
          m('i', { className: 'fas fa-' + (expanded ? 'caret-down' : 'caret-right') + ' LinkRobinsWiki-revision-caret' }),
          m('span', { className: 'LinkRobinsWiki-revision-date' }, formatDate(rev.createdAt())),
          editor ? m('span', { className: 'LinkRobinsWiki-revision-user' }, editor.displayName() || editor.username()) : null,
          !prev ? m('span', { className: 'LinkRobinsWiki-revision-tag' }, tr('show.initial_version', 'created')) : null,
          rev.summary && rev.summary() ? m('span', { className: 'LinkRobinsWiki-revision-summary' }, rev.summary()) : null,
        ]
      ),
      expanded ? this._renderDiff(rev, prev) : null,
    ]);
  }

  _renderDiff(rev: any, prev: any) {
    const newText = (rev.content && rev.content()) || '';
    const oldText = prev ? (prev.content && prev.content()) || '' : '';
    const newTitle = rev.title ? rev.title() : '';
    const oldTitle = prev && prev.title ? prev.title() : null;
    const titleChanged = prev && oldTitle !== newTitle;

    const diff = foldContext(lineDiff(oldText, newText));
    const bodyChanged = hasChanges(diff);

    const parts: any[] = [
      m(
        'div',
        { className: 'LinkRobinsWiki-diff-label' },
        prev ? tr('show.diff_from_previous', 'Changes from the previous version') : tr('show.diff_initial', 'Initial version')
      ),
    ];

    if (titleChanged) {
      parts.push(
        m('div', { className: 'LinkRobinsWiki-diff-titleChange' }, [
          m('span', { className: 'LinkRobinsWiki-diff-titleLabel' }, tr('show.diff_title', 'Title')),
          m('span', { className: 'LinkRobinsWiki-diff-del' }, oldTitle),
          m('i', { className: 'fas fa-arrow-right' }),
          m('span', { className: 'LinkRobinsWiki-diff-add' }, newTitle),
        ])
      );
    }

    if (bodyChanged) {
      parts.push(
        m(
          'div',
          { className: 'LinkRobinsWiki-diff' },
          diff.map((l) => this._renderDiffLine(l))
        )
      );
    } else if (!titleChanged) {
      parts.push(m('div', { className: 'LinkRobinsWiki-diff-none' }, tr('show.diff_none', 'No content changes.')));
    }

    return m('div', { className: 'LinkRobinsWiki-revisionDiff' }, parts);
  }

  _renderDiffLine(line: DiffLine) {
    if (line.type === 'fold') {
      return m('div', { className: 'LinkRobinsWiki-diff-fold' }, '⋯');
    }
    const cls = line.type === 'add' ? 'is-add' : line.type === 'del' ? 'is-del' : 'is-eq';
    const sign = line.type === 'add' ? '+' : line.type === 'del' ? '−' : ' ';
    return m('div', { className: 'LinkRobinsWiki-diff-line ' + cls }, [
      m('span', { className: 'LinkRobinsWiki-diff-sign' }, sign),
      m('span', { className: 'LinkRobinsWiki-diff-text' }, line.text || ' '),
    ]);
  }

  // --- Moderation --------------------------------------------------------

  _softDelete(article: any) {
    if (!confirm(tr('confirm.soft_delete', 'Delete this article? Editors can restore it later.'))) return;
    article
      .save({ isDeleted: true })
      .then(() => m.redraw())
      .catch(() => showError(tr('errors.delete_article', 'Could not delete the article.')));
  }

  _restore(article: any) {
    article
      .save({ isDeleted: false })
      .then(() => m.redraw())
      .catch(() => showError(tr('errors.restore_article', 'Could not restore the article.')));
  }

  _deleteForever(article: any) {
    if (!confirm(tr('confirm.delete_forever', 'Permanently delete this article and its history? This cannot be undone.'))) return;
    article
      .delete()
      .then(() => m.route.set(basePath() + BASE_PATH))
      .catch(() => showError(tr('errors.delete_article_forever', 'Could not permanently delete the article.')));
  }
}
