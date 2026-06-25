import { useState } from 'react';
import { api } from '../../utils/api';

/**
 * Upload a .md or .docx file and convert it to the HTML the rich-text editor
 * expects. Markdown is rendered client-side (markdown-it); Word documents are
 * converted with mammoth.js, which also extracts embedded images — each one
 * is pushed through the same image-upload endpoint the editor's "Insert
 * image" button uses, so the resulting HTML points at /image.php?id=… rather
 * than carrying base64 data inline.
 */
export default function DocumentImport({ postId, onImported }) {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  async function handleFile(file) {
    setBusy(true); setError('');
    try {
      const ext = file.name.toLowerCase().split('.').pop();
      let html;
      if (ext === 'md' || ext === 'markdown') {
        const text = await file.text();
        const { default: MarkdownIt } = await import('markdown-it');
        html = new MarkdownIt({ html: false, linkify: true }).render(text);
      } else if (ext === 'docx') {
        html = await convertDocx(file, postId);
      } else {
        throw new Error('Please choose a .md, .markdown or .docx file.');
      }
      onImported(html);
    } catch (err) {
      setError(err.message || 'Could not convert this file.');
    } finally {
      setBusy(false);
    }
  }

  function onChange(e) {
    const file = e.target.files?.[0];
    e.target.value = '';
    if (file) handleFile(file);
  }

  return (
    <div className="doc-import">
      <label className="admin-btn ghost doc-import-btn">
        {busy ? 'Converting…' : 'Import from .md / .docx'}
        <input type="file" accept=".md,.markdown,.docx" hidden onChange={onChange} disabled={busy} />
      </label>
      <span className="admin-hint">Replaces the body below with the converted document — review before saving.</span>
      {error && <div className="admin-banner err">{error}</div>}
    </div>
  );
}

async function convertDocx(file, postId) {
  // mammoth is a CJS-only package; depending on the bundler's interop, its
  // exports may land on the namespace object directly or under `.default`.
  const mod = await import('mammoth');
  const mammoth = mod.convertToHtml ? mod : mod.default;
  const arrayBuffer = await file.arrayBuffer();

  // Each embedded image is uploaded individually and the <img> src rewritten
  // to point at the stored copy, instead of leaving inline base64 in the body.
  const convertImage = mammoth.images.imgElement(async (image) => {
    const base64 = await image.read('base64');
    const blob = base64ToBlob(base64, image.contentType);
    const uploaded = await api.uploadImage(blob, { postId: postId || '' });
    return { src: uploaded.url };
  });

  const result = await mammoth.convertToHtml({ arrayBuffer }, { convertImage });
  return result.value;
}

function base64ToBlob(base64, mime) {
  const bytes = atob(base64);
  const arr = new Uint8Array(bytes.length);
  for (let i = 0; i < bytes.length; i++) arr[i] = bytes.charCodeAt(i);
  return new Blob([arr], { type: mime || 'image/png' });
}
