import Component from 'flarum/common/Component';
import Avatar from 'flarum/common/components/Avatar';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Button from 'flarum/common/components/Button';
import Dropdown from 'flarum/common/components/Dropdown';
import { tr } from '../utils/translate';
import { formatDate, userLink, showError } from '../utils/helpers';
import { canCommentWiki } from '../utils/permissions';
import { loadComments, postComment, WIKI_PAGE_LIMIT } from '../utils/api';
import { wikiComposerAvailable, wikiComposerOpenFor, openWikiComposer, wikiComposerPreview } from '../utils/composer';

/**
 * Comment thread for an article. Reads comments via the API and posts / edits
 * them through Flarum's real docked composer (the same UX as discussion
 * replies). Posting is gated by the linkrobins-wiki.comment permission.
 */
export default class WikiComments extends Component {
  article: any = null;
  comments: any[] = [];
  loading = true;
  loadingMore = false;
  hasMore = false;

  oninit(vnode: any) {
    super.oninit(vnode);
    this.article = this.attrs.article;
    this._load();
  }

  onbeforeupdate(vnode: any) {
    // The show page reuses this component instance across article navigations
    // (no vnode key), so reload when the article actually changes.
    const next = vnode.attrs && vnode.attrs.article;
    if (next && this.article && String(next.id()) !== String(this.article.id())) {
      this.article = next;
      this._load();
    }
  }

  _load() {
    this.loading = true;
    this.hasMore = false;
    loadComments(this.article.id())
      .then((comments: any[]) => {
        this.comments = comments || [];
        this.hasMore = this.comments.length >= WIKI_PAGE_LIMIT;
        this.loading = false;
        m.redraw();
      })
      .catch((err: any) => {
        this.loading = false;
        console.error('[linkrobins/wiki] comments load failed:', err);
        m.redraw();
      });
  }

  _loadMore() {
    if (this.loadingMore || !this.hasMore) return;
    this.loadingMore = true;
    loadComments(this.article.id(), this.comments.length)
      .then((more: any[]) => {
        const page = more || [];
        // Dedup by id when appending so a concurrently-posted comment can't
        // produce a duplicate Mithril key.
        const seen = new Set(this.comments.map((c: any) => String(c.id())));
        this.comments = this.comments.concat(page.filter((c: any) => !seen.has(String(c.id()))));
        this.hasMore = page.length >= WIKI_PAGE_LIMIT;
        this.loadingMore = false;
        m.redraw();
      })
      .catch((err: any) => {
        this.loadingMore = false;
        console.error('[linkrobins/wiki] more comments load failed:', err);
        m.redraw();
      });
  }

  view() {
    return m('section', { className: 'LinkRobinsWiki-comments' }, [
      m('h3', { className: 'LinkRobinsWiki-comments-heading' }, tr('comments.heading', 'Comments ({count})', { count: this.comments.length })),
      this.loading ? m(LoadingIndicator, { display: 'inline' }) : this._renderList(),
      this._renderLoadMore(),
      this._renderForm(),
    ]);
  }

  _renderLoadMore() {
    if (this.loading || !this.hasMore) return null;
    return m('div', { className: 'LinkRobinsWiki-comments-loadMore' }, m(
      Button,
      {
        className: 'Button Button--text',
        loading: this.loadingMore,
        onclick: () => this._loadMore(),
      },
      tr('comments.load_more', 'Load more comments')
    ));
  }

  _renderList() {
    if (!this.comments.length) {
      return m('div', { className: 'LinkRobinsWiki-comments-empty' }, tr('comments.empty', 'No comments yet.'));
    }
    return m('ul', { className: 'LinkRobinsWiki-commentList' }, this.comments.map((c: any) => this._renderComment(c)));
  }

  _renderComment(comment: any) {
    const user = comment.user && comment.user();
    const deleted = !!(comment.isDeleted && comment.isDeleted());

    return m('li', { className: 'LinkRobinsWiki-comment' + (deleted ? ' is-deleted' : ''), key: 'comment-' + comment.id() }, [
      m('div', { className: 'LinkRobinsWiki-comment-side' }, user ? m(Avatar, { user }) : null),
      m('div', { className: 'LinkRobinsWiki-comment-main' }, [
        m('div', { className: 'LinkRobinsWiki-comment-head' }, [
          m('span', { className: 'LinkRobinsWiki-comment-author' }, userLink(user)),
          m('span', { className: 'LinkRobinsWiki-comment-date' }, formatDate(comment.createdAt())),
          this._renderControls(comment),
        ]),
        deleted
          ? m('div', { className: 'LinkRobinsWiki-comment-deleted' }, tr('comments.deleted', 'This comment was deleted.'))
          : m('div', { className: 'LinkRobinsWiki-comment-body Post-body' }, m.trust(comment.contentHtml() || '')),
      ]),
    ]);
  }

  _renderControls(comment: any) {
    const canEdit = !!(comment.canEdit && comment.canEdit());
    const canDelete = !!(comment.canDelete && comment.canDelete());
    const deleted = !!(comment.isDeleted && comment.isDeleted());
    if (!canEdit && !canDelete) return null;

    const menu: any[] = [];
    if (canEdit && !deleted) {
      menu.push(m(Button, { icon: 'fas fa-pencil-alt', onclick: () => this._edit(comment) }, tr('action.edit', 'Edit')));
      menu.push(m(Button, { icon: 'fas fa-trash', onclick: () => this._softDelete(comment) }, tr('action.delete', 'Delete')));
    }
    if (canEdit && deleted) {
      menu.push(m(Button, { icon: 'fas fa-reply', onclick: () => this._restore(comment) }, tr('action.restore', 'Restore')));
    }
    if (canDelete && deleted) {
      menu.push(m(Button, { icon: 'fas fa-times', onclick: () => this._deleteForever(comment) }, tr('action.delete_forever', 'Delete forever')));
    }
    if (!menu.length) return null;

    return m(Dropdown, { className: 'LinkRobinsWiki-comment-controls', icon: 'fas fa-ellipsis-h', buttonClassName: 'Button Button--icon Button--flat' }, menu);
  }

  _renderForm() {
    if (!canCommentWiki()) return null;

    if (wikiComposerAvailable()) {
      return m('div', { className: 'LinkRobinsWiki-comments-form' }, wikiComposerPreview({
        composing: wikiComposerOpenFor(this._newContext()),
        placeholder: tr('comments.placeholder', 'Write a comment…'),
        onclick: () => this._openComposer(),
      }));
    }

    return m('div', { className: 'LinkRobinsWiki-comments-form' }, [
      m('textarea', {
        className: 'FormControl',
        rows: 3,
        placeholder: tr('comments.placeholder', 'Write a comment…'),
        value: this._fallbackBody || '',
        oninput: (e: any) => { this._fallbackBody = e.target.value; },
      }),
      m(Button, { className: 'Button Button--primary', onclick: () => this._submit(this._fallbackBody || '') }, tr('comments.post', 'Post comment')),
    ]);
  }

  _fallbackBody = '';

  _newContext() {
    return 'comment-new-' + this.article.id();
  }

  _openComposer() {
    openWikiComposer({
      wikiContext: this._newContext(),
      className: 'LinkRobinsWiki-commentComposer',
      placeholder: tr('comments.placeholder', 'Write a comment…'),
      submitLabel: tr('comments.post', 'Post comment'),
      confirmExit: tr('comments.discard_confirm', 'You have an unsubmitted comment. Discard it?'),
      onWikiSubmit: (content: string, body: any) => this._submit(content, body),
    });
  }

  _submit(content: string, body?: any) {
    const text = (content || '').trim();
    if (!text) {
      showError(tr('comments.empty_error', 'Please write something first.'));
      return;
    }
    if (body) body.loading = true;
    postComment(this.article, text)
      .then((comment: any) => {
        this.comments.push(comment);
        this._fallbackBody = '';
        if (body && body.composer) body.composer.hide();
        m.redraw();
      })
      .catch((err: any) => {
        if (body) body.loading = false;
        showError(tr('comments.error_post', 'Could not post the comment.'));
        console.error('[linkrobins/wiki] comment post failed:', err);
        m.redraw();
      });
  }

  _edit(comment: any) {
    openWikiComposer({
      wikiContext: 'comment-edit-' + comment.id(),
      className: 'LinkRobinsWiki-commentComposer',
      placeholder: tr('comments.placeholder', 'Write a comment…'),
      submitLabel: tr('action.save_changes', 'Save changes'),
      originalContent: comment.content() || '',
      onWikiSubmit: (content: string, body: any) => {
        const text = (content || '').trim();
        if (!text) {
          showError(tr('comments.empty_error', 'Please write something first.'));
          return;
        }
        if (body) body.loading = true;
        comment
          .save({ content: text })
          .then(() => {
            if (body && body.composer) body.composer.hide();
            m.redraw();
          })
          .catch(() => {
            if (body) body.loading = false;
            showError(tr('comments.error_edit', 'Could not save the comment.'));
          });
      },
    });
  }

  _softDelete(comment: any) {
    if (!confirm(tr('comments.confirm_delete', 'Delete this comment?'))) return;
    comment.save({ isDeleted: true }).then(() => m.redraw()).catch(() => showError(tr('comments.error_delete', 'Could not delete the comment.')));
  }

  _restore(comment: any) {
    comment.save({ isDeleted: false }).then(() => m.redraw()).catch(() => showError(tr('comments.error_delete', 'Could not restore the comment.')));
  }

  _deleteForever(comment: any) {
    if (!confirm(tr('comments.confirm_delete_forever', 'Permanently delete this comment? This cannot be undone.'))) return;
    comment
      .delete()
      .then(() => {
        this.comments = this.comments.filter((c: any) => c.id() !== comment.id());
        m.redraw();
      })
      .catch(() => showError(tr('comments.error_delete', 'Could not delete the comment.')));
  }
}
