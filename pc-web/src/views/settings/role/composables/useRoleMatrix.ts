// 权限矩阵页 composable — 数据加载 / 勾选状态管理 / 保存
// 从 role/Matrix.vue <script setup> 抽出, 主组件 + 子组件共享
import { ref, computed, onMounted, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { get, post } from '@/utils/request'
import { unwrapStats } from '@/utils/response'

// 角色
interface RoleItem {
  id?: number
  name: string
  description?: string
  is_system?: boolean
  [key: string]: unknown
}

// 菜单叶子（页面）
interface MenuLeaf {
  path: string
  title: string
  perm_key?: string
  perm_exists?: boolean
  [key: string]: unknown
}

// 菜单分组（含叶子）
interface MenuGroup {
  path: string
  title: string
  leaves: MenuLeaf[]
  [key: string]: unknown
}

// 菜单矩阵响应
interface MenuMatrixResponse {
  roles?: RoleItem[]
  menus?: MenuGroup[]
  rolePerms?: Record<string, string[]>
}

// API 错误
interface ApiError {
  message?: string
}

export function useRoleMatrix() {
  // ===== 数据 =====
  const allRoles = ref<RoleItem[]>([])
  const allMenus = ref<MenuGroup[]>([])
  const rolePermsMap = ref<Record<string, string[]>>({})
  const activeRole = ref('')
  const loadingRoles = ref(false)
  const loadingMenus = ref(false)
  const saving = ref(false)
  const searchKeyword = ref('')
  const roleFilter = ref('')
  const showOnlyMine = ref(false)
  const expandedMenus = ref<string[]>([])

  // ===== 当前角色状态 =====
  const checkedLeaves = ref<Set<string>>(new Set())

  const editingRole = computed(() => allRoles.value.find(r => r.name === activeRole.value))

  const ownPerms = computed<Set<string>>(() => new Set(rolePermsMap.value[activeRole.value] || []))

  const totalLeaves = computed(() => {
    let n = 0
    for (const m of allMenus.value) {
      if (m.path === 'admin') continue
      n += m.leaves.filter((l: MenuLeaf) => l.perm_key).length
    }
    return n
  })

  const checkedCount = computed(() => {
    let n = 0
    for (const key of checkedLeaves.value) {
      const [parent, child] = key.split('|')
      const menu = allMenus.value.find(m => m.path === parent)
      const leaf = menu?.leaves.find((l: MenuLeaf) => l.path === child)
      if (leaf?.perm_key && menu?.path !== 'admin') n++
    }
    return n
  })

  const missingPermCount = computed(() => {
    let n = 0
    for (const m of allMenus.value) {
      for (const l of m.leaves) {
        if (l.perm_key && !l.perm_exists && m.path !== 'admin') n++
      }
    }
    return n
  })

  // ===== 过滤 =====
  const filteredRoles = computed(() => {
    if (!roleFilter.value) return allRoles.value
    const kw = roleFilter.value.toLowerCase()
    return allRoles.value.filter(r =>
      r.name.toLowerCase().includes(kw) ||
      (r.description || '').toLowerCase().includes(kw)
    )
  })

  const visibleMenus = computed(() => {
    let menus = allMenus.value
    if (searchKeyword.value) {
      const kw = searchKeyword.value.toLowerCase()
      menus = menus
        .map(m => {
          const matchedLeaves = m.leaves.filter((l: MenuLeaf) =>
            l.title.toLowerCase().includes(kw) || m.title.toLowerCase().includes(kw) || (l.perm_key || '').toLowerCase().includes(kw)
          )
          const menuMatch = m.title.toLowerCase().includes(kw)
          return { ...m, leaves: menuMatch ? m.leaves : matchedLeaves }
        })
        .filter(m => m.leaves.length > 0)
    }
    if (showOnlyMine.value) {
      menus = menus
        .map(m => ({ ...m, leaves: m.leaves.filter((l: MenuLeaf) => isLeafCheckedByKey(m.path + '|' + l.path)) }))
        .filter(m => m.leaves.length > 0)
    }
    return menus
  })

  function visibleLeaves(menu: MenuGroup) {
    if (searchKeyword.value) return menu.leaves
    if (showOnlyMine.value) return menu.leaves.filter((l: MenuLeaf) => isLeafCheckedByKey(menu.path + '|' + l.path))
    return menu.leaves
  }

  function countOwnLeaves(roleName: string) {
    const perms = rolePermsMap.value[roleName] || []
    const keys = new Set<string>()
    for (const m of allMenus.value) {
      for (const l of m.leaves) {
        if (l.perm_key && perms.includes(l.perm_key)) {
          keys.add(m.path + '|' + l.path)
        }
      }
    }
    return keys.size
  }

  // ===== 加载 =====
  async function fetchMenuMatrix() {
    loadingRoles.value = true
    loadingMenus.value = true
    try {
      const res = await get('/roles/menu-matrix')
      const d = unwrapStats<MenuMatrixResponse>(res)
      allRoles.value = (d.roles || []).map((r: RoleItem) => ({
        ...r,
        is_system: r.name === 'admin' || r.name === 'system_admin' || r.name === 'manager' || r.name === 'user' || r.name === 'finance' || r.name === 'system',
      }))
      // V1.2.10: 按新菜单分组重新归类 (后端返回 23 个旧组 → 前端合并成 9 个新组)
      const GROUP_MAP: Record<string, { title: string; order: number }> = {
        dashboard: { title: '工作台', order: 1 }, 'project-overview': { title: '工作台', order: 1 },
        analytics: { title: '工作台', order: 1 }, message: { title: '工作台', order: 1 },
        screen: { title: '工作台', order: 1 }, approval: { title: '工作台', order: 1 },
        customer: { title: '销售中心', order: 2 }, sales: { title: '销售中心', order: 2 },
        project: { title: '项目管理', order: 3 }, construction: { title: '项目管理', order: 3 }, inspection: { title: '项目管理', order: 3 },
        maintenance: { title: '维修中心', order: 4 },
        'purchase-collab': { title: '采购管理', order: 5 },
        inventory: { title: '仓库管理', order: 6 },
        finance: { title: '财务中心', order: 7 }, expense: { title: '财务中心', order: 7 },
        employee: { title: '行政人事', order: 8 }, attendance: { title: '行政人事', order: 8 },
        vehicle: { title: '行政人事', order: 8 }, knowledge: { title: '行政人事', order: 8 }, disk: { title: '行政人事', order: 8 },
        settings: { title: '系统设置', order: 9 }, admin: { title: '系统设置', order: 9 },
      }
      const groupedMenus: Record<string, MenuGroup> = {}
      for (const m of (d.menus || [])) {
        const g = GROUP_MAP[m.path] || { title: m.title, order: 99 }
        if (!groupedMenus[g.title]) {
          groupedMenus[g.title] = { path: g.title, title: g.title, leaves: [] }
        }
        groupedMenus[g.title].leaves.push(...m.leaves)
      }
      allMenus.value = Object.values(groupedMenus).sort((a, b) => {
        const oa = GROUP_MAP[a.path]?.order ?? 99
        const ob = GROUP_MAP[b.path]?.order ?? 99
        return oa - ob
      })
      rolePermsMap.value = d.rolePerms || {}
      if (!activeRole.value && allRoles.value.length) {
        activeRole.value = allRoles.value[0].name
      }
      expandedMenus.value = allMenus.value.filter(m => m.path !== 'admin').map(m => m.path)
      rebuildCheckedLeaves()
    } catch (e) {
      const err = e as ApiError
      ElMessage.error('加载菜单矩阵失败: ' + (err?.message || ''))
    } finally {
      loadingRoles.value = false
      loadingMenus.value = false
    }
  }

  watch(activeRole, () => rebuildCheckedLeaves())

  function rebuildCheckedLeaves() {
    const set = new Set<string>()
    const perms = ownPerms.value
    for (const m of allMenus.value) {
      for (const l of m.leaves) {
        if (l.perm_key && perms.has(l.perm_key)) {
          set.add(m.path + '|' + l.path)
        }
      }
    }
    checkedLeaves.value = set
  }

  // ===== 操作 =====
  function isLeafCheckedByKey(key: string): boolean {
    return checkedLeaves.value.has(key)
  }
  function isLeafChecked(menu: MenuGroup, leaf: MenuLeaf): boolean {
    return checkedLeaves.value.has(menu.path + '|' + leaf.path)
  }
  function toggleLeaf(menu: MenuGroup, leaf: MenuLeaf, checked: boolean) {
    const key = menu.path + '|' + leaf.path
    const s = new Set(checkedLeaves.value)
    if (checked) s.add(key); else s.delete(key)
    checkedLeaves.value = s
  }
  function isMenuAllChecked(menu: MenuGroup): boolean {
    return menu.leaves.filter((l: MenuLeaf) => l.perm_key && l.perm_exists).every((l: MenuLeaf) => isLeafChecked(menu, l))
  }
  function isMenuIndeterminate(menu: MenuGroup): boolean {
    const valid = menu.leaves.filter((l: MenuLeaf) => l.perm_key && l.perm_exists)
    const c = valid.filter((l: MenuLeaf) => isLeafChecked(menu, l)).length
    return c > 0 && c < valid.length
  }
  function toggleMenu(menu: MenuGroup, checked: boolean) {
    const s = new Set(checkedLeaves.value)
    for (const l of menu.leaves) {
      if (!l.perm_key || !l.perm_exists) continue
      const key = menu.path + '|' + l.path
      if (checked) s.add(key); else s.delete(key)
    }
    checkedLeaves.value = s
  }
  function countChecked(menu: MenuGroup): number {
    return menu.leaves.filter((l: MenuLeaf) => l.perm_key && l.perm_exists && isLeafChecked(menu, l)).length
  }
  function selectAllVisible() {
    const s = new Set(checkedLeaves.value)
    for (const m of visibleMenus.value) toggleMenuInto(m, true, s)
    checkedLeaves.value = s
  }
  function deselectAllVisible() {
    const s = new Set(checkedLeaves.value)
    for (const m of visibleMenus.value) toggleMenuInto(m, false, s)
    checkedLeaves.value = s
  }
  function toggleMenuInto(menu: MenuGroup, checked: boolean, set: Set<string>) {
    for (const l of menu.leaves) {
      if (!l.perm_key || !l.perm_exists) continue
      const key = menu.path + '|' + l.path
      if (checked) set.add(key); else set.delete(key)
    }
  }
  function expandAll() {
    expandedMenus.value = allMenus.value.map(m => m.path)
  }
  function collapseAll() {
    expandedMenus.value = []
  }

  // ===== 保存 =====
  async function saveCurrent() {
    if (!activeRole.value) return
    saving.value = true
    try {
      // V1.2.10: 传 perm_key 列表 (不是 parent|child key)
      const perms: string[] = []
      for (const m of allMenus.value) {
        for (const l of m.leaves) {
          if (checkedLeaves.value.has(m.path + '|' + l.path) && l.perm_key) {
            perms.push(l.perm_key)
          }
        }
      }
      await post(`/roles/${activeRole.value}/menu-permissions`, { leaves: perms })
      ElMessage.success(`已保存「${activeRole.value}」的 ${perms.length} 个界面`)
      await fetchMenuMatrix()
    } catch (e) {
      const err = e as ApiError
      ElMessage.error('保存失败: ' + (err?.message || ''))
    } finally {
      saving.value = false
    }
  }

  async function resetAdminAll() {
    if (activeRole.value !== 'admin') return
    try {
      await ElMessageBox.confirm('将「admin」角色的权限重置为「全部业务界面」, 继续？', '重置确认', { type: 'warning' })
    } catch { return }
    saving.value = true
    try {
      const s = new Set<string>()
      for (const m of allMenus.value) {
        for (const l of m.leaves) {
          if (l.perm_key && l.perm_exists && m.path !== 'admin') {
            s.add(m.path + '|' + l.path)
          }
        }
      }
      checkedLeaves.value = s
      // V1.2.10: 传 perm_key 列表
      const perms: string[] = []
      for (const m of allMenus.value) {
        for (const l of m.leaves) {
          if (s.has(m.path + '|' + l.path) && l.perm_key) {
            perms.push(l.perm_key)
          }
        }
      }
      await post(`/roles/admin/menu-permissions`, { leaves: perms })
      ElMessage.success(`已重置 admin 为 ${perms.length} 个业务界面`)
      await fetchMenuMatrix()
    } catch (e) {
      const err = e as ApiError
      ElMessage.error('重置失败: ' + (err?.message || ''))
    } finally {
      saving.value = false
    }
  }

  onMounted(() => {
    fetchMenuMatrix()
  })

  return {
    allRoles, allMenus, activeRole, loadingRoles, loadingMenus, saving,
    searchKeyword, roleFilter, showOnlyMine, expandedMenus,
    editingRole, totalLeaves, checkedCount, missingPermCount,
    filteredRoles, visibleMenus, visibleLeaves, countOwnLeaves,
    isLeafChecked, toggleLeaf, isMenuAllChecked, isMenuIndeterminate,
    toggleMenu, countChecked, selectAllVisible, deselectAllVisible,
    expandAll, collapseAll, saveCurrent, resetAdminAll, fetchMenuMatrix,
  }
}
