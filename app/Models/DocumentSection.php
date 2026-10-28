<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentSection extends Model
{
    protected $fillable = ['document_id', 'index', 'heading', 'level', 'body'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function highlights(): HasMany
    {
        return $this->hasMany(Highlight::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
