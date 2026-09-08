# -*- coding: utf-8 -*-
# Resolve 3 conflict hunks in PurchaseFlowService.php by line range replacement.
import io

PATH = r"D:\work\website\OA\pc-api\app\Services\PurchaseFlowService.php"
with io.open(PATH, "r", encoding="utf-8") as f:
    lines = f.readlines()

# sanity: markers at expected positions (1-based)
for ln, exp in [(1161, "<<<<<<< HEAD"), (1197, "======="), (1229, ">>>>>>> origin/main"),
                (1253, "<<<<<<< HEAD"), (1257, "======="), (1260, ">>>>>>> origin/main"),
                (1269, "<<<<<<< HEAD"), (1306, "======="), (1338, ">>>>>>> origin/main")]:
    actual = lines[ln-1].rstrip("\r\n")
    assert actual == exp, f"marker mismatch at line {ln}: {actual!r} != {exp!r}"

R1 = """    /** 上传合同文件 */
    public function uploadContractFile(int $contractId, \\Illuminate\\Http\\UploadedFile $file, ?User $user = null): PurchaseContractFile
    {
        // 合规 (audit-2026-06-28 C3): 合同文件走 FileUploadService, 强制真实 MIME 白名单, 落盘私有存储 (disk=local, private/ 前缀)
        // 本地增强: 事务内行锁 + 合同可编辑校验; 任一环节失败清理已落盘文件
        $storedPath = null;
        try {
            $uploader = app(FileUploadService::class);
            $fakeReq = \\Illuminate\\Http\\Request::create('/', 'POST', [], [], ['file' => $file]);
            $result = $uploader->store($fakeReq, 'file', [
                'disk'         => 'local',
                'subdir'       => "private/purchase/contracts/{$contractId}",
                'allowed_ext'  => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'],
                'allowed_mime' => ['application/pdf', 'image/jpeg', 'image/png',
                    'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
                'max_size'     => 20480,
            ]);
            $storedPath = $result['path'];

            return DB::transaction(function () use ($contractId, $result, $user) {
                $contract = PurchaseContract::lockForUpdate()->findOrFail($contractId);
                $this->assertContractEditable($contract);
                $record = PurchaseContractFile::create([
                    'contract_id' => $contractId,
                    'file_path'   => $result['path'],
                    'file_name'   => $result['original_name'],
                    'mime'        => $result['mime'],
                    'size'        => $result['size'],
                    'uploaded_by' => $user?->id,
                    'uploaded_at' => now(),
                ]);
                $this->log(self::ENTITY_CONTRACT, $contractId, null, 'upload_file', 'upload_file', $user, "上传附件: {$record->file_name} (" . round($record->size / 1024, 1) . " KB)");
                return $record;
            });
        } catch (\\Throwable $e) {
            if ($storedPath) {
                \\App\\Support\\PrivateFileStorage::delete($storedPath);
            }
            throw $e;
        }
    }
"""

R2 = """            $contract = PurchaseContract::lockForUpdate()->findOrFail($contractId);
            $this->assertContractEditable($contract);
            $f = PurchaseContractFile::where('contract_id', $contractId)->where('id', $fileId)->lockForUpdate()->firstOrFail();
            \\App\\Support\\PrivateFileStorage::delete($f->file_path);
"""

R3 = """    /** 上传付款凭证 */
    public function uploadPaymentVoucher(int $paymentRequestId, \\Illuminate\\Http\\UploadedFile $file, ?User $user = null, ?string $remark = null): PurchasePaymentVoucher
    {
        // 合规 (audit-2026-06-28 C4): 付款凭证 (资金凭证) 走 FileUploadService, 强制真实 MIME 白名单, 落盘私有存储 (disk=local, private/ 前缀)
        // 本地增强: 事务内行锁 + 仅已审批/已付款可传凭证; 任一环节失败清理已落盘文件
        $storedPath = null;
        try {
            $uploader = app(FileUploadService::class);
            $fakeReq = \\Illuminate\\Http\\Request::create('/', 'POST', [], [], ['file' => $file]);
            $result = $uploader->store($fakeReq, 'file', [
                'disk'         => 'local',
                'subdir'       => "private/purchase/vouchers/{$paymentRequestId}",
                'allowed_ext'  => ['pdf', 'jpg', 'jpeg', 'png'],
                'allowed_mime' => ['application/pdf', 'image/jpeg', 'image/png'],
                'max_size'     => 10240,
            ]);
            $storedPath = $result['path'];

            return DB::transaction(function () use ($paymentRequestId, $result, $user, $remark) {
                $pr = PurchasePaymentRequest::lockForUpdate()->findOrFail($paymentRequestId);
                if (!in_array($pr->status, [self::STATUS_PAYREQ_APPROVED, self::STATUS_PAYREQ_PAID], true)) {
                    throw new \\RuntimeException('只有已审批或已付款的申请可以上传付款凭证');
                }
                PurchaseContract::findOrFail($pr->contract_id);

                $record = PurchasePaymentVoucher::create([
                    'payment_request_id' => $paymentRequestId,
                    'file_path'   => $result['path'],
                    'file_name'   => $result['original_name'],
                    'mime'        => $result['mime'],
                    'size'        => $result['size'],
                    'uploaded_by' => $user?->id,
                    'uploaded_at' => now(),
                    'remark'      => $remark,
                ]);
                $this->log(self::ENTITY_PAYMENT_REQ, $paymentRequestId, null, 'upload_voucher', 'upload_voucher', $user, "上传凭证: {$record->file_name}");
                return $record;
            });
        } catch (\\Throwable $e) {
            if ($storedPath) {
                \\App\\Support\\PrivateFileStorage::delete($storedPath);
            }
            throw $e;
        }
    }
"""

# Regions are inclusive 1-based line ranges to REPLACE entirely (markers + both sides + shared closing brace).
regions = [(1161, 1230, R1), (1253, 1260, R2), (1269, 1339, R3)]

out = []
cursor = 0  # 0-based index into lines
for (s, e, text) in regions:
    out.extend(lines[cursor:s-1])
    out.append(text)
    cursor = e
out.extend(lines[cursor:])

with io.open(PATH, "w", encoding="utf-8", newline="") as f:
    f.writelines(out)

print("done, wrote", len(out), "lines")
