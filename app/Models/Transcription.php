<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transcription extends Model
{
    protected $fillable = ['document_id', 'audio_path', 'text', 'duration'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
