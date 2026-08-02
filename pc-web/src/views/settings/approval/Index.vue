<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">审批流程引擎</span>
      <el-button type="primary" :icon="Plus" @click="handleAdd">新建流程模板</el-button>
    </div>

    <div class="content-card">
      <div class="toolbar">
        <el-input v-model="searchKey" placeholder="搜索流程名称" clearable style="width: 280px;" :prefix-icon="Search" @input="page = 1" />
        <el-select v-model="filterModule" placeholder="适用模块" clearable style="width: 160px; margin-left: 12px;" @change="page = 1">
          <el-option label="全部" value="" />
          <el-option label="请假" value="请假" />
          <el-option label="报销" value="报销" />
          <el-option label="出差" value="出差" />
          <el-option label="采购" value="采购" />
          <el-option label="合同" value="合同" />
        </el-select>
        <el-button :icon="Refresh" @click="loadTemplates" style="margin-left: 12px;">刷新</el-button>
      </div>

      <el-table :data="pagedTemplates" border stripe style="width: 100%; margin-top: 16px;" v-loading="loading">
        <el-table-column prop="name" label="流程名称" width="200">
          <template #default="{ row }">
            <div class="flow-name">
              <el-icon color="#0C447C" :size="18"><Share /></el-icon>
              <span>{{ row.name }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="module" label="适用模块" width="120">
          <template #default="{ row }">
            <el-tag :type="moduleTagType(row.module)" size="small">{{ row.module }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="nodeCount" label="节点数" width="100" align="center" />
        <el-table-column prop="status" label="状态" width="120" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === '启用' ? 'success' : 'info'" size="small">{{ row.status }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="updatedBy" label="最后修改人" width="120" />
        <el-table-column prop="updatedAt" label="修改时间" width="170" />
        <el-table-column label="操作" width="240" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" :icon="View" @click="handlePreview(row)">预览</el-button>
            <el-button link type="primary" size="small" :icon="Edit" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="warning" size="small" :icon="SwitchButton" @click="handleToggle(row)">
              {{ row.status === '启用' ? '停用' : '启用' }}
            </el-button>
            <el-popconfirm :title="`确定删除「${row.name}」？`" @confirm="handleDelete(row)">
              <template #reference>
                <el-button link type="danger" size="small" :icon="Delete">删除</el-button>
              </template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
    </div>
  </div>

  <!-- 新建/编辑流程模板 -->
  <el-dialog v-model="formVisible" :title="editingId ? '编辑流程模板' : '新建流程模板'" width="1500px" destroy-on-close>
      <el-form :model="form" label-width="100px">
        <el-form-item label="流程名称" required>
          <el-input v-model="form.name" placeholder="请输入流程名称" maxlength="200" show-word-limit />
        </el-form-item>
        <el-form-item label="适用模块" required>
          <el-select v-model="form.module" placeholder="请选择" style="width:100%">
            <el-option label="请假" value="请假" />
            <el-option label="报销" value="报销" />
            <el-option label="出差" value="出差" />
            <el-option label="采购" value="采购" />
            <el-option label="合同" value="合同" />
          </el-select>
        </el-form-item>
        <el-form-item label="流程描述">
          <el-input v-model="form.description" type="textarea" :rows="2" maxlength="500" show-word-limit />
        </el-form-item>
        <el-form-item label="状态">
          <el-radio-group v-model="form.status">
            <el-radio label="启用">启用</el-radio>
            <el-radio label="停用">停用</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="流程节点">
          <FlowEditor ref="flowEditorRef" style="width:100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="formVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="handleSave">保存</el-button>
      </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, nextTick } from 'vue'
import { Plus, Search, Share, View, Edit, Delete, SwitchButton, Connection, CircleCheck, User, Switch, Bell, CircleClose, ArrowDown, Refresh } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { get, post, put, del } from '@/utils/request'
import { unwrapList } from '@/utils/response'
import FlowEditor from './components/FlowEditor.vue'

interface FlowNode {
  id?: string
  name: string
  desc: string
  type: 'start' | 'approval' | 'condition' | 'notify' | 'end'
  approver?: number | null
  position?: { x: number; y: number }
}

interface FlowTemplate {
  id: number
  name: string
  module: string
  description?: string
  nodeCount: number
  status: '启用' | '停用'
  updatedBy: string
  updatedAt: string
  nodes?: FlowNode[]
}

const flowEditorRef = ref<InstanceType<typeof FlowEditor>>()
const searchKey = ref('')
const filterModule = ref('')
const page = ref(1)
const pageSize = ref(20)
const loading = ref(false)
const saving = ref(false)
const templates = ref<FlowTemplate[]>([])

const filteredTemplates = computed(() => {
  let list = templates.value
  if (searchKey.value) {
    const kw = searchKey.value.toLowerCase()
    list = list.filter(t => t.name.toLowerCase().includes(kw))
  }
  if (filterModule.value) {
    list = list.filter(t => t.module === filterModule.value)
  }
  return list
})

const pagedTemplates = computed(() => {
  const start = (page.value - 1) * pageSize.value
  return filteredTemplates.value.slice(start, start + pageSize.value)
})

function moduleTagType(module: string) {
  const map: Record<string, string> = { '请假': 'warning', '报销': 'danger', '出差': 'info', '采购': 'primary', '合同': 'success' }
  return map[module] || 'info'
}

async function loadTemplates() {
  loading.value = true
  try {
    const res = await get('/approval-templates')
    // V0.6.3: res = {code, data: <templates>} 可能是 array
    templates.value = unwrapList(res)
  } catch (e) {
    ElMessage.error('加载流程模板失败')
  } finally {
    loading.value = false
  }
}

// 预览
const previewVisible = ref(false)
const previewFlow = ref<FlowTemplate | null>(null)
function handlePreview(row: FlowTemplate) {
  previewFlow.value = row
  previewVisible.value = true
}

// 新建/编辑
const formVisible = ref(false)
const editingId = ref<number | null>(null)
const form = reactive<{ name: string; module: string; description: string; status: '启用' | '停用' }>({
  name: '', module: '请假', description: '', status: '启用',
})
function handleAdd() {
  editingId.value = null
  Object.assign(form, { name: '', module: '请假', description: '', status: '启用' })
  formVisible.value = true
  nextTick(() => {
    // 加载默认示例流程到画布
    flowEditorRef.value?.loadData({
      nodes: [
        { id: 'start-1', type: 'start', label: '发起申请', desc: '员工提交申请', position: { x: 250, y: 30 } },
        { id: 'approval-1', type: 'approval', label: '部门经理审批', desc: '部门经理审核', position: { x: 250, y: 165 } },
        { id: 'end-1', type: 'end', label: '流程结束', desc: '结束', position: { x: 250, y: 300 } },
      ],
      edges: [
        { source: 'start-1', target: 'approval-1' },
        { source: 'approval-1', target: 'end-1' },
      ],
    })
  })
}

function handleEdit(row: FlowTemplate) {
  editingId.value = row.id
  Object.assign(form, {
    name: row.name,
    module: row.module,
    description: row.description ?? '',
    status: row.status,
  })
  formVisible.value = true
  nextTick(() => {
    // 从后端节点数据还原画布
    const nodes = (row.nodes || []).map((n: any, i: number) => ({
      id: n.id || `node-${i}`,
      type: n.type || 'approval',
      label: n.name || n.label || '',
      desc: n.desc || '',
      approver: n.approver || null,
      position: n.position || { x: 250, y: i * 140 + 30 },
    }))
    flowEditorRef.value?.loadData({ nodes, edges: [] })
  })
}

async function handleSave() {
  if (!form.name) {
    ElMessage.warning('请输入流程名称')
    return
  }
  saving.value = true
  try {
    // 从画布获取节点数据
    const editorData = flowEditorRef.value?.getNodeData()
    const nodes = (editorData?.nodes || []).map((n: any) => ({
      name: n.label || n.name || '',
      desc: n.desc || '',
      type: n.type || 'approval',
      approver: n.approver || null,
    }))
    const payload = {
      name: form.name,
      module: form.module,
      description: form.description,
      nodes: nodes,
    }
    if (editingId.value) {
      await put(`/approval-templates/${editingId.value}`, payload)
      ElMessage.success('流程模板已更新')
    } else {
      await post('/approval-templates', payload)
      ElMessage.success('流程模板已创建')
    }
    formVisible.value = false
    loadTemplates()
  } catch (e: unknown) {
    /* request.ts 已 toast */
  } finally {
    saving.value = false
  }
}

async function handleDelete(row: FlowTemplate) {
  try {
    await del(`/approval-templates/${row.id}`)
    ElMessage.success(`流程「${row.name}」已删除`)
    loadTemplates()
  } catch (e) { /* handled */ }
}

async function handleToggle(row: FlowTemplate) {
  try {
    const res = await post(`/approval-templates/${row.id}/toggle`, {})
    if (res?.code === 0) {
      row.status = res.data?.status ?? (row.status === '启用' ? '停用' : '启用')
      ElMessage.success(`流程已${row.status}`)
    }
  } catch (e) { /* handled */ }
}

onMounted(() => {
  loadTemplates()
})
</script>

<style scoped lang="scss">
.page-container { padding: 20px; background: #f5f7fa; min-height: 100%; }
.page-header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 20px;
  .page-title { font-size: 20px; font-weight: 600; color: #303133; }
}
.content-card {
  background: #fff; border-radius: 8px; padding: 20px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06); margin-bottom: 20px;
}
.toolbar { display: flex; align-items: center; }
.flow-name { display: flex; align-items: center; gap: 8px; font-weight: 500; }

.flow-designer-card {
  background: #fff; border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06); overflow: hidden;
}
.flow-designer-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 20px 20px 0;
  .card-title { font-size: 16px; font-weight: 600; color: #303133; }
}
.flow-designer-placeholder { text-align: center; padding: 48px 20px;
  .placeholder-title { font-size: 18px; font-weight: 600; color: #909399; margin-top: 16px; }
  .placeholder-desc { font-size: 13px; color: #c0c4cc; margin-top: 12px; line-height: 1.8; max-width: 500px; margin-left: auto; margin-right: auto; }
}

.flow-preview { padding: 20px;
  .flow-steps { display: flex; flex-direction: column; align-items: center; gap: 0; }
  .flow-node { display: flex; align-items: center; gap: 16px; padding: 16px 24px; border: 1px solid #e4e7ed; border-radius: 8px; width: 320px; background: #fff;
    &--start    { border-left: 4px solid #1D9E75; }
    &--approval { border-left: 4px solid #0C447C; }
    &--condition{ border-left: 4px solid #BA7517; }
    &--notify   { border-left: 4px solid #534AB7; }
    &--end      { border-left: 4px solid #A32D2D; }
    &__icon { flex-shrink: 0; }
    &__info { display: flex; flex-direction: column; }
    &__name { font-size: 14px; font-weight: 600; color: #303133; }
    &__desc { font-size: 12px; color: #909399; margin-top: 2px; }
  }
  .flow-arrow { padding: 4px 0; }
}

.nodes-editor { width: 100%; }
.node-row { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
</style>
