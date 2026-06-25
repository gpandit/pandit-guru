import { useState, useEffect } from 'react';
import { api } from '../utils/api';
import { containsProfanity, isValidEmail } from '../utils/validation';

const REACTIONS = [
  { type: 'clap', emoji: '👏', label: 'Clap' },
  { type: 'thumbs_up', emoji: '👍', label: 'Thumbs up' },
  { type: 'love', emoji: '❤️', label: 'Love' },
];

function fmtDate(iso) {
  const d = new Date(iso);
  return Number.isNaN(d.getTime()) ? '' : d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

export default function PostComments({ postId }) {
  const [comments, setComments] = useState([]);
  const [counts, setCounts] = useState({ clap: 0, thumbs_up: 0, love: 0 });
  const [mine, setMine] = useState([]);
  const [form, setForm] = useState({ name: '', email: '', body: '', website: '' });
  const [errors, setErrors] = useState({});
  const [status, setStatus] = useState('idle'); // idle | sending | sent-approved | sent-pending | err
  const [reacting, setReacting] = useState(false);

  useEffect(() => {
    if (!postId) return;
    api.postComments(postId).then((d) => setComments(d.comments || [])).catch(() => {});
    api.postReactions(postId).then((d) => { setCounts(d.counts || {}); setMine(d.mine || []); }).catch(() => {});
  }, [postId]);

  async function onReact(type) {
    if (reacting) return;
    setReacting(true);
    // Optimistic update so the button feels instant.
    const wasMine = mine.includes(type);
    setMine((m) => (wasMine ? m.filter((t) => t !== type) : [...m, type]));
    setCounts((c) => ({ ...c, [type]: Math.max(0, (c[type] || 0) + (wasMine ? -1 : 1)) }));
    try {
      const d = await api.togglePostReaction(postId, type);
      setCounts(d.counts || {}); setMine(d.mine || []);
    } catch {
      // Roll back on failure.
      setMine((m) => (wasMine ? [...m, type] : m.filter((t) => t !== type)));
      setCounts((c) => ({ ...c, [type]: Math.max(0, (c[type] || 0) + (wasMine ? 1 : -1)) }));
    } finally {
      setReacting(false);
    }
  }

  function onChange(e) {
    setForm({ ...form, [e.target.name]: e.target.value });
    setErrors((er) => ({ ...er, [e.target.name]: undefined }));
  }

  async function onSubmit(e) {
    e.preventDefault();
    const fieldErrors = {};
    if (!isValidEmail(form.email)) fieldErrors.email = 'Enter a valid email address.';
    if (containsProfanity(form.name)) fieldErrors.name = 'Please enter your real name.';
    if (!form.body.trim()) fieldErrors.body = 'Write a comment first.';
    setErrors(fieldErrors);
    if (Object.keys(fieldErrors).length > 0) return;

    setStatus('sending');
    try {
      const d = await api.submitPostComment({ postId, name: form.name, email: form.email, body: form.body, website: form.website });
      if (d.status === 'approved' && d.comment) {
        setComments((c) => [...c, d.comment]);
        setStatus('sent-approved');
      } else {
        setStatus('sent-pending');
      }
      setForm({ name: '', email: '', body: '', website: '' });
    } catch (err) {
      setStatus('err');
    }
  }

  return (
    <section className="post-comments">
      <div className="post-reactions">
        {REACTIONS.map((r) => (
          <button
            key={r.type} type="button"
            className={`post-reaction-btn ${mine.includes(r.type) ? 'active' : ''}`}
            onClick={() => onReact(r.type)}
          >
            <span className="post-reaction-emoji">{r.emoji}</span>
            <span className="post-reaction-count">{counts[r.type] || 0}</span>
          </button>
        ))}
      </div>

      <h2 className="post-comments-title">Comments {comments.length > 0 && `(${comments.length})`}</h2>

      {comments.length > 0 && (
        <ul className="post-comment-list">
          {comments.map((c) => (
            <li key={c.id} className="post-comment">
              <div className="post-comment-head">
                <span className="post-comment-name">{c.name}</span>
                <span className="post-comment-date">{fmtDate(c.createdAt)}</span>
              </div>
              <p className="post-comment-body">{c.body}</p>
            </li>
          ))}
        </ul>
      )}

      <form className="post-comment-form" onSubmit={onSubmit}>
        {status === 'sent-approved' && <div className="form-banner ok">Comment posted — thanks!</div>}
        {status === 'sent-pending' && <div className="form-banner ok">Thanks — your comment is awaiting moderation.</div>}
        {status === 'err' && <div className="form-banner err">Something went wrong — please try again.</div>}
        <div className="row2">
          <div className="field">
            <label htmlFor="pc-name">Name</label>
            <input id="pc-name" name="name" required className={errors.name ? 'has-error' : ''} value={form.name} onChange={onChange} />
            {errors.name && <div className="field-error">{errors.name}</div>}
          </div>
          <div className="field">
            <label htmlFor="pc-email">Email (not published)</label>
            <input id="pc-email" name="email" type="email" required className={errors.email ? 'has-error' : ''} value={form.email} onChange={onChange} />
            {errors.email && <div className="field-error">{errors.email}</div>}
          </div>
        </div>
        <input type="text" name="website" value={form.website} onChange={onChange}
          className="post-comment-honeypot" tabIndex={-1} autoComplete="off" aria-hidden="true" />
        <div className="field">
          <label htmlFor="pc-body">Comment</label>
          <textarea id="pc-body" name="body" rows={3} className={errors.body ? 'has-error' : ''} value={form.body} onChange={onChange} />
          {errors.body && <div className="field-error">{errors.body}</div>}
        </div>
        <button type="submit" className="btn-primary" disabled={status === 'sending'}>
          {status === 'sending' ? 'Posting…' : 'Post comment'}
        </button>
      </form>
    </section>
  );
}
