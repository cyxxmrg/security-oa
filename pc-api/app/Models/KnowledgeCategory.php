<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeCategory extends Model
{
    use HasFactory;

    protected $fillable = ['parent_id', 'name', 'icon', 'sort_order', 'description'];

    public function parent(): BelongsTo { return $this->belongsTo(KnowledgeCategory::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(KnowledgeCategory::class, 'parent_id'); }
    public function articles(): HasMany { return $this->hasMany(KnowledgeArticle::class, 'category_id'); }
}
