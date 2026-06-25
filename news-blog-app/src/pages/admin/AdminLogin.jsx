import { useState } from 'react';
import { api } from '../../utils/api';
import '../../styles-admin.css';

function QrCode({ uri }) {
  const src = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(uri)}`;
  return <img className="admin-qr" src={src} alt="Scan this QR code with your authenticator app" width={200} height={200} />;
}

export default function AdminLogin({ onSuccess }) {
  const [stage, setStage] = useState('password'); // password | mfa | setup | forgot | forgotDone
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [code, setCode] = useState('');
  const [setupData, setSetupData] = useState(null);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  async function submitPassword(e) {
    e.preventDefault();
    setError(''); setBusy(true);
    try {
      const res = await api.login(email, password);
      if (res.mfa_enrolled) {
        setStage('mfa');
      } else {
        const data = await api.mfaSetup();
        setSetupData(data);
        setStage('setup');
      }
    } catch (err) {
      setError(err.message || 'Login failed');
    } finally {
      setBusy(false);
    }
  }

  async function submitCode(e) {
    e.preventDefault();
    setError(''); setBusy(true);
    try {
      if (stage === 'setup') await api.mfaSetupConfirm(code);
      else await api.mfaVerify(code);
      onSuccess();
    } catch (err) {
      setError(err.message || 'Verification failed');
    } finally {
      setBusy(false);
    }
  }

  async function submitForgot(e) {
    e.preventDefault();
    setError(''); setBusy(true);
    try {
      await api.requestReset(email);
      setStage('forgotDone');
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

        {stage === 'password' && (
          <form onSubmit={submitPassword}>
            <p className="admin-login-sub">Sign in to manage News &amp; Blog content.</p>
            {error && <div className="admin-banner err">{error}</div>}
            <label className="admin-field">
              <span>Email</span>
              <input type="email" value={email} autoFocus autoComplete="username"
                onChange={(e) => setEmail(e.target.value)} placeholder="you@pandit.guru" />
            </label>
            <label className="admin-field">
              <span>Password</span>
              <input type="password" value={password} autoComplete="current-password"
                onChange={(e) => setPassword(e.target.value)} placeholder="••••••••" />
            </label>
            <button type="submit" className="admin-btn" disabled={busy || !email || !password}>
              {busy ? 'Checking…' : 'Continue'}
            </button>
            <button type="button" className="admin-text-link" onClick={() => { setError(''); setStage('forgot'); }}>
              Forgot password?
            </button>
            <a href="/" className="admin-link-muted">← Back to site</a>
          </form>
        )}

        {stage === 'forgot' && (
          <form onSubmit={submitForgot}>
            <p className="admin-login-sub">Enter your email and we'll send you a reset link.</p>
            {error && <div className="admin-banner err">{error}</div>}
            <label className="admin-field">
              <span>Email</span>
              <input type="email" value={email} autoFocus
                onChange={(e) => setEmail(e.target.value)} placeholder="you@pandit.guru" />
            </label>
            <button type="submit" className="admin-btn" disabled={busy || !email}>
              {busy ? 'Sending…' : 'Send reset link'}
            </button>
            <button type="button" className="admin-text-link" onClick={() => setStage('password')}>← Back to sign in</button>
          </form>
        )}

        {stage === 'forgotDone' && (
          <>
            <div className="admin-banner ok">If an account exists for {email}, a reset link is on its way.</div>
            <button type="button" className="admin-btn" onClick={() => setStage('password')}>Back to sign in</button>
          </>
        )}

        {stage === 'setup' && setupData && (
          <form onSubmit={submitCode}>
            <p className="admin-login-sub">
              Set up two-factor authentication. Scan this QR code with Google Authenticator,
              Authy or 1Password, then enter the 6-digit code to finish.
            </p>
            {error && <div className="admin-banner err">{error}</div>}
            <QrCode uri={setupData.otpauth_uri} />
            <p className="admin-mfa-secret">Or enter this key manually:<br /><code>{setupData.secret}</code></p>
            <label className="admin-field">
              <span>6-digit code</span>
              <input inputMode="numeric" autoComplete="one-time-code" maxLength={6} value={code} autoFocus
                onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))} placeholder="000000" />
            </label>
            <button type="submit" className="admin-btn" disabled={busy || code.length !== 6}>
              {busy ? 'Verifying…' : 'Verify & finish setup'}
            </button>
          </form>
        )}

        {stage === 'mfa' && (
          <form onSubmit={submitCode}>
            <p className="admin-login-sub">Enter the 6-digit code from your authenticator app.</p>
            {error && <div className="admin-banner err">{error}</div>}
            <label className="admin-field">
              <span>6-digit code</span>
              <input inputMode="numeric" autoComplete="one-time-code" maxLength={6} value={code} autoFocus
                onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))} placeholder="000000" />
            </label>
            <button type="submit" className="admin-btn" disabled={busy || code.length !== 6}>
              {busy ? 'Verifying…' : 'Verify'}
            </button>
            <a href="/" className="admin-link-muted">← Back to site</a>
          </form>
        )}
      </div>
    </div>
  );
}
