import Page from 'flarum/common/components/Page';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Button from 'flarum/common/components/Button';
import Dropdown from 'flarum/common/components/Dropdown';
import PageStructure from 'flarum/forum/components/PageStructure';
import WikiIndexSidebar from './WikiIndexSidebar';
import { tr } from '../utils/translate';
import { basePath, BASE_PATH, formatDate, userLink, showError } from '../utils/helpers';
import { canEditWikiArticles } from '../utils/permissions';
import { loadArticle, loadRevisions } from '../utils/api';

export default class WikiShowPage extends Page {
  loading = true;
  error: any = null;
  article: any = null;

  historyOpen = false;
  revisions: any[] | null = null;
  revisionsLoading = false;
  expandedRevision: string | null = null;

  oninit(vnode: any) {
    super.oninit(vnode);
    this._load();
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
    this.historyOpen = false;
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
            return m(WikiIndexSidebar, { className: 'LinkRobinsWiki-sidebar' });
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

      m('header', { className: 'LinkRobinsWiki-articleHeader' }, [
        this._renderControls(article),
        m('h1', { className: 'LinkRobinsWiki-articleTitle' }, article.title()),
        this._renderByline(article),
      ]),

      m('div', { className: 'LinkRobinsWiki-articleBody Post-body' }, m.trust(article.contentHtml() || '')),

      this._renderHistory(article),
    ];
  }

  _renderByline(article: any) {
    const author = article.user && article.user();
    const editor = article.lastEditedBy && article.lastEditedBy();
    const cat = article.category && article.category();

    return m('div', { className: 'LinkRobinsWiki-byline' }, [
      cat
        ? m('a', { className: 'LinkRobinsWiki-byline-cat', href: basePath() + BASE_PATH + '?category=' + encodeURIComponent(cat.id()), style: 'color: ' + (cat.color() || 'inherit') }, cat.name())
        : null,
      author ? m('span', { className: 'LinkRobinsWiki-byline-author' }, [tr('show.by', 'By '), userLink(author)]) : null,
      editor
        ? m('span', { className: 'LinkRobinsWiki-byline-edited' }, [
            tr('show.last_edited', 'Last edited by '),
            userLink(editor),
            ' ',
            formatDate(article.lastEditedAt() || article.createdAt()),
          ])
        : m('span', { className: 'LinkRobinsWiki-byline-edited' }, formatDate(article.createdAt())),
    ]);
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
      menu.length
        ? m(Dropdown, { className: 'Dropdown--icon', icon: 'fas fa-ellipsis-h', buttonClassName: 'Button Button--icon' }, menu)
        : null,
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
      this.historyOpen ? this._renderRevisions() : null,
    ]);
  }

  _toggleHistory(article: any) {
    this.historyOpen = !this.historyOpen;
    if (this.historyOpen && this.revisions === null && !this.revisionsLoading) {
      this.revisionsLoading = true;
      loadRevisions(article.id())
        .then((revs: any[]) => {
          this.revisions = revs || [];
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

  _renderRevisions() {
    if (this.revisionsLoading || this.revisions === null) {
      return m(LoadingIndicator, { display: 'inline' });
    }
    if (!this.revisions.length) {
      return m('div', { className: 'LinkRobinsWiki-empty' }, tr('show.no_history', 'No revisions yet.'));
    }
    return m(
      'ul',
      { className: 'LinkRobinsWiki-revisions' },
      this.revisions.map((rev: any) => this._renderRevision(rev))
    );
  }

  _renderRevision(rev: any) {
    const editor = rev.user && rev.user();
    const id = String(rev.id());
    const expanded = this.expandedRevision === id;

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
          m('span', { className: 'LinkRobinsWiki-revision-date' }, formatDate(rev.createdAt())),
          editor ? m('span', { className: 'LinkRobinsWiki-revision-user' }, editor.displayName() || editor.username()) : null,
          rev.summary && rev.summary() ? m('span', { className: 'LinkRobinsWiki-revision-summary' }, rev.summary()) : null,
        ]
      ),
      expanded ? m('div', { className: 'LinkRobinsWiki-revision-body Post-body' }, m.trust(rev.contentHtml() || '')) : null,
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
