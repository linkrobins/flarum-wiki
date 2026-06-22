import { readForumAttribute } from './helpers';

function isAdmin(): boolean {
  try {
    const u = app.session && app.session.user;
    return !!(u && typeof u.isAdmin === 'function' && u.isAdmin());
  } catch (e) {
    return false;
  }
}

export function canCreateWikiArticle(): boolean {
  try {
    if (!app.session || !app.session.user) return false;
    if (isAdmin()) return true;
    return !!readForumAttribute('canCreateWikiArticle');
  } catch (e) {
    return false;
  }
}

export function canEditWikiArticles(): boolean {
  try {
    if (!app.session || !app.session.user) return false;
    if (isAdmin()) return true;
    return !!readForumAttribute('canEditWikiArticles');
  } catch (e) {
    return false;
  }
}

export function canCommentWiki(): boolean {
  try {
    if (!app.session || !app.session.user) return false;
    if (isAdmin()) return true;
    return !!readForumAttribute('canCommentWiki');
  } catch (e) {
    return false;
  }
}
