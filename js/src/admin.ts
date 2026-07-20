import WikiCategory from './common/models/WikiCategory';
import WikiAdminPage from './admin/components/WikiAdminPage';
import { tx } from './admin/utils';

app.initializers.add('linkrobins-wiki', () => {
  app.store.models['linkrobins-wiki-categories'] = WikiCategory;

  if (!app.registry || typeof app.registry.for !== 'function') {
    console.warn('[linkrobins/wiki] app.registry not available');
    return;
  }
  app.registry.for('linkrobins-wiki').registerPage(WikiAdminPage);

  try {
    if (typeof app.registry.registerPermission === 'function') {
      app.registry.registerPermission(
        {
          permission: 'linkrobins-wiki.createArticle',
          icon: 'fas fa-pencil-alt',
          label: tx('linkrobins-wiki.admin.permissions.create_article'),
        },
        'start',
        95
      );
      app.registry.registerPermission(
        {
          permission: 'linkrobins-wiki.comment',
          icon: 'fas fa-comment',
          label: tx('linkrobins-wiki.admin.permissions.comment'),
        },
        'reply',
        95
      );
      app.registry.registerPermission(
        {
          permission: 'linkrobins-wiki.editArticles',
          icon: 'fas fa-edit',
          label: tx('linkrobins-wiki.admin.permissions.edit_articles'),
        },
        'moderate',
        95
      );
      app.registry.registerPermission(
        {
          permission: 'linkrobins-wiki.viewHistory',
          icon: 'fas fa-history',
          label: tx('linkrobins-wiki.admin.permissions.view_history'),
          allowGuest: true,
        },
        'view',
        95
      );
    }
  } catch (e) {
    console.warn('[linkrobins/wiki] could not register permission:', e);
  }
});
