<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">招标中心</span>
      <div class="header-actions">
        <!-- V0.6.5 审核队列入口 -->
        <el-badge :value="pendingReviewCount" :hidden="pendingReviewCount === 0" type="warning">
          <el-button :icon="Bell" plain @click="showReviewQueue = true">待审核</el-button>
        </el-badge>
        <el-button :icon="Refresh" plain @click="loadList">刷新</el-button>
        <el-button :icon="Connection" plain @click="showConstructionDialog = true">施工询价</el-button>
        <el-button type="primary" :icon="Plus" @click="onCreate">设备询价</el-button>
      </div>
    </div>

    <div class="filter-bar">
      <el-form :inline="true">
        <el-form-item label="关键词">
          <el-input v-model="filter.keyword" placeholder="编号/名称" clearable style="width:240px" @keyup.enter="loadList" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="filter.status" placeholder="全部" clearable style="width:160px">
            <el-option v-for="o in STATUS_OPTIONS" :key="o.value" :label="o.label" :value="o.value" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :icon="Search" @click="loadList">查询</el-button>
        </el-form-item>
      </el-form>
    </div>

    <el-table :data="filteredList" v-loading="loading" stripe border>
      <el-table-column prop="code" label="编号" width="170" />
      <el-table-column prop="name" label="项目名称" min-width="220" show-overflow-tooltip />
      <el-table-column label="类型" width="100">
        <template #default="{ row }">
          <el-tag size="small" :type="row._type === 'tender' ? 'danger' : row._type === 'construction' ? 'warning' : 'info'" effect="light">
            {{ typeLabel(row._type) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="关联项目" width="160" show-overflow-tooltip>
        <template #default="{ row }">{{ row.project?.name || '-' }}</template>
      </el-table-column>
      <el-table-column label="状态" width="110">
        <template #default="{ row }">
          <el-tag size="small" :type="statusTag(row.status)" effect="light">
            {{ row.status_label || row.status }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="投标数" width="80" align="center">
        <template #default="{ row }">{{ row.bids_count ?? (row.bids_summary?.length ?? 0) }}</template>
      </el-table-column>
      <el-table-column label="截标" width="170">
        <template #default="{ row }">{{ fmt(row.deadline) }}</template>
      </el-table-column>
      <el-table-column label="中标供应商" width="160" show-overflow-tooltip>
        <template #default="{ row }">{{ row.awardedSupplier?.name || '-' }}</template>
      </el-table-column>
      <el-table-column label="操作" width="220" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="goDetail(row)">详情</el-button>
          <el-button link v-if="row.status === 'draft'" type="primary" @click="onEdit(row)">编辑</el-button>
          <el-button link v-if="row.status === 'draft'" type="warning" @click="onSubmitReviewRow(row)">提交审核</el-button>
          <el-button link v-if="row.status === 'draft'" type="success" @click="onPublish(row)">发布(旧)</el-button>
          <el-button link v-if="['open','bidding','evaluating'].includes(row.status)" type="danger" @click="onCancel(row)">取消</el-button>
        </template>
      </el-table-column>
    </el-table>

    <EditTenderDialog
      v-model:visible="showEditDialog"
      :tender="currentEdit"
      @saved="loadList"
    />

    <!-- V0.6.5 审核队列弹窗 -->
    <el-dialog v-model="showReviewQueue" title="待审核招标" width="800px">
      <el-table :data="pendingReviewList" v-loading="loadingReview" empty-text="暂无待审核">
        <el-table-column prop="code" label="编号" width="160" />
        <el-table-column prop="name" label="名称" min-width="200" show-overflow-tooltip />
        <el-table-column label="创建人" width="120">
          <template #default="{ row }">{{ row.creator?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="关联项目" width="140" show-overflow-tooltip>
          <template #default="{ row }">{{ row.project?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="更新于" width="160">
          <template #default="{ row }">{{ fmt(row.updated_at) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="100" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="goReview(row)">审核</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-dialog>

    <!-- 施工询价新建弹窗 -->
    <el-dialog v-model="showConstructionDialog" title="新建施工询价" width="700px" :close-on-click-modal="false">
      <el-form ref="constructionFormRef" :model="constructionForm" :rules="constructionRules" label-width="100px">
        <el-form-item label="标题" prop="title">
          <el-input v-model="constructionForm.title" maxlength="100" />
        </el-form-item>
        <el-form-item label="项目" prop="project_id">
          <el-select v-model="constructionForm.project_id" filterable placeholder="请选择" style="width:100%">
            <el-option v-for="p in projectOptions" :key="p.id" :label="`${p.code ? p.code + ' - ' : ''}${p.name || ''}`" :value="p.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="预算">
          <el-input-number v-model="constructionForm.budget" :min="0" :step="1000" :precision="2" style="width:100%" />
        </el-form-item>
        <el-form-item label="投标截止">
          <el-date-picker v-model="constructionForm.deadline" type="date" value-format="YYYY-MM-DD" style="width:100%" />
        </el-form-item>
        <el-form-item label="发包类型" prop="bid_type">
          <el-radio-group v-model="constructionForm.bid_type">
            <el-radio value="public">公开发包（所有人都可以投标）</el-radio>
            <el-radio value="internal">内部发包</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item v-if="constructionForm.bid_type === 'internal'" label="选择供应商">
          <el-select v-model="constructionForm.supplier_id" filterable placeholder="请选择供应商" style="width:100%" clearable>
            <el-option v-for="s in supplierOptions" :key="s.id" :label="s.name" :value="s.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="发包范围">
          <el-input v-model="constructionForm.scope" type="textarea" :rows="2" maxlength="1000" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="constructionForm.remark" type="textarea" :rows="2" maxlength="500" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showConstructionDialog = false">取消</el-button>
        <el-button type="primary" :loading="constructionSaving" @click="saveConstruction">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Search, Refresh, Bell, Connection } from '@element-plus/icons-vue'
import { tender } from '@/api/tender'
import { externalWorkApi } from '@/api/construction'
import { getProjectList } from '@/api/modules'
import { get } from '@/utils/request'
import { unwrapList, unwrapPaginate } from '@/utils/response'
import EditTenderDialog from './components/EditTenderDialog.vue'
import type { FormInstance } from 'element-plus'

const router = useRouter()
const list = ref<TenderProject[]>([])
const loading = ref(false)
const filter = reactive({ keyword: '', status: '' })

const showEditDialog = ref(false)
const currentEdit = ref<TenderProject | null>(null)

// 施工询价
const showConstructionDialog = ref(false)
const constructionSaving = ref(false)
const constructionFormRef = ref<FormInstance | null>(null)
const constructionForm = reactive({
  title: '', project_id: null as number | null, budget: 0, deadline: '',
  bid_type: 'public', supplier_id: null as number | null, scope: '', remark: '',
})
const constructionRules = {
  title:      [{ required: true, message: '请填写标题', trigger: 'blur' }],
  project_id: [{ required: true, message: '请选择项目', trigger: 'change' }],
  bid_type:   [{ required: true, message: '请选择发包类型', trigger: 'change' }],
}
const constructionList = ref<Record<string, unknown>[]>([])
const projectOptions = ref<{ id: number; name: string; code?: string }[]>([])
const supplierOptions = ref<{ id: number; name: string }[]>([])

// V0.6.5 Sprint 4: 审核队列
const showReviewQueue = ref(false)
const pendingReviewList = ref<TenderProject[]>([])
const loadingReview = ref(false)
const pendingReviewCount = ref(0)

// V0.6.5 状态机完整 7 状态选项
const STATUS_OPTIONS = [
  { value: 'draft', label: '草稿' },
  { value: 'pending_review', label: '待审核' },
  { value: 'open', label: '已发布' },
  { value: 'closed', label: '已定标' },
  { value: 'withdrawn', label: '已撤回' },
  { value: 'rejected', label: '已驳回' },
  { value: 'cancelled', label: '已废标' },
]

const typeLabel = (t?: string) =>
  t === 'tender' ? '招标' : t === 'rfq' ? '询价' : t === 'negotiation' ? '议价' : t === 'construction' ? '施工询价' : t || '-'

const statusTag = (s: string) => {
  return ({
    draft: 'info',
    pending_review: 'warning',
    open: 'success',
    bidding: 'warning', evaluating: 'primary',
    awarded: 'success', closed: '',
    cancelled: 'danger',
    withdrawn: 'info', rejected: 'danger',
  } as Record<string, '' | 'success' | 'warning' | 'info' | 'primary' | 'danger'>)[s] || ''
}

const fmt = (s?: string) => (s ? s.replace('T', ' ').slice(0, 16) : '-')

const filteredList = computed(() => {
  // 合并招标 + 施工询价
  const tenders = list.value.map((r: Record<string, unknown>) => ({ ...r, _type: 'tender' }))
  const works = constructionList.value.map((r: Record<string, unknown>) => ({
    id: r.id, code: r.code, name: r.title,
    project: r.project, status: r.status,
    deadline: r.deadline, awardedSupplier: r.awardedSupplier,
    bids_count: r.bid_count, _type: 'construction',
  }))
  const all = [...tenders, ...works]
  const kw = (filter.keyword || '').toLowerCase()
  if (kw) return all.filter(r => String(r.code || r.name || '').toLowerCase().includes(kw))
  return all
})

const loadList = async () => {
  loading.value = true
  try {
    const res = await tender.list({
      keyword: filter.keyword || undefined,
      status: filter.status || undefined,
      per_page: 200,
    })
    list.value = unwrapList(res)
  } finally {
    loading.value = false
  }
}

// V0.6.5: 加载审核队列
const loadReviewQueue = async () => {
  loadingReview.value = true
  try {
    const res = await tender.pendingReview()
    const pag = unwrapPaginate(res)
    pendingReviewList.value = pag.list
    pendingReviewCount.value = pag.total
  } catch {
    pendingReviewList.value = []
    pendingReviewCount.value = 0
  } finally {
    loadingReview.value = false
  }
}
const goReview = (row: TenderProject) => {
  showReviewQueue.value = false
  router.push({ name: 'TenderDetail', params: { id: String(row.id) } })
}

const onCreate = () => { currentEdit.value = null; showEditDialog.value = true }
const onEdit = (row: TenderProject) => { currentEdit.value = row; showEditDialog.value = true }

// 施工询价 — 保存
const saveConstruction = async () => {
  const valid = await constructionFormRef.value?.validate().catch(() => false)
  if (!valid) return
  constructionSaving.value = true
  try {
    const payload: Record<string, unknown> = {
      title: constructionForm.title,
      project_id: constructionForm.project_id,
      budget: Number(constructionForm.budget || 0),
      deadline: constructionForm.deadline,
      bid_type: constructionForm.bid_type,
      scope: constructionForm.scope,
      remark: constructionForm.remark,
    }
    if (constructionForm.bid_type === 'internal' && constructionForm.supplier_id) {
      payload.awarded_supplier_id = constructionForm.supplier_id
    }
    await externalWorkApi.create(payload)
    ElMessage.success('施工询价已创建')
    showConstructionDialog.value = false
    loadConstructionList()
  } catch { /* 拦截器已提示 */ }
  finally { constructionSaving.value = false }
}

// 加载施工询价列表
const loadConstructionList = async () => {
  try {
    const res = await externalWorkApi.list({ per_page: 200 })
    constructionList.value = unwrapList(res)
  } catch { constructionList.value = [] }
}

const loadProjectOptions = async () => {
  try {
    const res = await getProjectList({ per_page: 500 })
    projectOptions.value = unwrapList(res).map((p: Record<string, unknown>) => ({ id: p.id as number, name: p.name as string, code: p.code as string }))
  } catch { projectOptions.value = [] }
}

const loadSupplierOptions = async () => {
  try {
    const res = await get('/suppliers', { per_page: 500 })
    const d = (res as { data?: { data?: unknown[] } })?.data?.data || (res as { data?: unknown[] })?.data || []
    supplierOptions.value = (d as { id: number; name: string }[]) || []
  } catch { supplierOptions.value = [] }
}

// 重置施工询价表单
const resetConstructionForm = () => {
  constructionForm.title = ''
  constructionForm.project_id = null
  constructionForm.budget = 0
  constructionForm.deadline = ''
  constructionForm.bid_type = 'public'
  constructionForm.supplier_id = null
  constructionForm.scope = ''
  constructionForm.remark = ''
}
// 打开施工询价弹窗时加载选项
watch(showConstructionDialog, (v) => { if (v) { resetConstructionForm(); loadSupplierOptions() } })

const goDetail = (row: Record<string, unknown>) => {
  if (row._type === 'construction') {
    router.push(`/construction/external-work/${row.id}`)
  } else {
    router.push({ name: 'TenderDetail', params: { id: String(row.id) } })
  }
}

// V0.6.5: 列表行操作 — 用 submit-review 替代旧 publish
const onSubmitReviewRow = async (row: TenderProject) => {
  try {
    await ElMessageBox.confirm(`提交「${row.name}」进入待审核状态?`, '提交审核', { type: 'warning' })
  } catch { return }
  try {
    await tender.submitReview(row.id)
    ElMessage.success('已提交审核')
    loadList()
    loadReviewQueue()
  } catch (e: unknown) {
    ElMessage.error(e?.message || '提交失败')
  }
}

const onPublish = async (row: TenderProject) => {
  try {
    await ElMessageBox.confirm(`确认发布「${row.name}」? (旧接口, 推荐用「提交审核」走工作流)`, '发布确认', { type: 'success' })
  } catch { return }
  await tender.publish(row.id)
  ElMessage.success('已发布')
  loadList()
}

const onCancel = async (row: TenderProject) => {
  try {
    await ElMessageBox.confirm(`确认取消「${row.name}」?`, '取消确认', { type: 'warning' })
  } catch { return }
  await tender.cancel(row.id)
  ElMessage.success('已取消')
  loadList()
}

onMounted(() => {
  loadList()
  loadConstructionList()
  loadProjectOptions()
  loadReviewQueue()
})
</script>

<style scoped lang="scss">
.page-container { padding: 16px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.page-title { font-size: 18px; font-weight: 600; }
.header-actions { display: flex; gap: 8px; }
.filter-bar { margin-bottom: 12px; }
</style>
