<?php

namespace Modules\Workflows\Enums;

enum NodeConfigFieldType: string
{
    case TEXT = 'text';
    case TEXTAREA = 'textarea';
    case SELECT = 'select';
    case TOGGLE = 'toggle';
    case NUMBER = 'number';
    case TAGS = 'tags';
    case JSON = 'json';
    case EMAIL = 'email';
    case BRANCHES = 'branches';
    case READONLY = 'readonly';
    case FIELD_REFERENCE = 'field_reference';
}
