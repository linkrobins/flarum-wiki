import Model from 'flarum/common/Model';
import type User from 'flarum/common/models/User';
import type WikiArticle from './WikiArticle';

/**
 * A reader comment on an article. `content` is the raw markdown source (used to
 * pre-fill the editor); `contentHtml` is the rendered output.
 */
export default class WikiComment extends Model {
  content = Model.attribute<string>('content');
  contentHtml = Model.attribute<string>('contentHtml');

  canEdit = Model.attribute<boolean>('canEdit');
  canDelete = Model.attribute<boolean>('canDelete');
  isDeleted = Model.attribute<boolean>('isDeleted');

  createdAt = Model.attribute('createdAt', Model.transformDate);
  updatedAt = Model.attribute('updatedAt', Model.transformDate);
  deletedAt = Model.attribute('deletedAt', Model.transformDate);

  user = Model.hasOne<User>('user');
  article = Model.hasOne<WikiArticle>('article');
}
