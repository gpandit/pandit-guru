import { useState, useEffect } from 'react';
import { Helmet } from 'react-helmet-async';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import PageHeader from '../components/PageHeader';
import { staggerParent, staggerChild } from '../components/Kinetic';
import { api } from '../utils/api';

function fmtDate(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  return Number.isNaN(d.getTime()) ? '' : d.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
}

export default function PostList() {
  // Public list of published posts (blog + news combined).
  const [posts, setPosts] = useState(null); // null = loading
  const [error, setError] = useState('');

  useEffect(() => {
    let alive = true;
    api.publicPosts()
      .then((d) => { if (alive) setPosts(d.posts || []); })
      .catch((e) => { if (alive) { setError(e.message); setPosts([]); } });
    return () => { alive = false; };
  }, []);

  return (
    <main className="page">
      <Helmet>
        <title>News & Blog — Pandit Guru</title>
        <meta name="description" content="Articles, updates and announcements from Pandit Guru." />
        <link rel="canonical" href="https://pandit.guru/news-blog" />
        <meta property="og:title" content="News & Blog — Pandit Guru" />
        <meta property="og:url" content="https://pandit.guru/news-blog" />
        <meta property="og:description" content="Articles, updates and announcements from Pandit Guru." />
      </Helmet>
      <PageHeader eyebrow="Insights" titleA="News &" titleB="Blog." sub="Articles, updates and announcements from Pandit Guru." />

      {posts === null && <p className="post-status">Loading…</p>}
      {error && <p className="post-status">{error}</p>}
      {posts && posts.length === 0 && !error && <p className="post-status">No posts published yet — check back soon.</p>}

      {posts && posts.length > 0 && (
        <motion.div
          className="post-grid"
          variants={staggerParent} initial="hidden" whileInView="show"
          viewport={{ once: true, amount: 0.05 }}
        >
          {posts.map((p) => (
            <motion.article className="post-card" key={p.id} variants={staggerChild} whileHover={{ y: -6 }}>
              <Link to={`/news-blog/${p.slug}`} className="post-card-link">
                <div className="post-card-img">
                  {p.coverImage
                    ? <img src={p.coverImage} alt="" loading="lazy" />
                    : <div className="post-card-img-ph">{(p.title || '?').charAt(0)}</div>}
                </div>
                <div className="post-card-body">
                  {p.tags?.length > 0 && <div className="post-card-tag">{p.tags[0]}</div>}
                  <h3 className="post-card-title">{p.title}</h3>
                  {p.excerpt && <p className="post-card-excerpt">{p.excerpt}</p>}
                  <div className="post-card-meta">
                    <span>{p.author}</span>
                    {p.publishedAt && <span>· {fmtDate(p.publishedAt)}</span>}
                  </div>
                  <span className="post-card-readmore">Read more →</span>
                </div>
              </Link>
            </motion.article>
          ))}
        </motion.div>
      )}
    </main>
  );
}
