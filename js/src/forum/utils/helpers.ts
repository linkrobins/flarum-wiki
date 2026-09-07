import Link from 'flarum/common/components/Link';
import type User from 'flarum/common/models/User';
import { trText } from './translate';

export const BASE_PATH = '/wiki';

export function readForumAttribute(key: string): any {
  try {
    if (app.forum && typeof app.forum.attribute === 'function') {
      return app.forum.attribute(key);
    }
  } catch (e) {}
  return null;
}

// --- Layout settings --------------------------------------------------

// Where the global "Wiki" nav link sits, as an ItemList priority. Core puts
// "All Discussions" at 100 and flarum/tags puts its "Tags" link at -10, its
// separator at -12 and the tag list at -14, so these four values give the
// admin's chosen position without needing to know any of that.
const NAV_PRIORITIES: Record<string, number> = {
  top: 200,
  below_all: 50,
  sections: -11,
  bottom: -1000,
};

export function navPriority(): number {
  const value = String(readForumAttribute('linkrobinsWikiNavPosition') || 'sections');
  const priority = NAV_PRIORITIES[value];
  return priority === undefined ? NAV_PRIORITIES.sections : priority;
}

// When on, wiki pages drop the forum sidebar and use the whole container. Off
// unless the attribute says otherwise, so an older backend keeps the sidebar.
export function fullWidth(): boolean {
  return !!readForumAttribute('linkrobinsWikiFullWidth');
}

// Related articles: a short list of siblings from the same category under each
// article. Defaults match the backend so an older payload behaves sensibly.
export function relatedEnabled(): boolean {
  const v = readForumAttribute('linkrobinsWikiRelatedEnabled');
  return v === null || v === undefined ? true : !!v;
}

export function relatedLimit(): number {
  const n = parseInt(String(readForumAttribute('linkrobinsWikiRelatedLimit')), 10);
  return isNaN(n) || n < 1 ? 5 : Math.min(n, 20);
}

// PageStructure always renders a .Page-sidebar, and pushes whatever the
// sidebar callback returns through ItemList.toArray(). That converts a
// non-object to Object(content), so a null becomes {} -- a bare object Mithril
// then treats as a vnode and reads `.view` off, which blanks the whole page.
// Omitting the callback entirely hits the same path. So full-width mode hands
// it an empty element and the LESS collapses the column.
export function emptySidebar(): any {
  return m('div', { className: 'LinkRobinsWiki-sidebarOff' });
}

// Append the full-width modifier when the setting is on, so the LESS can
// collapse the sidebar column and widen the content.
export function pageClassName(base: string): string {
  return fullWidth() ? base + ' LinkRobinsWiki-page--full' : base;
}

export function basePath(): string {
  try {
    return (app.forum && app.forum.attribute && app.forum.attribute('basePath')) || '';
  } catch (e) {
    return '';
  }
}

// The canonical URL segment for an article: its slug when it has one, its id
// otherwise (pre-slug rows whose title had nothing slug-like). The server
// resolves both.
export function articleSegment(article: any): string {
  try {
    const slug = article && article.slug && article.slug();
    if (slug) return String(slug);
  } catch (e) {}
  return String(article && article.id ? article.id() : '');
}

export function articleHref(article: any): string {
  return basePath() + BASE_PATH + '/' + encodeURIComponent(articleSegment(article));
}

export function suppressTagsList(): void {
  try {
    if (app.current && typeof app.current.set === 'function') {
      app.current.set('noTagsList', true);
    }
  } catch (e) {}
}

export function formatDate(value: Date | string | null | undefined): string {
  if (!value) return '';
  try {
    const d = value instanceof Date ? value : new Date(value);
    if (isNaN(d.getTime())) return '';
    // Format date and time separately and join with "at" rather than
    // toLocaleString's single comma-glued string, which reads as an awkward
    // double-comma run when embedded in a sentence.
    // Format in the forum's selected locale (not just the browser's), so dates
    // localize with the rest of the UI.
    const locale = (app && app.data && app.data.locale) || undefined;
    const datePart = d.toLocaleDateString(locale, { year: 'numeric', month: 'short', day: 'numeric' });
    const timePart = d.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
    return trText('common.date_at_time', '{date} at {time}', { date: datePart, time: timePart });
  } catch (e) {
    return '';
  }
}

export function safeNavigate(href: string, ev?: any): void {
  if (ev && (ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.button === 1)) return;
  if (typeof href !== 'string' || href === '') return;
  const base = basePath();
  let path = href;
  if (base && href.indexOf(base) === 0) path = href.slice(base.length);
  if (path.charAt(0) !== '/') return;
  if (ev) ev.preventDefault();
  try {
    m.route.set(path);
  } catch (e) {}
}

export function showError(message: any): void {
  try {
    if (app && app.alerts && typeof app.alerts.show === 'function') {
      app.alerts.show({ type: 'error' }, message);
      return;
    }
  } catch (e) {}
  // Fallback when the alert system isn't available. Must NOT call showError
  // again (that recurses infinitely and overflows the stack); log instead.
  try {
    console.error('[linkrobins/wiki] ' + message);
  } catch (e) {}
}

/**
 * Run the <script> tags embedded in formatted content, once per content
 * string. The formatter can emit scripts inside rendered HTML (core's
 * markdown does this for its syntax-highlighting loader), but HTML inserted
 * via m.trust never executes them -- so without this, code blocks render
 * unhighlighted. Core's CommentPost does the same for discussion posts.
 *
 * The executed-content marker lives on the DOM node itself: Mithril only
 * rewrites the element's HTML when the trusted string changes, so a stale
 * marker exactly tracks a stale (already-executed) DOM.
 */
export function executeContentScripts(el: Element | null | undefined, html: string): void {
  if (!el) return;
  const node = el as any;
  if (node._lrWikiScriptedHtml === html) return;
  node._lrWikiScriptedHtml = html;

  el.querySelectorAll('script').forEach((inert) => {
    try {
      const script = document.createElement('script');
      script.textContent = inert.textContent;
      Array.from(inert.attributes).forEach((attr) => script.setAttribute(attr.name, attr.value));
      inert.parentNode && inert.parentNode.replaceChild(script, inert);
    } catch (e) {}
  });
}

/**
 * Render a username as a link to the user's profile. Accepts a store User
 * model; falls back to plain text if there's no username.
 */
export function userLink(user: User | null | undefined): any {
  if (!user) return '';
  const username = user.username && user.username();
  const label = (user.displayName && user.displayName()) || username || '';
  if (!username) return label;
  const href = basePath() + '/u/' + encodeURIComponent(username);
  return Link.component({ href }, label);
}
