import { useState, useEffect } from 'react';
import { api } from '../../utils/api';

function fmtDate(iso) {
  if (!iso) return '—';
  const d = new Date(iso);
  return Number.isNaN(d.getTime()) ? iso : d.toLocaleString();
}

export default function AdminComments() {
  const [comments, setComments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [statusFilter, setStatusFilter] = useState('queue'); // queue | approved | all
  const [busyId, setBusyId] = useState(null);

  function load() {
    setLoading(true);
    api.adminComments(statusFilter)
      .then((d) => setComments(d.comments || []))
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }
  useEffect(load, [statusFilter]);

  async function setStatus(c, status) {
    setBusyId(c.id);
    try {
      await api.moderateComment(c.id, status);
      setComments((cs) => cs.filter((x) => x.id !== c.id));
    } catch (err) {
      setError(err.message);
    } finally {
      setBusyId(null);
    }
  }

  async function remove(c) {
    if (!window.confirm('Permanently delete this comment?')) return;
    setBusyId(c.id);
    try {
      await api.deleteComment(c.id);
      setComments((cs) => cs.filter((x) => x.id !== c.id));
    } catch (err) {
      setError(err.message);
    } finally {
      setBusyId(null);
    }
  }

  return (
    <div className="admin-page">
      <header className="admin-page-head">
        <h1>Comments <span className="admin-count">{comments.length}</span></h1>
      </header>

      <div className="admin-toolbar">
        <div className="admin-tabs">
          {[['queue', 'Needs review'], ['approved', 'Approved'], ['all', 'All']].map(([v, label]) => (
            <button key={v} className={statusFilter === v ? 'active' : ''} onClick={() => setStatusFilter(v)}>{label}</button>
          ))}
        </div>
      </div>

      {loading && <div className="admin-loading">Loading…</div>}
      {error && <div className="admin-banner err">{error}</div>}

      {!loading && (
        <div className="admin-comment-list">
          {comments.map((c) => (
            <div key={c.id} className="admin-card admin-comment-card">
              <div className="admin-comment-meta">
                <span className={`qual-badge s-${c.status === 'approved' ? 'qualified' : c.status === 'spam' ? 'disqualified' : 'new'}`}>{c.status}</span>
                <span className="admin-comment-post">{c.postTitle || '(post deleted)'}</span>
                <span className="admin-comment-date">{fmtDate(c.createdAt)}</span>
              </div>
              <p className="admin-comment-body">“{c.body}”</p>
              <div className="admin-comment-author">{c.name} · {c.email} · IP {c.ip}</div>
              <div className="admin-actions">
                {c.status !== 'approved' && <button className="admin-link-btn" disabled={busyId === c.id} onClick={() => setStatus(c, 'approved')}>Approve</button>}
                {c.status !== 'spam' && <button className="admin-link-btn" disabled={busyId === c.id} onClick={() => setStatus(c, 'spam')}>Mark spam</button>}
                {c.status !== 'pending' && <button className="admin-link-btn" disabled={busyId === c.id} onClick={() => setStatus(c, 'pending')}>Unpublish</button>}
                <button className="admin-link-btn danger" disabled={busyId === c.id} onClick={() => remove(c)}>Delete</button>
              </div>
            </div>
          ))}
          {comments.length === 0 && <div className="admin-empty">Nothing here.</div>}
        </div>
      )}
    </div>
  );
}
