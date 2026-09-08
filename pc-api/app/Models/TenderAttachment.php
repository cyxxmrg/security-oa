<?php

namespace App\Models;

use App\Concerns\HasDataScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenderAttachment extends Model
{
    use HasDataScope;

    protected $table = 'tender_attachments';
    protected $fillable = [
        'tender_project_id', 'tender_bid_id',
        'uploaded_by_user_id', 'uploaded_by_supplier_id',
        'file_name', 'file_path', 'mime_type', 'file_size',
        'category', 'visibility',
    ];

    public function project(): BelongsTo { return $this->belongsTo(TenderProject::class, 'tender_project_id'); }
    public function bid(): BelongsTo { return $this->belongsTo(TenderBid::class, 'tender_bid_id'); }
    public function uploadedBy(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by_user_id'); }
    public function uploadedBySupplier(): BelongsTo { return $this->belongsTo(Supplier::class, 'uploaded_by_supplier_id'); }

    public function getUrlAttribute(): string
    {
        // 合并结论 (origin/main 版): 统一返回鉴权下载 URL —
        // 1) 供应商门户不消费本 accessor (PortalController::tenderByToken 自行构造
        //    /api/portal/t/{token}/attachments/{id} 的公开 URL);
        // 2) 业务端 (useTenderDetail/privateFile) 用 Bearer fetch 打开, 需要 /api 路径;
        // 3) 该路径与两侧路由块注册的 URI 一致, 不依赖命名路由 tenders.attachments.download 是否存在。
        return url("/api/tenders/{$this->tender_project_id}/attachments/{$this->id}/download");
    }
}
