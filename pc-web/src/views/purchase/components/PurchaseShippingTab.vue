<template>
  <div v-loading="loadingShipping" class="tab-content">
    <el-empty v-if="!loadingShipping && shippingList.length === 0" description="该合同暂无发货记录" :image-size="100" />
    <template v-else>
      <!-- 发货预期 + 快递单号 (按合同清单行拆分) -->
      <div class="section-title">
        发货预期 / 快递单号
        <el-button size="small" type="primary" :icon="Plus" plain style="margin-left:8px" @click="$emit('add-shipping')">添加</el-button>
        <el-button size="small" :icon="Refresh" plain @click="$emit('load-shipping')">刷新</el-button>
      </div>
      <el-table :data="shippingList" stripe border size="small">
        <el-table-column prop="item_label" label="物料 / 范围" min-width="180" show-overflow-tooltip />
        <el-table-column label="预计发货" width="120" align="center">
          <template #default="{ row }">{{ row.expected_at || '-' }}</template>
        </el-table-column>
        <el-table-column label="实际发货" width="120" align="center">
          <template #default="{ row }">{{ row.shipped_at || '-' }}</template>
        </el-table-column>
        <el-table-column prop="carrier" label="物流公司" width="120" align="center" />
        <el-table-column prop="tracking_no" label="快递单号" width="160" />
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag size="small" :type="shippingStatusType(row.status)">{{ row.status }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="remark" label="备注" min-width="120" show-overflow-tooltip />
      </el-table>
    </template>
  </div>
</template>

<script setup lang="ts">
import { Plus, Refresh } from '@element-plus/icons-vue'

defineProps<{
  shippingList: Record<string, unknown>[]
  loadingShipping: boolean
}>()

defineEmits<{
  (e: 'add-shipping'): void
  (e: 'load-shipping'): void
}>()

const SHIP_STATUS_TYPES: Record<string, string> = { planned: 'info', shipped: 'warning', in_transit: 'warning', arrived: 'success', received: 'success' }
const shippingStatusType = (s: string): string => SHIP_STATUS_TYPES[s] || ''
</script>

<style scoped>
.tab-content { padding: 8px 4px; }
.section-title {
  font-size: 14px; font-weight: 600; color: #303133; margin-bottom: 8px;
  padding-left: 8px; border-left: 3px solid #0C447C;
  display: flex; align-items: center; gap: 8px;
}
</style>
