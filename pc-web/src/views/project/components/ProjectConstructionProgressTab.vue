<template>
  <div class="construction-progress-tab">
    <el-empty
      v-if="processInstances.length === 0"
      :description="`该项目暂无工序数据, 可在「施工 → 工序实例」中维护`"
    >
      <el-button
        type="primary"
        :icon="Connection"
        @click="goConstructionProcessList"
      >前往「施工 → 工序实例」</el-button>
    </el-empty>

    <div v-else class="progress-quick">
      <el-alert
        title="工序属于现场施工管理, 详细操作已迁移到「施工」菜单"
        type="info"
        :closable="false"
        show-icon
        style="margin-bottom: 16px"
      />
      <!-- V0.4.9 A1: 真实甘特图 -->
      <MiniGantt :instances="processInstances" />

      <el-row :gutter="16" style="margin-top: 16px">
        <el-col :span="8">
          <el-statistic title="工序实例总数" :value="processInstances.length" />
        </el-col>
        <el-col :span="8">
          <el-statistic
            title="已完成"
            :value="processInstances.filter(i => i.status === 'completed' || i.status === 'accepted').length"
            :value-style="{ color: '#1D9E75' }"
          />
        </el-col>
        <el-col :span="8">
          <el-statistic
            title="进行中"
            :value="processInstances.filter(i => i.status === 'in_progress' || i.status === 'pending').length"
            :value-style="{ color: '#BA7517' }"
          />
        </el-col>
      </el-row>
      <div class="progress-actions" style="margin-top: 24px">
        <el-button
          type="primary"
          :icon="Connection"
          @click="goConstructionProcessList"
        >前往「施工 → 工序实例」</el-button>
        <el-button
          :icon="View"
          @click="goConstructionInspections"
        >查看验收记录</el-button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Connection, View } from '@element-plus/icons-vue'
import MiniGantt from '@/components/MiniGantt.vue'
import type { ProcessInstance } from '../types'

const props = defineProps<{
  processInstances: ProcessInstance[]
  projectId: number
}>()

const router = useRouter()

const goConstructionProcessList = () => {
  if (!props.projectId) {
    ElMessage.warning('项目 ID 缺失')
    return
  }
  router.push({
    path: '/construction/process/instances',
    query: { project_id: String(props.projectId) },
  })
}

const goConstructionInspections = () => {
  if (!props.projectId) {
    ElMessage.warning('项目 ID 缺失')
    return
  }
  router.push({
    path: '/construction/process/inspections',
    query: { project_id: String(props.projectId) },
  })
}
</script>

<style scoped>
.construction-progress-tab {
  padding: 24px 0;
}
.progress-quick {
  max-width: 720px;
  margin: 0 auto;
}
.progress-actions {
  display: flex;
  gap: 12px;
  justify-content: center;
}
</style>
