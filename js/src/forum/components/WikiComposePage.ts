import Page from 'flarum/common/components/Page';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import TextEditor from 'flarum/common/components/TextEditor';
import Select from 'flarum/common/components/Select';
import Button from 'flarum/common/components/Button';
import PageStructure from 'flarum/forum/components/PageStructure';
import WikiIndexSidebar from './WikiIndexSidebar';
import { tr } from '../utils/translate';
import { basePath, BASE_PATH, showError } from '../utils/helpers';
import { canCreateWikiArticle } from '../utils/permissions';
import { loadArticle, loadCategories, createArticle, updateArticle } from '../utils/api';

/**
 * Full-page editor for creating a new article (/wiki/new) or editing an
 * existing one (/wiki/:id/edit). Title + optional category + markdown body.
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

  view() {
    return m(
      PageStructure,
      {
        className: 'IndexPage LinkRobinsWiki-page LinkRobinsWiki-page--compose',
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
    if (this.loadError) {
      return m('div', { className: 'LinkRobinsWiki-empty' }, tr('errors.load_article', 'Could not load this article.'));
    }

    const categoryOptions: Record<string, string> = { '': tr('compose.no_category', 'No category') };
    this.categories.forEach((cat: any) => {
      categoryOptions[String(cat.id())] = cat.name();
    });

    return [
      m('header', { className: 'LinkRobinsWiki-header' }, [
        m('h1', { className: 'LinkRobinsWiki-title' }, this.editing ? tr('compose.title_edit', 'Edit article') : tr('compose.title', 'New article')),
      ]),

      m('form', { className: 'LinkRobinsWiki-form', onsubmit: (e: any) => { e.preventDefault(); this._save(); } }, [
        m('div', { className: 'Form-group' }, [
          m('label', tr('compose.title_label', 'Title')),
          m('input', {
            className: 'FormControl',
            value: this.title,
            placeholder: tr('compose.title_placeholder', 'Article title'),
            oninput: (e: any) => { this.title = e.target.value; },
          }),
        ]),

        this.categories.length
          ? m('div', { className: 'Form-group' }, [
              m('label', tr('compose.category_label', 'Category')),
              m(Select, {
                options: categoryOptions,
                value: this.categoryId,
                onchange: (v: string) => { this.categoryId = v; },
              }),
            ])
          : null,

        m('div', { className: 'Form-group' }, [
          m('label', tr('compose.body_label', 'Content')),
          m(TextEditor, {
            value: this.body,
            disabled: this.saving,
            placeholder: tr('compose.body_placeholder', 'Write the article. Markdown is supported.'),
            onchange: (v: string) => { this.body = v; },
            onsubmit: () => this._save(),
          }),
        ]),

        m('div', { className: 'Form-group LinkRobinsWiki-formActions' }, [
          m(
            Button,
            { type: 'submit', className: 'Button Button--primary', loading: this.saving, disabled: this.saving },
            this.editing ? tr('compose.submit_update', 'Save changes') : tr('compose.submit_create', 'Publish article')
          ),
          m(Button, { type: 'button', className: 'Button', onclick: () => this._cancel() }, tr('action.cancel', 'Cancel')),
        ]),
      ]),
    ];
  }

  _save() {
    if (this.saving) return;

    const title = (this.title || '').trim();
    const body = (this.body || '').trim();
    if (!title) {
      showError(tr('errors.title_required', 'Please enter a title.'));
      return;
    }
    if (!body) {
      showError(tr('errors.empty_body', 'Please write the article body before submitting.'));
      return;
    }

    const category = this.categoryId
      ? app.store.getById('linkrobins-wiki-categories', this.categoryId) || null
      : null;

    this.saving = true;
    m.redraw();

    const done = (article: any) => {
      this.saving = false;
      m.route.set(basePath() + BASE_PATH + '/' + encodeURIComponent(article.id()));
    };
    const fail = (err: any) => {
      this.saving = false;
      showError(tr('errors.submit', 'Could not save the article.'));
      console.error('[linkrobins/wiki] save failed:', err);
      m.redraw();
    };

    if (this.editing && this.article) {
      updateArticle(this.article, { title, content: body, relationships: { category } }).then(done).catch(fail);
    } else {
      createArticle(title, body, category).then(done).catch(fail);
    }
  }

  _cancel() {
    if (this.editing && this.article) {
      m.route.set(basePath() + BASE_PATH + '/' + encodeURIComponent(this.article.id()));
    } else {
      m.route.set(basePath() + BASE_PATH);
    }
  }
}
