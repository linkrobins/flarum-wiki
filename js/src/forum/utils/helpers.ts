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

/**
 * Whether the admin has asked for no Wiki link in the navigation at all.
 *
 * Checked before the item is added rather than hidden with CSS, so it is gone
 * from the sidebar and the mobile drawer alike, both of which render the same
 * IndexSidebar item list.
 */
export function navHidden(): boolean {
  return String(readForumAttribute('linkrobinsWikiNavPosition') || 'sections') === 'hidden';
}

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
/**
 * The wiki's title for the phone header, in full-width mode only.
 *
 * Core positions any `.App-titleControl` found on the page into the middle of
 * the phone header, between the drawer toggle and the primary control. The
 * wiki's copy of that control normally rides in the index sidebar, but
 * full-width mode hides the sidebar outright, which left the bar on a phone
 * showing a hamburger, a plus, and nothing at all between them.
 *
 * `--text` is core's own non-interactive variant of the control (the one Modal
 * and HeaderList use), so this picks up the header's line height, colour and
 * ellipsis without any styling of its own. The stylesheet hides it above phone
 * width, where the sidebar's own control does this job and every wiki page
 * already carries a real heading.
 */
export function mobileTitle(): any {
  if (!fullWidth()) {
    return null;
  }

  return m('h4', { className: 'App-titleControl App-titleControl--text LinkRobinsWiki-mobileTitle' }, trText('nav', 'Wiki'));
}

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
 * Make FoF Upload's file buttons (`[upl-file]`) download inside wiki content.
 *
 * FoF Upload renders the button as plain markup carrying
 * data-fof-upload-download-uuid and binds the click only inside its own Post
 * component, so in anything that is not a discussion post (articles, FAQ
 * answers, comments) the button shows but does nothing. We bind the same click
 * ourselves, using FoF Upload's own post-less route
 * (/api/fof/download/{uuid}), which checks the actor's download permission
 * server-side; the post-scoped route needs a post id a wiki article does not
 * have. Nothing here touches FoF Upload's JS, so it works the same whether
 * FoF Upload is absent (no such buttons exist), old or current.
 *
 * One delegated listener per content node; the node outlives m.trust rewrites.
 */
export function bindUploadDownloads(el: Element): void {
  const node = el as any;
  if (node._lrWikiDownloadsBound) return;
  node._lrWikiDownloadsBound = true;

  el.addEventListener('click', (e: Event) => {
    try {
      const target = e.target as Element | null;
      const button = target && target.closest ? (target.closest('[data-fof-upload-download-uuid]') as HTMLElement | null) : null;
      if (!button || !el.contains(button)) return;
      const uuid = button.getAttribute('data-fof-upload-download-uuid');
      if (!uuid) return;

      e.preventDefault();
      e.stopPropagation();

      if (readForumAttribute('fof-upload.canDownload') === false) {
        let message: any = app.translator.trans('fof-upload.forum.states.unauthorized', {}, true);
        if (typeof message !== 'string' || message.indexOf('fof-upload.') === 0) {
          message = trText('errors.download_denied', 'You do not have permission to download this file.');
        }
        showError(message);
        return;
      }

      window.open(app.forum.attribute('apiUrl') + '/fof/download/' + encodeURIComponent(uuid));
    } catch (err) {
      try {
        console.error('[linkrobins/wiki] file download failed:', err);
      } catch (e2) {}
    }
  });
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
 *
 * Also wires FoF Upload's download buttons inside the content (see
 * bindUploadDownloads), since every place that renders formatted wiki HTML
 * already calls this.
 */
export function executeContentScripts(el: Element | null | undefined, html: string): void {
  if (!el) return;
  bindUploadDownloads(el);
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

// --- Category colors as text ---------------------------------------------

/** Relative luminance of an sRGB color (WCAG 2.x). */
function luminance([r, g, b]: number[]): number {
  const lin = (v: number) => {
    const c = v / 255;
    return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
  };
  return 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b);
}

function contrast(a: number[], b: number[]): number {
  const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x);
  return (hi + 0.05) / (lo + 0.05);
}

function parseHex(color: string): number[] | null {
  const m = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.exec(String(color || '').trim());
  if (!m) return null;
  const h = m[1].length === 3 ? m[1].replace(/./g, '$&$&') : m[1];
  return [0, 2, 4].map((i) => parseInt(h.slice(i, i + 2), 16));
}

const toHex = (rgb: number[]) => '#' + rgb.map((v) => Math.round(v).toString(16).padStart(2, '0')).join('');

/** Move a color toward `target` until it clears `ratio` against `bg` (hue is kept). */
function nudge(rgb: number[], target: number[], bg: number[], ratio: number): number[] {
  let out = rgb;
  for (let t = 0; t <= 1.0001 && contrast(out, bg) < ratio; t += 0.02) {
    out = rgb.map((v, i) => v + (target[i] - v) * t);
  }
  return out;
}

// The worst-case backgrounds category text sits on: white (and the faintly
// gray section tint, hence the margin over 4.5) in the light theme, and core's
// dark control background in the dark theme.
const LIGHT_BG = [255, 255, 255];
const DARK_BG = [30, 36, 43];

/**
 * An inline style that hands a category's color to the stylesheet as two
 * text-safe variants: `--lr-wiki-cat-l` for the light theme and
 * `--lr-wiki-cat-d` for the dark one. An admin picks a category color as an
 * accent, and mid-tones like #22a06b fail 4.5:1 as text on white; this darkens
 * (light) or lightens (dark) just enough to pass, the way the site's own cyan
 * is deepened to #07788f. Anything that is not a hex color is passed through.
 */
export function categoryTextStyle(color: string | null | undefined): string | undefined {
  if (!color) return undefined;
  const rgb = parseHex(color);
  if (!rgb) return `--lr-wiki-cat-l:${color};--lr-wiki-cat-d:${color}`;
  const light = toHex(nudge(rgb, [0, 0, 0], LIGHT_BG, 4.8));
  const dark = toHex(nudge(rgb, [255, 255, 255], DARK_BG, 4.8));
  return `--lr-wiki-cat-l:${light};--lr-wiki-cat-d:${dark}`;
}
