<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeArticle extends Model
{
    use HasFactory;

    protected $fillable = ['category_id', 'title', 'content', 'author_id', 'tags', 'view_count', 'like_count', 'status', 'published_at', 'summary', 'cover_image', 'content_type', 'file_path', 'file_name', 'file_size'];

    protected $casts = ['tags' => 'array', 'published_at' => 'datetime'];

    public function category(): BelongsTo { return $this->belongsTo(KnowledgeCategory::class); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'author_id'); }
}
