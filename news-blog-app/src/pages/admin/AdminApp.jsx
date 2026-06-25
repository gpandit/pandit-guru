import { useState, useEffect, useCallback } from 'react';
import { Routes, Route, NavLink, Navigate, useNavigate, useLocation } from 'react-router-dom';
import { api } from '../../utils/api';
import AdminLogin from './AdminLogin';
import AdminPosts from './AdminPosts';
import AdminAuthors from './AdminAuthors';
import AdminComments from './AdminComments';
import AdminAnalytics from './AdminAnalytics';
import AdminUsers from './AdminUsers';
import AdminAccount from './AdminAccount';
import SetPassword from './SetPassword';
import '../../styles-admin.css';

export default function AdminApp() {
  const [user, setUser] = useState(undefined); // undefined = checking, null = logged out
  const navigate = useNavigate();
  const location = useLocation();

  const check = useCallback(async () => {
    try {
      const res = await api.me();
      setUser(res.authed ? res.user : null);
    } catch {
      setUser(null);
    }
  }, []);

  useEffect(() => { check(); }, [check]);

  async function onLogout() {
    try { await api.logout(); } catch { /* ignore */ }
    setUser(null);
    navigate('/admin');
  }

  // Public token-based password set / reset — no auth required.
  if (location.pathname === '/admin/set-password') {
    return <SetPassword />;
  }

  if (user === undefined) {
    return <div className="admin-shell"><div className="admin-loading">Loading…</div></div>;
  }

  if (!user) {
    return <AdminLogin onSuccess={check} />;
  }

  const can = user.isAdmin ? { content: true } : user.perms;
  const home = can.content ? '/admin/posts' : '/admin/account';

  return (
    <div className="admin-shell">
      <aside className="admin-sidebar">
        <div className="admin-brand">Pandit Guru <span>Admin</span></div>
        <nav className="admin-nav">
          {can.content && <NavLink to="/admin/posts" className={({ isActive }) => isActive ? 'active' : ''}>News &amp; Blog</NavLink>}
          {can.content && <NavLink to="/admin/authors" className={({ isActive }) => isActive ? 'active' : ''}>Authors</NavLink>}
          {can.content && <NavLink to="/admin/comments" className={({ isActive }) => isActive ? 'active' : ''}>Comments</NavLink>}
          {can.content && <NavLink to="/admin/analytics" className={({ isActive }) => isActive ? 'active' : ''}>Analytics</NavLink>}
          {user.isAdmin && <NavLink to="/admin/users" className={({ isActive }) => isActive ? 'active' : ''}>Users</NavLink>}
          <NavLink to="/admin/account" className={({ isActive }) => isActive ? 'active' : ''}>My account</NavLink>
        </nav>
        <div className="admin-sidebar-foot">
          <span className="admin-whoami">{user.email}</span>
          <a href="/" className="admin-link-muted">← Back to site</a>
          <button className="admin-logout" onClick={onLogout}>Log out</button>
        </div>
      </aside>
      <main className="admin-main">
        <Routes>
          <Route index element={<Navigate to={home} replace />} />
          {can.content && <Route path="posts" element={<AdminPosts />} />}
          {can.content && <Route path="authors" element={<AdminAuthors />} />}
          {can.content && <Route path="comments" element={<AdminComments />} />}
          {can.content && <Route path="analytics" element={<AdminAnalytics />} />}
          {user.isAdmin && <Route path="users" element={<AdminUsers currentUserId={user.id} />} />}
          <Route path="account" element={<AdminAccount user={user} />} />
          <Route path="*" element={<Navigate to={home} replace />} />
        </Routes>
      </main>
    </div>
  );
}
