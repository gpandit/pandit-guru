import { useState } from 'react';
import { api } from '../utils/api';
import { containsProfanity, isValidEmail } from '../utils/validation';

/**
 * Inline lead-capture CTA shown on every blog/news article, regardless of
 * how much (if any) of the post the reader engaged with.
 */
export default function PostLeadForm({ postSlug }) {
  const [submitted, setSubmitted] = useState(false);
  const [status, setStatus] = useState('idle'); // idle | sending | err
  const [form, setForm] = useState({ name: '', email: '' });
  const [errors, setErrors] = useState({});

  function onChange(e) {
    setForm({ ...form, [e.target.name]: e.target.value });
    setErrors((er) => ({ ...er, [e.target.name]: undefined }));
  }

  async function onSubmit(e) {
    e.preventDefault();
    const fieldErrors = {};
    if (!isValidEmail(form.email)) fieldErrors.email = 'Enter a valid email address.';
    if (containsProfanity(form.name)) fieldErrors.name = 'Please enter your real name.';
    setErrors(fieldErrors);
    if (Object.keys(fieldErrors).length > 0) return;

    setStatus('sending');
    try {
      await api.submitBlogLead({ name: form.name, email: form.email, postSlug });
      setSubmitted(true);
    } catch (err) {
      console.error('Blog lead submission error', err);
      setStatus('err');
    }
  }

  if (submitted) {
    return <div className="post-lead-card"><div className="form-banner ok">Thanks — we'll be in touch.</div></div>;
  }

  return (
    <div className="post-lead-card">
      <div className="post-lead-copy">
        <div className="post-lead-eyebrow">Talk to us</div>
        <p>Got a project in mind, or just want to dig deeper into this topic? Leave your details and we'll reach out.</p>
      </div>
      <form className="post-lead-form" onSubmit={onSubmit}>
        {status === 'err' && <div className="form-banner err">Something went wrong — please try again.</div>}
        <div className="field">
          <label htmlFor="post-lead-name">Name</label>
          <input id="post-lead-name" name="name" type="text" required
            className={errors.name ? 'has-error' : ''} value={form.name} onChange={onChange} />
          {errors.name && <div className="field-error">{errors.name}</div>}
        </div>
        <div className="field">
          <label htmlFor="post-lead-email">Email</label>
          <input id="post-lead-email" name="email" type="email" required
            className={errors.email ? 'has-error' : ''} value={form.email} onChange={onChange} />
          {errors.email && <div className="field-error">{errors.email}</div>}
        </div>
        <button type="submit" className="btn-primary" disabled={status === 'sending'}>
          {status === 'sending' ? 'Sending…' : 'Get in touch'}
        </button>
      </form>
    </div>
  );
}
