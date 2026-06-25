import { useEffect, useRef, useState } from 'react';
import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import TiptapImage from '@tiptap/extension-image';
import TiptapLink from '@tiptap/extension-link';
import { api } from '../../utils/api';

/**
 * Rich-text body editor for posts. Backed by TipTap; persists plain HTML to
 * match the existing `body` column (rendered via dangerouslySetInnerHTML on
 * the public site — see PostArticle.jsx). A raw-HTML source view is offered
 * as an escape hatch for anything the toolbar can't express.
 */
export default function RichTextEditor({ value, onChange, postId }) {
  const [sourceMode, setSourceMode] = useState(false);
  const [sourceDraft, setSourceDraft] = useState(value || '');
  const [uploading, setUploading] = useState(false);
  const fileRef = useRef(null);

  const editor = useEditor({
    extensions: [
      StarterKit,
      TiptapLink.configure({ openOnClick: false }),
      TiptapImage.configure({ HTMLAttributes: { loading: 'lazy' } }),
    ],
    content: value || '',
    onUpdate: ({ editor: ed }) => onChange(ed.getHTML()),
  });

  // Keep the editor in sync when the parent swaps content out from under us
  // (e.g. after a Markdown/Word import, or switching between posts).
  useEffect(() => {
    if (editor && !sourceMode && value !== editor.getHTML()) {
      editor.commands.setContent(value || '', false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [value, sourceMode]);

  function toggleSource() {
    if (!sourceMode) {
      setSourceDraft(editor ? editor.getHTML() : value || '');
      setSourceMode(true);
    } else {
      onChange(sourceDraft);
      editor?.commands.setContent(sourceDraft, false);
      setSourceMode(false);
    }
  }

  async function insertImage(file) {
    setUploading(true);
    try {
      const img = await api.uploadImage(file, { postId: postId || '' });
      editor?.chain().focus().setImage({ src: img.url, alt: '' }).run();
    } catch (err) {
      window.alert(`Image upload failed: ${err.message}`);
    } finally {
      setUploading(false);
    }
  }

  function onPickImage(e) {
    const file = e.target.files?.[0];
    if (file) insertImage(file);
    e.target.value = '';
  }

  function setLink() {
    const url = window.prompt('Link URL');
    if (url === null) return;
    if (url === '') { editor?.chain().focus().unsetLink().run(); return; }
    editor?.chain().focus().setLink({ href: url }).run();
  }

  if (!editor) return null;

  return (
    <div className="rte">
      <div className="rte-toolbar">
        <button type="button" className={editor.isActive('bold') ? 'active' : ''} onClick={() => editor.chain().focus().toggleBold().run()} disabled={sourceMode} title="Bold"><b>B</b></button>
        <button type="button" className={editor.isActive('italic') ? 'active' : ''} onClick={() => editor.chain().focus().toggleItalic().run()} disabled={sourceMode} title="Italic"><i>I</i></button>
        <span className="rte-sep" />
        <button type="button" className={editor.isActive('heading', { level: 2 }) ? 'active' : ''} onClick={() => editor.chain().focus().toggleHeading({ level: 2 }).run()} disabled={sourceMode} title="Heading">H2</button>
        <button type="button" className={editor.isActive('heading', { level: 3 }) ? 'active' : ''} onClick={() => editor.chain().focus().toggleHeading({ level: 3 }).run()} disabled={sourceMode} title="Subheading">H3</button>
        <span className="rte-sep" />
        <button type="button" className={editor.isActive('bulletList') ? 'active' : ''} onClick={() => editor.chain().focus().toggleBulletList().run()} disabled={sourceMode} title="Bullet list">• ―</button>
        <button type="button" className={editor.isActive('orderedList') ? 'active' : ''} onClick={() => editor.chain().focus().toggleOrderedList().run()} disabled={sourceMode} title="Numbered list">1.</button>
        <button type="button" className={editor.isActive('blockquote') ? 'active' : ''} onClick={() => editor.chain().focus().toggleBlockquote().run()} disabled={sourceMode} title="Quote">"</button>
        <button type="button" className={editor.isActive('codeBlock') ? 'active' : ''} onClick={() => editor.chain().focus().toggleCodeBlock().run()} disabled={sourceMode} title="Code block">{'</>'}</button>
        <span className="rte-sep" />
        <button type="button" className={editor.isActive('link') ? 'active' : ''} onClick={setLink} disabled={sourceMode} title="Link">🔗</button>
        <button type="button" onClick={() => fileRef.current?.click()} disabled={sourceMode || uploading} title="Insert image">
          {uploading ? 'Uploading…' : '🖼 Image'}
        </button>
        <input ref={fileRef} type="file" accept="image/*" hidden onChange={onPickImage} />
        <span className="rte-spacer" />
        <button type="button" className={`rte-source-toggle ${sourceMode ? 'active' : ''}`} onClick={toggleSource} title="Toggle HTML source">{'</>'} HTML</button>
      </div>

      {sourceMode ? (
        <textarea
          className="rte-source"
          rows={16}
          value={sourceDraft}
          onChange={(e) => setSourceDraft(e.target.value)}
        />
      ) : (
        <EditorContent editor={editor} className="rte-content" />
      )}
      <p className="admin-hint">
        Guideline: roughly one image per 300–500 words placed after the paragraph it illustrates — for a
        1,000–1,500 word post that's usually 2–3 inline images in addition to the cover image.
      </p>
    </div>
  );
}
