<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepairStepPhoto extends Model
{
    protected $table = 'repair_step_photos';
    public $timestamps = false;

    protected $fillable = [
        'target_type', 'target_id', 'step', 'file_path', 'file_name',
        'file_type', 'file_size', 'description', 'uploaded_by', 'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'file_size'   => 'integer',
    ];

    public const STEPS = [
        'diagnose' => '🔍 诊断',
        'disassemble' => '🔧 拆机',
        'replace'    => '🔄 换件',
        'debug'      => '⚙️ 调试',
        'power_on'   => '⚡ 通电',
        'test'       => '✅ 测试',
        'package'    => '📦 包装',
        'other'      => '📌 其他',
    ];
}
