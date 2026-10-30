<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    protected $fillable = [
        'user_id', 'title', 'type', 'status', 'source_path', 'review_summary',
    ];

    protected $casts = ['review_summary' => 'array'];

    public function sections(): HasMany
    {
        return $this->hasMany(DocumentSection::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function highlights(): HasMany
    {
        return $this->hasMany(Highlight::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

}
