<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SkillTag extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'category', 'color', 'description', 'sort_order'];

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(EmployeeProfile::class, 'employee_skills')
            ->withPivot('proficiency')
            ->withTimestamps();
    }
}
