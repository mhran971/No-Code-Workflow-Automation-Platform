// Config field & node-schema type contracts shared by every schema file.
// To add a new field type: add it to ConfigFieldType, then add a renderer +
// a `case` in components/workflow/config-fields/FieldRenderer.tsx.

export type ConfigFieldType =
  | 'text'
  | 'textarea'
  | 'select'
  | 'number'
  | 'toggle'
  | 'keyvalue'
  | 'fieldlist'
  | 'code'
  | 'tags'
  | 'readonly'
  | 'section'
  | 'userselect'
  | 'identifier'
  | 'inputfieldlist'
  | 'templatetext'
  | 'templatetextarea'
  | 'emailtemplate'
  | 'branchlist'
  | 'kbdocuments'
  | 'workflowselect'
  | 'triggermapping'
  | 'workflowoutput';

export interface ConfigFieldOption {
  label: string;
  value: string;
}

export interface ConfigField {
  key: string;
  label: string;
  type: ConfigFieldType;
  placeholder?: string;
  description?: string;
  required?: boolean;
  defaultValue?: unknown;
  options?: ConfigFieldOption[];
  min?: number;
  max?: number;
  fields?: ConfigField[]; // for nested fieldlist items
}

export interface NodeConfigSchema {
  nodeType: string;
  sections: {
    title: string;
    fields: ConfigField[];
  }[];
}
