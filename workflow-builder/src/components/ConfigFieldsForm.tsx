import type { ConfigField } from '../types';
import { fieldInputType, parseFieldValue, serializeFieldValue } from '../utils';

interface ConfigFieldsFormProps {
  fields: ConfigField[];
  values: Record<string, unknown>;
  onChange: (values: Record<string, unknown>) => void;
}

export function ConfigFieldsForm({ fields, values, onChange }: ConfigFieldsFormProps) {
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

        if (field.type === 'textarea') {
          return (
            <label key={field.key}>
              {field.label}
              {field.required ? <span className="required">*</span> : null}
              <textarea
                rows={4}
                value={serializeFieldValue(value, field.type)}
                placeholder={field.placeholder ?? ''}
                onChange={(event) => updateField(field, event.target.value)}
              />
            </label>
          );
        }

        if (field.type === 'select') {
          return (
            <label key={field.key}>
              {field.label}
              {field.required ? <span className="required">*</span> : null}
              <select
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
          );
        }

        if (field.type === 'toggle') {
          return (
            <label key={field.key} className="checkbox-row">
              <input
                type="checkbox"
                checked={Boolean(value)}
                onChange={(event) => updateField(field, event.target.checked ? 'true' : 'false')}
              />
              <span>
                {field.label}
                {field.required ? <span className="required">*</span> : null}
              </span>
            </label>
          );
        }

        if (field.type === 'json' || field.type === 'tags') {
          return (
            <label key={field.key}>
              {field.label}
              {field.required ? <span className="required">*</span> : null}
              <textarea
                rows={4}
                value={serializeFieldValue(value, field.type)}
                placeholder={
                  field.placeholder ??
                  (field.type === 'tags' ? '["tag-one", "tag-two"]' : '{"key": "value"}')
                }
                onChange={(event) => updateField(field, event.target.value)}
              />
            </label>
          );
        }

        return (
          <label key={field.key}>
            {field.label}
            {field.required ? <span className="required">*</span> : null}
            <input
              type={fieldInputType(field)}
              value={serializeFieldValue(value, field.type)}
              placeholder={field.placeholder ?? ''}
              onChange={(event) => updateField(field, event.target.value)}
            />
          </label>
        );
      })}
    </div>
  );
}
