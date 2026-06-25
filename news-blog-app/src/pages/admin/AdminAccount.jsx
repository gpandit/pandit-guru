import { useState } from 'react';
import { api } from '../../utils/api';

const PERM_LABELS = { leads: 'Leads & marketing', applicants: 'Applicants', content: 'Blog & News' };

export default function AdminAccount({ user }) {
  const [current, setCurrent] = useState('');
  const [password, setPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [status, setStatus] = useState('idle');
  const [error, setError] = useState('');

  async function submit(e) {
    e.preventDefault();
    setError(''); setStatus('idle');
    if (password.length < 10) { setError('New password must be at least 10 characters.'); return; }
    if (password !== confirm) { setError('Passwords do not match.'); return; }
    setStatus('saving');
    try {
      await api.changePassword(current, password);
      setStatus('done');
      setCurrent(''); setPassword(''); setConfirm('');
    } catch (err) {
      setError(err.message); setStatus('idle');
    }
  }

  const perms = user.isAdmin
    ? ['leads', 'applicants', 'content']
    : Object.keys(user.perms).filter((k) => user.perms[k]);

  return (
    <div className="admin-page">
      <header className="admin-page-head"><h1>My account</h1></header>

      <div className="admin-account-grid">
        <div className="admin-card">
          <h2>Profile</h2>
          <div className="admin-kv"><span>Email</span><strong>{user.email}</strong></div>
          <div className="admin-kv"><span>Name</span><strong>{user.name || '—'}</strong></div>
          <div className="admin-kv"><span>Role</span><strong>{user.isAdmin ? 'Administrator' : 'Staff'}</strong></div>
          <div className="admin-kv"><span>Access</span><strong>{user.isAdmin ? 'Full access' : (perms.map((p) => PERM_LABELS[p]).join(', ') || 'None')}</strong></div>
          <div className="admin-kv"><span>Two-factor</span><strong>{user.mfaEnrolled ? 'Enabled ✓' : 'Not set up'}</strong></div>
        </div>

        <div className="admin-card">
          <h2>Change password</h2>
          {error && <div className="admin-banner err">{error}</div>}
          {status === 'done' && <div className="admin-banner ok">Password updated.</div>}
          <form onSubmit={submit}>
            <label className="admin-field">
              <span>Current password</span>
              <input type="password" value={current} autoComplete="current-password"
                onChange={(e) => setCurrent(e.target.value)} />
            </label>
            <label className="admin-field">
              <span>New password (min 10 characters)</span>
              <input type="password" value={password} autoComplete="new-password"
                onChange={(e) => setPassword(e.target.value)} />
            </label>
            <label className="admin-field">
              <span>Confirm new password</span>
              <input type="password" value={confirm} autoComplete="new-password"
                onChange={(e) => setConfirm(e.target.value)} />
            </label>
            <button type="submit" className="admin-btn" disabled={status === 'saving' || !current || !password}>
              {status === 'saving' ? 'Saving…' : 'Update password'}
            </button>
          </form>
        </div>
      </div>
    </div>
  );
}
