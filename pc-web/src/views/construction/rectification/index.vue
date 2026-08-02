<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">整改工单</span>
      <div class="header-actions">
        <ScopeToggle @change="loadList" />
        <el-button :icon="Refresh" plain @click="loadList">刷新</el-button>
        <el-button type="primary" :icon="Plus" @click="handleAdd">新建整改</el-button>
      </div>
    </div>

    <div class="filter-bar">
      <el-form :inline="true" :model="searchForm" @submit.prevent="handleSearch">
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="全部" clearable style="width: 140px">
            <el-option v-for="s in statusOptions" :key="s.value" :label="s.label" :value="s.value" />
          </el-select>
        </el-form-item>
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
        <el-form-item label="关键词">
          <el-input v-model="searchForm.keyword" placeholder="编号 / 整改内容" clearable style="width: 220px" />
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
        <el-table-column prop="code" label="整改编号" width="160" fixed show-overflow-tooltip />
        <el-table-column label="项目" min-width="180" show-overflow-tooltip>
          <template #default="{ row }">{{ row.project?.name || row.project_id || '-' }}</template>
        </el-table-column>
        <el-table-column prop="title" label="整改内容" min-width="240" show-overflow-tooltip />
        <el-table-column label="责任人" width="100" align="center">
          <template #default="{ row }">{{ row.responsible?.name || row.responsible_id || '-' }}</template>
        </el-table-column>
        <el-table-column prop="deadline" label="截止日期" width="120" align="center" />
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="statusTagType(row.status)" effect="plain" size="small">
              {{ statusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="160" align="center" show-overflow-tooltip />
        <el-table-column label="操作" width="180" fixed="right" align="center">
          <template #default="{ row }">
            <el-button link type="primary" :icon="View" @click="handleView(row)">详情</el-button>
            <el-button
              v-if="row.status !== 'completed'"
              link
              type="success"
              :icon="CircleCheck"
              @click="handleComplete(row)"
            >完成</el-button>
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

    <!-- 简易 dialog -->
    <el-dialog
      v-model="showFormDialog"
      title="新建整改工单"
      width="720px"
      :close-on-click-modal="false"
    >
      <el-form ref="formRef" :model="formData" :rules="formRules" label-width="100px">
        <el-form-item label="项目" prop="project_id">
          <el-select v-model="formData.project_id" filterable placeholder="请选择" style="width: 100%">
            <el-option
              v-for="p in projectOptions"
              :key="p.id"
              :label="`${p.code ? p.code + ' - ' : ''}${p.name || ''}`"
              :value="p.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="来源" prop="source_type">
          <el-select v-model="formData.source_type" placeholder="请选择" style="width: 100%">
            <el-option label="巡检" value="inspection" />
            <el-option label="巡查" value="patrol" />
            <el-option label="投诉" value="complaint" />
            <el-option label="审计" value="audit" />
            <el-option label="其他" value="other" />
          </el-select>
        </el-form-item>
        <el-form-item label="严重程度" prop="severity">
          <el-select v-model="formData.severity" placeholder="请选择" style="width: 100%">
            <el-option label="低" value="low" />
            <el-option label="中" value="medium" />
            <el-option label="高" value="high" />
            <el-option label="紧急" value="critical" />
          </el-select>
        </el-form-item>
        <el-form-item label="整改内容" prop="title">
          <el-input v-model="formData.title" type="textarea" :rows="2" />
        </el-form-item>
        <el-form-item label="详细描述" prop="description">
          <el-input v-model="formData.description" type="textarea" :rows="3" />
        </el-form-item>
        <el-form-item label="责任人" prop="responsible_id">
          <el-select v-model="formData.responsible_id" filterable placeholder="请选择" style="width: 100%">
            <el-option v-for="u in userOptions" :key="u.id" :label="u.name" :value="u.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="截止日期" prop="deadline">
          <el-date-picker
            v-model="formData.deadline"
            type="date"
            value-format="YYYY-MM-DD"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="问题图片">
          <el-button type="primary" :icon="Plus" @click="triggerFormUpload">添加图片</el-button>
          <input ref="formFileRef" type="file" multiple accept="image/*" style="display:none" @change="handleFormFileChange" />
          <div v-if="formPhotoList.length" class="photo-list" style="margin-top:8px">
            <div v-for="(img, idx) in formPhotoList" :key="idx" class="photo-item">
              <el-image :src="img.url" fit="cover" style="width:80px;height:80px;border-radius:4px" />
              <el-button link type="danger" size="small" @click="formPhotoList.splice(idx, 1); formImagesData.splice(idx, 1)" style="position:absolute;top:-4px;right:-4px"><el-icon><Close /></el-icon></el-button>
            </div>
          </div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showFormDialog = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="handleSave">保存</el-button>
      </template>
    </el-dialog>

    <!-- 详情弹窗 -->
    <el-dialog
      v-model="showDetailDialog"
      :title="'整改工单详情 — ' + (viewDetail?.code || '')"
      width="900px"
      :close-on-click-modal="false"
    >
      <div v-loading="detailLoading">
        <template v-if="viewDetail">
          <el-descriptions :column="3" border size="default">
            <el-descriptions-item label="整改编号">{{ viewDetail.code || '-' }}</el-descriptions-item>
            <el-descriptions-item label="项目">{{ viewDetail.project?.name || viewDetail.project_id || '-' }}</el-descriptions-item>
            <el-descriptions-item label="责任人">{{ viewDetail.responsible?.name || viewDetail.responsible_id || '-' }}</el-descriptions-item>
            <el-descriptions-item label="来源">{{ viewDetail.source_type || '-' }}</el-descriptions-item>
            <el-descriptions-item label="严重程度">
              <el-tag :type="viewDetail.severity === 'critical' ? 'danger' : viewDetail.severity === 'high' ? 'warning' : viewDetail.severity === 'medium' ? 'primary' : 'info'" size="small">{{ viewDetail.severity }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="截止日期">{{ viewDetail.deadline || '-' }}</el-descriptions-item>
            <el-descriptions-item label="整改内容" :span="3">
              <div style="white-space: pre-wrap">{{ viewDetail.title || '-' }}</div>
            </el-descriptions-item>
            <el-descriptions-item label="详细描述" :span="3">
              <div style="white-space: pre-wrap">{{ viewDetail.description || '-' }}</div>
            </el-descriptions-item>
            <el-descriptions-item v-if="viewDetail.remark" label="整改结果" :span="3">
              <div style="white-space: pre-wrap">{{ viewDetail.remark }}</div>
            </el-descriptions-item>
          </el-descriptions>

          <!-- 整改前图片 -->
          <div v-if="viewImages.length" style="margin-top:16px">
            <h4 style="margin-bottom:8px">整改前图片 ({{ viewImages.length }})</h4>
            <div class="photo-list">
              <div v-for="(img, idx) in viewImages" :key="idx" class="photo-item">
                <el-image :src="img" fit="cover" style="width:120px;height:120px;border-radius:6px;cursor:pointer" :preview-src-list="viewImages" preview-teleported />
              </div>
            </div>
          </div>

          <!-- 完成时上传的整改后图片 -->
          <div v-if="completeImages.length" style="margin-top:16px">
            <h4 style="margin-bottom:8px">整改后图片 ({{ completeImages.length }})</h4>
            <div class="photo-list">
              <div v-for="(img, idx) in completeImages" :key="idx" class="photo-item">
                <el-image :src="img" fit="cover" style="width:120px;height:120px;border-radius:6px;cursor:pointer" :preview-src-list="completeImages" preview-teleported />
              </div>
            </div>
          </div>
        </template>
        <el-empty v-else-if="!detailLoading" description="未找到数据" />
      </div>
      <template #footer>
        <el-button @click="showDetailDialog = false">关闭</el-button>
      </template>
    </el-dialog>

    <!-- 完成弹窗（需上传整改后图片） -->
    <el-dialog
      v-model="showCompleteDialog"
      title="完成整改"
      width="500px"
      :close-on-click-modal="false"
    >
      <el-form label-width="100px">
        <el-form-item label="整改说明">
          <el-input v-model="completeResult" type="textarea" :rows="3" placeholder="请说明整改完成情况（可选）" />
        </el-form-item>
        <el-form-item label="整改后图片" required>
          <el-button type="primary" :icon="Plus" @click="triggerCompleteUpload">添加图片</el-button>
          <input ref="completeFileRef" type="file" multiple accept="image/*" style="display:none" @change="handleCompleteFileChange" />
          <div v-if="completePhotoList.length" class="photo-list" style="margin-top:8px">
            <div v-for="(img, idx) in completePhotoList" :key="idx" class="photo-item">
              <el-image :src="img.url" fit="cover" style="width:80px;height:80px;border-radius:4px" />
              <el-button link type="danger" size="small" @click="completePhotoList.splice(idx, 1); completeImagesData.splice(idx, 1)" style="position:absolute;top:-4px;right:-4px"><el-icon><Close /></el-icon></el-button>
            </div>
          </div>
          <div style="font-size:12px;color:#94a3b8;margin-top:4px">至少上传 1 张图片才能完成</div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCompleteDialog = false">取消</el-button>
        <el-button type="success" :loading="completing" @click="submitComplete">确认完成</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox, type FormInstance } from 'element-plus'
import { Plus, Search, Refresh, View, CircleCheck, Close } from '@element-plus/icons-vue'
import { rectificationApi } from '@/api/construction'
import { getProjectList, getUserList } from '@/api/modules'
import { unwrapList } from '@/utils/response'
import ScopeToggle from '@/components/ScopeToggle.vue'
import type { Rectification, ProjectOption, UserOption } from '../types'

const router = useRouter()

const statusOptions = [
  { value: 'pending',   label: '待处理' },
  { value: 'in_progress', label: '处理中' },
  { value: 'completed', label: '已完成' },
  { value: 'rejected',  label: '已驳回' },
]
const statusLabel = (s: string) => statusOptions.find(x => x.value === s)?.label || s || '-'
const statusTagType = (s: string): string => ({
  pending: 'info', in_progress: 'warning', completed: 'success', rejected: 'danger',
} as Record<string, string>)[s] || 'info'

const loading = ref(false)
const saving = ref(false)
const page = ref(1)
const pageSize = ref(10)
const list = ref<Rectification[]>([])
const projectOptions = ref<ProjectOption[]>([])
const userOptions = ref<UserOption[]>([])

const searchForm = reactive<{ project_id: number | null; status: string; keyword: string }>({
  project_id: null, status: '', keyword: '',
})

const showFormDialog = ref(false)
const formRef = ref<FormInstance | null>(null)
const formData = reactive({
  project_id: null as number | null,
  source_type: 'other',
  severity: 'medium',
  title: '',
  description: '',
  responsible_id: null as number | null,
  deadline: '',
})
// 新建表单图片
const formPhotoList = ref<{ name: string; url: string }[]>([])
const formImagesData = ref<string[]>([])
const formFileRef = ref<HTMLInputElement | null>(null)

// 详情弹窗
const showDetailDialog = ref(false)
const viewDetail = ref<Record<string, unknown> | null>(null)
const detailLoading = ref(false)
const viewImages = computed(() => {
  const imgs = viewDetail.value?.images
  return Array.isArray(imgs) ? imgs : []
})
const completeImages = computed(() => {
  const imgs = viewDetail.value?.complete_images
  return Array.isArray(imgs) ? imgs : []
})

// 完成弹窗
const showCompleteDialog = ref(false)
const completing = ref(false)
const completingTarget = ref<Record<string, unknown> | null>(null)
const completeResult = ref('')
const completePhotoList = ref<{ name: string; url: string }[]>([])
const completeImagesData = ref<string[]>([])
const completeFileRef = ref<HTMLInputElement | null>(null)

const formRules = {
  project_id: [{ required: true, message: '请选择项目', trigger: 'change' }],
  source_type: [{ required: true, message: '请选择来源', trigger: 'change' }],
  severity:    [{ required: true, message: '请选择严重程度', trigger: 'change' }],
  title:       [{ required: true, message: '请填写整改内容', trigger: 'blur' }],
  description: [{ required: true, message: '请填写详细描述', trigger: 'blur' }],
}

const filteredList = computed(() => {
  let arr = [...list.value]
  if (searchForm.project_id) arr = arr.filter(r => Number(r.project_id) === Number(searchForm.project_id))
  if (searchForm.status) arr = arr.filter(r => r.status === searchForm.status)
  if (searchForm.keyword) {
    const kw = searchForm.keyword.toLowerCase()
    arr = arr.filter(r =>
      (r.code || '').toLowerCase().includes(kw) ||
      (r.title || '').toLowerCase().includes(kw)
    )
  }
  return arr
})

const pagedList = computed(() => {
  const start = (page.value - 1) * pageSize.value
  return filteredList.value.slice(start, start + pageSize.value)
})

const loadList = async () => {
  loading.value = true
  try {
    const params: Record<string, unknown> = { per_page: 500, page: 1 }
    if (searchForm.project_id) params.project_id = searchForm.project_id
    if (searchForm.status) params.status = searchForm.status
    if (searchForm.keyword) params.keyword = searchForm.keyword
    const res = await rectificationApi.list(params)
    list.value = unwrapList(res)
  } catch {
    list.value = []
  } finally {
    loading.value = false
  }
}

const loadOptions = async () => {
  try {
    const [p, u] = await Promise.all([
      getProjectList({ per_page: 500 }),
      getUserList({ per_page: 500 }),
    ])
    projectOptions.value = unwrapList(p).map((x: Record<string, unknown>) => ({ id: x.id, code: x.code, name: x.name }))
    userOptions.value = unwrapList(u)
  } catch {
    projectOptions.value = []
    userOptions.value = []
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

const handleAdd = () => {
  formData.project_id = null
  formData.source_type = 'other'
  formData.severity = 'medium'
  formData.title = ''
  formData.description = ''
  formData.responsible_id = null
  formData.deadline = ''
  formPhotoList.value = []
  formImagesData.value = []
  showFormDialog.value = true
}

const handleSave = async () => {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  saving.value = true
  try {
    await rectificationApi.create({
      project_id: formData.project_id,
      source_type: formData.source_type,
      severity: formData.severity,
      title: formData.title,
      description: formData.description || formData.title,
      responsible_id: formData.responsible_id,
      deadline: formData.deadline || null,
      images: formImagesData.value.length ? formImagesData.value : null,
    })
    ElMessage.success('已创建')
    showFormDialog.value = false
    await loadList()
  } catch { /* 拦截器已提示 */ }
  finally { saving.value = false }
}

const goDetail = (row: Rectification) => router.push(`/construction/rectification/${row.id}`)

const handleView = async (row: Rectification) => {
  viewDetail.value = null
  showDetailDialog.value = true
  detailLoading.value = true
  try {
    const res = await rectificationApi.show(row.id)
    viewDetail.value = res?.data || res || null
  } catch {
    viewDetail.value = null
  } finally {
    detailLoading.value = false
  }
}

// 新建表单图片上传
const triggerFormUpload = () => formFileRef.value?.click()
const handleFormFileChange = (e: Event) => {
  const files = (e.target as HTMLInputElement).files
  if (!files) return
  for (let i = 0; i < files.length; i++) {
    const file = files[i]
    const reader = new FileReader()
    reader.onload = (ev) => {
      const url = ev.target?.result as string
      if (url) {
        formPhotoList.value.push({ name: file.name, url })
        formImagesData.value.push(url)
      }
    }
    reader.readAsDataURL(file)
  }
  ;(e.target as HTMLInputElement).value = ''
}

// 完成弹窗
const handleComplete = (row: Record<string, unknown>) => {
  completingTarget.value = row
  completeResult.value = ''
  completePhotoList.value = []
  completeImagesData.value = []
  showCompleteDialog.value = true
}
const triggerCompleteUpload = () => completeFileRef.value?.click()
const handleCompleteFileChange = (e: Event) => {
  const files = (e.target as HTMLInputElement).files
  if (!files) return
  for (let i = 0; i < files.length; i++) {
    const file = files[i]
    const reader = new FileReader()
    reader.onload = (ev) => {
      const url = ev.target?.result as string
      if (url) {
        completePhotoList.value.push({ name: file.name, url })
        completeImagesData.value.push(url)
      }
    }
    reader.readAsDataURL(file)
  }
  ;(e.target as HTMLInputElement).value = ''
}
const submitComplete = async () => {
  if (!completeImagesData.value.length) {
    ElMessage.warning('请至少上传 1 张整改后图片')
    return
  }
  completing.value = true
  try {
    const payload: Record<string, unknown> = {
      rectify_result: completeResult.value || '已完成整改',
      complete_images: completeImagesData.value,
    }
    await rectificationApi.complete(completingTarget.value?.id, payload)
    ElMessage.success('整改已完成')
    showCompleteDialog.value = false
    await loadList()
  } catch { /* 拦截器已提示 */ }
  finally { completing.value = false }
}

onMounted(() => {
  loadOptions()
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
.filter-bar {
  background: #fff; padding: 16px 20px; border-radius: 8px;
  margin-bottom: 12px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
.content-card {
  background: #fff; padding: 20px; border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
.pagination-wrapper { margin-top: 16px; display: flex; justify-content: flex-end; }
</style>
