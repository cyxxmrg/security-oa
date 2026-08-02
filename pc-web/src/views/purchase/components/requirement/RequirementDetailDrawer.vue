<template>
  <el-drawer
    v-model="visible"
    title="采购需求详情"
    size="780px"
    direction="rtl"
    destroy-on-close
  >
    <div v-if="item" class="detail-content">
      <div class="detail-header">
        <div class="detail-code">{{ item.code }}</div>
        <el-tag :type="statusTagType(item.status)" effect="dark" size="default">
          {{ statusLabel(item.status) }}
        </el-tag>
      </div>

      <!-- V0.6.3 8 步进度条 + 流转历史 -->
      <PurchaseFlowStepper entity-type="requirement" :entity-status="item.status" />
      <PurchaseFlowHistory entity-type="requirements" :entity-id="item.id" />

      <el-descriptions :column="2" border>
        <el-descriptions-item label="需求编号">{{ item.code }}</el-descriptions-item>
        <el-descriptions-item label="优先级">
          <el-tag :type="priorityTagType(item.priority)" effect="dark" size="small">
            {{ priorityLabel(item.priority) }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="关联项目" :span="2">
          <span class="link-text" @click="emit('viewProject', item)">{{ item.project_name }}</span>
        </el-descriptions-item>
        <el-descriptions-item label="需求日期">{{ formatDate(item.need_date) }}</el-descriptions-item>
        <el-descriptions-item label="发起时间">{{ formatDate(item.created_at) }}</el-descriptions-item>
        <el-descriptions-item label="发起人">{{ item.creator }}</el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="statusTagType(item.status)" effect="plain" size="small">
            {{ statusLabel(item.status) }}
          </el-tag>
        </el-descriptions-item>
      </el-descriptions>

      <h4 class="sub-title">需求物资明细</h4>
      <el-table :data="parseMaterials(item)" border size="default">
        <el-table-column type="index" label="#" width="50" align="center" />
        <el-table-column prop="name" label="物资名称" min-width="180" />
        <el-table-column prop="spec" label="规格" width="160">
          <template #default="{ row }">{{ row.spec || '-' }}</template>
        </el-table-column>
        <el-table-column prop="quantity" label="数量" width="100" align="right" />
        <el-table-column prop="unit" label="单位" width="80" align="center" />
      </el-table>

      <h4 class="sub-title">备注</h4>
      <div class="remark-box">{{ item.remark || '无' }}</div>

      <h4 class="sub-title" v-if="item.review_remark">审核意见</h4>
      <div class="remark-box" v-if="item.review_remark">
        <el-tag
          :type="item.status === 'rejected' ? 'danger' : 'success'"
          size="small"
          effect="light"
          style="margin-right: 8px"
        >{{ statusLabel(item.status) }}</el-tag>
        {{ item.review_remark }}
      </div>
    </div>

    <template #footer>
      <el-button @click="visible = false">关闭</el-button>
      <el-button
        v-if="item && item.status === 'pending'"
        type="warning"
        :icon="Edit"
        @click="emit('edit')"
      >编辑</el-button>
    </template>
  </el-drawer>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Edit } from '@element-plus/icons-vue'
import type { Requirement } from './types'
import { formatDate, statusLabel, statusTagType, priorityLabel, priorityTagType, parseMaterials } from './types'
import PurchaseFlowStepper from '@/components/PurchaseFlowStepper.vue'
import PurchaseFlowHistory from '@/components/PurchaseFlowHistory.vue'

// v0.3.23 抽自 purchase/Requirement.vue:242-310
const props = defineProps<{
  modelValue: boolean
  item: Requirement | null
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
  (e: 'viewProject', row: Requirement): void
  (e: 'edit'): void
}>()

const visible = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})
</script>

<style lang="scss" scoped>
.detail-content { padding: 0 4px; }
.detail-header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 16px; padding-bottom: 12px;
  border-bottom: 1px solid #ebeef5;
  .detail-code { font-size: 18px; font-weight: 700; color: #0C447C; }
}
.sub-title {
  font-size: 14px; font-weight: 600; color: #303133;
  margin: 20px 0 12px;
  padding-left: 8px; border-left: 3px solid #0C447C;
}
.remark-box {
  padding: 12px; background: #fafbfc; border-radius: 4px;
  font-size: 13px; color: #606266; line-height: 1.6;
  border: 1px solid #ebeef5;
}
.link-text {
  color: #0C447C; cursor: pointer; font-weight: 500;
  &:hover { text-decoration: underline; }
}
</style>
