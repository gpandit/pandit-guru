import { lazy, Suspense } from 'react';
import { Routes, Route } from 'react-router-dom';
import PostList from './pages/PostList';
import PostArticle from './pages/PostArticle';

const AdminApp = lazy(() => import('./pages/admin/AdminApp'));

export default function App() {
  return (
    <Suspense fallback={<div className="admin-loading">Loading…</div>}>
      <Routes>
        <Route path="/admin/*" element={<AdminApp />} />
        <Route path="/news-blog" element={<PostList />} />
        <Route path="/news-blog/:slug" element={<PostArticle />} />
      </Routes>
    </Suspense>
  );
}
