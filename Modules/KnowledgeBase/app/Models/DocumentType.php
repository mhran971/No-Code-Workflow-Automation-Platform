<?php

namespace Modules\KnowledgeBase\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    protected $fillable = [
        'name',
        'description',
    ];
}
