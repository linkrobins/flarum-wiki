export interface WikiBlock {
  type: string;
  attrs: Record<string, string>;
  lines?: string[];
}

function parseAttrs(s: string): Record<string, string> {
  const out: Record<string, string> = {};
  const re = /([\w-]+)=(?:"([^"]*)"|'([^']*)'|([^\s\]]+))/g;
  let m;
  while ((m = re.exec(s))) {
    out[m[1].toLowerCase()] = m[2] ?? m[3] ?? m[4] ?? '';
  }
  return out;
}

/**
 * Parse the admin-authored index layout into a list of blocks. A line that is
 * exactly a shortcode (e.g. `[articles category="guides" limit="5"]`) becomes a
 * dynamic block; everything else is collected into `prose` blocks (rendered as
 * headings / paragraphs).
 *
 * Supported shortcodes:
 *   [articles]                              all articles
 *   [articles category="slug-or-id"]        articles in a category
 *   [articles limit="N"]                    most-recent N
 *   [articles ... title="Heading"]          optional heading above the list
 *   [article id="N"]                        link to one article
 *   [categories]                            the category list
 */
export function parseIndexLayout(text: string): WikiBlock[] {
  const blocks: WikiBlock[] = [];
  let buf: string[] = [];

  const flush = () => {
    if (buf.some((l) => l.trim() !== '')) {
      blocks.push({ type: 'prose', attrs: {}, lines: buf.slice() });
    }
    buf = [];
  };

  (text || '').split('\n').forEach((line) => {
    const m = line.trim().match(/^\[(\w[\w-]*)((?:\s+[\w-]+=(?:"[^"]*"|'[^']*'|[^\s\]]+))*)\s*\]$/);
    if (m) {
      flush();
      blocks.push({ type: m[1].toLowerCase(), attrs: parseAttrs(m[2]) });
    } else {
      buf.push(line);
    }
  });
  flush();

  return blocks;
}

/** True when at least one block needs article/category data fetched. */
export function layoutHasDynamicBlocks(blocks: WikiBlock[]): boolean {
  return blocks.some((b) => b.type === 'articles' || b.type === 'article' || b.type === 'categories');
}
