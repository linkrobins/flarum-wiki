import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import LinkButton from 'flarum/common/components/LinkButton';
import Button from 'flarum/common/components/Button';
import SelectDropdown from 'flarum/common/components/SelectDropdown';
import Separator from 'flarum/common/components/Separator';
import ItemList from 'flarum/common/utils/ItemList';
import { tr } from '../utils/translate';
import { basePath, BASE_PATH, safeNavigate } from '../utils/helpers';
import { canCreateWikiArticle } from '../utils/permissions';
import { loadCategories } from '../utils/api';

export default class WikiIndexSidebar extends IndexSidebar {
  categories: any[] = [];

  oninit(vnode: any) {
    super.oninit(vnode);
    loadCategories()
      .then((cats: any[]) => {
        this.categories = cats || [];
        m.redraw();
      })
      .catch(() => {});
  }

  items() {
    const items = new ItemList();

    // "New article" primary button -- mirrors the blog's "Compose" button.
    if (canCreateWikiArticle()) {
      const newHref = basePath() + BASE_PATH + '/new';
      items.add(
        'newArticle',
        m(
          Button,
          {
            icon: 'fas fa-plus',
            className: 'Button Button--primary LinkRobinsWiki-newArticleButton',
            itemClassName: 'App-primaryControl',
            'aria-label': tr('index.new_article', 'New article'),
            title: tr('index.new_article_tooltip', 'Write a new article'),
            onclick: (e: any) => {
              safeNavigate(newHref, e);
            },
          },
          tr('index.new_article', 'New article')
        ),
        110
      );
    }

    items.add(
      'nav',
      m(
        SelectDropdown,
        {
          buttonClassName: 'Button',
          className: 'App-titleControl',
          defaultLabel: tr('nav', 'Wiki'),
        },
        this.navItems().toArray()
      ),
      90
    );

    return items;
  }

  navItems() {
    let items;
    try {
      items = super.navItems();
    } catch (e) {
      console.warn('[linkrobins/wiki] super.navItems() threw, falling back:', e);
      items = new ItemList();
    }
    if (!items) return new ItemList();

    // The Tags extension injects a tag list into IndexSidebar.navItems via
    // extend(); because we subclass IndexSidebar we inherit it. Strip the
    // per-tag clutter but keep the top-level "Tags" link.
    try {
      const all = (items as any)._items || {};
      Object.keys(all).forEach((key) => {
        if (key === 'moreTags' || key === 'separator' || /^tag\d+$/.test(key)) {
          if (typeof items.remove === 'function') items.remove(key);
        }
      });
    } catch (e) {}

    // The active category comes from the page (e.g. the article's category on
    // an article page), falling back to the ?category= filter on the index.
    const active =
      this.attrs && this.attrs.activeCategory != null && this.attrs.activeCategory !== '' ? this.attrs.activeCategory : m.route.param('category');

    items.add('linkrobinsWikiSeparator', m(Separator), -10);

    // "Index" -- the full article list. Active when no category is selected
    // (and on uncategorized articles).
    items.add(
      'wiki-index',
      m(
        LinkButton,
        {
          href: basePath() + BASE_PATH,
          icon: 'fas fa-list',
          active: !active,
        },
        tr('index.index_label', 'Index')
      ),
      -11
    );

    this.categories.forEach((cat: any, i: number) => {
      items.add(
        'wiki-cat-' + cat.id(),
        m(
          LinkButton,
          {
            href: basePath() + BASE_PATH + '?category=' + encodeURIComponent(cat.id()),
            icon: cat.icon() || 'fas fa-folder',
            active: String(active) === String(cat.id()),
          },
          cat.name()
        ),
        -12 - i
      );
    });

    return items;
  }
}
