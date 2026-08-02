<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'parent_id', 'manager_id', 'sort_order', 'status', 'description'];

    protected $casts = ['status' => 'string'];

    public function parent(): BelongsTo { return $this->belongsTo(Department::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(Department::class, 'parent_id'); }
    public function manager(): BelongsTo { return $this->belongsTo(User::class, 'manager_id'); }
    public function positions(): HasMany { return $this->hasMany(Position::class); }
    public function users(): HasMany { return $this->hasMany(User::class); }
}
