import FormModal from 'flarum/common/components/FormModal';
import Form from 'flarum/common/components/Form';
import Button from 'flarum/common/components/Button';
import { tr, trText } from '../utils/translate';

const REASONS = ['inaccurate', 'outdated', 'off_topic', 'duplicate', 'other'];

/**
 * Report an article to the editors.
 *
 * A reason from a fixed list so the queue can be grouped and translated, plus
 * the reporter's own words, which is the part that usually explains it. The
 * article is named in the modal because this is reachable from a menu, and a
 * reader who opened the wrong one should be able to see that before sending.
 */
export default class WikiReportModal extends FormModal {
  article: any = null;
  reason = 'inaccurate';
  detail = '';
  saving = false;
  error: string | null = null;

  oninit(vnode: any) {
    super.oninit(vnode);
    this.article = (this.attrs && this.attrs.article) || null;
    this.reason = 'inaccurate';
    this.detail = '';
    this.saving = false;
    this.error = null;
  }

  className() {
    return 'LinkRobinsWikiReportModal Modal--small';
  }

  title() {
    return trText('report.title', 'Report this article');
  }

  content() {
    return m('div', { className: 'Modal-body' }, [
      m(Form, [
        this.article
          ? m('p', { className: 'helpText' }, trText('report.intro', 'Editors will see this along with the article.'))
          : null,

        m('div', { className: 'Form-group' }, [
          m('label', tr('report.reason_label', 'What is wrong with it?')),
          m(
            'div',
            { className: 'LinkRobinsWikiReport-reasons' },
            REASONS.map((key) =>
              m('label', { className: 'LinkRobinsWikiReport-reason', key }, [
                m('input', {
                  type: 'radio',
                  name: 'linkrobins-wiki-report-reason',
                  value: key,
                  checked: this.reason === key,
                  disabled: this.saving,
                  onchange: () => {
                    this.reason = key;
                  },
                }),
                m('span', tr('report.reason_' + key, key)),
              ])
            )
          ),
        ]),

        m('div', { className: 'Form-group' }, [
          m('label', tr('report.detail_label', 'Anything to add? (optional)')),
          m('textarea', {
            className: 'FormControl',
            rows: 4,
            maxlength: 1000,
            value: this.detail,
            disabled: this.saving,
            oninput: (e: any) => {
              this.detail = e.target.value;
            },
          }),
        ]),

        this.error ? m('div', { className: 'Alert Alert--error' }, this.error) : null,

        m('div', { className: 'Form-group' }, [
          m(
            Button,
            { className: 'Button Button--primary', type: 'submit', loading: this.saving, disabled: this.saving },
            tr('report.submit', 'Send report')
          ),
        ]),
      ]),
    ]);
  }

  onsubmit(e: any) {
    e.preventDefault();

    if (this.saving || !this.article) return;

    this.saving = true;
    this.error = null;

    app.store
      .createRecord('linkrobins-wiki-reports')
      .save(
        { reason: this.reason, detail: this.detail },
        { relationships: { article: this.article } }
      )
      .then(() => {
        this.saving = false;
        this.hide();
        app.alerts.show({ type: 'success' }, trText('report.sent', 'Thank you. An editor will look at this.'));
      })
      .catch((err: any) => {
        this.saving = false;
        // The server explains itself for the cases a reader can cause, the
        // duplicate report above all; anything else gets the generic line
        // rather than a raw response.
        this.error =
          err && err.response && err.response.errors && err.response.errors[0] && err.response.errors[0].detail
            ? err.response.errors[0].detail
            : trText('report.failed', 'That could not be sent. Please try again.');
        m.redraw();
      });
  }
}
