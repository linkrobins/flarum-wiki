import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import CategoryEditorModal from './CategoryEditorModal';
import { t, loadCategoriesList } from '../utils';

export default class WikiAdminPage extends ExtensionPage {
  loading = true;
  categories: any[] = [];

  oninit(vnode: any) {
    super.oninit(vnode);
    this._load();
  }

  _load() {
    this.loading = true;
    loadCategoriesList()
      .then((cats: any[]) => {
        this.categories = cats || [];
        this.loading = false;
        m.redraw();
      })
      .catch(() => {
        this.loading = false;
        m.redraw();
      });
  }

  content() {
    return m('div', { className: 'ExtensionPage-settings' }, [
      m('div', { className: 'container' }, [
        m('div', { className: 'LinkRobinsWikiAdmin' }, [this._renderIndexLayout(), this._renderToc(), this._renderCategories()]),
      ]),
    ]);
  }

  // --- Index layout (customizable homepage) ----------------------------

  _renderIndexLayout() {
    return m('section', { className: 'LinkRobinsWikiAdmin-section' }, [
      m('h2', t('linkrobins-wiki.admin.index_layout.heading')),
      m('p', { className: 'helpText' }, t('linkrobins-wiki.admin.index_layout.intro')),

      this.buildSettingComponent({
        type: 'textarea',
        setting: 'linkrobins-wiki.index_layout',
        label: t('linkrobins-wiki.admin.index_layout.label'),
        help: t('linkrobins-wiki.admin.index_layout.help'),
        rows: 10,
        placeholder: '[articles limit="5" title="Recent"]\n[categories]',
      }),

      m('div', { className: 'LinkRobinsWikiAdmin-shortcodes' }, [
        m('h4', t('linkrobins-wiki.admin.index_layout.shortcodes_heading')),
        m('ul', [
          this._shortcodeRow('[articles]', 'shortcode_articles'),
          this._shortcodeRow('[articles category="slug" limit="5"]', 'shortcode_articles_cat'),
          this._shortcodeRow('[article id="3"]', 'shortcode_article'),
          this._shortcodeRow('[categories]', 'shortcode_categories'),
          this._shortcodeRow('# Heading', 'shortcode_heading'),
        ]),
      ]),

      this.submitButton(),
    ]);
  }

  _shortcodeRow(code: string, key: string) {
    return m('li', [m('code', code), ' — ', t('linkrobins-wiki.admin.index_layout.' + key)]);
  }

  // --- Table of contents -----------------------------------------------

  _renderToc() {
    return m('section', { className: 'LinkRobinsWikiAdmin-section' }, [
      m('h2', t('linkrobins-wiki.admin.toc.heading')),
      m('p', { className: 'helpText' }, t('linkrobins-wiki.admin.toc.intro')),

      this.buildSettingComponent({
        type: 'boolean',
        setting: 'linkrobins-wiki.toc_enabled',
        label: t('linkrobins-wiki.admin.toc.enabled_label'),
        help: t('linkrobins-wiki.admin.toc.enabled_help'),
      }),

      this.buildSettingComponent({
        type: 'number',
        setting: 'linkrobins-wiki.toc_min_headings',
        min: 1,
        label: t('linkrobins-wiki.admin.toc.min_headings_label'),
        help: t('linkrobins-wiki.admin.toc.min_headings_help'),
      }),

      this.submitButton(),
    ]);
  }

  // --- Categories ------------------------------------------------------

  _renderCategories() {
    return m('section', { className: 'LinkRobinsWikiAdmin-section' }, [
      m('h2', t('linkrobins-wiki.admin.categories.heading')),
      m('p', { className: 'helpText' }, t('linkrobins-wiki.admin.categories.intro')),

      m(
        Button,
        { className: 'Button Button--primary', icon: 'fas fa-plus', onclick: () => this._openEditor(null) },
        t('linkrobins-wiki.admin.categories.new_button')
      ),

      this.loading ? m(LoadingIndicator) : this._renderTable(),
    ]);
  }

  _renderTable() {
    if (!this.categories.length) {
      return m('p', { className: 'LinkRobinsWikiAdmin-empty' }, t('linkrobins-wiki.admin.categories.empty'));
    }

    return m('table', { className: 'LinkRobinsWikiAdmin-table' }, [
      m(
        'thead',
        m('tr', [
          m('th', t('linkrobins-wiki.admin.categories.column_name')),
          m('th', t('linkrobins-wiki.admin.categories.column_slug')),
          m('th', t('linkrobins-wiki.admin.categories.column_articles')),
          m('th'),
        ])
      ),
      m(
        'tbody',
        this.categories.map((cat: any) =>
          m('tr', { key: 'cat-' + cat.id() }, [
            m('td', [cat.icon() ? m('i', { className: cat.icon(), style: 'color: ' + (cat.color() || 'inherit') }) : null, ' ', cat.name()]),
            m('td', m('code', cat.slug())),
            m('td', String(cat.articleCount ? cat.articleCount() : 0)),
            m(
              'td',
              { className: 'LinkRobinsWikiAdmin-rowActions' },
              m(
                Button,
                {
                  className: 'Button',
                  icon: 'fas fa-pencil-alt',
                  title: t('linkrobins-wiki.admin.categories.edit_button'),
                  onclick: () => this._openEditor(cat),
                },
                t('linkrobins-wiki.admin.categories.edit_button')
              )
            ),
          ])
        )
      ),
    ]);
  }

  _openEditor(category: any) {
    app.modal.show(CategoryEditorModal, { category, onSaved: () => this._load() });
  }
}
