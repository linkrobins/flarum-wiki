import { extend } from 'flarum/common/extend';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import LinkButton from 'flarum/common/components/LinkButton';

import WikiCategory from './common/models/WikiCategory';
import WikiArticle from './common/models/WikiArticle';
import WikiRevision from './common/models/WikiRevision';
import WikiComment from './common/models/WikiComment';

import WikiIndexPage from './forum/components/WikiIndexPage';
import WikiComposePage from './forum/components/WikiComposePage';
import WikiShowPage from './forum/components/WikiShowPage';

import { tr } from './forum/utils/translate';
import { basePath, BASE_PATH } from './forum/utils/helpers';

app.initializers.add('linkrobins-wiki', () => {
  // Register the store models so app.store.find()/createRecord() return typed,
  // cached, relationship-aware records for our resources.
  app.store.models['linkrobins-wiki-categories'] = WikiCategory;
  app.store.models['linkrobins-wiki-articles'] = WikiArticle;
  app.store.models['linkrobins-wiki-revisions'] = WikiRevision;
  app.store.models['linkrobins-wiki-comments'] = WikiComment;

  app.routes['linkrobins-wiki.index'] = { path: BASE_PATH, component: WikiIndexPage };
  app.routes['linkrobins-wiki.compose'] = { path: BASE_PATH + '/new', component: WikiComposePage };
  app.routes['linkrobins-wiki.show'] = { path: BASE_PATH + '/:id', component: WikiShowPage };
  app.routes['linkrobins-wiki.edit'] = { path: BASE_PATH + '/:id/edit', component: WikiComposePage };

  // Global "Wiki" link in the index sidebar nav (shown on every page).
  extend(IndexSidebar.prototype, 'navItems', (items: any) => {
    items.add(
      'linkrobins-wiki',
      m(LinkButton, { href: basePath() + BASE_PATH, icon: 'fas fa-book' }, tr('nav', 'Wiki')),
      30
    );
  });
});
