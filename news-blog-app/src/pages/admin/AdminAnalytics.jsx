import { useState, useEffect, useCallback } from 'react';
import { api } from '../../utils/api';

export default function AdminAnalytics() {
  const [posts, setPosts] = useState(null);
  const [error, setError] = useState('');
  const [selected, setSelected] = useState(null); // { postId, postTitle, views }
  const [logLoading, setLogLoading] = useState(false);

  const load = useCallback(async () => {
    try {
      const res = await api.postViewsSummary();
      setPosts(res.posts);
    } catch (e) {
      setError(e.message);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  async function openLog(postId) {
    setLogLoading(true);
    setSelected(null);
    try {
      const res = await api.postViewsLog(postId);
      setSelected(res);
    } catch (e) {
      setError(e.message);
    } finally {
      setLogLoading(false);
    }
  }

  if (error) return <div className="admin-banner err">{error}</div>;
  if (!posts) return <div className="admin-loading">Loading…</div>;

  return (
    <div>
      <div className="admin-page-head"><h1>Analytics</h1></div>
      <div className="admin-table-wrap">
        <table className="admin-table">
          <thead>
            <tr>
              <th>Title</th><th>Type</th><th>Status</th><th>Views</th><th>Avg. time on page</th><th>Unique IPs</th><th></th>
            </tr>
          </thead>
          <tbody>
            {posts.map((p) => (
              <tr key={p.id}>
                <td>{p.title}</td>
                <td>{p.type}</td>
                <td>{p.status}</td>
                <td>{p.views}</td>
                <td>{p.avgSecondsOnPage}s</td>
                <td>{p.uniqueIps}</td>
                <td><button className="admin-link-btn" onClick={() => openLog(p.id)}>View log</button></td>
              </tr>
            ))}
            {posts.length === 0 && <tr><td colSpan={7}>No posts yet.</td></tr>}
          </tbody>
        </table>
      </div>

      {logLoading && <div className="admin-loading">Loading log…</div>}

      {selected && (
        <div className="admin-card admin-views-log">
          <div className="admin-views-log-head">
            <h2>{selected.postTitle || selected.postId}</h2>
            <div className="admin-views-log-actions">
              <a className="admin-btn ghost" href={api.postViewsCsvUrl(selected.postId)}>Export CSV</a>
              <button className="admin-link-btn" onClick={() => setSelected(null)}>Close</button>
            </div>
          </div>
          <div className="admin-table-wrap">
            <table className="admin-table">
              <thead>
                <tr><th>IP</th><th>Country</th><th>Seconds spent</th><th>Referrer</th><th>First viewed</th><th>Last updated</th></tr>
              </thead>
              <tbody>
                {selected.views.map((v, i) => (
                  <tr key={i}>
                    <td>{v.ip}</td>
                    <td>{v.country || '—'}</td>
                    <td>{v.secondsSpent}s</td>
                    <td>{v.referrer || '—'}</td>
                    <td>{v.createdAt}</td>
                    <td>{v.updatedAt}</td>
                  </tr>
                ))}
                {selected.views.length === 0 && <tr><td colSpan={6}>No recorded views yet.</td></tr>}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
}
