// Thin wrapper around fetch for the PHP backend. Endpoint paths are resolved
// against the SITE ROOT (leading slash) — NOT relative to the current page.
// A relative path breaks on deep routes: from /admin/set-password a relative
// "admin-api/x.php" resolves to /admin/admin-api/x.php (404), which is what
// made password-reset and login appear to fail.
function rootUrl(url) {
  if (/^https?:\/\//.test(url) || url.startsWith('/')) return url;
  return '/' + url;
}

async function request(url, { method = 'GET', body, json = true } = {}) {
  const opts = {
    method,
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  };
  if (body !== undefined) {
    opts.headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify(body);
  }
  const res = await fetch(rootUrl(url), opts);
  let data = null;
  try { data = await res.json(); } catch { /* non-JSON response */ }
  if (!res.ok) {
    const message = (data && (data.error || data.detail)) || `Request failed (${res.status})`;
    const err = new Error(message);
    err.status = res.status;
    err.data = data;
    throw err;
  }
  if (json && data === null) {
    // Successful status but no JSON body (e.g. backend not reachable in dev).
    const err = new Error('Unexpected response from server');
    err.status = res.status;
    throw err;
  }
  return json ? data : res;
}

export const api = {
  // auth
  me: () => request('admin-api/me.php'),
  login: (email, password) => request('admin-api/login.php', { method: 'POST', body: { email, password } }),
  logout: () => request('admin-api/logout.php', { method: 'POST' }),
  // multi-factor auth
  mfaSetup: () => request('admin-api/mfa-setup.php'),
  mfaSetupConfirm: (code) => request('admin-api/mfa-setup.php', { method: 'POST', body: { code } }),
  mfaVerify: (code) => request('admin-api/mfa-verify.php', { method: 'POST', body: { code } }),
  // password reset / change
  requestReset: (email) => request('admin-api/request-reset.php', { method: 'POST', body: { email } }),
  checkResetToken: (token) => request(`admin-api/set-password.php?token=${encodeURIComponent(token)}`),
  setPassword: (token, password) => request('admin-api/set-password.php', { method: 'POST', body: { token, password } }),
  changePassword: (current, password) => request('admin-api/change-password.php', { method: 'POST', body: { current, password } }),
  // users (admin)
  users: () => request('admin-api/users.php'),
  createUser: (payload) => request('admin-api/users.php', { method: 'POST', body: payload }),
  updateUser: (payload) => request('admin-api/users.php', { method: 'PUT', body: payload }),
  deactivateUser: (id) => request('admin-api/users.php', { method: 'DELETE', body: { id } }),
  // posts (admin)
  adminPosts: () => request('admin-api/posts.php'),
  createPost: (post) => request('admin-api/posts.php', { method: 'POST', body: post }),
  updatePost: (post) => request('admin-api/posts.php', { method: 'PUT', body: post }),
  deletePost: (id) => request('admin-api/posts.php', { method: 'DELETE', body: { id } }),
  // authors (admin)
  authors: () => request('admin-api/authors.php'),
  createAuthor: (author) => request('admin-api/authors.php', { method: 'POST', body: author }),
  updateAuthor: (author) => request('admin-api/authors.php', { method: 'PUT', body: author }),
  deleteAuthor: (id) => request('admin-api/authors.php', { method: 'DELETE', body: { id } }),
  // image upload (admin) — multipart, so it bypasses the JSON request() helper
  uploadImage: async (file, { alt = '', postId = '' } = {}) => {
    const data = new FormData();
    // A plain Blob (e.g. an image extracted from a .docx) has no filename;
    // give it one so the multipart part carries a name PHP can log/inspect.
    data.set('file', file, file.name || `upload.${(file.type || 'image/png').split('/')[1] || 'png'}`);
    if (alt) data.set('alt', alt);
    if (postId) data.set('postId', postId);
    const res = await fetch(rootUrl('admin-api/image-upload.php'), {
      method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json' }, body: data,
    });
    const out = await res.json().catch(() => null);
    if (!res.ok) throw new Error((out && out.error) || `Upload failed (${res.status})`);
    return out;
  },
  // public content — type is optional ('blog' | 'news'); omitted = combined feed
  publicPosts: (type) => request(`content-api/posts.php${type ? `?type=${encodeURIComponent(type)}` : ''}`),
  publicPost: (slug) => request(`content-api/posts.php?slug=${encodeURIComponent(slug)}`),
  // post view/dwell tracking (beacon — fire-and-forget, never throws)
  trackPostView: (payload) => {
    try {
      const data = JSON.stringify(payload);
      if (navigator.sendBeacon) navigator.sendBeacon(rootUrl('content-api/post-track.php'), new Blob([data], { type: 'application/json' }));
      else fetch(rootUrl('content-api/post-track.php'), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: data, keepalive: true });
    } catch { /* tracking must never break the reader */ }
  },
  // post comments (public)
  postComments: (postId) => request(`content-api/post-comments.php?postId=${encodeURIComponent(postId)}`),
  submitPostComment: (payload) => request('content-api/post-comments.php', { method: 'POST', body: payload }),
  // post reactions (public)
  postReactions: (postId) => request(`content-api/post-reactions.php?postId=${encodeURIComponent(postId)}`),
  togglePostReaction: (postId, type) => request('content-api/post-reactions.php', { method: 'POST', body: { postId, type } }),
  // blog inline lead capture (public)
  submitBlogLead: (payload) => request('blog-lead-handler.php', { method: 'POST', body: payload }),
  // comment moderation (admin)
  adminComments: (status = 'queue') => request(`admin-api/post-comments.php?status=${encodeURIComponent(status)}`),
  moderateComment: (id, status) => request('admin-api/post-comments.php', { method: 'PUT', body: { id, status } }),
  deleteComment: (id) => request('admin-api/post-comments.php', { method: 'DELETE', body: { id } }),

  // analytics
  postViewsSummary: () => request('admin-api/post-views.php'),
  postViewsLog: (postId) => request(`admin-api/post-views.php?postId=${encodeURIComponent(postId)}`),
  postViewsCsvUrl: (postId) => rootUrl(`admin-api/post-views.php?postId=${encodeURIComponent(postId)}&format=csv`),
};
