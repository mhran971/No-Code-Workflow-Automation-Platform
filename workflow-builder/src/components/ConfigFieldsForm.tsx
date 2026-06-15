import type { ConfigField } from '../types';
import { fieldInputType, parseFieldValue, serializeFieldValue } from '../utils';

interface ConfigFieldsFormProps {
  fields: ConfigField[];
  values: Record<string, unknown>;
  onChange: (values: Record<string, unknown>) => void;
  fieldErrors?: Record<string, string[]>;
}

export function ConfigFieldsForm({ fields, values, onChange, fieldErrors }: ConfigFieldsFormProps) {
  if (fields.length === 0) {
    return <p className="hint">This node has no configuration fields.</p>;
  }

  const updateField = (field: ConfigField, raw: string) => {
    onChange({
      ...values,
      [field.key]: parseFieldValue(raw, field.type),
    });
  };

  return (
    <div className="config-fields">
      {fields.map((field) => {
        const value = values[field.key];
        const errors = fieldErrors?.[field.key] ?? [];
        const hasError = errors.length > 0;
        const groupClass = `field-group${hasError ? ' has-error' : ''}`;

        const errorMessages = hasError ? (
          <div className="field-errors">
            {errors.map((msg, i) => (
              <span key={i} className="field-error-msg">
                {msg}
              </span>
            ))}
          </div>
        ) : null;

        const inputClass = hasError ? 'field-error-input' : undefined;

        if (field.type === 'textarea') {
          return (
            <div key={field.key} className={groupClass}>
              <label>
                {field.label}
                {field.required ? <span className="required">*</span> : null}
                <textarea
                  className={inputClass}
                  rows={4}
                  value={serializeFieldValue(value, field.type)}
                  placeholder={field.placeholder ?? ''}
                  onChange={(event) => updateField(field, event.target.value)}
                />
              </label>
              {errorMessages}
            </div>
          );
        }

        if (field.type === 'select') {
          return (
            <div key={field.key} className={groupClass}>
              <label>
                {field.label}
                {field.required ? <span className="required">*</span> : null}
                <select
                  className={inputClass}
                  value={serializeFieldValue(value, field.type)}
                  onChange={(event) => updateField(field, event.target.value)}
                >
                  <option value="">Select…</option>
                  {(field.options ?? []).map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </label>
              {errorMessages}
            </div>
          );
        }

        if (field.type === 'toggle') {
          return (
            <div key={field.key} className={groupClass}>
              <label className="checkbox-row">
                <input
                  type="checkbox"
                  className={inputClass}
                  checked={Boolean(value)}
                  onChange={(event) => updateField(field, event.target.checked ? 'true' : 'false')}
                />
                <span>
                  {field.label}
                  {field.required ? <span className="required">*</span> : null}
                </span>
              </label>
              {errorMessages}
            </div>
          );
        }

        if (field.type === 'json' || field.type === 'tags') {
          return (
            <div key={field.key} className={groupClass}>
              <label>
                {field.label}
                {field.required ? <span className="required">*</span> : null}
                <textarea
                  className={inputClass}
                  rows={4}
                  value={serializeFieldValue(value, field.type)}
                  placeholder={
                    field.placeholder ??
                    (field.type === 'tags' ? '["tag-one", "tag-two"]' : '{"key": "value"}')
                  }
                  onChange={(event) => updateField(field, event.target.value)}
                />
              </label>
              {errorMessages}
            </div>
          );
        }

        return (
          <div key={field.key} className={groupClass}>
            <label>
              {field.label}
              {field.required ? <span className="required">*</span> : null}
              <input
                className={inputClass}
                type={fieldInputType(field)}
                value={serializeFieldValue(value, field.type)}
                placeholder={field.placeholder ?? ''}
                onChange={(event) => updateField(field, event.target.value)}
              />
            </label>
            {errorMessages}
          </div>
        );
      })}
    </div>
  );
}
