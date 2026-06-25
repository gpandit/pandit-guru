import { useState, useEffect } from 'react';
import { api } from '../../utils/api';
import RichTextEditor from './RichTextEditor';
import DocumentImport from './DocumentImport';

const EMPTY = {
  type: 'blog', title: '', slug: '', excerpt: '', body: '',
  author: 'Pandit Guru', authorId: null, coverImage: '', coverImageId: null, tags: '', status: 'draft',
  publishedAt: '', metaTitle: '', metaDescription: '', metaKeywords: '',
};

function toDateInput(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  return Number.isNaN(d.getTime()) ? '' : d.toISOString().slice(0, 10);
}

// Rule-based excerpt: strip HTML, take whole sentences up to ~160 chars.
function autoExcerpt(html) {
  const text = (html || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
  if (text.length <= 160) return text;
  const cut = text.slice(0, 160);
  const lastStop = Math.max(cut.lastIndexOf('. '), cut.lastIndexOf('? '), cut.lastIndexOf('! '));
  return (lastStop > 60 ? cut.slice(0, lastStop + 1) : cut.slice(0, cut.lastIndexOf(' '))) + (lastStop > 60 ? '' : '…');
}

const STOPWORDS = new Set('a an the and or but if then else for of in on at to from by with as is are was were be been being this that these those it its it\'s we our you your they their he she his her not no do does did can will would should could'.split(' '));

// Rule-based keyword suggestion: top frequent significant words/bigrams.
function suggestKeywords(html, title) {
  const text = `${title || ''} ${(html || '').replace(/<[^>]*>/g, ' ')}`.toLowerCase();
  const words = text.match(/[a-z][a-z'-]{2,}/g) || [];
  const counts = {};
  words.forEach((w) => { if (!STOPWORDS.has(w)) counts[w] = (counts[w] || 0) + 1; });
  for (let i = 0; i < words.length - 1; i++) {
    if (STOPWORDS.has(words[i]) || STOPWORDS.has(words[i + 1])) continue;
    const bigram = `${words[i]} ${words[i + 1]}`;
    counts[bigram] = (counts[bigram] || 0) + 1.5; // weight phrases slightly higher
  }
  return Object.entries(counts)
    .filter(([, c]) => c > 1)
    .sort((a, b) => b[1] - a[1])
    .slice(0, 8)
    .map(([w]) => w);
}

export default function AdminPosts() {
  const [posts, setPosts] = useState([]);
  const [authors, setAuthors] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [editing, setEditing] = useState(null); // null | post object (form state)
  const [typeFilter, setTypeFilter] = useState('all');
  const [saving, setSaving] = useState(false);
  const [uploadingCover, setUploadingCover] = useState(false);
  const [keywordSuggestions, setKeywordSuggestions] = useState([]);

  function load() {
    setLoading(true);
    Promise.all([api.adminPosts(), api.authors().catch(() => ({ authors: [] }))])
      .then(([p, a]) => { setPosts(p.posts || []); setAuthors(a.authors || []); })
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }
  useEffect(load, []);

  function startNew() { setEditing({ ...EMPTY }); setKeywordSuggestions([]); }
  function startEdit(p) {
    setEditing({ ...p, tags: Array.isArray(p.tags) ? p.tags.join(', ') : '', publishedAt: toDateInput(p.publishedAt) });
    setKeywordSuggestions([]);
  }

  async function onCoverChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    setUploadingCover(true); setError('');
    try {
      const img = await api.uploadImage(file, { alt: editing.title, postId: editing.id || '' });
      setEditing((cur) => ({ ...cur, coverImage: img.url, coverImageId: img.id }));
    } catch (err) {
      setError(err.message);
    } finally {
      setUploadingCover(false);
    }
  }

  function regenerateExcerpt() {
    setEditing((cur) => ({ ...cur, excerpt: autoExcerpt(cur.body) }));
  }

  function suggestKeywordsClick() {
    setKeywordSuggestions(suggestKeywords(editing.body, editing.title));
  }

  function addKeyword(word) {
    setEditing((cur) => {
      const existing = (cur.metaKeywords || '').split(',').map((k) => k.trim()).filter(Boolean);
      if (existing.includes(word)) return cur;
      return { ...cur, metaKeywords: [...existing, word].join(', ') };
    });
  }

  async function save(e) {
    e.preventDefault();
    setSaving(true); setError('');
    const payload = {
      ...editing,
      tags: editing.tags.split(',').map((t) => t.trim()).filter(Boolean),
    };
    try {
      if (editing.id) await api.updatePost(payload);
      else await api.createPost(payload);
      setEditing(null);
      load();
    } catch (err) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  }

  async function remove(p) {
    if (!window.confirm(`Delete "${p.title}"? This cannot be undone.`)) return;
    try { await api.deletePost(p.id); load(); }
    catch (err) { setError(err.message); }
  }

  async function togglePublish(p) {
    try {
      await api.updatePost({ ...p, status: p.status === 'published' ? 'draft' : 'published' });
      load();
    } catch (err) { setError(err.message); }
  }

  const visible = posts.filter((p) => typeFilter === 'all' || p.type === typeFilter);

  if (editing) {
    return (
      <div className="admin-page">
        <header className="admin-page-head">
          <h1>{editing.id ? 'Edit' : 'New'} {editing.type === 'news' ? 'news item' : 'blog post'}</h1>
          <button className="admin-btn ghost" onClick={() => setEditing(null)}>Cancel</button>
        </header>
        {error && <div className="admin-banner err">{error}</div>}
        <form className="admin-card admin-post-form" onSubmit={save}>
          <div className="admin-row2">
            <label className="admin-field">
              <span>Type</span>
              <select value={editing.type} onChange={(e) => setEditing({ ...editing, type: e.target.value })}>
                <option value="blog">Blog post</option>
                <option value="news">News</option>
              </select>
            </label>
            <label className="admin-field">
              <span>Status</span>
              <select value={editing.status} onChange={(e) => setEditing({ ...editing, status: e.target.value })}>
                <option value="draft">Draft</option>
                <option value="published">Published</option>
              </select>
            </label>
          </div>
          <label className="admin-field">
            <span>Title</span>
            <input value={editing.title} required onChange={(e) => setEditing({ ...editing, title: e.target.value })} />
          </label>
          <label className="admin-field">
            <span>Slug (optional — auto-generated from title)</span>
            <input value={editing.slug} placeholder="my-post-url" onChange={(e) => setEditing({ ...editing, slug: e.target.value })} />
          </label>
          <label className="admin-field">
            <span>Excerpt (shown in lists)
              <button type="button" className="admin-link-btn inline" onClick={regenerateExcerpt}>Regenerate from body</button>
            </span>
            <textarea rows={2} value={editing.excerpt} onChange={(e) => setEditing({ ...editing, excerpt: e.target.value })} />
          </label>
          <label className="admin-field">
            <span>Body</span>
            <DocumentImport postId={editing.id} onImported={(html) => setEditing((cur) => ({ ...cur, body: html }))} />
            <RichTextEditor
              value={editing.body}
              onChange={(html) => setEditing((cur) => ({ ...cur, body: html }))}
              postId={editing.id}
            />
          </label>
          <div className="admin-row2">
            <label className="admin-field">
              <span>Author</span>
              <select value={editing.authorId || ''} onChange={(e) => {
                const a = authors.find((x) => x.id === e.target.value);
                setEditing({ ...editing, authorId: e.target.value || null, author: a ? a.name : editing.author });
              }}>
                <option value="">— Free text only —</option>
                {authors.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
              </select>
            </label>
            <label className="admin-field">
              <span>Byline text {editing.authorId ? '(overridden by selected author)' : ''}</span>
              <input value={editing.author} disabled={!!editing.authorId}
                onChange={(e) => setEditing({ ...editing, author: e.target.value })} />
            </label>
          </div>
          <div className="admin-row2">
            <label className="admin-field">
              <span>Tags (comma-separated)</span>
              <input value={editing.tags} onChange={(e) => setEditing({ ...editing, tags: e.target.value })} />
            </label>
            <label className="admin-field">
              <span>Published date</span>
              <input type="date" value={editing.publishedAt} onChange={(e) => setEditing({ ...editing, publishedAt: e.target.value })} />
              <span className="admin-hint">Defaults to today on publish; set a past date to back-date.</span>
            </label>
          </div>
          <label className="admin-field">
            <span>Cover image</span>
            {editing.coverImage && <img src={editing.coverImage} alt="" className="admin-cover-preview" />}
            <input type="file" accept="image/*" onChange={onCoverChange} disabled={uploadingCover} />
            {uploadingCover && <span className="admin-hint">Uploading…</span>}
            <input value={editing.coverImage} placeholder="…or paste an image URL" onChange={(e) => setEditing({ ...editing, coverImage: e.target.value, coverImageId: null })} />
          </label>

          <fieldset className="admin-fieldset">
            <legend>SEO meta</legend>
            <label className="admin-field">
              <span>Meta title</span>
              <input value={editing.metaTitle} placeholder={editing.title} onChange={(e) => setEditing({ ...editing, metaTitle: e.target.value })} />
            </label>
            <label className="admin-field">
              <span>Meta description</span>
              <textarea rows={2} value={editing.metaDescription} placeholder={editing.excerpt} onChange={(e) => setEditing({ ...editing, metaDescription: e.target.value })} />
            </label>
            <label className="admin-field">
              <span>Meta keywords (comma-separated)
                <button type="button" className="admin-link-btn inline" onClick={suggestKeywordsClick}>Suggest keywords</button>
              </span>
              <input value={editing.metaKeywords} onChange={(e) => setEditing({ ...editing, metaKeywords: e.target.value })} />
              {keywordSuggestions.length > 0 && (
                <div className="admin-keyword-chips">
                  {keywordSuggestions.map((w) => (
                    <button type="button" key={w} className="mini-tag clickable" onClick={() => addKeyword(w)}>+ {w}</button>
                  ))}
                </div>
              )}
            </label>
          </fieldset>

          <button type="submit" className="admin-btn" disabled={saving}>
            {saving ? 'Saving…' : editing.id ? 'Save changes' : 'Create'}
          </button>
        </form>
      </div>
    );
  }

  return (
    <div className="admin-page">
      <header className="admin-page-head">
        <h1>Blog &amp; News</h1>
        <button className="admin-btn" onClick={startNew}>+ New post</button>
      </header>

      <div className="admin-toolbar">
        <div className="admin-tabs">
          {['all', 'blog', 'news'].map((f) => (
            <button key={f} className={typeFilter === f ? 'active' : ''} onClick={() => setTypeFilter(f)}>
              {f === 'all' ? 'All' : f === 'blog' ? 'Blog' : 'News'}
            </button>
          ))}
        </div>
      </div>

      {loading && <div className="admin-loading">Loading…</div>}
      {error && <div className="admin-banner err">{error}</div>}

      {!loading && (
        <div className="admin-table-wrap">
          <table className="admin-table">
            <thead>
              <tr><th>Title</th><th>Type</th><th>Status</th><th>Engagement</th><th>Updated</th><th></th></tr>
            </thead>
            <tbody>
              {visible.map((p) => (
                <tr key={p.id}>
                  <td>{p.title}</td>
                  <td><span className={`src-badge ${p.type}`}>{p.type}</span></td>
                  <td><span className={`status-badge ${p.status}`}>{p.status}</span></td>
                  <td className="nowrap">
                    <span className="admin-stat-pill" title="Page views">👁 {p.views || 0}</span>
                    {p.avgSecondsOnPage > 0 && <span className="admin-stat-pill" title="Avg. time on page">⏱ {Math.round(p.avgSecondsOnPage / 60) || '<1'}m</span>}
                    <span className="admin-stat-pill" title="Approved comments">💬 {p.approvedComments || 0}</span>
                    {p.pendingComments > 0 && <span className="admin-stat-pill warn" title="Comments awaiting moderation">⚠ {p.pendingComments}</span>}
                  </td>
                  <td className="nowrap">{p.updatedAt ? new Date(p.updatedAt).toLocaleDateString() : '—'}</td>
                  <td className="admin-actions">
                    <button className="admin-link-btn" onClick={() => togglePublish(p)}>
                      {p.status === 'published' ? 'Unpublish' : 'Publish'}
                    </button>
                    <button className="admin-link-btn" onClick={() => startEdit(p)}>Edit</button>
                    <button className="admin-link-btn danger" onClick={() => remove(p)}>Delete</button>
                  </td>
                </tr>
              ))}
              {visible.length === 0 && <tr><td colSpan={6} className="admin-empty">No posts yet.</td></tr>}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
