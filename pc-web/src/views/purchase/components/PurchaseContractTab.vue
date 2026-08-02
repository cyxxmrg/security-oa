<template>
  <div v-if="!contract" v-loading="loadingContract" class="empty-hint">
    <el-empty description="该订单尚未创建合同" :image-size="100" />
  </div>
  <div v-else v-loading="loadingContract" class="tab-content">
    <!-- 合同基本信息 -->
    <div class="section-title">合同基本信息</div>
    <el-descriptions :column="3" border>
      <el-descriptions-item label="合同编号">{{ contract.code }}</el-descriptions-item>
      <el-descriptions-item label="状态">
        <el-tag size="small" :type="contract.status === 'signed' || contract.status === 'effective' ? 'success' : 'info'">{{ contract.status }}</el-tag>
      </el-descriptions-item>
      <el-descriptions-item label="金额">¥ {{ formatMoney(contract.total_amount) }}</el-descriptions-item>
      <el-descriptions-item label="签订日期">{{ contract.signed_at || '-' }}</el-descriptions-item>
      <el-descriptions-item label="生效日期">{{ contract.start_date || '-' }}</el-descriptions-item>
      <el-descriptions-item label="截止日期">{{ contract.end_date || '-' }}</el-descriptions-item>
      <el-descriptions-item label="付款条款" :span="2">{{ contract.payment_terms || '-' }}</el-descriptions-item>
      <el-descriptions-item label="收货地址">{{ contract.delivery_address || '-' }}</el-descriptions-item>
    </el-descriptions>

    <!-- 合同清单 -->
    <div class="section-title" style="margin-top:16px">
      合同清单
      <el-button size="small" type="primary" :icon="Plus" plain style="margin-left:8px" @click="$emit('add-contract-item')">添加行</el-button>
      <el-button size="small" :icon="Refresh" plain @click="$emit('load-contract-items')">刷新</el-button>
    </div>
    <el-table :data="contractItems" stripe border size="small">
      <el-table-column prop="material" label="物料" min-width="160" />
      <el-table-column prop="spec" label="规格" min-width="120" show-overflow-tooltip />
      <el-table-column prop="qty" label="数量" width="90" align="right" />
      <el-table-column prop="unit" label="单位" width="70" align="center" />
      <el-table-column label="单价" width="120" align="right">
        <template #default="{ row }">
          <el-input-number
            v-if="editingItemId === row.id"
            v-model="row.unit_price"
            :min="0"
            :precision="2"
            :step="10"
            size="small"
            style="width:100px"
          />
          <span v-else>¥ {{ formatMoney(row.unit_price) }}</span>
        </template>
      </el-table-column>
      <el-table-column label="小计" width="120" align="right">
        <template #default="{ row }">¥ {{ formatMoney(row.subtotal) }}</template>
      </el-table-column>
      <el-table-column prop="remark" label="备注" min-width="120" show-overflow-tooltip />
      <el-table-column label="操作" width="170" fixed="right">
        <template #default="{ row }">
          <template v-if="editingItemId === row.id">
            <el-button link type="success" size="small" @click="$emit('save-contract-item', row)">保存</el-button>
            <el-button link type="info" size="small" @click="$emit('cancel-edit-item')">取消</el-button>
          </template>
          <template v-else>
            <el-button link type="primary" size="small" @click="$emit('edit-item', row.id)">编辑</el-button>
            <el-button link type="danger" size="small" @click="$emit('delete-contract-item', row)">删除</el-button>
          </template>
        </template>
      </el-table-column>
    </el-table>

    <!-- 合同附件 -->
    <div class="section-title" style="margin-top:16px">
      合同附件 (PDF)
      <el-upload
        :show-file-list="false"
        :before-upload="beforeUpload"
        :http-request="handleUploadRequest"
        accept=".pdf,.png,.jpeg,.jpg,.doc,.docx"
        style="display:inline-block;margin-left:8px"
      >
        <el-button size="small" type="primary" :icon="Upload" plain>上传附件</el-button>
      </el-upload>
    </div>
    <el-table :data="contractFiles" stripe border size="small">
      <el-table-column prop="name" label="文件名" min-width="200" show-overflow-tooltip />
      <el-table-column prop="size_human" label="大小" width="100" align="center" />
      <el-table-column prop="mime" label="类型" width="120" align="center" />
      <el-table-column prop="uploaded_at" label="上传时间" width="160" align="center" />
      <el-table-column label="操作" width="220" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" size="small" @click="$emit('preview-file', row)">预览</el-button>
          <el-button link type="success" size="small" @click="$emit('download-file', row)">下载</el-button>
          <el-button link type="danger" size="small" @click="$emit('delete-contract-file', row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>

<script setup lang="ts">
import { Plus, Refresh, Upload } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import type { PurchaseContract, PurchaseContractFile, PurchaseItem } from '../types'

defineProps<{
  contract: PurchaseContract | null
  contractItems: PurchaseItem[]
  contractFiles: PurchaseContractFile[]
  loadingContract: boolean
  editingItemId: number | null
}>()

const emit = defineEmits<{
  'add-contract-item': []
  'load-contract-items': []
  'save-contract-item': [row: PurchaseItem]
  'cancel-edit-item': []
  'edit-item': [id: number]
  'delete-contract-item': [row: PurchaseItem]
  'upload-contract-file': [opts: Record<string, unknown>]
  'preview-file': [row: PurchaseContractFile]
  'download-file': [row: PurchaseContractFile]
  'delete-contract-file': [row: PurchaseContractFile]
}>()

const beforeUpload = (file: { size: number }) => {
  const maxSize = 20 * 1024 * 1024
  if (file.size > maxSize) {
    ElMessage.error('文件大小不能超过 20MB')
    return false
  }
  return true
}
const handleUploadRequest = (opts: Record<string, unknown>) => emit('upload-contract-file', opts)

const formatMoney = (n: number) => Number(n || 0).toLocaleString('zh-CN', { maximumFractionDigits: 2 })
</script>

<style scoped>
.tab-content { padding: 8px 4px; }
.empty-hint { padding: 30px 0; text-align: center; }
.section-title {
  font-size: 14px; font-weight: 600; color: #303133; margin-bottom: 8px;
  padding-left: 8px; border-left: 3px solid #0C447C;
  display: flex; align-items: center; gap: 8px;
}
</style>
