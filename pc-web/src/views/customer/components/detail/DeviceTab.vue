<template>
  <div class="tab-content">
    <el-table :data="devices" border empty-text="暂无设备">
      <el-table-column type="index" label="#" width="55" align="center" />
      <el-table-column prop="device_name" label="设备名称" min-width="180" />
      <el-table-column prop="device_type" label="类型" width="120" align="center" />
      <el-table-column prop="brand" label="品牌" width="100" />
      <el-table-column prop="model" label="型号" width="120" />
      <el-table-column prop="serial_number" label="序列号" width="160" />
      <el-table-column prop="install_location" label="安装位置" width="140" />
      <el-table-column prop="install_date" label="安装日期" width="120" />
      <el-table-column prop="status" label="状态" width="100" align="center">
        <template #default="{ row }">
          <el-tag :type="deviceStatusType(row.status)" size="small" effect="light">
            {{ row.status }}
          </el-tag>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>

<script setup lang="ts">
import type { Device } from './types'
import { deviceStatusType } from './types'

// v0.3.20 抽自 customer/Detail.vue:155-176
defineProps<{
  devices: Device[]
}>()
</script>
