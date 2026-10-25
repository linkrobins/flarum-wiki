import app from 'flarum/forum/app';

/**
 * Link Preview cards for wiki pages, only when Link Preview 2.1+ is really there.
 *
 * Looked up at runtime instead of imported: a forum can have Link Preview enabled but serve an older build of it
 * (an older version, or compiled assets not rebuilt after an update), and a missing extender must never stop the
 * wiki from starting (2026-10-25: "linkrobins-wiki failed to initialize. TypeError: A() is not a constructor").
 */
export default function extendLinkPreview() {
  const LinkPreview = (flarum as any).reg?.get?.('datlechin-link-preview', 'forum/extenders/LinkPreview');
  if (typeof LinkPreview !== 'function') return;

  try {
    new LinkPreview()
      .content('ext:linkrobins/wiki/forum/components/WikiShowPage', '.LinkRobinsWiki-articleBody, .LinkRobinsWiki-faq-answer')
      .content('ext:linkrobins/wiki/forum/components/WikiComments', '.LinkRobinsWiki-comment-body')
      .extend(app, { name: 'linkrobins-wiki', exports: {} });
  } catch (e) {
    console.warn('[linkrobins/wiki] Link Preview integration skipped:', e);
  }
}
