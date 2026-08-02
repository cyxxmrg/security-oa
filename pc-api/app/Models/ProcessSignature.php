<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessSignature extends Model
{
    use HasFactory;

    const SIGNER_CONTRACTOR = 'contractor';
    const SIGNER_OWNER      = 'owner';
    const SIGNER_SUPERVISOR = 'supervisor';
    const SIGNER_INSPECTOR  = 'inspector';

    protected $fillable = [
        'process_instance_id', 'inspection_id', 'signer_type', 'signer_id',
        'signer_name', 'signer_phone', 'signer_role', 'signature_data', 'signature_image_path',
        'ip_address', 'user_agent', 'signed_at', 'expires_at',
        'verification_code', 'is_verified', 'hash',
    ];

    protected $casts = [
        'signed_at'   => 'datetime',
        'expires_at'  => 'datetime',
        'is_verified' => 'boolean',
    ];

    public function processInstance(): BelongsTo { return $this->belongsTo(ProcessInstance::class); }
    public function inspection(): BelongsTo { return $this->belongsTo(ProcessInspection::class, 'inspection_id'); }
    public function signer(): BelongsTo { return $this->belongsTo(User::class, 'signer_id'); }

    public static function makeHash(array $payload): string
    {
        ksort($payload);
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE));
    }
}
