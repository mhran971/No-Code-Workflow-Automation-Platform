import type { ConfigField } from '@/config/types';
import type { KnowledgeBaseDocument, TenantUser } from '@/lib/api/types';
import type { NestedFieldErrors } from '@/hooks/useFieldValidationErrors';

// Shared shapes for the config field renderers.

// One row in an 'inputfieldlist' (form/task input field definitions).
export interface InputFieldItem {
  key: string;
  label: string;
  type: string;
  options?: string[];
}

// One row in a 'branchlist' (fork outgoing branches).
export interface BranchItem {
  name: string;
  key: string;
}

// Props passed to the central FieldRenderer dispatcher.
export interface FieldRendererProps {
  field: ConfigField;
  value: unknown;
  onChange: (v: unknown) => void;
  users?: TenantUser[];
  kbDocuments?: KnowledgeBaseDocument[];
  // Per-row errors for repeating list renderers (inputfieldlist), so each
  // nested error can render under its related input.
  nestedErrors?: NestedFieldErrors;
}
