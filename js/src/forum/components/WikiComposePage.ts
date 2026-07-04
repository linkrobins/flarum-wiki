import Page from 'flarum/common/components/Page';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Select from 'flarum/common/components/Select';
import Button from 'flarum/common/components/Button';
import PageStructure from 'flarum/forum/components/PageStructure';
import WikiIndexSidebar from './WikiIndexSidebar';
import { tr } from '../utils/translate';
import { basePath, BASE_PATH, showError } from '../utils/helpers';
import { canCreateWikiArticle } from '../utils/permissions';
import { loadArticle, loadCategories, createArticle, updateArticle } from '../utils/api';
import { wikiComposerAvailable, wikiComposerOpenFor, openWikiComposer, wikiComposerPreview } from '../utils/composer';

/**
 * Create a new article (/wiki/new) or edit an existing one (/wiki/:id/edit).
 * Title + optional category live on the page; the body is written in Flarum's
 * real docked composer (full width, with the editor toolbar / rich text /
 * upload) -- the same UX as a normal discussion reply.
 */
export default class WikiComposePage extends Page {
  loading = true;
  loadError = false;
  saving = false;
  editing = false;
  article: any = null;
  categories: any[] = [];

  title = '';
  body = '';
  categoryId: string = '';

  oninit(vnode: any) {
    super.oninit(vnode);

    const id = m.route.param('id');
    this.editing = !!id;

    try {
      app.setTitle(this.editing ? tr('compose.title_edit', 'Edit article') : tr('compose.title', 'New article'));
    } catch (e) {}

    const work: Promise<any>[] = [
      loadCategories()
        .then((cats: any[]) => {
          this.categories = cats || [];
        })
        .catch(() => {}),
    ];

    if (this.editing) {
      work.push(
        loadArticle(id)
          .then((article: any) => {
            this.article = article;
            this.title = article.title() || '';
            this.body = article.content() || '';
            const cat = article.category && article.category();
            this.categoryId = cat ? String(cat.id()) : '';
          })
          .catch((err: any) => {
            this.loadError = true;
            console.error('[linkrobins/wiki] could not load article for edit:', err);
          })
      );
    } else if (!canCreateWikiArticle()) {
      this.loadError = true;
    }

    Promise.all(work).then(() => {
      this.loading = false;
      m.redraw();
    });
  }

  onremove() {
    // Close our composer if the user navigates away mid-draft.
    try {
      if (wikiComposerOpenFor(this._context()) && app.composer && app.composer.close) app.composer.close();
    } catch (e) {}
  }

  _context() {
    return this.editing ? 'article-edit-' + m.route.param('id') : 'article-new';
  }

  view() {
    return m(
      PageStructure,
      {
        className: 'IndexPage LinkRobinsWiki-page LinkRobinsWiki-page--compose',
        sidebar: () => {
          try {
            return m(WikiIndexSidebar, { className: 'LinkRobinsWiki-sidebar', activeCategory: this.categoryId || null });
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
    if (this.loadError) {
      return m('div', { className: 'LinkRobinsWiki-empty' }, tr('errors.load_article', 'Could not load this article.'));
    }

    const categoryOptions: Record<string, string> = { '': tr('compose.no_category', 'No category') };
    this.categories.forEach((cat: any) => {
      categoryOptions[String(cat.id())] = cat.name();
    });

    const composerOpen = wikiComposerOpenFor(this._context());

    return [
      m('header', { className: 'LinkRobinsWiki-header' }, [
        m('h1', { className: 'LinkRobinsWiki-title' }, this.editing ? tr('compose.title_edit', 'Edit article') : tr('compose.title', 'New article')),
      ]),

      m('div', { className: 'LinkRobinsWiki-form' }, [
        m('div', { className: 'Form-group' }, [
          m('label', tr('compose.title_label', 'Title')),
          m('input', {
            className: 'FormControl',
            value: this.title,
            disabled: this.saving,
            placeholder: tr('compose.title_placeholder', 'Article title'),
            oninput: (e: any) => {
              this.title = e.target.value;
            },
          }),
        ]),

        this.categories.length
          ? m('div', { className: 'Form-group' }, [
              m('label', tr('compose.category_label', 'Category')),
              m(Select, {
                options: categoryOptions,
                value: this.categoryId,
                disabled: this.saving,
                onchange: (v: string) => {
                  this.categoryId = v;
                },
              }),
            ])
          : null,

        m('div', { className: 'Form-group' }, [
          m('label', tr('compose.body_label', 'Content')),
          wikiComposerAvailable()
            ? m(
                'div',
                { className: 'LinkRobinsWiki-composePreview' },
                wikiComposerPreview({
                  composing: composerOpen,
                  placeholder: this.body
                    ? tr('compose.body_placeholder_edit', 'Click to continue editing…')
                    : tr('compose.body_placeholder_click', 'Click to write the article…'),
                  onclick: () => this._openComposer(),
                })
              )
            : this._renderTextareaFallback(),
        ]),

        // With the docked composer the submit lives inside it; only the
        // fallback needs page-level Save/Cancel buttons.
        !wikiComposerAvailable()
          ? m('div', { className: 'Form-group LinkRobinsWiki-formActions' }, [
              m(
                Button,
                { className: 'Button Button--primary', loading: this.saving, disabled: this.saving, onclick: () => this._submit() },
                this.editing ? tr('compose.submit_update', 'Save changes') : tr('compose.submit_create', 'Publish article')
              ),
              m(Button, { className: 'Button', onclick: () => this._cancel() }, tr('action.cancel', 'Cancel')),
            ])
          : m(
              'div',
              { className: 'LinkRobinsWiki-formActions' },
              m(Button, { className: 'Button', onclick: () => this._cancel() }, tr('action.cancel', 'Cancel'))
            ),
      ]),
    ];
  }

  _renderTextareaFallback() {
    return m('textarea', {
      className: 'FormControl',
      rows: 12,
      value: this.body,
      disabled: this.saving,
      placeholder: tr('compose.body_placeholder', 'Write the article. Markdown is supported.'),
      oninput: (e: any) => {
        this.body = e.target.value;
      },
    });
  }

  _openComposer() {
    const cat = this.categoryId ? this.categories.find((c: any) => String(c.id()) === this.categoryId) : null;
    openWikiComposer({
      wikiContext: this._context(),
      className: 'LinkRobinsWiki-articleComposer',
      placeholder: tr('compose.body_placeholder', 'Write the article. Markdown is supported.'),
      submitLabel: this.editing ? tr('compose.submit_update', 'Save changes') : tr('compose.submit_create', 'Publish article'),
      confirmExit: tr('compose.discard_confirm', 'You have an unsaved article. Discard it?'),
      originalContent: this.body || '',
      wikiHeaderItems: () => [
        {
          name: 'title',
          content: m('h3', { className: 'LinkRobinsWiki-composerTitle' }, [
            m('i', { className: (cat && cat.icon()) || 'fas fa-book' }),
            ' ',
            this.title || tr('compose.title', 'New article'),
            cat && cat.name() ? m('span', { className: 'LinkRobinsWiki-composerTitle-cat' }, ' · ' + cat.name()) : null,
          ]),
        },
      ],
      onWikiSubmit: (content: string, body: any) => this._submit(content, body),
    });
  }

  _submit(content?: string, body?: any) {
    if (this.saving) return;

    const title = (this.title || '').trim();
    const bodyText = (typeof content === 'string' ? content : this.body || '').trim();
    if (!title) {
      showError(tr('errors.title_required', 'Please enter a title.'));
      return;
    }
    if (!bodyText) {
      showError(tr('errors.empty_body', 'Please write the article body before submitting.'));
      return;
    }

    const category = this.categoryId ? app.store.getById('linkrobins-wiki-categories', this.categoryId) || null : null;

    this.saving = true;
    if (body) body.loading = true;
    m.redraw();

    const done = (article: any) => {
      this.saving = false;
      this.body = '';
      if (body && body.composer) body.composer.hide();
      m.route.set(basePath() + BASE_PATH + '/' + encodeURIComponent(article.id()));
    };
    const fail = (err: any) => {
      this.saving = false;
      if (body) body.loading = false;
      showError(tr('errors.submit', 'Could not save the article.'));
      console.error('[linkrobins/wiki] save failed:', err);
      m.redraw();
    };

    if (this.editing && this.article) {
      updateArticle(this.article, { title, content: bodyText, relationships: { category } }).then(done).catch(fail);
    } else {
      createArticle(title, bodyText, category).then(done).catch(fail);
    }
  }

  _cancel() {
    try {
      if (wikiComposerOpenFor(this._context()) && app.composer && app.composer.close) app.composer.close();
    } catch (e) {}
    if (this.editing && this.article) {
      m.route.set(basePath() + BASE_PATH + '/' + encodeURIComponent(this.article.id()));
    } else {
      m.route.set(basePath() + BASE_PATH);
    }
  }
}
