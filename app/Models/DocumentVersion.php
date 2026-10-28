<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVersion extends Model
{
    protected $fillable = ['document_id', 'label', 'sections'];

    protected $casts = ['sections' => 'array'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
