import { tr } from './translate';
import type WikiArticle from '../../common/models/WikiArticle';
import type WikiCategory from '../../common/models/WikiCategory';

function apiUrl(): string {
  return app.forum.attribute('apiUrl');
}

// Server caps both the comment and revision Index endpoints at 100 per page;
// we request a full page and let the caller page through with an offset rather
// than silently dropping everything past the first 100.
export const WIKI_PAGE_LIMIT = 100;

// --- Reads (cached + relationship-resolved via the store) ---------------

export function loadArticles(params?: Record<string, any>): Promise<any> {
  return app.store.find(
    'linkrobins-wiki-articles',
    Object.assign(
      {
        sort: '-lastEditedAt',
        page: { limit: 25 },
        include: 'user,category',
      },
      params || {}
    )
  );
}

export function loadArticle(id: string | number): Promise<WikiArticle> {
  return app.store.find('linkrobins-wiki-articles', String(id), {
    include: 'user,category,lastEditedBy',
  });
}

export function loadRevisions(articleId: string | number, offset = 0): Promise<any> {
  return app.store.find('linkrobins-wiki-revisions', {
    sort: '-createdAt',
    filter: { articleId },
    page: { limit: WIKI_PAGE_LIMIT, offset },
    include: 'user',
    // The history view diffs the markdown `content`; skip the rendered
    // contentHtml so the server doesn't format every revision per load.
    fields: { 'linkrobins-wiki-revisions': 'title,content,summary,createdAt,user' },
  });
}

export function loadCategories(): Promise<any> {
  return app.store.find('linkrobins-wiki-categories', {
    sort: 'position',
    page: { limit: 100 },
  });
}

export function loadComments(articleId: string | number, offset = 0): Promise<any> {
  return app.store.find('linkrobins-wiki-comments', {
    sort: 'createdAt',
    filter: { articleId },
    page: { limit: WIKI_PAGE_LIMIT, offset },
    include: 'user',
  });
}

export function postComment(article: any, content: string): Promise<any> {
  return app.store.createRecord('linkrobins-wiki-comments').save({ content, relationships: { article } });
}

// --- Writes (store records: cached, reactive, relationship-aware) --------

export function createArticle(title: string, body: string, category: WikiCategory | null): Promise<WikiArticle> {
  return app.store.createRecord('linkrobins-wiki-articles').save({ title, content: body, relationships: { category: category || null } });
}

export function updateArticle(article: WikiArticle, attrs: Record<string, any>): Promise<WikiArticle> {
  return article.save(attrs);
}

// --- File upload --------------------------------------------------------
//
// fof/upload is a bespoke multipart endpoint, not a JSON:API resource the
// store models, so it stays a raw request. Used only by the plain-textarea
// fallback editor -- the real docked composer ships FoF Upload's own button.
//
// When the caller is backed by Flarum's TextEditor (which owns its own
// textarea), pass `editorGetter` so we insert into the live editor (whose
// oninput syncs target[bodyKey] back for us); plain-textarea callers omit it.
export function uploadFilesToBody(target: any, files: FileList | File[], bodyKey: string, editorGetter?: () => any): Promise<void> {
  target.uploadError = null;
  target.uploadingCount = (target.uploadingCount || 0) + files.length;
  m.redraw();

  const form = new FormData();
  for (let i = 0; i < files.length; i++) {
    form.append('files[]', files[i]);
  }

  return app
    .request({
      method: 'POST',
      url: apiUrl() + '/fof/upload',
      body: form,
      serialize: (raw: any) => raw,
    })
    .then((resp: any) => {
      target.uploadingCount = Math.max(0, target.uploadingCount - files.length);
      const data = (resp && resp.data) || [];
      let inserted = '';
      data.forEach((file: any) => {
        const attrs = (file && file.attributes) || {};
        let bb = attrs.bbcode;
        if (!bb && attrs.uuid) {
          const name = attrs.base_name || attrs.uuid;
          const size = attrs.size != null ? String(attrs.size) : '0';
          bb = '[upl-file uuid="' + attrs.uuid + '" size="' + size + '"]' + name + '[/upl-file]';
        }
        if (bb) inserted += (inserted ? '\n' : '') + bb;
      });
      if (inserted) {
        const editor = typeof editorGetter === 'function' ? editorGetter() : null;
        if (editor && typeof editor.insertAt === 'function' && editor.el) {
          const curVal = editor.el.value || '';
          const lead = curVal && !curVal.endsWith('\n') ? '\n' : '';
          editor.insertAt(curVal.length, lead + inserted + '\n');
        } else {
          const existing = target[bodyKey] || '';
          const sep = existing && !existing.endsWith('\n') ? '\n' : '';
          target[bodyKey] = existing + sep + inserted + '\n';
        }
      } else {
        target.uploadError = tr('errors.upload_no_files', 'Upload returned no files. Please try again.');
      }
      m.redraw();
    })
    .catch((err: any) => {
      target.uploadingCount = Math.max(0, target.uploadingCount - files.length);
      let msg = tr('errors.upload', 'Could not upload file.');
      try {
        const resp = err && err.response;
        if (resp && resp.errors && resp.errors[0]) {
          msg = resp.errors[0].detail || resp.errors[0].title || msg;
        }
      } catch (e) {}
      target.uploadError = msg;
      console.error('[linkrobins/wiki] upload failed:', err);
      m.redraw();
    });
}
