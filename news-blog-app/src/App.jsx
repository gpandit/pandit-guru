import { lazy, Suspense } from 'react';
import { Routes, Route } from 'react-router-dom';
import PostList from './pages/PostList';
import PostArticle from './pages/PostArticle';
import SiteHeader from './components/SiteHeader';
import SiteFooter from './components/SiteFooter';
import './site-chrome.css';

const AdminApp = lazy(() => import('./pages/admin/AdminApp'));

// Public /news-blog pages get the same header/footer as the rest of
// pandit.guru so they don't look orphaned from the main site. /admin keeps
// its own layout untouched.
function BlogPage({ children }) {
  return (
    <>
      <SiteHeader />
      {children}
      <SiteFooter />
    </>
  );
}

export default function App() {
  return (
    <Suspense fallback={<div className="admin-loading">Loading…</div>}>
      <Routes>
        <Route path="/admin/*" element={<AdminApp />} />
        <Route path="/news-blog" element={<BlogPage><PostList /></BlogPage>} />
        <Route path="/news-blog/:slug" element={<BlogPage><PostArticle /></BlogPage>} />
      </Routes>
    </Suspense>
  );
}
