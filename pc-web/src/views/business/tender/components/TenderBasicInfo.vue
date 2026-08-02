<template>
  <div>
    <el-descriptions :column="3" border>
      <el-descriptions-item label="编号">{{ detail?.code }}</el-descriptions-item>
      <el-descriptions-item label="类型">{{ typeLabel(detail?.type) }}</el-descriptions-item>
      <el-descriptions-item label="创建人">{{ detail?.creator?.name || '-' }}</el-descriptions-item>
      <el-descriptions-item label="关联项目">{{ detail?.project?.name || '-' }}</el-descriptions-item>
      <el-descriptions-item label="截标时间">{{ fmt(detail?.deadline) }}</el-descriptions-item>
      <el-descriptions-item label="开标时间">{{ fmt(detail?.open_at) }}</el-descriptions-item>
      <el-descriptions-item label="发布时间">{{ fmt(detail?.publish_at) }}</el-descriptions-item>
      <el-descriptions-item label="中标时间">{{ fmt(detail?.awarded_at) }}</el-descriptions-item>
      <el-descriptions-item label="中标供应商">{{ detail?.awardedSupplier?.name || '-' }}</el-descriptions-item>
      <el-descriptions-item label="公开链接" :span="3">
        <el-input v-if="detail?.public_token" :model-value="publicUrl" readonly>
          <template #append>
            <el-button :icon="CopyDocument" @click="$emit('copy-url')">复制</el-button>
          </template>
        </el-input>
        <span v-else class="muted">未发布</span>
      </el-descriptions-item>
      <el-descriptions-item label="说明" :span="3">{{ detail?.description || '-' }}</el-descriptions-item>
    </el-descriptions>

    <h4 class="block-title">必购清单</h4>
    <el-table :data="detail?.required_items || []" border size="small" empty-text="无必购项">
      <el-table-column prop="name" label="物料/服务" min-width="200" />
      <el-table-column prop="spec" label="规格" width="160" />
      <el-table-column prop="qty" label="数量" width="100" align="right" />
      <el-table-column prop="unit" label="单位" width="80" />
    </el-table>
  </div>
</template>

<script setup lang="ts">
import { CopyDocument } from '@element-plus/icons-vue'
import type { TenderProject } from '@/api/tender'
import { typeLabel, fmt } from '../utils'

defineProps<{
  detail: TenderProject | null
  publicUrl: string
}>()

defineEmits<{
  'copy-url': []
}>()
</script>

<style scoped>
.muted { color: #999; }
.block-title { margin: 16px 0 8px; font-size: 14px; font-weight: 600; }
</style>
