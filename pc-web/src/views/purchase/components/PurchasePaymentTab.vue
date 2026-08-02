<template>
  <div v-loading="loadingPayments" class="tab-content">
    <el-empty v-if="!loadingPayments && paymentRequests.length === 0" description="该合同暂无付款申请" :image-size="100" />
    <el-collapse v-else v-model="activePayReqIdsModel">
      <el-collapse-item
        v-for="pr in paymentRequests"
        :key="pr.id"
        :name="pr.id"
      >
        <template #title>
          <div class="payreq-title">
            <span class="link-text">#{{ pr.id }} {{ pr.code }}</span>
            <el-tag size="small" :type="paymentStatusTagType(pr.status)">{{ pr.status }}</el-tag>
            <span class="payreq-amount">¥ {{ formatMoney(pr.amount) }}</span>
            <span class="payreq-stage">{{ pr.stage_label || pr.payment_type }}</span>
            <span class="payreq-date">{{ pr.request_date ? String(pr.request_date).slice(0, 10) : '' }}</span>
          </div>
        </template>

        <!-- 付款申请详情 -->
        <el-descriptions :column="3" border size="small">
          <el-descriptions-item label="编号">{{ pr.code }}</el-descriptions-item>
          <el-descriptions-item label="阶段">{{ pr.stage_label || pr.payment_type }}</el-descriptions-item>
          <el-descriptions-item label="金额">¥ {{ formatMoney(pr.amount) }}</el-descriptions-item>
          <el-descriptions-item label="申请人">{{ pr.applicant || '-' }}</el-descriptions-item>
          <el-descriptions-item label="申请时间">{{ pr.request_date || '-' }}</el-descriptions-item>
          <el-descriptions-item label="审批时间">{{ pr.approved_at || '-' }}</el-descriptions-item>
          <el-descriptions-item label="原因" :span="3">{{ pr.reason || '-' }}</el-descriptions-item>
        </el-descriptions>

        <!-- 付款凭证 -->
        <div class="section-title" style="margin-top:12px">
          付款凭证
          <el-upload
            :show-file-list="false"
            :before-upload="(f: Record<string, unknown>) => beforeUploadVoucher(f, pr.id)"
            :http-request="(opts: Record<string, unknown>) => $emit('upload-voucher', opts, pr.id)"
            accept=".pdf,.png,.jpeg,.jpg"
            style="display:inline-block;margin-left:8px"
          >
            <el-button size="small" type="primary" :icon="Upload" plain>上传凭证</el-button>
          </el-upload>
        </div>
        <el-table :data="pr._vouchers || []" stripe border size="small" empty-text="暂无凭证">
          <el-table-column prop="name" label="文件名" min-width="200" show-overflow-tooltip />
          <el-table-column prop="size_human" label="大小" width="100" align="center" />
          <el-table-column prop="uploaded_at" label="上传时间" width="160" align="center" />
          <el-table-column label="操作" width="200" fixed="right">
            <template #default="{ row }">
              <el-button link type="primary" size="small" @click="$emit('preview-voucher', row)">预览</el-button>
              <el-button link type="success" size="small" @click="$emit('download-voucher', row)">下载</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-collapse-item>
    </el-collapse>
  </div>
</template>

<script setup lang="ts">
import { Upload } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'

defineProps<{
  paymentRequests: Record<string, unknown>[]
  loadingPayments: boolean
}>()

defineEmits<{
  (e: 'upload-voucher', opts: Record<string, unknown>, prId: number): void
  (e: 'preview-voucher', row: Record<string, unknown>): void
  (e: 'download-voucher', row: Record<string, unknown>): void
}>()

const activePayReqIdsModel = defineModel<number[]>('activePayReqIds', { default: () => [] })

const beforeUploadVoucher = (file: { size: number }, _prId: number) => {
  const maxSize = 20 * 1024 * 1024
  if (file.size > maxSize) {
    ElMessage.error('文件大小不能超过 20MB')
    return false
  }
  return true
}

const formatMoney = (n: number) => Number(n || 0).toLocaleString('zh-CN', { maximumFractionDigits: 2 })
const PAYMENT_STATUS_TYPES: Record<string, string> = { pending: 'warning', approved: 'success', paid: 'success', rejected: 'danger' }
const paymentStatusTagType = (s: string): string => PAYMENT_STATUS_TYPES[s] || 'info'
</script>

<style scoped>
.tab-content { padding: 8px 4px; }
.payreq-title {
  display: flex; align-items: center; gap: 12px;
  .payreq-amount { color: #1D9E75; font-weight: 600; }
  .payreq-stage { color: #909399; font-size: 12px; }
  .payreq-date { color: #909399; font-size: 12px; }
}
.link-text { color: #0C447C; cursor: pointer; font-weight: 500; }
.section-title {
  font-size: 14px; font-weight: 600; color: #303133; margin-bottom: 8px;
  padding-left: 8px; border-left: 3px solid #0C447C;
  display: flex; align-items: center; gap: 8px;
}
</style>
