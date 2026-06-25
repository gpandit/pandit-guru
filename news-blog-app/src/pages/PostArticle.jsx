import { useState, useEffect, useRef } from 'react';
import { Helmet } from 'react-helmet-async';
import { Link, useParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import { api } from '../utils/api';
import PostComments from '../components/PostComments';
import PostLeadForm from '../components/PostLeadForm';

function fmtDate(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  return Number.isNaN(d.getTime()) ? '' : d.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
}

function sessionKey() {
  const k = 'pg_session_id';
  let v = sessionStorage.getItem(k);
  if (!v) { v = (crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`); sessionStorage.setItem(k, v); }
  return v;
}

/** Beacons dwell time on the article while the tab is visible. */
function useViewTracking(postId) {
  const secondsRef = useRef(0);

  useEffect(() => {
    if (!postId) return;
    const key = sessionKey();
    const send = () => api.trackPostView({ postId, sessionKey: key, secondsSpent: secondsRef.current, referrer: document.referrer });

    const tick = setInterval(() => { if (document.visibilityState === 'visible') secondsRef.current += 1; }, 1000);
    const flush = setInterval(send, 15000);
    const onHide = () => { if (document.visibilityState === 'hidden') send(); };
    document.addEventListener('visibilitychange', onHide);
    window.addEventListener('pagehide', send);
    send(); // initial "view_open" style ping

    return () => {
      clearInterval(tick); clearInterval(flush);
      document.removeEventListener('visibilitychange', onHide);
      window.removeEventListener('pagehide', send);
      send();
    };
  }, [postId]);
}

export default function PostArticle() {
  const { slug } = useParams();
  const [post, setPost] = useState(null); // null = loading, false = not found
  const [error, setError] = useState('');

  useViewTracking(post ? post.id : null);

  useEffect(() => {
    let alive = true;
    setPost(null); setError('');
    api.publicPost(slug)
      .then((d) => { if (alive) setPost(d.post); })
      .catch((e) => { if (alive) { setError(e.status === 404 ? 'This post could not be found.' : e.message); setPost(false); } });
    return () => { alive = false; };
  }, [slug]);

  return (
    <main className="page post-article-page">
      <Link to="/news-blog" className="post-back">← All posts</Link>

      {post === null && <p className="post-status">Loading…</p>}
      {error && <p className="post-status">{error}</p>}

      {post && (
        <>
          <Helmet>
            <title>{post.metaTitle || post.title} — Pandit Guru</title>
            <meta name="description" content={post.metaDescription || post.excerpt || ''} />
            {post.metaKeywords && <meta name="keywords" content={post.metaKeywords} />}
            <link rel="canonical" href={`https://pandit.guru/news-blog/${post.slug}`} />
            <meta property="og:title" content={post.metaTitle || post.title} />
            <meta property="og:description" content={post.metaDescription || post.excerpt || ''} />
            <meta property="og:type" content="article" />
            <meta property="og:url" content={`https://pandit.guru/news-blog/${post.slug}`} />
            {post.coverImage && <meta property="og:image" content={post.coverImage} />}
            <script type="application/ld+json">{JSON.stringify({
              '@context': 'https://schema.org',
              '@type': 'Article',
              headline: post.title,
              description: post.metaDescription || post.excerpt || '',
              author: { '@type': 'Person', name: post.author || 'Pandit Guru' },
              datePublished: post.publishedAt || undefined,
              image: post.coverImage || undefined,
            })}</script>
          </Helmet>
          <motion.article
            className="post-article"
            initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.5 }}
          >
            {post.tags?.length > 0 && <div className="post-article-tag">{post.tags[0]}</div>}
            <h1 className="post-article-title display">{post.title}</h1>
            <div className="post-article-meta">
              <span>{post.author}</span>
              {post.publishedAt && <span>· {fmtDate(post.publishedAt)}</span>}
              {post.readingTimeMinutes && <span>· {post.readingTimeMinutes} min read</span>}
            </div>
            {post.coverImage && (
              <div className="post-article-cover"><img src={post.coverImage} alt="" /></div>
            )}
            <div className="post-article-body" dangerouslySetInnerHTML={{ __html: post.body || '' }} />
            {post.tags?.length > 1 && (
              <div className="post-article-tags">
                {post.tags.map((tag) => <span key={tag} className="post-chip">{tag}</span>)}
              </div>
            )}
            {post.authorBio && (
              <div className="post-author-card">
                {post.authorAvatarUrl && <img src={post.authorAvatarUrl} alt={post.author} className="post-author-avatar" />}
                <div>
                  <div className="post-author-name">{post.author}</div>
                  <p className="post-author-bio">{post.authorBio}</p>
                </div>
              </div>
            )}

            <PostLeadForm postSlug={post.slug} />
            <PostComments postId={post.id} />
          </motion.article>
        </>
      )}
    </main>
  );
}
