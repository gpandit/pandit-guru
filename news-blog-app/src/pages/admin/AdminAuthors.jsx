import { useState, useEffect } from 'react';
import { api } from '../../utils/api';

const EMPTY = { name: '', bio: '', avatarImageId: null, avatarUrl: null };

export default function AdminAuthors() {
  const [authors, setAuthors] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [editing, setEditing] = useState(null);
  const [saving, setSaving] = useState(false);
  const [uploading, setUploading] = useState(false);

  function load() {
    setLoading(true);
    api.authors()
      .then((d) => setAuthors(d.authors || []))
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }
  useEffect(load, []);

  function startNew() { setEditing({ ...EMPTY }); }
  function startEdit(a) { setEditing({ ...a }); }

  async function onAvatarChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    setUploading(true); setError('');
    try {
      const img = await api.uploadImage(file, { alt: editing.name });
      setEditing((cur) => ({ ...cur, avatarImageId: img.id, avatarUrl: img.url }));
    } catch (err) {
      setError(err.message);
    } finally {
      setUploading(false);
    }
  }

  async function save(e) {
    e.preventDefault();
    setSaving(true); setError('');
    try {
      if (editing.id) await api.updateAuthor(editing);
      else await api.createAuthor(editing);
      setEditing(null);
      load();
    } catch (err) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  }

  async function remove(a) {
    if (!window.confirm(`Delete author "${a.name}"? Existing posts keep their byline text but lose the link.`)) return;
    try { await api.deleteAuthor(a.id); load(); }
    catch (err) { setError(err.message); }
  }

  if (editing) {
    return (
      <div className="admin-page">
        <header className="admin-page-head">
          <h1>{editing.id ? 'Edit' : 'New'} author</h1>
          <button className="admin-btn ghost" onClick={() => setEditing(null)}>Cancel</button>
        </header>
        {error && <div className="admin-banner err">{error}</div>}
        <form className="admin-card admin-post-form" onSubmit={save}>
          <label className="admin-field">
            <span>Name</span>
            <input value={editing.name} required onChange={(e) => setEditing({ ...editing, name: e.target.value })} />
          </label>
          <label className="admin-field">
            <span>Bio</span>
            <textarea rows={4} value={editing.bio || ''} onChange={(e) => setEditing({ ...editing, bio: e.target.value })} />
          </label>
          <label className="admin-field">
            <span>Avatar</span>
            {editing.avatarUrl && (
              <img src={editing.avatarUrl} alt="" className="admin-avatar-preview" />
            )}
            <input type="file" accept="image/*" onChange={onAvatarChange} disabled={uploading} />
            {uploading && <span className="admin-hint">Uploading…</span>}
          </label>
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
        <h1>Authors</h1>
        <button className="admin-btn" onClick={startNew}>+ New author</button>
      </header>

      {loading && <div className="admin-loading">Loading…</div>}
      {error && <div className="admin-banner err">{error}</div>}

      {!loading && (
        <div className="admin-table-wrap">
          <table className="admin-table">
            <thead><tr><th></th><th>Name</th><th>Bio</th><th></th></tr></thead>
            <tbody>
              {authors.map((a) => (
                <tr key={a.id}>
                  <td>{a.avatarUrl ? <img src={a.avatarUrl} alt="" className="admin-avatar-thumb" /> : '—'}</td>
                  <td>{a.name}</td>
                  <td className="admin-truncate">{a.bio || '—'}</td>
                  <td className="admin-actions">
                    <button className="admin-link-btn" onClick={() => startEdit(a)}>Edit</button>
                    <button className="admin-link-btn danger" onClick={() => remove(a)}>Delete</button>
                  </td>
                </tr>
              ))}
              {authors.length === 0 && <tr><td colSpan={4} className="admin-empty">No authors yet.</td></tr>}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
