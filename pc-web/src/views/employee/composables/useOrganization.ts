// 员工组织页 composable — 数据加载 / 员工 CRUD / 技能管理
// 从 employee/Organization.vue <script setup> 抽出, 主组件 + 子组件共享
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { User, Files, SwitchButton } from '@element-plus/icons-vue'
import { get, post, put, del } from '@/utils/request'
import { unwrapPaginate } from '@/utils/response'
import type { EmployeeForm } from '../orgTypes'

// ===== 类型定义 =====
interface NamedItem { id: number; name: string; [k: string]: unknown }
interface DepartmentItem extends NamedItem {}
interface PositionItem extends NamedItem { department_id: number }
interface UserItem { id: number; name?: string; username?: string; department_id?: number; position_id?: number; phone?: string; is_active?: boolean; [k: string]: unknown }
interface RoleItem { id: number; name: string; display_name?: string; description?: string; [k: string]: unknown }
interface SkillTag { id: number; name: string; [k: string]: unknown }
interface OrgNode { id: string | number; type?: 'dept' | 'position' | string; label?: string; [k: string]: unknown }
interface MemberItem { id: number; name?: string; username?: string; position?: string; phone?: string; is_active?: boolean }
interface ApiResponse<T = unknown> { data?: T; [k: string]: unknown }
interface ApiError { message?: string; response?: { data?: { message?: string } } }
interface EmployeeListParams { page?: number; per_page?: number; keyword?: string; department_id?: number | null; status?: string; [k: string]: unknown }
interface EmployeePayload {
  name?: string; department_id?: number | null; position_id?: number | null; phone?: string
  email?: string; is_active?: boolean; role_id?: number | null; hire_date?: string
  username?: string; password?: string
}
interface EmployeeSaveResult { id?: number; data?: { id?: number }; temporary_password?: string | null; [k: string]: unknown }
interface EmployeeRow extends UserItem {}

export function useOrganization() {
  const router = useRouter()

  // ============== Tab 切换 ==============
  type TabKey = 'list' | 'onboarding' | 'resignation'
  const activeTab = ref<TabKey>('list')
  const tabs = [
    { key: 'list' as TabKey,        label: '员工列表',   icon: User },
    { key: 'onboarding' as TabKey,  label: '入职档案',   icon: Files },
    { key: 'resignation' as TabKey, label: '离职办理',   icon: SwitchButton },
  ]

  function goOnboardings() {
    router.push('/employee/onboardings')
  }
  function goResignations() {
    router.push('/employee/resignations')
  }

  // ============== 通用 ==============
  const submitting = ref(false)
  const deptList = ref<DepartmentItem[]>([])
  const posList = ref<PositionItem[]>([])
  const userList = ref<UserItem[]>([])
  const roles = ref<RoleItem[]>([])
  const roleNameMap: Record<string, string> = {
    system_admin: '系统管理员',
    admin: '业务管理员',
    manager: '部门经理',
    finance: '财务',
    user: '普通员工',
    sales_manager: '销售经理',
  }

  const normalizeRole = (role: RoleItem) => ({
    ...role,
    display_name: role.display_name || roleNameMap[role.name] || role.description || role.name,
  })

  // ============== Tab 1: 员工列表 ==============
  const selectedDeptId = ref<number | null>(null)
  const listFilters = reactive({ keyword: '', department_id: null as number | null, status: '' })
  const pagination = reactive({ page: 1, pageSize: 10, total: 0 })
  const tableData = ref<EmployeeRow[]>([])
  const loading = ref(false)

  // 部门树选中 → 同步到筛选条
  watch(selectedDeptId, (v) => {
    listFilters.department_id = v
    handleListSearch()
  })

  async function loadEmployees() {
    loading.value = true
    try {
      const params: EmployeeListParams = { page: pagination.page, per_page: pagination.pageSize }
      if (listFilters.keyword)       params.keyword       = listFilters.keyword
      if (listFilters.department_id) params.department_id = listFilters.department_id
      if (listFilters.status)        params.status        = listFilters.status
      const res: ApiResponse<{ data?: EmployeeRow[]; total?: number }> = await get('/employees', params)
      const pag = unwrapPaginate(res)
      tableData.value = pag.list
      pagination.total = pag.total
    } catch (e) {
      console.warn('[loadEmployees]', e)
    } finally {
      loading.value = false
    }
  }

  function handleListSearch() {
    pagination.page = 1
    loadEmployees()
  }
  function handleListReset() {
    listFilters.keyword = ''
    listFilters.department_id = null
    listFilters.status = ''
    selectedDeptId.value = null
    pagination.page = 1
    loadEmployees()
  }

  function onTreeRefresh() {
    loadEmployees()
  }

  // ---- 新建/编辑员工 (dialog 内嵌子组件) ----
  const employeeDialogVisible = ref(false)
  const editingEmployee = ref<EmployeeRow | null>(null)
  const skillOptions = ref<SkillTag[]>([])
  const selectedSkillIds = ref<number[]>([])
  const loadingSkillOptions = ref(false)

  async function loadSkillOptions() {
    loadingSkillOptions.value = true
    try {
      const { data } = await get('/employees/skills')
      const items = Array.isArray(data) ? data : (data as { data?: unknown } | undefined)?.data
      skillOptions.value = Array.isArray(items)
        ? items.map((item) => ({ id: Number((item as Record<string, unknown>).id) || 0, name: String((item as Record<string, unknown>).name ?? '') }))
        : []
    } catch {
      skillOptions.value = []
    } finally {
      loadingSkillOptions.value = false
    }
  }

  async function loadEmployeeSkills(userId: number) {
    try {
      const { data } = await get(`/employees/${userId}/skills`)
      const items = Array.isArray(data) ? data : (data as { data?: unknown } | undefined)?.data
      selectedSkillIds.value = Array.isArray(items)
        ? items.map((item) => Number((item as Record<string, unknown>).id) || 0)
        : []
    } catch {
      selectedSkillIds.value = []
    }
  }

  async function syncEmployeeSkills(userId: number, targetIds: number[]) {
    const { data } = await get(`/employees/${userId}/skills`)
    const currentIds = new Set((Array.isArray(data) ? data : data?.data || []).map((tag: SkillTag) => Number(tag.id)))
    const idsToAttach = targetIds.map((id) => Number(id)).filter((id) => id && !currentIds.has(id))
    const idsToDetach = [...currentIds].filter((id) => !targetIds.some((targetId) => Number(targetId) === id))
    await Promise.all(idsToAttach.map((tagId) => post(`/employees/skills/${tagId}/attach`, { user_id: userId })))
    await Promise.all(idsToDetach.map((tagId) => post(`/employees/skills/${tagId}/detach`, { user_id: userId })))
  }

  function openCreateEmployee() {
    editingEmployee.value = null
    employeeDialogVisible.value = true
    loadSkillOptions()
    selectedSkillIds.value = []
  }

  function openEditEmployee(row: EmployeeRow) {
    editingEmployee.value = row
    employeeDialogVisible.value = true
    loadSkillOptions()
    loadEmployeeSkills(row.id)
  }

  async function submitEmployee({
    form,
    selectedSkillIds: skillIds,
    isEdit,
  }: {
    form: EmployeeForm
    selectedSkillIds: number[]
    isEdit: boolean
  }) {
    submitting.value = true
    try {
      const payload: EmployeePayload = {
        name: form.name,
        department_id: form.department_id,
        position_id: form.position_id,
        phone: form.phone,
        email: form.email,
        is_active: form.is_active,
        role_id: form.role_id || null,
      }
      if (form.hire_date) payload.hire_date = form.hire_date
      let res: EmployeeSaveResult | null = null
      if (isEdit) {
        const employeeId = editingEmployee.value?.id
        if (!employeeId) return
        res = await put(`/employees/${employeeId}`, payload)
        ElMessage.success('员工已更新')
      } else {
        payload.username = form.username
        if (form.password) payload.password = form.password
        res = await post('/employees', payload)
        if (res?.temporary_password) {
          await ElMessageBox.alert(`一次性密码: ${res.temporary_password}`, '员工已创建', {
            type: 'success',
            confirmButtonText: '已妥善记录',
          })
        } else {
          ElMessage.success('员工已创建')
        }
      }
      const savedId = editingEmployee.value?.id || res?.id || res?.data?.id
      if (savedId && skillIds.length) {
        await syncEmployeeSkills(savedId, skillIds)
      }
      employeeDialogVisible.value = false
      loadEmployees()
    } catch (e: unknown) {
      const error = e as ApiError
      ElMessage.error(error?.response?.data?.message || error?.message || '保存失败')
    } finally {
      submitting.value = false
    }
  }

  async function handleDeleteEmployee(row: EmployeeRow) {
    try {
      await del(`/employees/${row.id}`)
      ElMessage.success('员工已删除')
      if (tableData.value.length === 1 && pagination.page > 1) pagination.page -= 1
      loadEmployees()
    } catch (e: unknown) {
      const error = e as ApiError
      ElMessage.error(error?.response?.data?.message || '删除失败')
    }
  }

  // ============== Tab 2: 组织架构（只读） ==============
  const selectedDetailNode = ref<OrgNode | null>(null)
  const memberList = ref<MemberItem[]>([])
  const loadingMembers = ref(false)

  const detailTitle = computed(() => {
    if (!selectedDetailNode.value) return '组织详情'
    const n = selectedDetailNode.value
    if (n.type === 'dept' || n.type === 'position') return n.label
    return '组织详情'
  })
  const detailCount = computed(() => memberList.value.length)
  const subPositionCount = computed(() => {
    if (!selectedDetailNode.value || selectedDetailNode.value.type !== 'dept') return 0
    const deptId = Number(String(selectedDetailNode.value.id).replace('d-', ''))
    return posList.value.filter((p) => p.department_id === deptId).length
  })

  async function loadDetailMembers(node: OrgNode | null) {
    if (!node) {
      memberList.value = []
      return
    }
    loadingMembers.value = true
    try {
      if (node.type === 'dept') {
        const deptId = Number(String(node.id).replace('d-', ''))
        const list = userList.value.filter((u: UserItem) => u.department_id === deptId)
        memberList.value = list.map((u: UserItem) => ({
          id: u.id, name: u.name, username: u.username,
          position: posList.value.find((p) => p.id === u.position_id)?.name || '--',
          phone: u.phone || '--', is_active: u.is_active,
        }))
      } else if (node.type === 'position') {
        const posId = Number(String(node.id).replace('p-', ''))
        const list = userList.value.filter((u: UserItem) => u.position_id === posId)
        memberList.value = list.map((u: UserItem) => ({
          id: u.id, name: u.name, username: u.username,
          position: posList.value.find((p) => p.id === u.position_id)?.name || '--',
          phone: u.phone || '--', is_active: u.is_active,
        }))
      } else {
        memberList.value = userList.value.map((u: UserItem) => ({
          id: u.id, name: u.name, username: u.username,
          position: posList.value.find((p) => p.id === u.position_id)?.name || '--',
          phone: u.phone || '--', is_active: u.is_active,
        }))
      }
    } finally {
      loadingMembers.value = false
    }
  }

  function onOrgNodeClick(node: OrgNode) {
    selectedDetailNode.value = node
    loadDetailMembers(node)
  }

  // ============== 加载 ==============
  async function loadAll() {
    const [d, p, u] = await Promise.all([
      get('/employees/departments'),
      get('/employees/positions'),
      get('/employees', { per_page: 200 }),
    ])
    const toList = <T>(response: unknown): Record<string, unknown>[] => {
      let current: unknown = response
      for (let depth = 0; depth < 3; depth += 1) {
        if (Array.isArray(current)) return current as Record<string, unknown>[]
        if (!current || typeof current !== 'object') return []
        current = (current as Record<string, unknown>).data
      }
      return Array.isArray(current) ? current as Record<string, unknown>[] : []
    }
    deptList.value = toList(d).map((item) => ({ id: Number(item.id) || 0, name: String(item.name ?? '') }))
    posList.value = toList(p).map((item) => ({ id: Number(item.id) || 0, name: String(item.name ?? ''), department_id: Number(item.department_id) || 0 }))
    userList.value = toList(u) as UserItem[]
  }

  onMounted(async () => {
    try {
      await Promise.all([
        loadAll(),
        loadEmployees(),
        (async () => {
          const r = await get('/roles').catch(() => null)
          const roleItems = Array.isArray(r) ? r : (r as ApiResponse<{ data?: unknown }> | null)?.data
          const roleList = Array.isArray(roleItems) ? roleItems : (roleItems as { data?: unknown } | undefined)?.data
          roles.value = (Array.isArray(roleList) ? roleList : []).map((item) => normalizeRole({
            id: Number((item as Record<string, unknown>).id) || 0,
            name: String((item as Record<string, unknown>).name ?? ''),
            display_name: typeof (item as Record<string, unknown>).display_name === 'string' ? (item as Record<string, unknown>).display_name as string : undefined,
            description: typeof (item as Record<string, unknown>).description === 'string' ? (item as Record<string, unknown>).description as string : undefined,
          }))
        })(),
      ])
    } catch (e) {
      /* ignore */
    }
  })

  return {
    activeTab, tabs, goOnboardings, goResignations,
    submitting, deptList, posList, roles,
    selectedDeptId, listFilters, pagination, tableData, loading,
    loadEmployees, handleListSearch, handleListReset, onTreeRefresh,
    employeeDialogVisible, editingEmployee, skillOptions, selectedSkillIds, loadingSkillOptions,
    openCreateEmployee, openEditEmployee, submitEmployee, handleDeleteEmployee,
    selectedDetailNode, memberList, loadingMembers,
    detailTitle, detailCount, subPositionCount,
    loadDetailMembers, onOrgNodeClick,
  }
}
