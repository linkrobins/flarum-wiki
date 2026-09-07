import LinkButton from 'flarum/common/components/LinkButton';
import extractText from 'flarum/common/utils/extractText';
import highlight from 'flarum/common/helpers/highlight';

import { tr } from '../utils/translate';
import { articleHref, basePath, BASE_PATH } from '../utils/helpers';

/**
 * Puts wiki articles in the forum's own search dropdown, alongside discussions
 * and users. Implements core's GlobalSearchSource contract structurally rather
 * than by importing the interface, which is a type-only export.
 */
export default class WikiSearchSource {
  results = new Map<string, any[]>();

  resource = 'linkrobins-wiki-articles';

  title(): string {
    return extractText(tr('search.heading', 'Wiki'));
  }

  isCached(query: string): boolean {
    return this.results.has(query.toLowerCase());
  }

  search(query: string, limit: number): Promise<void> {
    return app.store
      .find('linkrobins-wiki-articles', {
        filter: { q: query },
        page: { limit },
        include: 'category',
      })
      .then((results: any) => {
        this.results.set(query.toLowerCase(), results || []);
        m.redraw();
      })
      .catch(() => {
        // A failed lookup shouldn't take the whole dropdown down with it.
        this.results.set(query.toLowerCase(), []);
        m.redraw();
      });
  }

  view(query: string): any[] {
    const q = query.toLowerCase();

    return (this.results.get(q) || []).map((article: any) => {
      const category = article.category && article.category();

      return m(
        'li',
        { className: 'LinkRobinsWiki-searchResult', 'data-index': 'linkrobins-wiki-articles' + article.id(), 'data-id': article.id() },
        m('a', { href: articleHref(article) }, [
          m('i', { className: 'fas fa-book LinkRobinsWiki-searchResult-icon', 'aria-hidden': 'true' }),
          m('span', { className: 'LinkRobinsWiki-searchResult-title' }, highlight(article.title() || '', query)),
          category ? m('span', { className: 'LinkRobinsWiki-searchResult-category' }, category.name()) : null,
        ])
      );
    });
  }

  customGrouping(): boolean {
    return false;
  }

  // "See all results" sends the reader to the wiki's own search page.
  fullPage(query: string): any {
    return m(
      'li',
      m(
        LinkButton,
        { icon: 'fas fa-search', href: basePath() + BASE_PATH + '?q=' + encodeURIComponent(query) },
        tr('search.all_button', 'Search the wiki for "{query}"', { query })
      )
    );
  }

  gotoItem(id: string): string | null {
    const article = app.store.getById('linkrobins-wiki-articles', id);
    if (!article) return null;
    return articleHref(article);
  }
}
