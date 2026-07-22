import Model from 'flarum/common/Model';
import type User from 'flarum/common/models/User';
import type WikiCategory from './WikiCategory';

/**
 * A wiki article. `content` is the raw markdown source (used to pre-fill the
 * editor); `contentHtml` is the rendered output. The `can*` booleans are
 * server-computed per-actor gates the UI uses to decide which controls to show.
 */
export default class WikiArticle extends Model {
  title = Model.attribute<string>('title');
  slug = Model.attribute<string | null>('slug');
  faq = Model.attribute<{ question: string; answer: string; answerHtml: string }[]>('faq');
  content = Model.attribute<string>('content');
  contentHtml = Model.attribute<string>('contentHtml');
  revisionCount = Model.attribute<number>('revisionCount');

  canUpdate = Model.attribute<boolean>('canUpdate');
  canDelete = Model.attribute<boolean>('canDelete');
  isDeleted = Model.attribute<boolean>('isDeleted');

  createdAt = Model.attribute('createdAt', Model.transformDate);
  updatedAt = Model.attribute('updatedAt', Model.transformDate);
  lastEditedAt = Model.attribute('lastEditedAt', Model.transformDate);
  deletedAt = Model.attribute('deletedAt', Model.transformDate);

  user = Model.hasOne<User>('user');
  lastEditedBy = Model.hasOne<User>('lastEditedBy');
  category = Model.hasOne<WikiCategory>('category');
}
