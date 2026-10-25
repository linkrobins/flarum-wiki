import LinkPreview from 'ext:datlechin/flarum-link-preview/forum/extenders/LinkPreview';

export default function extendLinkPreview() {
  new LinkPreview()
    .content('ext:linkrobins/wiki/forum/components/WikiShowPage', '.LinkRobinsWiki-articleBody, .LinkRobinsWiki-faq-answer')
    .content('ext:linkrobins/wiki/forum/components/WikiComments', '.LinkRobinsWiki-comment-body')
    .extend(app, { name: 'linkrobins-wiki', exports: {} });
}
