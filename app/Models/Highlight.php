<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Highlight berwarna dari AI: teks bermasalah + saran revisi.
 * status: pending | accepted | rejected
 */
class Highlight extends Model
{
    protected $fillable = [
        'document_id', 'document_section_id', 'paragraph_index', 'quote',
        'type', 'severity', 'color', 'comment', 'suggestion', 'replacement', 'status',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(DocumentSection::class, 'document_section_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
