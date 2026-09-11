import Model from 'flarum/common/Model';
import type User from 'flarum/common/models/User';
import type WikiArticle from './WikiArticle';

/**
 * A reader's report about an article. Only editors ever load these: the API
 * returns an empty set to anybody else.
 */
export default class WikiReport extends Model {
  reason = Model.attribute<string>('reason');
  detail = Model.attribute<string | null>('detail');
  isResolved = Model.attribute<boolean>('isResolved');

  createdAt = Model.attribute('createdAt', Model.transformDate);
  resolvedAt = Model.attribute('resolvedAt', Model.transformDate);

  user = Model.hasOne<User>('user');
  resolvedBy = Model.hasOne<User>('resolvedBy');
  article = Model.hasOne<WikiArticle>('article');
}
