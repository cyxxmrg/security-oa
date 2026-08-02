<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">施工发包</span>
      <div class="header-actions">
        <el-button :icon="Refresh" plain @click="loadList">刷新</el-button>
        <el-button type="primary" :icon="Plus" @click="handleAdd">新建发包</el-button>
      </div>
    </div>

    <div class="kpi-row">
      <el-card v-for="kpi in kpis" :key="kpi.label" shadow="hover" :body-style="{ padding: '14px 18px' }" class="kpi-card">
        <div class="kpi-label">{{ kpi.label }}</div>
        <div class="kpi-value" :style="{ color: kpi.color }">{{ kpi.value }}</div>
      </el-card>
    </div>

    <div class="filter-bar">
      <el-form :inline="true" :model="searchForm" @submit.prevent="handleSearch">
        <el-form-item label="项目">
          <el-select
            v-model="searchForm.project_id"
            placeholder="全部"
            clearable
            filterable
            style="width: 220px"
          >
            <el-option
              v-for="p in projectOptions"
              :key="p.id"
              :label="`${p.code ? p.code + ' - ' : ''}${p.name || ''}`"
              :value="p.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="全部" clearable style="width: 140px">
            <el-option v-for="s in statusOptions" :key="s.value" :label="s.label" :value="s.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="关键词">
          <el-input v-model="searchForm.keyword" placeholder="标题 / 编号" clearable style="width: 220px" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
          <el-button :icon="Refresh" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <div class="content-card">
      <el-table
        :data="pagedList"
        v-loading="loading"
        stripe
        border
        :header-cell-style="{ background: '#f5f7fa', color: '#303133', fontWeight: 600 }"
      >
        <el-table-column prop="code" label="发包编号" width="160" fixed show-overflow-tooltip>
          <template #default="{ row }">
            <el-link type="primary" :underline="false" @click="goDetail(row)">{{ row.code || '-' }}</el-link>
          </template>
        </el-table-column>
        <el-table-column prop="title" label="标题" min-width="200" show-overflow-tooltip />
        <el-table-column label="项目" min-width="160" show-overflow-tooltip>
          <template #default="{ row }">{{ row.project?.name || row.project_id || '-' }}</template>
        </el-table-column>
        <el-table-column label="预算" width="130" align="right">
          <template #default="{ row }">¥ {{ formatMoney(row.budget) }}</template>
        </el-table-column>
        <el-table-column label="中标价" width="130" align="right">
          <template #default="{ row }">
            <span v-if="row.award_amount" style="color:#67c23a;font-weight:600">¥ {{ formatMoney(row.award_amount) }}</span>
            <span v-else class="muted">-</span>
          </template>
        </el-table-column>
        <el-table-column label="投标数" width="80" align="center">
          <template #default="{ row }">
            <el-tag type="info" size="small">{{ row.bid_count ?? 0 }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="deadline" label="投标截止" width="120" align="center" />
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="statusTagType(row.status)" effect="plain" size="small">
              {{ statusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="240" fixed="right" align="center">
          <template #default="{ row }">
            <el-button link type="primary" :icon="View" @click="goDetail(row)">详情</el-button>
            <el-button
              v-if="row.status === 'open'"
              link
              type="success"
              :icon="Trophy"
              @click="handleAward(row)"
            >定标</el-button>
            <el-button
              v-if="['open', 'bidding'].includes(row.status)"
              link
              type="warning"
              :icon="CircleClose"
              @click="handleClose(row)"
            >关闭</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrapper">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="pageSize"
          :page-sizes="[10, 20, 50]"
          :total="filteredList.length"
          layout="total, sizes, prev, pager, next, jumper"
          background
        />
      </div>
    </div>

    <!-- 新建 dialog -->
    <el-dialog
      v-model="showDialog"
      title="新建发包"
      width="700px"
      :close-on-click-modal="false"
    >
      <el-form :model="formData" label-width="100px">
        <el-form-item label="标题" required>
          <el-input v-model="formData.title" maxlength="100" />
        </el-form-item>
        <el-form-item label="项目" required>
          <el-select v-model="formData.project_id" filterable placeholder="请选择" style="width: 100%">
            <el-option
              v-for="p in projectOptions"
              :key="p.id"
              :label="`${p.code ? p.code + ' - ' : ''}${p.name || ''}`"
              :value="p.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="预算">
          <el-input-number v-model="formData.budget" :min="0" :step="1000" :precision="2" style="width: 100%" />
        </el-form-item>
        <el-form-item label="投标截止">
          <el-date-picker v-model="formData.deadline" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
        </el-form-item>
        <el-form-item label="发包类型" required>
          <el-radio-group v-model="formData.bid_type">
            <el-radio value="public" style="margin-bottom:8px">
              <div>
                <div style="font-weight:500">公开发包</div>
                <div style="font-size:12px;color:#909399">所有人都可以投标</div>
              </div>
            </el-radio>
            <el-radio value="internal">
              <div>
                <div style="font-weight:500">内部发包</div>
                <div style="font-size:12px;color:#909399">自由选择内部已添加的供应商</div>
              </div>
            </el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item v-if="formData.bid_type === 'internal'" label="选择供应商">
          <el-select v-model="formData.supplier_id" filterable placeholder="请选择供应商" style="width:100%" clearable>
            <el-option v-for="s in supplierOptions" :key="s.id" :label="s.name" :value="s.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="发包范围">
          <el-input v-model="formData.scope" type="textarea" :rows="3" maxlength="1000" show-word-limit />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="formData.remark" type="textarea" :rows="2" maxlength="500" show-word-limit />
        </el-form-item>
        <el-form-item label="图纸附件">
          <el-button type="primary" :icon="Plus" @click="triggerUpload">上传图纸</el-button>
          <input ref="fileInputRef" type="file" multiple accept=".pdf,.jpg,.jpeg,.png,.dwg,.dxf" style="display:none" @change="handleFileChange" />
          <div v-if="drawingList.length" class="drawing-list" style="margin-top:8px">
            <div v-for="(d, idx) in drawingList" :key="idx" class="drawing-item" style="display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border:1px solid #e4e7ed;border-radius:4px;margin:0 6px 6px 0">
              <el-icon><Document /></el-icon>
              <span style="font-size:13px;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ d.name }}</span>
              <el-button link type="danger" size="small" @click="drawingList.splice(idx, 1); drawingData.splice(idx, 1)" style="padding:0"><el-icon><Close /></el-icon></el-button>
            </div>
          </div>
          <div style="font-size:12px;color:#94a3b8;margin-top:4px">支持 PDF / JPG / PNG / DWG / DXF 格式，可多选</div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showDialog = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="handleSave">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Search, Refresh, View, Trophy, CircleClose, Close, Document } from '@element-plus/icons-vue'
import { externalWorkApi } from '@/api/construction'
import { getProjectList } from '@/api/modules'
import { get } from '@/utils/request'
import { unwrapList } from '@/utils/response'
import type { ExternalWork, ProjectOption, Bid, TagType } from '../types'

const router = useRouter()

const statusOptions = [
  { value: 'draft',     label: '草稿' },
  { value: 'open',      label: '招标中' },
  { value: 'bidding',   label: '评标中' },
  { value: 'awarded',   label: '已定标' },
  { value: 'closed',    label: '已关闭' },
]
const statusLabel = (s: string) => statusOptions.find(x => x.value === s)?.label || s || '-'
const statusTagType = (s: string): TagType => ({
  draft: 'info', open: 'warning', bidding: 'warning', awarded: 'success', closed: 'danger',
} as Record<string, TagType>)[s] || 'info'

const formatMoney = (n: number | string | null | undefined) => Number(n || 0).toLocaleString('zh-CN', { maximumFractionDigits: 2 })

const loading = ref(false)
const saving = ref(false)
const page = ref(1)
const pageSize = ref(10)
const list = ref<ExternalWork[]>([])
const projectOptions = ref<ProjectOption[]>([])

const searchForm = reactive<{ project_id: number | null; status: string; keyword: string }>({
  project_id: null, status: '', keyword: '',
})

const showDialog = ref(false)
const formData = reactive({ title: '', project_id: null as number | null, budget: 0, deadline: '', bid_type: 'public', supplier_id: null as number | null, scope: '', remark: '' })

// 供应商列表
const supplierOptions = ref<{ id: number; name: string }[]>([])

// 图纸上传
const drawingList = ref<{ name: string; size: number }[]>([])
const drawingData = ref<string[]>([])
const fileInputRef = ref<HTMLInputElement | null>(null)

const filteredList = computed(() => {
  let arr = [...list.value]
  if (searchForm.project_id) arr = arr.filter(r => Number(r.project_id) === Number(searchForm.project_id))
  if (searchForm.status) arr = arr.filter(r => r.status === searchForm.status)
  if (searchForm.keyword) {
    const kw = searchForm.keyword.toLowerCase()
    arr = arr.filter(r => (r.code || '').toLowerCase().includes(kw) || (r.title || '').toLowerCase().includes(kw))
  }
  return arr
})

const pagedList = computed(() => {
  const start = (page.value - 1) * pageSize.value
  return filteredList.value.slice(start, start + pageSize.value)
})

const kpis = computed(() => {
  const total = list.value.length
  const open = list.value.filter(r => ['open', 'bidding'].includes(r.status)).length
  const awarded = list.value.filter(r => r.status === 'awarded').length
  const totalBudget = list.value.reduce((s, r) => s + Number(r.budget || 0), 0)
  return [
    { label: '发包总数', value: total, color: '#0C447C' },
    { label: '招标中',   value: open, color: '#E6A23C' },
    { label: '已定标',   value: awarded, color: '#67c23a' },
    { label: '发包总预算', value: '¥ ' + formatMoney(totalBudget), color: '#1D9E75' },
  ]
})

const loadList = async () => {
  loading.value = true
  try {
    const params: Record<string, unknown> = { per_page: 500, page: 1 }
    if (searchForm.project_id) params.project_id = searchForm.project_id
    if (searchForm.status) params.status = searchForm.status
    if (searchForm.keyword) params.keyword = searchForm.keyword
    const res = await externalWorkApi.list(params)
    list.value = unwrapList(res)
  } catch {
    list.value = []
  } finally {
    loading.value = false
  }
}

const loadProjects = async () => {
  try {
    const res = await getProjectList({ per_page: 500 })
    projectOptions.value = unwrapList(res).map((p: ProjectOption) => ({ id: p.id, code: p.code, name: p.name }))
  } catch {
    projectOptions.value = []
  }
}

const loadSuppliers = async () => {
  try {
    const res = await get('/suppliers', { per_page: 500 })
    const d = (res as { data?: { data?: unknown[] } })?.data?.data || (res as { data?: unknown[] })?.data || []
    supplierOptions.value = (d as { id: number; name: string }[]) || []
  } catch {
    supplierOptions.value = []
  }
}

const handleSearch = () => { page.value = 1; loadList() }
const handleReset = () => {
  searchForm.project_id = null
  searchForm.status = ''
  searchForm.keyword = ''
  page.value = 1
  loadList()
}

const goDetail = (row: ExternalWork) => router.push(`/construction/external-work/${row.id}`)

const handleAdd = () => {
  formData.title = ''
  formData.project_id = null
  formData.budget = 0
  formData.deadline = ''
  formData.bid_type = 'public'
  formData.supplier_id = null
  formData.scope = ''
  formData.remark = ''
  drawingList.value = []
  drawingData.value = []
  loadSuppliers()
  showDialog.value = true
}

const handleSave = async () => {
  if (!formData.title || !formData.project_id) {
    ElMessage.warning('请填写标题与项目')
    return
  }
  saving.value = true
  try {
    const payload: Record<string, unknown> = {
      title: formData.title,
      project_id: formData.project_id,
      bid_type: formData.bid_type,
      budget: Number(formData.budget || 0),
      deadline: formData.deadline,
      scope: formData.scope,
      remark: formData.remark,
    }
    if (formData.bid_type === 'internal' && formData.supplier_id) {
      payload.awarded_supplier_id = formData.supplier_id
    }
    if (drawingData.value.length) payload.attachments = drawingData.value
    await externalWorkApi.create(payload)
    ElMessage.success('已创建')
    showDialog.value = false
    await loadList()
  } catch { /* 拦截器已提示 */ }
  finally { saving.value = false }
}

// 图纸上传
const triggerUpload = () => fileInputRef.value?.click()
const handleFileChange = (e: Event) => {
  const files = (e.target as HTMLInputElement).files
  if (!files) return
  for (let i = 0; i < files.length; i++) {
    const file = files[i]
    drawingList.value.push({ name: file.name, size: file.size })
    const reader = new FileReader()
    reader.onload = (ev) => {
      const url = ev.target?.result as string
      if (url) drawingData.value.push(url)
    }
    reader.readAsDataURL(file)
  }
  ;(e.target as HTMLInputElement).value = ''
}

const handleClose = async (row: ExternalWork) => {
  try {
    await ElMessageBox.confirm(
      `确认关闭发包「${row.code}」？关闭后将不再接受投标。`,
      '关闭确认',
      { type: 'warning', confirmButtonText: '确认关闭', cancelButtonText: '取消' }
    )
  } catch { return }
  try {
    await externalWorkApi.close(row.id)
    ElMessage.success('已关闭')
    await loadList()
  } catch { /* 拦截器已提示 */ }
}

const handleAward = async (row: ExternalWork) => {
  let bidId: number | null = null
  let bids: Bid[] = []
  try {
    const res = await externalWorkApi.listBids(row.id)
    bids = unwrapList(res)
  } catch { bids = [] }
  if (!bids.length) {
    ElMessage.warning('暂无投标，无法定标')
    return
  }
  try {
    const { value } = await ElMessageBox.prompt(
      `请输入要定标的投标 ID（可选投标：${bids.map(b => b.id).join(', ')}）`,
      '定标',
      {
        inputType: 'number',
        inputValue: bids[0].id,
        inputValidator: (val: string) => {
          if (!bids.find(b => String(b.id) === String(val))) return '投标 ID 不在列表中'
          return true
        },
      }
    )
    bidId = Number(value)
  } catch { return }
  try {
    await externalWorkApi.award(row.id, { bid_id: bidId })
    ElMessage.success('已定标')
    await loadList()
  } catch { /* 拦截器已提示 */ }
}

onMounted(() => {
  loadProjects()
  loadList()
})
</script>

<style lang="scss" scoped>
.page-container { padding: 16px; background: #f5f7fa; min-height: calc(100vh - 60px); }
.page-header {
  display: flex; justify-content: space-between; align-items: center;
  background: #fff; padding: 16px 20px; border-radius: 8px; margin-bottom: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  .page-title {
    font-size: 18px; font-weight: 600; color: #0C447C;
    border-left: 4px solid #0C447C; padding-left: 10px;
  }
  .header-actions { display: flex; gap: 8px; }
}
.kpi-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 12px; }
.kpi-card {
  .kpi-label { color: #909399; font-size: 13px; }
  .kpi-value { font-size: 20px; font-weight: 700; margin-top: 4px; }
}
.filter-bar {
  background: #fff; padding: 16px 20px; border-radius: 8px;
  margin-bottom: 12px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
.content-card {
  background: #fff; padding: 20px; border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
.pagination-wrapper { margin-top: 16px; display: flex; justify-content: flex-end; }
.muted { color: #c0c4cc; }
</style>
