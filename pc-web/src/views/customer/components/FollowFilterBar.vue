<script setup lang="ts">
/**
 * 跟进日历筛选条
 */
import { Refresh } from '@element-plus/icons-vue'

defineProps<{
  filterMonth: string
  filterUser: number | null
  filterCustomer: number | null
  userOptions: { id: number; name: string }[]
  customerOptions: { id: number; name: string }[]
}>()

const emit = defineEmits<{
  (e: 'update:filterMonth', v: string): void
  (e: 'update:filterUser', v: number | null): void
  (e: 'update:filterCustomer', v: number | null): void
  (e: 'refresh'): void
}>()
</script>

<template>
  <div class="filter-bar glass">
    <el-form :inline="true">
      <el-form-item label="月份">
        <el-date-picker
          :model-value="filterMonth"
          type="month"
          placeholder="选择月份"
          value-format="YYYY-MM"
          style="width: 160px"
          @update:model-value="emit('update:filterMonth', $event as string)"
          @change="emit('refresh')"
        />
      </el-form-item>
      <el-form-item label="跟进人">
        <el-select
          :model-value="filterUser"
          placeholder="全部"
          clearable
          style="width: 160px"
          @update:model-value="emit('update:filterUser', $event)"
          @change="emit('refresh')"
        >
          <el-option v-for="u in userOptions" :key="u.id" :label="u.name" :value="u.id" />
        </el-select>
      </el-form-item>
      <el-form-item label="客户">
        <el-select
          :model-value="filterCustomer"
          placeholder="全部"
          clearable
          filterable
          style="width: 200px"
          @update:model-value="emit('update:filterCustomer', $event)"
          @change="emit('refresh')"
        >
          <el-option v-for="c in customerOptions" :key="c.id" :label="c.name" :value="c.id" />
        </el-select>
      </el-form-item>
      <el-form-item>
        <el-button :icon="Refresh" plain @click="emit('refresh')">刷新</el-button>
      </el-form-item>
    </el-form>
  </div>
</template>

<style lang="scss" scoped>
.filter-bar {
  background: rgba(255, 255, 255, 0.7);
  backdrop-filter: blur(20px);
  border: 1px solid rgba(255, 255, 255, 0.5);
  border-radius: 12px;
  padding: 16px 20px;
  margin-bottom: 14px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}
</style>
