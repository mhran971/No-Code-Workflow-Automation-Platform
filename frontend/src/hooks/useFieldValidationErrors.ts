import { useMemo } from 'react';
import type { ValidationIssue } from '@/lib/api/types';

// Errors for a single repeating list field, keyed by row index then by the
// row's sub-key (e.g. 'key' | 'label' | 'type' | 'options'). The special
// sub-key '_' holds a row-level error that isn't tied to one input.
export type NestedFieldErrors = Record<number, Record<string, string>>;

interface ParsedPath {
  fieldKey: string;
  index: number | null;
  subKey: string | null;
}

// Pull the config field (and optional nested row index / sub-key) out of a
// validation issue path like "nodes[3].config.subject" or
// "nodes[3].config.inputFields[0].key".
function parseConfigPath(path: string | null | undefined): ParsedPath | null {
  if (!path) return null;
  const match = path.match(/\.config\.(\w+)(?:\[(\d+)\](?:\.(\w+))?)?$/);
  if (!match) return null;
  return {
    fieldKey: match[1],
    index: match[2] !== undefined ? Number(match[2]) : null,
    subKey: match[3] ?? null,
  };
}

export interface FieldValidationErrors {
  fieldErrors: Record<string, string>;             // top-level field key → message
  nestedErrors: Record<string, NestedFieldErrors>; // field key → row index → sub-key → message
  nodeErrors: ValidationIssue[];                    // node-level errors (no field key)
}

// Split error-severity validation issues into per-field, per-nested-row, and
// node-level buckets so each error can render under its related input.
export function useFieldValidationErrors(validationIssues?: ValidationIssue[]): FieldValidationErrors {
  return useMemo(() => {
    const fieldErrors: Record<string, string> = {};
    const nestedErrors: Record<string, NestedFieldErrors> = {};
    const nodeErrors: ValidationIssue[] = [];
    for (const issue of validationIssues ?? []) {
      if (issue.severity !== 'error') continue;
      const path = issue.location?.path ?? issue.path ?? null;
      const parsed = parseConfigPath(path);
      if (!parsed) {
        nodeErrors.push(issue);
        continue;
      }
      if (parsed.index === null) {
        fieldErrors[parsed.fieldKey] = issue.message;
      } else {
        const rows = nestedErrors[parsed.fieldKey] ?? {};
        const row = rows[parsed.index] ?? {};
        row[parsed.subKey ?? '_'] = issue.message;
        rows[parsed.index] = row;
        nestedErrors[parsed.fieldKey] = rows;
      }
    }
    return { fieldErrors, nestedErrors, nodeErrors };
  }, [validationIssues]);
}
