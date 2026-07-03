import {
  TextField, TextareaField, CodeField, NumberField, SelectField, ToggleField, ReadonlyField,
} from './basic';
import {
  KeyValueField, TagsField, FieldListField,
} from './collections';
import { InputFieldListField, BranchListField } from './lists';
import {
  TemplateTextField, TemplateTextareaField, EmailTemplateField, IdentifierField, UserSelectField, KbDocumentsField,
} from './templates';
import type { FieldRendererProps, InputFieldItem, BranchItem } from './types';

// Central dispatcher: maps a field's `type` to its renderer.
// To support a new field type: add it to ConfigFieldType (config/types.ts),
// write a renderer in basic/collections/templates, then add a `case` here.
export function FieldRenderer({ field, value, onChange, users, kbDocuments, nestedErrors }: FieldRendererProps) {
  switch (field.type) {
    case 'text': return <TextField field={field} value={value as string} onChange={onChange} />;
    case 'textarea': return <TextareaField field={field} value={value as string} onChange={onChange} />;
    case 'code': return <CodeField field={field} value={value as string} onChange={onChange} />;
    case 'number': return <NumberField field={field} value={value as number} onChange={v => onChange(v)} />;
    case 'select': return <SelectField field={field} value={value as string} onChange={onChange} />;
    case 'toggle': return <ToggleField field={field} value={value as boolean} onChange={onChange} />;
    case 'readonly': return <ReadonlyField field={field} value={value as string} />;
    case 'keyvalue': return <KeyValueField field={field} value={value as Array<{ key: string; value: string }>} onChange={v => onChange(v)} />;
    case 'tags': return <TagsField field={field} value={value as string[]} onChange={v => onChange(v)} />;
    case 'fieldlist': return <FieldListField field={field} value={value as Array<Record<string, unknown>>} onChange={v => onChange(v)} />;
    case 'identifier': return <IdentifierField field={field} value={value as string} onChange={onChange} />;
    case 'userselect': return <UserSelectField field={field} value={value as string | number} onChange={onChange} users={users ?? []} />;
    case 'kbdocuments': return <KbDocumentsField field={field} value={value as number[]} onChange={v => onChange(v)} kbDocuments={kbDocuments ?? []} />;
    case 'inputfieldlist': return <InputFieldListField field={field} value={value as InputFieldItem[]} onChange={v => onChange(v)} nestedErrors={nestedErrors} />;
    case 'templatetext': return <TemplateTextField field={field} value={value as string} onChange={onChange} />;
    case 'templatetextarea': return <TemplateTextareaField field={field} value={value as string} onChange={onChange} />;
    case 'emailtemplate': return <EmailTemplateField field={field} value={value as string} onChange={onChange} />;
    case 'branchlist': return <BranchListField field={field} value={value as BranchItem[]} onChange={v => onChange(v)} />;
    default: return null;
  }
}
