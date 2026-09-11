import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import CategoryEditorModal from './CategoryEditorModal';
import { t, tx, loadCategoriesList, loadReports, resolveReport } from '../utils';

export default class WikiAdminPage extends ExtensionPage {
  loading = true;
  categories: any[] = [];
  reports: any[] = [];
  reportsLoading = true;
  showResolved = false;

  oninit(vnode: any) {
    super.oninit(vnode);
    this._load();
    this._loadReports();
  }

  _loadReports() {
    this.reportsLoading = true;
    loadReports(this.showResolved)
      .then((reports: any[]) => {
        this.reports = reports || [];
        this.reportsLoading = false;
        m.redraw();
      })
      .catch(() => {
        this.reports = [];
        this.reportsLoading = false;
        m.redraw();
      });
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
        m('div', { className: 'LinkRobinsWikiAdmin' }, [
          this._renderReports(),
          this._renderIndexLayout(),
          this._renderLayout(),
          this._renderToc(),
          this._renderRelated(),
          this._renderCategories(),
        ]),
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
          this._shortcodeRow('[Label](https://example.com)', 'shortcode_link'),
          this._shortcodeRow('[html] … [/html]', 'shortcode_html'),
        ]),
      ]),

      this.submitButton(),
    ]);
  }

  _shortcodeRow(code: string, key: string) {
    return m('li', [m('code', code), ' — ', t('linkrobins-wiki.admin.index_layout.' + key)]);
  }

  // --- Layout ----------------------------------------------------------

  _renderLayout() {
    return m('section', { className: 'LinkRobinsWikiAdmin-section' }, [
      m('h2', t('linkrobins-wiki.admin.layout.heading')),
      m('p', { className: 'helpText' }, t('linkrobins-wiki.admin.layout.intro')),

      this.buildSettingComponent({
        type: 'select',
        setting: 'linkrobins-wiki.nav_position',
        default: 'sections',
        options: {
          top: t('linkrobins-wiki.admin.layout.nav_position_top'),
          below_all: t('linkrobins-wiki.admin.layout.nav_position_below_all'),
          sections: t('linkrobins-wiki.admin.layout.nav_position_sections'),
          bottom: t('linkrobins-wiki.admin.layout.nav_position_bottom'),
          hidden: t('linkrobins-wiki.admin.layout.nav_position_hidden'),
        },
        label: t('linkrobins-wiki.admin.layout.nav_position_label'),
        help: t('linkrobins-wiki.admin.layout.nav_position_help'),
      }),

      this.buildSettingComponent({
        type: 'boolean',
        setting: 'linkrobins-wiki.full_width',
        label: t('linkrobins-wiki.admin.layout.full_width_label'),
        help: t('linkrobins-wiki.admin.layout.full_width_help'),
      }),

      this.submitButton(),
    ]);
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

  // --- Related articles -------------------------------------------------

  _renderRelated() {
    return m('section', { className: 'LinkRobinsWikiAdmin-section' }, [
      m('h2', t('linkrobins-wiki.admin.related.heading')),
      m('p', { className: 'helpText' }, t('linkrobins-wiki.admin.related.intro')),

      this.buildSettingComponent({
        type: 'boolean',
        setting: 'linkrobins-wiki.related_enabled',
        label: t('linkrobins-wiki.admin.related.enabled_label'),
        help: t('linkrobins-wiki.admin.related.enabled_help'),
      }),

      this.buildSettingComponent({
        type: 'number',
        setting: 'linkrobins-wiki.related_limit',
        min: 1,
        max: 20,
        label: t('linkrobins-wiki.admin.related.limit_label'),
        help: t('linkrobins-wiki.admin.related.limit_help'),
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

  // --- Reports ---------------------------------------------------------

  _renderReports() {
    const open = this.reports.filter((r: any) => !r.isResolved()).length;

    return m('section', { className: 'LinkRobinsWikiAdmin-section' }, [
      m('h2', [
        t('linkrobins-wiki.admin.reports.heading'),
        // The count is why this section leads: an editor should see something
        // is waiting without opening anything.
        open ? m('span', { className: 'LinkRobinsWikiAdmin-reportBadge' }, String(open)) : null,
      ]),
      m('p', { className: 'helpText' }, t('linkrobins-wiki.admin.reports.intro')),

      m(
        Button,
        {
          className: 'Button',
          icon: this.showResolved ? 'fas fa-eye-slash' : 'fas fa-eye',
          onclick: () => {
            this.showResolved = !this.showResolved;
            this._loadReports();
          },
        },
        this.showResolved ? t('linkrobins-wiki.admin.reports.hide_resolved') : t('linkrobins-wiki.admin.reports.show_resolved')
      ),

      this.reportsLoading ? m(LoadingIndicator) : this._renderReportList(),
    ]);
  }

  _renderReportList() {
    if (!this.reports.length) {
      return m('p', { className: 'LinkRobinsWikiAdmin-empty' }, t('linkrobins-wiki.admin.reports.empty'));
    }

    return m(
      'ul',
      { className: 'LinkRobinsWikiAdmin-reports' },
      this.reports.map((report: any) => {
        const article = report.article && report.article();
        const reporter = report.user && report.user();
        const resolved = report.isResolved();

        return m('li', { className: 'LinkRobinsWikiAdmin-report' + (resolved ? ' is-resolved' : ''), key: 'report-' + report.id() }, [
          m('div', { className: 'LinkRobinsWikiAdmin-report-main' }, [
            m('div', { className: 'LinkRobinsWikiAdmin-report-title' }, [
              article
                ? m('a', { href: '/wiki/' + article.id(), target: '_blank' }, article.title())
                : t('linkrobins-wiki.admin.reports.article_gone'),
              m('span', { className: 'LinkRobinsWikiAdmin-report-reason' }, tx('linkrobins-wiki.admin.reports.reason_' + report.reason())),
            ]),
            report.detail() ? m('div', { className: 'LinkRobinsWikiAdmin-report-detail' }, report.detail()) : null,
            m('div', { className: 'LinkRobinsWikiAdmin-report-meta' }, [
              reporter ? reporter.displayName() || reporter.username() : t('linkrobins-wiki.admin.reports.deleted_user'),
              ' \u00b7 ',
              report.createdAt() ? new Date(report.createdAt()).toLocaleString() : '',
            ]),
          ]),
          m(
            Button,
            {
              className: 'Button Button--small',
              icon: resolved ? 'fas fa-undo' : 'fas fa-check',
              onclick: () => {
                resolveReport(report, !resolved).then(() => this._loadReports());
              },
            },
            resolved ? t('linkrobins-wiki.admin.reports.reopen') : t('linkrobins-wiki.admin.reports.resolve')
          ),
        ]);
      })
    );
  }

  _openEditor(category: any) {
    app.modal.show(CategoryEditorModal, { category, onSaved: () => this._load() });
  }
}
