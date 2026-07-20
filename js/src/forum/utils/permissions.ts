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

// No logged-in requirement: history is guest-visible unless an admin
// restricts the permission, and the attribute is serialized for guests too.
export function canViewWikiHistory(): boolean {
  try {
    if (isAdmin()) return true;
    return !!readForumAttribute('canViewWikiHistory');
  } catch (e) {
    return false;
  }
}
