<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMaterial extends Model
{
    use HasFactory;

    protected $fillable = ['project_id', 'material_name', 'specification', 'quantity', 'unit', 'unit_cost', 'total_cost', 'used_by', 'use_date', 'inventory_item_id', 'notes'];

    protected $casts = ['quantity' => 'decimal:2', 'unit_cost' => 'decimal:2', 'total_cost' => 'decimal:2', 'use_date' => 'date'];

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function usedByUser(): BelongsTo { return $this->belongsTo(User::class, 'used_by'); }
}
