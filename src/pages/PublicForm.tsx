import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { CheckCircle2, Loader2, Workflow as WorkflowIcon } from 'lucide-react';
import { ApiError, fetchPublicForm, submitPublicForm } from '@/lib/api/client';
import { DEFAULT_API_BASE_URL, STORAGE_KEYS } from '@/lib/api/config';
import type { PublicFormSchema } from '@/lib/api/types';
import { readStoredValue } from '@/lib/api/utils';

// Public, unauthenticated page — no ApiConnectionDialog/JWT. The API base URL defaults to the
// same-origin '/api/v1'; a locally-configured override (set via the internal app) is honored so
// local development against a non-default host still works when previewing this page.
const apiBaseUrl = readStoredValue(STORAGE_KEYS.apiBaseUrl, DEFAULT_API_BASE_URL);

export default function PublicForm() {
  const { publicToken } = useParams<{ publicToken: string }>();

  const [schema, setSchema] = useState<PublicFormSchema | null>(null);
  const [loading, setLoading] = useState(true);
  const [notFound, setNotFound] = useState(false);

  const [values, setValues] = useState<Record<string, string>>({});
  const [fieldErrors, setFieldErrors] = useState<string[]>([]);
  const [submitting, setSubmitting] = useState(false);
  const [submitted, setSubmitted] = useState(false);

  useEffect(() => {
    if (!publicToken) return;
    setLoading(true);
    setNotFound(false);
    fetchPublicForm(apiBaseUrl, publicToken)
      .then((data) => setSchema(data))
      .catch(() => setNotFound(true))
      .finally(() => setLoading(false));
  }, [publicToken]);

  const setValue = (key: string, value: string) => {
    setValues((prev) => ({ ...prev, [key]: value }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!publicToken || !schema) return;

    setSubmitting(true);
    setFieldErrors([]);

    try {
      await submitPublicForm(apiBaseUrl, publicToken, values);
      setSubmitted(true);
    } catch (e) {
      if (e instanceof ApiError && e.status === 422) {
        const body = e.body as { errors?: string[] } | null;
        setFieldErrors(body?.errors ?? [e.message]);
      } else {
        setFieldErrors([e instanceof ApiError ? e.message : 'Failed to submit the form. Please try again.']);
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="min-h-screen bg-background flex items-center justify-center p-6">
      <div className="w-full max-w-lg">
        {loading && (
          <div className="flex items-center justify-center py-20">
            <Loader2 className="h-6 w-6 text-muted-foreground animate-spin" />
          </div>
        )}

        {!loading && notFound && (
          <div className="rounded-lg border border-border p-8 text-center">
            <WorkflowIcon className="h-8 w-8 text-muted-foreground/30 mx-auto mb-3" />
            <p className="text-sm font-medium text-foreground">This form isn't available</p>
            <p className="text-xs text-muted-foreground mt-1">
              The link may be incorrect, or the form is no longer accepting submissions.
            </p>
          </div>
        )}

        {!loading && !notFound && schema && submitted && (
          <div className="rounded-lg border border-border p-8 text-center">
            <CheckCircle2 className="h-8 w-8 text-success mx-auto mb-3" />
            <p className="text-sm font-medium text-foreground">Thank you!</p>
            <p className="text-xs text-muted-foreground mt-1">Your submission has been received.</p>
          </div>
        )}

        {!loading && !notFound && schema && !submitted && (
          <div className="rounded-lg border border-border p-8">
            <h1 className="text-xl font-semibold text-foreground mb-1">{schema.form_name}</h1>
            {schema.form_description && (
              <p className="text-sm text-muted-foreground mb-6">{schema.form_description}</p>
            )}

            <form onSubmit={handleSubmit} className="space-y-5">
              {schema.fields.map((field) => (
                <div key={field.key}>
                  <label className="block text-xs font-medium text-foreground mb-1.5">
                    {field.label}
                    {field.required && <span className="text-destructive"> *</span>}
                  </label>

                  {field.type === 'textarea' ? (
                    <textarea
                      value={values[field.key] ?? ''}
                      onChange={(e) => setValue(field.key, e.target.value)}
                      required={field.required}
                      rows={3}
                      className="w-full px-3 py-2 rounded-md border border-border bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary transition-colors resize-none"
                    />
                  ) : field.type === 'select' || field.type === 'checkbox' ? (
                    <select
                      value={values[field.key] ?? ''}
                      onChange={(e) => setValue(field.key, e.target.value)}
                      required={field.required}
                      className="w-full h-9 px-3 rounded-md border border-border bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary transition-colors"
                    >
                      <option value="">— Select —</option>
                      {field.options.map((option) => (
                        <option key={option} value={option}>{option}</option>
                      ))}
                    </select>
                  ) : (
                    <input
                      type={field.type === 'number' ? 'number' : field.type === 'date' ? 'date' : 'text'}
                      value={values[field.key] ?? ''}
                      onChange={(e) => setValue(field.key, e.target.value)}
                      required={field.required}
                      className="w-full h-9 px-3 rounded-md border border-border bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary transition-colors"
                    />
                  )}
                </div>
              ))}

              {fieldErrors.length > 0 && (
                <div className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-xs text-destructive space-y-0.5">
                  {fieldErrors.map((err) => <p key={err}>{err}</p>)}
                </div>
              )}

              <button
                type="submit"
                disabled={submitting}
                className="w-full h-9 flex items-center justify-center gap-1.5 rounded-lg bg-primary text-primary-foreground text-xs font-semibold hover:bg-primary/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {submitting && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                {submitting ? 'Submitting…' : 'Submit'}
              </button>
            </form>
          </div>
        )}
      </div>
    </div>
  );
}
