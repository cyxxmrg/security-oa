<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceSerialNumber extends Model
{
    use HasFactory;

    protected $fillable = ['inventory_item_id', 'serial_number', 'status', 'project_id', 'customer_device_id', 'stock_record_id', 'install_date', 'notes'];

    protected $casts = ['install_date' => 'date'];

    public function inventoryItem(): BelongsTo { return $this->belongsTo(InventoryItem::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function customerDevice(): BelongsTo { return $this->belongsTo(CustomerDevice::class); }
}
