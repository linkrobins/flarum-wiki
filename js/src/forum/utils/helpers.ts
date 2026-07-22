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
