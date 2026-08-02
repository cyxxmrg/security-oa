<template>
  <div class="page-container">
    <div class="page-header">
      <div class="title-area">
        <span class="page-title">项目看板</span>
        <el-tag effect="light" type="info">{{ list.length }} 个项目</el-tag>
        <el-tag v-if="draggingId" type="warning" effect="dark" size="small">拖拽中…</el-tag>
      </div>
      <div class="header-actions">
        <el-button :icon="Refresh" @click="loadList">刷新</el-button>
        <el-button type="primary" :icon="Plus" @click="$router.push('/project/create')">新建项目</el-button>
      </div>
    </div>

    <div class="board-wrapper">
      <div class="board-canvas">
        <div
          v-for="col in stageColumns"
          :key="col.value"
          class="board-column"
          :class="{ 'is-drop-target': dragOverCol === col.value }"
          @dragover.prevent="onDragOver(col.value, $event)"
          @dragleave="onDragLeave(col.value)"
          @drop="onDrop(col.value)"
        >
          <div class="column-header" :style="{ background: col.bg, color: col.color }">
            <span class="col-name">{{ col.label }}</span>
            <span class="col-count">{{ grouped[col.value]?.length || 0 }}</span>
          </div>
          <div class="column-body">
            <div
              v-for="p in grouped[col.value] || []"
              :key="p.id"
              class="project-card"
              :draggable="true"
              @dragstart="onDragStart(p)"
              @dragend="onDragEnd"
              @click="$router.push(`/project/detail/${p.id}`)"
            >
              <div class="card-name">{{ p.name }}</div>
              <div class="card-customer">
                <el-icon :size="12"><OfficeBuilding /></el-icon>
                {{ p.customer?.name || '-' }}
              </div>
              <div class="card-progress">
                <el-progress :percentage="p.progress || 0" :stroke-width="6" :show-text="false" :color="progressColor(p.progress)" />
                <span class="card-progress-text">{{ p.progress || 0 }}%</span>
              </div>
              <div class="card-meta">
                <span class="card-manager">
                  <el-icon :size="12"><User /></el-icon>
                  {{ p.manager?.name || '-' }}
                </span>
                <span class="card-deadline" :class="{ 'overdue': isOverdue(p.end_date) }">
                  <el-icon :size="12"><Clock /></el-icon>
                  {{ formatDate(p.end_date) }}
                </span>
              </div>
              <el-tag v-if="p.priority === 'urgent' || p.priority === 'high'" type="danger" size="small" effect="dark" class="card-priority">
                {{ p.priority === 'urgent' ? '紧急' : '高优' }}
              </el-tag>
            </div>
            <el-empty v-if="!grouped[col.value]?.length" :image-size="50" description="拖入项目" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { KanbanProject } from "./types"
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Plus, Refresh, OfficeBuilding, User, Clock } from '@element-plus/icons-vue'
import { get, put } from '@/utils/request'

const router = useRouter()
const list = ref<KanbanProject[]>([])
const draggingId = ref<number | null>(null)
const dragOverCol = ref<string | null>(null)

const stageColumns = [
  { value: 'mobilization', label: '进场准备', bg: 'rgba(12, 68, 124, 0.1)', color: '#0C447C' },
  { value: 'construction', label: '现场施工', bg: 'rgba(163, 45, 45, 0.1)', color: '#A32D2D' },
  { value: 'acceptance', label: '验收交付', bg: 'rgba(186, 117, 23, 0.1)', color: '#BA7517' },
  { value: 'settlement', label: '项目结算', bg: 'rgba(29, 158, 117, 0.1)', color: '#1D9E75' },
  { value: 'warranty', label: '售后质保', bg: 'rgba(29, 158, 117, 0.15)', color: '#1D9E75' },
  { value: 'closed', label: '已关闭', bg: 'rgba(144, 147, 153, 0.1)', color: '#909399' },
]

const grouped = computed(() => {
  const g: Record<string, KanbanProject[]> = {}
  for (const c of stageColumns) g[c.value] = []
  for (const p of list.value) {
    if (g[p.stage]) g[p.stage].push(p)
  }
  return g
})

const loadList = async () => {
  try {
    const r = await get('/projects', { per_page: 200 })
    // V0.6.3: res = {code, data: paginator}
    const d = r?.data ?? {}
    const items = Array.isArray(d?.data) ? d.data : (Array.isArray(d) ? d : [])
    list.value = Array.isArray(items) ? items : []
  } catch (e) {
    ElMessage.error('加载项目列表失败')
  }
}

const onDragStart = (p: KanbanProject) => {
  draggingId.value = p.id
  // 阻止 card click 事件触发
  event?.stopPropagation()
}

const onDragEnd = () => {
  draggingId.value = null
  dragOverCol.value = null
}

const onDragOver = (col: string, e: DragEvent) => {
  e.preventDefault()
  dragOverCol.value = col
}

const onDragLeave = (col: string) => {
  if (dragOverCol.value === col) dragOverCol.value = null
}

const onDrop = async (newStage: string) => {
  const pid = draggingId.value
  if (!pid) return
  const project = list.value.find(p => p.id === pid)
  if (!project) return
  const oldStage = project.stage
  if (oldStage === newStage) {
    draggingId.value = null
    dragOverCol.value = null
    return
  }
  // 乐观更新
  project.stage = newStage
  draggingId.value = null
  dragOverCol.value = null
  try {
    await put(`/projects/${pid}/stage`, { stage: newStage })
    ElMessage.success(`项目已推进到「${stageColumns.find(s => s.value === newStage)?.label || newStage}」`)
  } catch (e: unknown) {
    project.stage = oldStage
    ElMessage.error(e?.response?.data?.message || '阶段更新失败，已回滚')
  }
}

const progressColor = (p: number) => {
  if (p >= 80) return '#1D9E75'
  if (p >= 50) return '#0C447C'
  if (p >= 30) return '#BA7517'
  return '#A32D2D'
}

const isOverdue = (d: string) => d && new Date(d) < new Date()
const formatDate = (d: string) => d ? d.slice(0, 10) : '-'

onMounted(() => {
  loadList()
})
</script>

<style lang="scss" scoped>
.page-container {
  padding: 16px;
  background: #f5f7fa;
  height: calc(100vh - 60px);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #fff;
  padding: 14px 20px;
  border-radius: 8px;
  margin-bottom: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  flex-shrink: 0;

  .title-area { display: flex; align-items: center; gap: 10px; }
  .page-title { font-size: 18px; font-weight: 600; color: #0C447C; border-left: 4px solid #0C447C; padding-left: 10px; }
  .header-actions { display: flex; gap: 8px; }
}

.board-wrapper {
  flex: 1;
  overflow-x: auto;
  overflow-y: hidden;
  background: #fff;
  border-radius: 8px;
  padding: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}

.board-canvas {
  display: flex;
  gap: 10px;
  height: 100%;
  min-width: max-content;
}

.board-column {
  width: 220px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  background: #f5f7fa;
  border-radius: 6px;
  transition: all 0.2s;
  border: 2px solid transparent;

  &.is-drop-target {
    border-color: #0C447C;
    background: rgba(12, 68, 124, 0.05);
    box-shadow: 0 0 12px rgba(12, 68, 124, 0.15);
  }

  .column-header {
    padding: 8px 12px;
    border-radius: 6px 6px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
    font-weight: 600;

    .col-count {
      background: rgba(255, 255, 255, 0.6);
      padding: 1px 8px;
      border-radius: 10px;
      font-size: 11px;
    }
  }

  .column-body {
    flex: 1;
    overflow-y: auto;
    padding: 6px;
    display: flex;
    flex-direction: column;
    gap: 6px;
  }
}

.project-card {
  background: #fff;
  border-radius: 4px;
  padding: 8px 10px;
  cursor: grab;
  border: 1px solid #ebeef5;
  position: relative;
  transition: all 0.2s;
  user-select: none;

  &:hover { box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); transform: translateY(-1px); }
  &:active { cursor: grabbing; }

  .card-name {
    font-size: 13px;
    font-weight: 600;
    color: #303133;
    line-height: 1.3;
    margin-bottom: 4px;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
  }
  .card-customer {
    font-size: 11px;
    color: #606266;
    display: flex;
    align-items: center;
    gap: 3px;
    margin-bottom: 6px;
  }
  .card-progress {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 4px;
    :deep(.el-progress) { flex: 1; }
    .card-progress-text { font-size: 11px; color: #909399; min-width: 28px; text-align: right; }
  }
  .card-meta {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    color: #909399;
    .card-manager, .card-deadline { display: flex; align-items: center; gap: 2px; }
    .card-deadline.overdue { color: #A32D2D; font-weight: 600; }
  }
  .card-priority {
    position: absolute;
    top: 4px;
    right: 4px;
  }
}
</style>
