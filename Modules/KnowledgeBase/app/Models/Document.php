<?php

namespace Modules\KnowledgeBase\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Auth\Models\Tenant;

class Document extends Model
{
    protected $fillable = [
        'tenant_id',
        'title',
        'document_type_id',
        'file_path',
        'is_active',
        'index_status',
        'index_error',
        'chunks_count',
    ];

    protected $casts = [
        'is_active' => 'bool',
        'chunks_count' => 'integer',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'document_tag')->withTimestamps();
    }
}
