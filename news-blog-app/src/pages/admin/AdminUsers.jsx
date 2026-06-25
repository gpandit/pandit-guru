import { useState, useEffect } from 'react';
import { api } from '../../utils/api';

const BLANK = { email: '', name: '', isAdmin: false, perms: { content: false } };

export default function AdminUsers({ currentUserId }) {
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [editing, setEditing] = useState(null); // null | user form
  const [saving, setSaving] = useState(false);

  function load() {
    setLoading(true);
    api.users()
      .then((d) => setUsers(d.users || []))
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }
  useEffect(load, []);

  function startNew() { setError(''); setNotice(''); setEditing({ ...BLANK, perms: { ...BLANK.perms } }); }
  function startEdit(u) { setError(''); setNotice(''); setEditing({ ...u, perms: { ...u.perms } }); }

  function togglePerm(key) {
    setEditing((e) => ({ ...e, perms: { ...e.perms, [key]: !e.perms[key] } }));
  }

  async function save(e) {
    e.preventDefault();
    setSaving(true); setError('');
    try {
      if (editing.id) {
        await api.updateUser({ id: editing.id, name: editing.name, isAdmin: editing.isAdmin, perms: editing.perms, active: editing.active });
        setNotice('User updated.');
      } else {
        const res = await api.createUser({ email: editing.email, name: editing.name, isAdmin: editing.isAdmin, perms: editing.perms });
        setNotice(res.invited ? `Invite sent to ${editing.email}.` : `User created, but the invite email failed to send.`);
      }
      setEditing(null);
      load();
    } catch (err) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  }

  async function resendInvite(u) {
    setError(''); setNotice('');
    try {
      const res = await api.updateUser({ id: u.id, resendInvite: true });
      setNotice(res.invited ? `Reset link sent to ${u.email}.` : 'Could not send the email.');
    } catch (err) { setError(err.message); }
  }

  async function toggleActive(u) {
    try {
      if (u.active) { await api.deactivateUser(u.id); }
      else { await api.updateUser({ id: u.id, active: true }); }
      load();
    } catch (err) { setError(err.message); }
  }

  function permSummary(u) {
    if (u.isAdmin) return 'Administrator (full access)';
    const labels = [];
    if (u.perms.content) labels.push('Content');
    return labels.join(', ') || 'No access';
  }

  if (editing) {
    return (
      <div className="admin-page">
        <header className="admin-page-head">
          <h1>{editing.id ? 'Edit user' : 'New user'}</h1>
          <button className="admin-btn ghost" onClick={() => setEditing(null)}>Cancel</button>
        </header>
        {error && <div className="admin-banner err">{error}</div>}
        <form className="admin-card admin-user-form" onSubmit={save}>
          <label className="admin-field">
            <span>Email (login id)</span>
            <input type="email" value={editing.email} required disabled={!!editing.id}
              onChange={(e) => setEditing({ ...editing, email: e.target.value })} placeholder="person@pandit.guru" />
          </label>
          <label className="admin-field">
            <span>Name</span>
            <input value={editing.name} onChange={(e) => setEditing({ ...editing, name: e.target.value })} />
          </label>

          <div className="admin-field">
            <span>Permissions</span>
            <label className="admin-check"><input type="checkbox" checked={editing.isAdmin}
              onChange={(e) => setEditing({ ...editing, isAdmin: e.target.checked })} /> Administrator (manage users + full access)</label>
            {!editing.isAdmin && (
              <div className="admin-perm-list">
                <label className="admin-check"><input type="checkbox" checked={editing.perms.content} onChange={() => togglePerm('content')} /> Publish/edit/delete blogs &amp; news</label>
              </div>
            )}
          </div>

          {!editing.id && <p className="admin-hint">An email invite with a link to set their password will be sent. They set up two-factor authentication on first sign-in.</p>}
          <button type="submit" className="admin-btn" disabled={saving}>
            {saving ? 'Saving…' : editing.id ? 'Save changes' : 'Create &amp; send invite'}
          </button>
        </form>
      </div>
    );
  }

  return (
    <div className="admin-page">
      <header className="admin-page-head">
        <h1>Users <span className="admin-count">{users.length}</span></h1>
        <button className="admin-btn" onClick={startNew}>+ New user</button>
      </header>

      {error && <div className="admin-banner err">{error}</div>}
      {notice && <div className="admin-banner ok">{notice}</div>}
      {loading && <div className="admin-loading">Loading…</div>}

      {!loading && (
        <div className="admin-table-wrap">
          <table className="admin-table">
            <thead>
              <tr><th>Email</th><th>Name</th><th>Access</th><th>Status</th><th>2FA</th><th></th></tr>
            </thead>
            <tbody>
              {users.map((u) => (
                <tr key={u.id} className={u.active ? '' : 'unsub'}>
                  <td>{u.email}{u.id === currentUserId && <span className="tag-self">you</span>}</td>
                  <td>{u.name || '—'}</td>
                  <td>{permSummary(u)}</td>
                  <td>{u.active ? <span className="status-badge published">active</span> : <span className="status-badge draft">disabled</span>}{!u.passwordSet && <span className="tag-pending">invited</span>}</td>
                  <td>{u.mfaEnrolled ? '✓' : '—'}</td>
                  <td className="admin-actions">
                    <button className="admin-link-btn" onClick={() => startEdit(u)}>Edit</button>
                    <button className="admin-link-btn" onClick={() => resendInvite(u)}>{u.passwordSet ? 'Send reset' : 'Resend invite'}</button>
                    {u.id !== currentUserId && (
                      <button className="admin-link-btn danger" onClick={() => toggleActive(u)}>{u.active ? 'Disable' : 'Enable'}</button>
                    )}
                  </td>
                </tr>
              ))}
              {users.length === 0 && <tr><td colSpan={6} className="admin-empty">No users yet.</td></tr>}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
