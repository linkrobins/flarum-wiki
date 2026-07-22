// Table of contents for wiki articles. The article body is server-rendered
// HTML (Flarum's markdown formatter), so `#` / `##` / `###` arrive as
// <h1>/<h2>/<h3>. We scan those, give each a stable anchor id, and hand back a
// flat list the show page turns into the sticky contents rail.

export interface WikiTocEntry {
  id: string;
  text: string;
  level: number;
}

// # / ## / ### -> h1 / h2 / h3. Stopping at h3 keeps the rail from filling up
// with deep sub-sub-headings.
export const WIKI_TOC_MAX_DEPTH = 3;

// Read a forum boot attribute, tolerating app.forum not being ready yet (e.g.
// if ever called during init). Callers apply their own default on undefined.
function forumAttr(key: string): any {
  try {
    if (app.forum && typeof app.forum.attribute === 'function') {
      return app.forum.attribute(key);
    }
  } catch (e) {
    // app.forum not built yet -- fall through to undefined.
  }
  return undefined;
}

// The admin toggle. Defaults to true (matching the Settings default) so the
// feature is on out of the box even before the attribute round-trips.
export function tocEnabled(): boolean {
  const v = forumAttr('linkrobinsWikiTocEnabled');
  if (v === undefined || v === null) return true;
  return !!v;
}

// Only render the rail once an article has at least this many headings; a lone
// heading isn't worth a contents panel.
export function tocMinHeadings(): number {
  const raw = parseInt(String(forumAttr('linkrobinsWikiTocMinHeadings')), 10);
  if (isNaN(raw) || raw < 1) return 2;
  return raw;
}

function headingSelector(): string {
  const parts: string[] = [];
  for (let i = 1; i <= WIKI_TOC_MAX_DEPTH; i++) parts.push(`h${i}`);
  return parts.join(', ');
}

function slugify(text: string): string {
  if (typeof text !== 'string') return 'section';
  const s = text
    .toLowerCase()
    .replace(/[\s_]+/g, '-')
    .replace(/[^a-z0-9\-]+/g, '')
    .replace(/-+/g, '-')
    .replace(/^-+|-+$/g, '');
  return s || 'section';
}

// Return an id not yet in `used`, suffixing `-2`, `-3`, ... on collision. We
// track every assigned id (not just a per-base counter) so a deduped
// `intro` -> `intro-2` can't collide with a heading that naturally slugs to
// `intro-2`.
function uniqueSlug(base: string, used: Set<string>): string {
  if (!used.has(base)) {
    used.add(base);
    return base;
  }
  let n = 2;
  while (used.has(`${base}-${n}`)) n++;
  const id = `${base}-${n}`;
  used.add(id);
  return id;
}

// Read + mutate: give every heading in the article body a stable id (idempotent
// across redraws via a data attribute) and return the contents entries in
// document order. Ids are prefixed `wiki-` so they can't clash with other
// anchors elsewhere on the page.
export function processWikiHeadings(bodyEl: Element | null): WikiTocEntry[] {
  if (!bodyEl || typeof bodyEl.querySelectorAll !== 'function') return [];

  const headings = Array.from(bodyEl.querySelectorAll<HTMLElement>(headingSelector()));
  const used = new Set<string>();
  const entries: WikiTocEntry[] = [];

  headings.forEach((h) => {
    // Reuse the id assigned on an earlier render so in-page links stay stable,
    // and reserve it so later headings can't be slugged onto it.
    if (h.dataset.linkrobinsWikiTocId) {
      used.add(h.dataset.linkrobinsWikiTocId);
      entries.push({
        id: h.dataset.linkrobinsWikiTocId,
        text: h.dataset.linkrobinsWikiTocText || (h.textContent || '').trim(),
        level: parseInt(h.tagName.substring(1), 10) || 1,
      });
      return;
    }

    const text = (h.textContent || '').trim();
    if (!text) return;

    const id = uniqueSlug('wiki-' + slugify(text), used);
    h.id = id;
    h.dataset.linkrobinsWikiTocId = id;
    h.dataset.linkrobinsWikiTocText = text;
    h.classList.add('LinkRobinsWiki-heading');

    entries.push({ id, text, level: parseInt(h.tagName.substring(1), 10) || 1 });
  });

  return entries;
}

// The height of the chrome actually overlaying the top of the viewport. On
// desktop that's the fixed .App-header; on phones the header element is inside
// the drawer (static), and the fixed titlebar's height is the
// --header-height-phone custom property instead.
export function fixedChromeHeight(): number {
  const header = document.querySelector('.App-header');
  if (header && getComputedStyle(header).position === 'fixed') {
    return header.getBoundingClientRect().height;
  }
  const v = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--header-height-phone'));
  return isNaN(v) ? 46 : v;
}

// Smooth-scroll to a heading, offsetting for the fixed forum chrome so the
// target isn't hidden underneath it.
export function scrollToAnchor(id: string): void {
  if (!id) return;
  const el = document.getElementById(id);
  if (!el) return;

  let offsetY = el.getBoundingClientRect().top + window.scrollY - fixedChromeHeight();
  // The phone contents bar pins below the titlebar once the page scrolls, so
  // any anchor target will end up under it too. Its row measures 0 on
  // desktop, where the bar is display: none.
  const bar = document.querySelector('.LinkRobinsWiki-mobileToc-bar');
  if (bar) offsetY -= bar.getBoundingClientRect().height;
  offsetY -= 12;

  try {
    window.scrollTo({ top: offsetY, behavior: 'smooth' });
  } catch (e) {
    window.scrollTo(0, offsetY);
  }
}
