<?php

namespace Modules\KnowledgeBase\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Auth\Models\Tenant;

class Tag extends Model
{
    protected $fillable = [
        'name',
        'tenant_id',
    ];

    /**
     * Get the tenant that owns the tag.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the documents that use this tag.
     */
    public function documents()
    {
        return $this->belongsToMany(Document::class, 'document_tag')->withTimestamps();
    }
}
