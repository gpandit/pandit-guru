import { useState, useEffect } from 'react';
import { api } from '../../utils/api';
import '../../styles-admin.css';

function getToken() {
  return new URLSearchParams(window.location.search).get('token') || '';
}

export default function SetPassword() {
  const [token] = useState(getToken);
  const [state, setState] = useState('checking'); // checking | valid | invalid | done
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (!token) { setState('invalid'); return; }
    api.checkResetToken(token)
      .then((d) => { if (d.valid) { setEmail(d.email); setState('valid'); } else setState('invalid'); })
      .catch(() => setState('invalid'));
  }, [token]);

  async function submit(e) {
    e.preventDefault();
    setError('');
    if (password.length < 10) { setError('Password must be at least 10 characters.'); return; }
    if (password !== confirm) { setError('Passwords do not match.'); return; }
    setBusy(true);
    try {
      await api.setPassword(token, password);
      setState('done');
    } catch (err) {
      setError(err.message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="admin-login-wrap">
      <div className="admin-login-card">
        <div className="admin-brand">Pandit Guru <span>Admin</span></div>

        {state === 'checking' && <p className="admin-login-sub">Checking your link…</p>}

        {state === 'invalid' && (
          <>
            <div className="admin-banner err">This link is invalid or has expired.</div>
            <a href="/admin" className="admin-btn" style={{ textAlign: 'center', textDecoration: 'none' }}>Go to sign in</a>
          </>
        )}

        {state === 'valid' && (
          <form onSubmit={submit}>
            <p className="admin-login-sub">Choose a password for <strong>{email}</strong>. You'll set up two-factor authentication the first time you sign in.</p>
            {error && <div className="admin-banner err">{error}</div>}
            <label className="admin-field">
              <span>New password (min 10 characters)</span>
              <input type="password" value={password} autoFocus autoComplete="new-password"
                onChange={(e) => setPassword(e.target.value)} />
            </label>
            <label className="admin-field">
              <span>Confirm password</span>
              <input type="password" value={confirm} autoComplete="new-password"
                onChange={(e) => setConfirm(e.target.value)} />
            </label>
            <button type="submit" className="admin-btn" disabled={busy || !password || !confirm}>
              {busy ? 'Saving…' : 'Set password'}
            </button>
          </form>
        )}

        {state === 'done' && (
          <>
            <div className="admin-banner ok">Password set. You can now sign in.</div>
            <a href="/admin" className="admin-btn" style={{ textAlign: 'center', textDecoration: 'none' }}>Go to sign in</a>
          </>
        )}
      </div>
    </div>
  );
}
