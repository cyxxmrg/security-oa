<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">技能标签管理</span>
      <el-button type="primary" :icon="Plus" @click="handleAddTag">新增标签</el-button>
    </div>

    <!-- 标签管理区 -->
    <div class="content-card">
      <div class="card-title">标签列表</div>
      <div class="tag-container" v-loading="loading">
        <el-tag
          v-for="tag in skillTags"
          :key="tag.id"
          :color="tag.color || '#409EFF'"
          closable
          size="large"
          class="skill-tag"
          @close="handleDeleteTag(tag)"
          @click="handleEditTag(tag)"
        >
          <span class="tag-name">{{ tag.name }}</span>
          <span class="tag-count">{{ tag.employees_count || 0 }}人</span>
        </el-tag>
        <el-tag
          v-if="!loading && skillTags.length === 0"
          type="info"
          size="large"
          class="skill-tag"
        >
          暂无标签，请点击右上角新增
        </el-tag>
      </div>
    </div>

    <!-- 员工技能分配表格 -->
    <div class="content-card" style="margin-top: 16px">
      <div class="card-title">员工技能分配</div>

      <div class="filter-bar">
        <el-input v-model="empFilter.keyword" placeholder="搜索员工姓名" clearable style="width: 200px">
          <template #prefix><el-icon><Search /></el-icon></template>
        </el-input>
        <el-select v-model="empFilter.department_id" placeholder="选择部门" clearable style="width: 160px">
          <el-option v-for="d in deptList" :key="d.id" :label="d.name" :value="d.id" />
        </el-select>
        <el-button type="primary" @click="handleSearch">查询</el-button>
        <el-button @click="handleResetEmp">重置</el-button>
      </div>

      <el-table :data="empTableData" stripe style="width: 100%" v-loading="loadingEmp">
        <el-table-column type="index" label="序号" width="55" />
        <el-table-column prop="name" label="员工姓名" min-width="90" />
        <el-table-column label="部门" min-width="100">
          <template #default="{ row }">
            {{ row.department?.name || '--' }}
          </template>
        </el-table-column>
        <el-table-column label="岗位" min-width="110">
          <template #default="{ row }">
            {{ row.position?.name || '--' }}
          </template>
        </el-table-column>
        <el-table-column label="技能标签" min-width="280">
          <template #default="{ row }">
            <el-tag
              v-for="skill in (row.skills || [])"
              :key="skill.id"
              :color="skill.color || '#409EFF'"
              size="small"
              class="skill-row-tag"
              closable
              @close="handleRemoveEmpSkill(row, skill)"
            >
              {{ skill.name }}
            </el-tag>
            <el-button type="primary" link size="small" @click="handleAddEmpSkill(row)" v-if="skillTags.length > 0">
              <el-icon><Plus /></el-icon>
              添加
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrapper">
        <el-pagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.pageSize"
          :total="pagination.total"
          :page-sizes="[10, 20, 50]"
          layout="total, sizes, prev, pager, next, jumper"
          @current-change="loadEmp"
          @size-change="loadEmp"
        />
      </div>
    </div>

    <!-- 新增/编辑标签对话框 -->
    <SkillTagDialog
      v-model:visible="tagDialogVisible"
      :is-edit="!!editingTag"
      :form="tagForm"
      :submitting="submitting"
      @submit="handleSubmitTag"
    />

    <!-- 添加员工技能对话框 -->
    <AddSkillDialog
      v-model:visible="skillDialogVisible"
      :form="skillForm"
      :tags="availableTags"
      :emp-name="currentEmpRow?.name"
      :submitting="submitting"
      @submit="handleSubmitSkill"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { Plus, Search } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { get, post, put, del } from '@/utils/request'
import { unwrapList } from '@/utils/response'
import SkillTagDialog from './components/skill/SkillTagDialog.vue'
import AddSkillDialog from './components/skill/AddSkillDialog.vue'

interface SkillTag {
  id: number
  name: string
  category?: string
  color?: string
  description?: string
  employees_count?: number
  [key: string]: unknown
}

interface SkillLite {
  id: number
  name?: string
  color?: string
  level?: string
  [key: string]: unknown
}

interface DepartmentOption {
  id: number
  name: string
  [key: string]: unknown
}

interface EmployeeRow {
  id: number
  name?: string
  department?: { id?: number; name?: string } | null
  position?: { id?: number; name?: string } | null
  skills?: SkillLite[]
  [key: string]: unknown
}

const loading = ref(false)
const loadingEmp = ref(false)
const submitting = ref(false)
const tagDialogVisible = ref(false)
const skillDialogVisible = ref(false)

const tagFormRef = ref()
const editingTag = ref<SkillTag | null>(null)
const currentEmpRow = ref<EmployeeRow | null>(null)

const skillTags = ref<SkillTag[]>([])
const empTableData = ref<EmployeeRow[]>([])
const deptList = ref<DepartmentOption[]>([])

const empFilter = reactive({
  keyword: '',
  department_id: null as number | null,
})

const pagination = reactive({
  page: 1,
  pageSize: 10,
  total: 0,
})

const tagForm = reactive({
  name: '',
  category: 'other',
  color: '#409EFF',
  description: '',
})
const tagRules = {
  name: [{ required: true, message: '请输入标签名称', trigger: 'blur' }],
  category: [{ required: true, message: '请选择分类', trigger: 'change' }],
  color: [{ required: true, message: '请选择颜色', trigger: 'change' }],
}

const skillForm = reactive({
  skillId: null as number | null,
  level: 'intermediate',
})

// 当前员工已有技能
const availableTags = computed(() => {
  if (!currentEmpRow.value) return skillTags.value
  const owned = new Set((currentEmpRow.value.skills || []).map((s: SkillLite) => s.id))
  return skillTags.value.filter(t => !owned.has(t.id))
})

async function loadTags() {
  loading.value = true
  try {
    const res = await get('/employees/skills')
    // axios 拦截器已解包 → res 是后端 data 字段
    // 后端返回 {code:0, data: [...]} → res 是数组
    skillTags.value = unwrapList(res)
  } catch (e) { /* ignore */ }
  loading.value = false
}

async function loadDepts() {
  try {
    const data = await get('/employees/departments')
    deptList.value = data || []
  } catch (e) { /* ignore */ }
}

async function loadEmp() {
  loadingEmp.value = true
  try {
    const params: Record<string, unknown> = { page: pagination.page, per_page: pagination.pageSize }
    if (empFilter.keyword) params.keyword = empFilter.keyword
    if (empFilter.department_id) params.department_id = empFilter.department_id
    const data = await get('/employees', params)
    // V0.6.3+ 响应: {code, data: {data: [...], total, ...}}
    const payload = data?.data ?? data ?? {}
    empTableData.value = Array.isArray(payload) ? payload : (payload?.data ?? payload?.items ?? [])
    pagination.total = Number(
      payload?.total ?? data?.data?.total ?? data?.total ?? 0
    )
  } catch (e) { /* ignore */ }
  loadingEmp.value = false
}

function handleSearch() {
  pagination.page = 1
  loadEmp()
}

function handleResetEmp() {
  empFilter.keyword = ''
  empFilter.department_id = null
  pagination.page = 1
  loadEmp()
}

function handleAddTag() {
  editingTag.value = null
  Object.assign(tagForm, { name: '', category: 'other', color: '#409EFF', description: '' })
  tagDialogVisible.value = true
}

function handleEditTag(tag: SkillTag) {
  editingTag.value = tag
  Object.assign(tagForm, {
    name: tag.name,
    category: tag.category || 'other',
    color: tag.color || '#409EFF',
    description: tag.description || '',
  })
  tagDialogVisible.value = true
}

async function handleSubmitTag() {
  const valid = await tagFormRef.value?.validate().catch(() => false)
  if (!valid) return
  submitting.value = true
  try {
    if (editingTag.value) {
      await put(`/employees/skills/${editingTag.value.id}`, {
        name: tagForm.name,
        category: tagForm.category,
        color: tagForm.color,
        description: tagForm.description || null,
      })
      ElMessage.success('标签已更新')
    } else {
      await post('/employees/skills', {
        name: tagForm.name,
        category: tagForm.category,
        color: tagForm.color,
        description: tagForm.description || null,
      })
      ElMessage.success('标签已创建')
    }
    tagDialogVisible.value = false
    loadTags()
  } catch (e: unknown) {
    ElMessage.error((e as { response?: { data?: { message?: string } } })?.response?.data?.message || '保存失败')
  }
  submitting.value = false
}

async function handleDeleteTag(tag: SkillTag) {
  try {
    await ElMessageBox.confirm(`确定删除标签「${tag.name}」吗？${(tag.employees_count ?? 0) > 0 ? '该标签下还有 ' + tag.employees_count + ' 名员工，将自动解绑' : ''}`, '确认删除', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning',
    })
  } catch { return }
  try {
    const r = await del(`/employees/skills/${tag.id}`)
    ElMessage.success(r?.message || '标签已删除')
    loadTags()
  } catch (e: unknown) {
    ElMessage.error((e as { response?: { data?: { message?: string } } })?.response?.data?.message || '删除失败')
  }
}

function handleAddEmpSkill(row: EmployeeRow) {
  currentEmpRow.value = row
  skillForm.skillId = null
  skillForm.level = 'intermediate'
  skillDialogVisible.value = true
}

async function handleSubmitSkill() {
  if (!skillForm.skillId) { ElMessage.warning('请选择技能标签'); return }
  if (!currentEmpRow.value) return
  submitting.value = true
  try {
    // attach skill to employee via skill_tags employee relationship
    // 后端用 skillTag->employees() attach
    // 没有专门的 endpoint，所以走 skill 自己的控制器 attach
    // 用 employees/skills/{id}/attach?user_id=
    const tag = skillTags.value.find(t => t.id === skillForm.skillId)
    if (!tag) { ElMessage.error('标签不存在'); submitting.value = false; return }
    // 通过后端 skill controller 的 attach
    // 简单起见用 sync endpoint
    await post(`/employees/skills/${tag.id}/attach`, {
      user_id: currentEmpRow.value.id,
      proficiency: skillForm.level,
    })
    ElMessage.success('技能已添加')
    skillDialogVisible.value = false
    loadEmp()
  } catch (e: unknown) {
    ElMessage.error((e as { response?: { data?: { message?: string } } })?.response?.data?.message || '添加失败')
  }
  submitting.value = false
}

async function handleRemoveEmpSkill(row: EmployeeRow, skill: SkillLite) {
  try {
    await ElMessageBox.confirm(`确定从「${row.name}」移除技能「${skill.name}」吗？`, '确认移除', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning',
    })
  } catch { return }
  try {
    await post(`/employees/skills/${skill.id}/detach`, { user_id: row.id })
    ElMessage.success(`已移除技能: ${skill.name}`)
    loadEmp()
  } catch (e: unknown) {
    ElMessage.error((e as { response?: { data?: { message?: string } } })?.response?.data?.message || '移除失败')
  }
}

onMounted(async () => {
  await Promise.all([loadTags(), loadDepts(), loadEmp()])
})
</script>

<style scoped lang="scss">
.page-container {
  padding: 20px;
  background: #f5f7fa;
  min-height: 100%;
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;

  .page-title {
    font-size: 20px;
    font-weight: 600;
    color: #303133;
  }
}

.content-card {
  background: #fff;
  border-radius: 8px;
  padding: 20px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);

  .card-title {
    font-size: 16px;
    font-weight: 600;
    color: #303133;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid #ebeef5;
  }
}

.filter-bar {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 16px;
  align-items: center;
}

.tag-container {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  min-height: 60px;
}

.skill-tag {
  font-size: 14px;
  padding: 6px 14px;
  border: none;
  cursor: pointer;

  .el-tag__close {
    color: #fff !important;
  }

  .tag-name {
    color: #fff;
    margin-right: 6px;
    font-weight: 500;
  }

  .tag-count {
    color: rgba(255, 255, 255, 0.85);
    font-size: 12px;
    padding: 0 6px;
    background: rgba(255, 255, 255, 0.18);
    border-radius: 10px;
  }
}

.skill-row-tag {
  margin-right: 6px;
  margin-bottom: 4px;
  border: none;
}

.pagination-wrapper {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}
</style>
