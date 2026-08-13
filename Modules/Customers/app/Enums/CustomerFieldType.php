<?php

namespace Modules\Customers\Enums;

/**
 * Deliberately independent of Modules\Workflows\Enums\NodeConfigFieldType, even though the
 * shape mirrors it (CustomerField mirrors NodeConfigField) — importing across modules here
 * would invert this module's one-way Workflows -> Customers dependency direction.
 */
enum CustomerFieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Select = 'select';
    case Toggle = 'toggle';
    case Number = 'number';
    case Date = 'date';
    case Email = 'email';
}
