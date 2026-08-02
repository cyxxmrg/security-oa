<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="(v) => emit('update:visible', v)"
    :title="dialogTitle"
    width="1440px"
    top="3vh"
    :close-on-click-modal="false"
    destroy-on-close
    class="instance-detail-dialog"
  >
    <div v-if="loading" class="loading-skeleton">
      <el-skeleton :rows="6" animated />
    </div>

    <template v-else-if="instance.id">
      <div class="page-header">
        <div class="title-area">
          <span class="page-title">{{ instance.name || '—' }}</span>
          <span v-if="instance.code" class="code-text">{{ instance.code }}</span>
          <el-tag :type="statusTagType(instance.status)" effect="dark" size="default">
            {{ statusLabel(instance.status) }}
          </el-tag>
          <el-tag v-if="instance.industry" effect="plain" size="default" type="info">
            {{ industryLabel(instance.industry) }}
          </el-tag>
          <el-tag v-if="instance.is_overdue" effect="dark" size="default" type="danger">
            已超期
          </el-tag>
        </div>
        <div class="header-actions">
          <el-button
            v-if="instance.status === 'pending'"
            type="primary"
            :icon="VideoPlay"
            :loading="actionLoading.start"
            @click="handleStart"
          >开始施工</el-button>
          <el-button
            v-if="instance.status === 'in_progress'"
            type="success"
            :icon="CircleCheck"
            :loading="actionLoading.complete"
            @click="handleComplete"
          >标记完成</el-button>
          <el-button :icon="Edit" @click="openEditDialog">编辑</el-button>
          <el-button :icon="Refresh" @click="loadAll">刷新</el-button>
          <el-button type="danger" :icon="Delete" :loading="actionLoading.delete" @click="handleDelete">
            删除
          </el-button>
        </div>
      </div>

      <InstanceInfoCard :instance="instance" @view-project="goProject" />

      <ProgressCard :instance="instance" :inspection-list="inspectionList" />

      <InspectionTable
        :inspection-list="inspectionList"
        @add="openInspectionDialog"
        @delete="handleDeleteInspection"
      />

      <el-card class="info-card" shadow="never">
        <template #header>
          <div class="card-header">
            <span class="card-title">
              <el-icon><InfoFilled /></el-icon>备注 & 元数据
            </span>
          </div>
        </template>
        <el-collapse v-model="metaCollapse">
          <el-collapse-item title="创建 / 更新时间" name="meta">
            <el-descriptions :column="2" border>
              <el-descriptions-item label="创建时间">
                {{ formatDateTime(instance.created_at) }}
              </el-descriptions-item>
              <el-descriptions-item label="更新时间">
                {{ formatDateTime(instance.updated_at) }}
              </el-descriptions-item>
              <el-descriptions-item label="验收时间">
                {{ instance.accepted_at ? formatDateTime(instance.accepted_at) : '—' }}
              </el-descriptions-item>
              <el-descriptions-item label="验收人">
                {{ instance.acceptedByUser?.name || instance.accepted_by || '—' }}
              </el-descriptions-item>
              <el-descriptions-item v-if="instance.parent" label="上级工序" :span="2">
                {{ instance.parent.name }} ({{ instance.parent.code }})
              </el-descriptions-item>
              <el-descriptions-item
                v-if="instance.children && instance.children.length"
                label="下级工序"
                :span="2"
              >
                <el-tag
                  v-for="c in instance.children"
                  :key="c.id"
                  size="small"
                  effect="plain"
                  style="margin-right: 6px"
                >
                  {{ c.name }} ({{ c.status }})
                </el-tag>
              </el-descriptions-item>
            </el-descriptions>
          </el-collapse-item>
        </el-collapse>
      </el-card>
    </template>

    <template v-else>
      <el-empty description="未找到工序实例" />
    </template>

    <InspectionDialog
      v-model:visible="inspectionDialog.visible"
      :submitting="inspectionDialog.loading"
      @submit="submitInspection"
    />

    <EditInstanceDialog
      v-model:visible="editDialog.visible"
      :submitting="editDialog.loading"
      :target="instance"
      :user-options="userOptions"
      @submit="submitEdit"
    />
  </el-dialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useRouter } from 'vue-router'
import {
  Refresh, Edit, Delete, CircleCheck, VideoPlay, InfoFilled,
} from '@element-plus/icons-vue'
import { processApi, getUserList } from '@/api/modules'
import { unwrapList, unwrapItem } from '@/utils/response'
import ProgressCard from './components/ProgressCard.vue'
import InspectionTable from './components/InspectionTable.vue'
import InspectionDialog from './components/InspectionDialog.vue'
import EditInstanceDialog, { type InstanceEditForm } from './components/EditInstanceDialog.vue'
import InstanceInfoCard from './components/instance-detail/InstanceInfoCard.vue'
import {
  statusLabel, statusTagType, industryLabel, formatDateTime,
  type ProcessInstance, type Inspection,
} from './types'

// v1.2.12p 工序详情改成弹窗模式 (1440px), 由 InstanceList 通过 props.id 控制
const props = defineProps<{
  visible: boolean
  id: number | null
}>()

const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'done'): void
}>()

const router = useRouter()
const loading = ref(false)
const metaCollapse = ref<string[]>(['meta'])

const instance = ref<ProcessInstance>({} as ProcessInstance)
const inspectionList = ref<Inspection[]>([])
const userOptions = ref<{ id: number; string?: string; name: string }[]>([])

const actionLoading = reactive({
  start: false, complete: false, delete: false,
})

const dialogTitle = computed(() => {
  if (loading.value) return '加载中...'
  if (!instance.value.id) return '工序详情'
  return `工序详情 #${instance.value.id} - ${instance.value.name || instance.value.template?.name || ''}`
})

// 监听 props.id 变化, 自动重新加载
watch(() => props.id, (newId) => {
  if (props.visible && newId) loadAll()
}, { immediate: true })

watch(() => props.visible, (v) => {
  if (v && props.id) loadAll()
})

// ===== 加载数据 =====
const loadInstance = async (): Promise<void> => {
  if (!props.id) return
  try {
    const res = await processApi.instanceDetail(props.id)
    instance.value = (unwrapItem(res) || {}) as ProcessInstance
  } catch (e: unknown) {
    ElMessage.error((e as { response?: { data?: { message?: string } }; message?: string })?.response?.data?.message
      || (e as { message?: string })?.message || '加载工序详情失败')
    instance.value = {} as ProcessInstance
  }
}

const loadInspections = async (): Promise<void> => {
  if (!props.id) return
  try {
    const res = await processApi.inspectionList({ process_instance_id: props.id, per_page: 200 })
    inspectionList.value = unwrapList(res) as unknown as Inspection[]
  } catch { /* 静默 */ }
}

const loadUsers = async (): Promise<void> => {
  try {
    const res = await getUserList({ page: 1, per_page: 200 })
    userOptions.value = unwrapList(res) as unknown as { id: number; name: string }[]
  } catch { userOptions.value = [] }
}

const loadAll = async (): Promise<void> => {
  loading.value = true
  try {
    await loadInstance()
    if (!instance.value.inspections || !instance.value.inspections.length) {
      await loadInspections()
    } else {
      inspectionList.value = instance.value.inspections
    }
  } finally {
    loading.value = false
  }
}

const goProject = (projectId?: number | null) => {
  if (!projectId) return
  emit('update:visible', false)
  router.push(`/project/detail/${projectId}`)
}

const handleStart = async () => {
  if (!instance.value.id) return
  actionLoading.start = true
  try {
    await processApi.instanceProgress(instance.value.id, {
      progress: instance.value.progress ?? 0,
      status: 'in_progress',
      remark: '开始施工',
    })
    ElMessage.success('已开始施工')
    await loadAll()
    emit('done')
  } catch (e: unknown) {
    ElMessage.error((e as { response?: { data?: { message?: string } }; message?: string })?.response?.data?.message
      || (e as { message?: string })?.message || '操作失败')
  } finally { actionLoading.start = false }
}

const handleComplete = async () => {
  if (!instance.value.id) return
  try {
    await ElMessageBox.confirm(
      `确认将「${instance.value.name || instance.value.template?.name || '#' + instance.value.id}」标记为已完成？`,
      '标记完成', { confirmButtonText: '确认完成', cancelButtonText: '取消', type: 'warning' }
    )
  } catch { return }
  actionLoading.complete = true
  try {
    await processApi.instanceProgress(instance.value.id, {
      progress: 100, status: 'completed', remark: '施工完成',
    })
    ElMessage.success('已标记为完成')
    await loadAll()
    emit('done')
  } catch (e: unknown) {
    ElMessage.error((e as { response?: { data?: { message?: string } }; message?: string })?.response?.data?.message
      || (e as { message?: string })?.message || '操作失败')
  } finally { actionLoading.complete = false }
}

const handleDelete = async () => {
  if (!instance.value.id) return
  try {
    await ElMessageBox.confirm(
      `确认删除工序实例 #${instance.value.id}？此操作不可恢复。`,
      '删除工序', { confirmButtonText: '删除', cancelButtonText: '取消', type: 'warning' }
    )
  } catch { return }
  actionLoading.delete = true
  try {
    await processApi.instanceDelete(instance.value.id)
    ElMessage.success('已删除')
    emit('update:visible', false)
    emit('done')
  } catch (e: unknown) {
    ElMessage.error((e as { response?: { data?: { message?: string } }; message?: string })?.response?.data?.message
      || (e as { message?: string })?.message || '删除失败')
  } finally { actionLoading.delete = false }
}

const inspectionDialog = reactive({ visible: false, loading: false })
const openInspectionDialog = () => { inspectionDialog.visible = true; inspectionDialog.loading = false }

const submitInspection = async ({ payload }: { backendResult: string; payload: Record<string, unknown> }) => {
  inspectionDialog.loading = true
  try {
    const id = instance.value.id
    await processApi.inspectionCreate({ ...payload, process_instance_id: id })
    ElMessage.success('验收记录已添加')
    inspectionDialog.visible = false
    await loadAll()
    emit('done')
  } catch (e: unknown) {
    ElMessage.error((e as { response?: { data?: { message?: string } }; message?: string })?.response?.data?.message
      || (e as { message?: string })?.message || '提交失败')
  } finally { inspectionDialog.loading = false }
}

const handleDeleteInspection = async (row: Inspection) => {
  try {
    await ElMessageBox.confirm(`确认删除验收记录 #${row.id}？`, '删除验收', { type: 'warning' })
  } catch { return }
  try {
    await processApi.inspectionDelete(row.id)
    ElMessage.success('已删除')
    await loadInspections()
  } catch (e: unknown) {
    ElMessage.error((e as { response?: { data?: { message?: string } }; message?: string })?.response?.data?.message
      || (e as { message?: string })?.message || '删除失败')
  }
}

const editDialog = reactive({ visible: false, loading: false })
const openEditDialog = () => { editDialog.visible = true; editDialog.loading = false }

const submitEdit = async (form: InstanceEditForm) => {
  editDialog.loading = true
  try {
    await processApi.instanceUpdate(instance.value.id, form)
    ElMessage.success('已保存')
    editDialog.visible = false
    await loadAll()
    emit('done')
  } catch (e: unknown) {
    ElMessage.error((e as { response?: { data?: { message?: string } }; message?: string })?.response?.data?.message
      || (e as { message?: string })?.message || '保存失败')
  } finally { editDialog.loading = false }
}
</script>

<style lang="scss" scoped>
.loading-skeleton { padding: 32px 16px; }

.page-header {
  display: flex; justify-content: space-between; align-items: center;
  background: #fff; padding: 16px 20px; border-radius: 8px; margin-bottom: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  .title-area { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
  .page-title { font-size: 18px; font-weight: 600; color: #0C447C; }
  .code-text { font-family: monospace; color: #909399; font-size: 13px; }
  .header-actions { display: flex; gap: 8px; }
}

.info-card {
  background: #fff;
  border-radius: 8px;
  margin-bottom: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
.card-header { display: flex; align-items: center; gap: 8px; }
.card-title {
  font-size: 15px;
  font-weight: 600;
  color: #303133;
  display: flex;
  align-items: center;
  gap: 6px;
}
</style>

<style lang="scss">
// 弹窗内部铺满 (避免内容被压太窄)
.instance-detail-dialog {
  .el-dialog__body {
    padding: 16px 24px 24px;
    max-height: 86vh;
    overflow-y: auto;
  }
  .el-dialog__header {
    padding: 16px 24px 12px;
    border-bottom: 1px solid #f0f2f5;
    background: #fafbfc;
  }
}
</style>