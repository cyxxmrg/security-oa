<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">组织结构</span>
    </div>

    <div class="content-card">
      <el-tabs v-model="activeTab" type="border-card" v-loading="loading">
        <!-- 部门管理 -->
        <el-tab-pane label="部门管理" name="dept">
          <DeptTab
            :tree="deptTree"
            :tree-props="treeProps"
            @add-dept="handleAddDept"
            @edit-dept="handleEditDept"
            @delete-dept="handleDeleteDept"
            @show-employees="showDeptEmployees"
            @refresh="loadDepts"
          />
        </el-tab-pane>

        <!-- 岗位管理 -->
        <el-tab-pane label="岗位管理" name="position">
          <PositionTab
            v-model:search="positionSearch"
            :paged="pagedPositions"
            :page="positionPage"
            :page-size="positionPageSize"
            :total="filteredPositions.length"
            @search-change="positionPage = 1"
            @add-position="handleAddPosition"
            @edit="handleEditPosition"
            @delete="handleDeletePosition"
            @page-change="(p: number) => positionPage = p"
            @size-change="(s: number) => { positionPageSize = s; positionPage = 1 }"
          />
        </el-tab-pane>

        <!-- 技能标签 -->
        <el-tab-pane label="技能标签" name="skill">
          <SkillTab
            v-model:search="skillSearch"
            :paged="pagedSkills"
            :page="skillPage"
            :page-size="skillPageSize"
            :total="filteredSkills.length"
            :category-label="categoryLabel"
            :category-type="categoryType"
            @search-change="skillPage = 1"
            @add-skill="handleAddSkill"
            @edit="handleEditSkill"
            @delete="handleDeleteSkill"
            @page-change="(p: number) => skillPage = p"
            @size-change="(s: number) => { skillPageSize = s; skillPage = 1 }"
          />
        </el-tab-pane>
      </el-tabs>
    </div>

    <!-- 部门表单对话框 -->
    <DeptFormDialog
      v-model:visible="deptDialogVisible"
      :title="deptDialogTitle"
      :form="deptForm"
      :employee-list="employeeList"
      :saving="deptSaving"
      @save="handleDeptSave"
    />

    <!-- 部门员工列表 -->
    <DeptEmployeesDialog
      v-model:visible="deptEmpDialogVisible"
      :title="deptEmpDialogTitle"
      :employees="deptEmployees"
    />

    <!-- 岗位表单对话框 -->
    <PositionFormDialog
      v-model:visible="positionDialogVisible"
      :title="positionDialogTitle"
      :form="positionForm"
      :dept-list="deptList"
      :saving="positionSaving"
      @save="handlePositionSave"
    />

  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { Plus, RefreshRight, Edit, Delete, FolderOpened } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { get, post, put, del } from '@/utils/request'
import { unwrapList } from '@/utils/response'
import DeptTab, { type DeptNode } from './components/organization/DeptTab.vue'
import PositionTab, { type PositionRow } from './components/organization/PositionTab.vue'
import SkillTab, { type SkillRow } from './components/organization/SkillTab.vue'
import DeptFormDialog from './components/organization/DeptFormDialog.vue'
import PositionFormDialog from './components/organization/PositionFormDialog.vue'
import SkillFormDialog from './components/organization/SkillFormDialog.vue'
import DeptEmployeesDialog from './components/organization/DeptEmployeesDialog.vue'

interface DeptFlat {
  id: number
  name: string
  parent_id: number | null
  manager: string | null
  manager_id?: number | null
  count: number
  sort_order: number
  description?: string
  status?: string
  [key: string]: unknown
}

// API 错误
interface ApiError {
  message?: string
  response?: { data?: { message?: string } }
}

// 员工
interface EmployeeItem {
  id: number
  name: string
  username: string
  department_id?: number
  [key: string]: unknown
}

const activeTab = ref('dept')
const loading = ref(false)
const treeProps = { children: 'children', label: 'label' }

// ========== 部门管理 ==========
const deptList = ref<DeptFlat[]>([])
const employeeList = ref<{ id: number; name: string; username: string }[]>([])
const deptDialogVisible = ref(false)
const deptDialogTitle = ref('新增部门')
const editingDeptId = ref<number | null>(null)
const deptForm = reactive({ name: '', parentName: '', manager_id: null as number | null, sort_order: 0, description: '', parent_id: null as number | null })
const deptSaving = ref(false)

// 树形数据
const deptTree = computed<DeptNode[]>(() => {
  const map = new Map<number, DeptNode>()
  const roots: DeptNode[] = []
  // 第一遍:建节点（label 字段必须填，否则 el-tree 不显示文字）
  deptList.value.forEach(d => {
    map.set(d.id, {
      id: d.id,
      label: d.name,        // el-tree 用 label 字段
      name: d.name,
      count: d.count,
      manager: d.manager,
      children: [],
    })
  })
  // 第二遍:建树
  deptList.value.forEach(d => {
    const node = map.get(d.id)!
    if (d.parent_id && map.has(d.parent_id)) {
      map.get(d.parent_id)!.children!.push(node)
    } else {
      roots.push(node)
    }
  })
  return roots
})

async function loadDepts() {
  loading.value = true
  try {
    const res = await get('/employees/departments') // 动态响应结构
    // V1.2.10 用 unwrapList 统一解包
    deptList.value = unwrapList(res)
    if (unwrapList(res).length === 0 && res) {
      console.warn('[org] unexpected departments response:', res)
    }
  } catch (e) {
    /* request.ts already toasted */
  } finally {
    loading.value = false
  }
}

async function loadEmployees() {
  try {
    // 拉取员工列表（按 status 排序），给部门主管/项目成员下拉用
    // V0.6.3: res = {code, data: paginator}
    const res = await get('/employees', { per_page: 500 })
    const list = unwrapList(res)
    employeeList.value = list.map((u: EmployeeItem) => ({ id: u.id, name: u.name, username: u.username }))
  } catch (e) {
    /* fallback */
  }
}

function findDept(id: number): DeptFlat | undefined {
  return deptList.value.find(d => d.id === id)
}

function handleAddDept(parent: DeptNode | null) {
  editingDeptId.value = null
  deptDialogTitle.value = parent ? `新增子部门 - ${parent.name}` : '新增顶级部门'
  Object.assign(deptForm, {
    name: '',
    parentName: parent ? parent.name : '顶级部门',
    manager_id: null,
    sort_order: 0,
    description: '',
    parent_id: parent ? parent.id : null,
  })
  deptDialogVisible.value = true
}

function handleEditDept(data: DeptNode) {
  editingDeptId.value = data.id
  const flat = findDept(data.id)
  deptDialogTitle.value = `编辑部门 - ${data.name}`
  Object.assign(deptForm, {
    name: flat?.name ?? data.name,
    parentName: flat?.parent_id ? findDept(flat.parent_id)?.name ?? '' : '顶级部门',
    manager_id: flat?.manager_id ?? null,
    sort_order: flat?.sort_order ?? 0,
    description: flat?.description ?? '',
    parent_id: flat?.parent_id ?? null,
  })
  deptDialogVisible.value = true
}

async function handleDeleteDept(data: DeptNode) {
  try {
    const r = await del(`/employees/departments/${data.id}`) // 动态响应结构
    // request.ts 解包后 r 可能是 {message, code} 形式；判断 r.code === 0
    if (!r || r.code === 0 || r.message) {
      ElMessage.success(r?.message || `部门「${data.name}」已删除`)
      loadDepts()
    } else {
      ElMessage.error(r.message ?? '删除失败')
    }
  } catch (e: unknown) {
    const err = e as ApiError
    if (err?.response?.data?.message) {
      ElMessage.error(err.response.data.message)
    }
  }
}

// 显示部门员工
const deptEmpDialogVisible = ref(false)
const deptEmpDialogTitle = ref('')
const deptEmployees = ref<EmployeeItem[]>([])
async function showDeptEmployees(data: DeptNode) {
  deptEmpDialogTitle.value = `${data.name} - 员工列表`
  deptEmpDialogVisible.value = true
  try {
    // 查所有用户，过滤部门
    // V0.6.3: res = {code, data: paginator}
    const res = await get('/employees', { per_page: 500, status: 'active' })
    const list = unwrapList(res)
    deptEmployees.value = list.filter((u: EmployeeItem) => u.department_id === data.id)
    if (deptEmployees.value.length === 0) {
      ElMessage.info(`「${data.name}」暂无员工`)
    }
  } catch (e) {
    deptEmployees.value = []
  }
}

async function handleDeptSave() {
  if (!deptForm.name) {
    ElMessage.warning('请输入部门名称')
    return
  }
  deptSaving.value = true
  try {
    const payload = {
      name: deptForm.name,
      parent_id: deptForm.parent_id,
      manager_id: deptForm.manager_id || null,
      sort_order: deptForm.sort_order,
      description: deptForm.description || null,
    }
    if (editingDeptId.value) {
      const r = await put(`/employees/departments/${editingDeptId.value}`, payload) // 动态响应结构
      // request.ts 解包后 r 是 {id, name, ...} 形式（PUT 的 data 字段）
      if (r && (r.id || r.code === 0)) {
        ElMessage.success('部门信息已更新')
        deptDialogVisible.value = false
        loadDepts()
      } else {
        ElMessage.error(r?.message ?? '更新失败')
      }
    } else {
      const r = await post('/employees/departments', payload) // 动态响应结构
      if (r && (r.id || r.code === 0)) {
        ElMessage.success('新部门已创建')
        deptDialogVisible.value = false
        loadDepts()
      } else {
        ElMessage.error(r?.message ?? '创建失败')
      }
    }
  } catch (e: unknown) {
    const err = e as ApiError
    if (err?.response?.data?.message) ElMessage.error(err.response.data.message)
  } finally {
    deptSaving.value = false
  }
}

// ========== 岗位管理 ==========
const positionSearch = ref('')
const positions = ref<PositionRow[]>([])
const positionDialogVisible = ref(false)
const positionDialogTitle = ref('新增岗位')
const editingPositionId = ref<number | null>(null)
const positionForm = reactive({ name: '', department_id: null as number | null, level: 'P5', description: '', sort_order: 0 })
const positionSaving = ref(false)
const positionPage = ref(1)
const positionPageSize = ref(10)

const filteredPositions = computed(() => {
  if (!positionSearch.value) return positions.value
  const kw = positionSearch.value.toLowerCase()
  return positions.value.filter(p => p.name.toLowerCase().includes(kw) || (p.department ?? '').includes(kw))
})

const pagedPositions = computed(() => {
  const start = (positionPage.value - 1) * positionPageSize.value
  return filteredPositions.value.slice(start, start + positionPageSize.value)
})

async function loadPositions() {
  try {
    const res = await get('/employees/positions') // 动态响应结构
    positions.value = unwrapList(res)
  } catch (e) { /* handled */ }
}

function handleAddPosition() {
  editingPositionId.value = null
  positionDialogTitle.value = '新增岗位'
  Object.assign(positionForm, { name: '', department_id: null, level: 'P5', description: '', sort_order: 0 })
  positionDialogVisible.value = true
}

function handleEditPosition(row: PositionRow) {
  editingPositionId.value = row.id
  positionDialogTitle.value = `编辑岗位 - ${row.name}`
  Object.assign(positionForm, {
    name: row.name,
    department_id: row.department_id,
    level: row.level || 'P5',
    description: row.description ?? '',
    sort_order: 0,
  })
  positionDialogVisible.value = true
}

async function handleDeletePosition(row: PositionRow) {
  try {
    const r = await del(`/employees/positions/${row.id}`) // 动态响应结构
    if (r.code === 0) {
      ElMessage.success(`岗位「${row.name}」已删除`)
      loadPositions()
    } else {
      ElMessage.error(r.message ?? '删除失败')
    }
  } catch (e: unknown) {
    const err = e as ApiError
    if (err?.response?.data?.message) ElMessage.error(err.response.data.message)
  }
}

async function handlePositionSave() {
  if (!positionForm.name) {
    ElMessage.warning('请输入岗位名称')
    return
  }
  if (!positionForm.department_id) {
    ElMessage.warning('请选择所属部门')
    return
  }
  positionSaving.value = true
  try {
    const payload = { ...positionForm }
    if (editingPositionId.value) {
      const r = await put(`/employees/positions/${editingPositionId.value}`, payload) // 动态响应结构
      if (r.code === 0) {
        ElMessage.success('岗位信息已更新')
        positionDialogVisible.value = false
        loadPositions()
      } else ElMessage.error(r.message ?? '更新失败')
    } else {
      const r = await post('/employees/positions', payload) // 动态响应结构
      if (r.code === 0) {
        ElMessage.success('新岗位已创建')
        positionDialogVisible.value = false
        loadPositions()
      } else ElMessage.error(r.message ?? '创建失败')
    }
  } catch (e: unknown) {
    const err = e as ApiError
    if (err?.response?.data?.message) ElMessage.error(err.response.data.message)
  } finally {
    positionSaving.value = false
  }
}

// ========== 技能标签 ==========
const skillSearch = ref('')
const skills = ref<SkillRow[]>([])
const skillDialogVisible = ref(false)
const skillDialogTitle = ref('新增技能')
const editingSkillId = ref<number | null>(null)
const skillForm = reactive({ name: '', category: 'other', color: '#0C447C', description: '' })
const skillSaving = ref(false)
const skillPage = ref(1)
const skillPageSize = ref(10)

const filteredSkills = computed(() => {
  if (!skillSearch.value) return skills.value
  const kw = skillSearch.value.toLowerCase()
  return skills.value.filter(s => s.name.toLowerCase().includes(kw) || s.category.includes(kw))
})

const pagedSkills = computed(() => {
  const start = (skillPage.value - 1) * skillPageSize.value
  return filteredSkills.value.slice(start, start + skillPageSize.value)
})

const categoryLabel = (c: string): string => ({
  install: '安装', debug: '调试', network: '网络', cloud: '云服务', maintain: '运维', other: '其他',
} as Record<string, string>)[c] ?? c

const categoryType = (c: string): 'success' | 'warning' | 'info' | 'danger' | 'primary' =>
  (({ install: 'primary', debug: 'success', network: 'warning', cloud: 'info', maintain: 'danger', other: 'info' }) as Record<string, 'success' | 'warning' | 'info' | 'danger' | 'primary'>)[c] ?? 'info'

async function loadSkills() {
  try {
    const res = await get('/employees/skills') // 动态响应结构
    skills.value = unwrapList(res)
  } catch (e) { /* handled */ }
}

function handleAddSkill() {
  editingSkillId.value = null
  skillDialogTitle.value = '新增技能'
  Object.assign(skillForm, { name: '', category: 'other', color: '#0C447C', description: '' })
  skillDialogVisible.value = true
}

function handleEditSkill(row: SkillRow) {
  editingSkillId.value = row.id
  skillDialogTitle.value = `编辑技能 - ${row.name}`
  Object.assign(skillForm, { name: row.name, category: row.category || 'other', color: row.color || '#0C447C', description: row.description ?? '' })
  skillDialogVisible.value = true
}

async function handleDeleteSkill(row: SkillRow) {
  try {
    const r = await del(`/employees/skills/${row.id}`) // 动态响应结构
    if (r.code === 0) {
      ElMessage.success(r.message ?? `技能「${row.name}」已删除`)
      loadSkills()
    } else {
      ElMessage.error(r.message ?? '删除失败')
    }
  } catch (e: unknown) {
    const err = e as ApiError
    if (err?.response?.data?.message) ElMessage.error(err.response.data.message)
  }
}

async function handleSkillSave() {
  if (!skillForm.name) {
    ElMessage.warning('请输入技能名称')
    return
  }
  skillSaving.value = true
  try {
    const payload = { ...skillForm }
    if (editingSkillId.value) {
      const r = await put(`/employees/skills/${editingSkillId.value}`, payload) // 动态响应结构
      if (r.code === 0) {
        ElMessage.success('技能信息已更新')
        skillDialogVisible.value = false
        loadSkills()
      } else ElMessage.error(r.message ?? '更新失败')
    } else {
      const r = await post('/employees/skills', payload) // 动态响应结构
      if (r.code === 0) {
        ElMessage.success('新技能已创建')
        skillDialogVisible.value = false
        loadSkills()
      } else ElMessage.error(r.message ?? '创建失败')
    }
  } catch (e: unknown) {
    const err = e as ApiError
    if (err?.response?.data?.message) ElMessage.error(err.response.data.message)
  } finally {
    skillSaving.value = false
  }
}

onMounted(() => {
  loadDepts()
  loadEmployees()
  loadPositions()
  loadSkills()
})
</script>

<style scoped lang="scss">
.page-container {
  padding: 20px;
  background: #f5f7fa;
  min-height: 100%;
}

.page-header {
  margin-bottom: 20px;
  .page-title {
    font-size: 20px;
    font-weight: 600;
    color: #303133;
  }
}

.content-card {
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
  overflow: hidden;
}

.tab-toolbar {
  padding: 16px 0;
}

.tree-node {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex: 1;
  padding: 4px 0;

  &__label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: #303133;
  }

  &__count {
    margin-left: 4px;
  }

  &__manager {
    font-size: 12px;
    color: #909399;
    &::before {
      content: '主管: ';
    }
  }

  &__actions {
    display: flex;
    gap: 4px;
  }
}
</style>
