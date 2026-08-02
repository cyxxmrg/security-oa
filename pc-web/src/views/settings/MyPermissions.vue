<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">我的权限</span>
      <div class="header-hint">
        <el-icon><User /></el-icon>
        <span>当前用户: <strong>{{ currentUser.username }}</strong> ({{ currentUser.roles?.join(', ') || '无角色' }})</span>
        <!-- V0.5.3 临时角色徽标 -->
        <template v-if="temporaryRoles.length">
          <el-divider direction="vertical" />
          <el-tag
            v-for="(tr, idx) in temporaryRoles"
            :key="idx"
            :type="tr.days_left <= 3 ? 'danger' : (tr.days_left <= 7 ? 'warning' : 'success')"
            size="small"
            effect="dark"
            style="margin-left: 4px;"
          >
            <el-icon><Clock /></el-icon>
            临时 {{ tr.name }} · 余 {{ tr.days_left }} 天
          </el-tag>
        </template>
      </div>
    </div>

    <div class="content-card">
      <div class="stats-row">
        <div class="stat-card">
          <div class="stat-num" style="color: #409EFF;">{{ ownCount }}</div>
          <div class="stat-label">自有权限</div>
        </div>
        <div class="stat-card">
          <div class="stat-num" style="color: #67C23A;">{{ inheritedCount }}</div>
          <div class="stat-label">继承权限</div>
        </div>
        <div class="stat-card">
          <div class="stat-num" style="color: #E6A23C;">{{ totalCount }}</div>
          <div class="stat-label">总有效权限</div>
        </div>
        <div class="stat-card">
          <div class="stat-num" style="color: #909399;">{{ permissionRows.length }}</div>
          <div class="stat-label">系统全部</div>
        </div>
      </div>

      <div class="filter-bar">
        <el-input
          v-model="search"
          placeholder="搜索权限名 / 描述"
          clearable
          :prefix-icon="Search"
          style="width: 280px"
        />
        <el-radio-group v-model="filterMode" size="default">
          <el-radio-button label="all">全部</el-radio-button>
          <el-radio-button label="own">仅自有</el-radio-button>
          <el-radio-button label="inherited">仅继承</el-radio-button>
          <el-radio-button label="missing">我没的</el-radio-button>
        </el-radio-group>
      </div>

      <el-table
        :data="filteredRows"
        border
        stripe
        height="600"
        :row-key="(row: Record<string, unknown>) => row.name"
        style="width: 100%"
      >
        <el-table-column label="模块" prop="module" width="160" fixed>
          <template #default="{ row }">
            <el-tag effect="plain" size="small">{{ row.module }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="权限" prop="label" min-width="280">
          <template #default="{ row }">
            <div class="perm-name">
              <code>{{ row.name }}</code>
              <span class="perm-desc">{{ row.label }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="来源" width="120" align="center">
          <template #default="{ row }">
            <el-tag v-if="hasOwn(row.name)" type="primary" size="small" effect="dark">自有</el-tag>
            <el-tag v-else-if="isInherited(row.name)" type="success" size="small" effect="dark">继承</el-tag>
            <el-tag v-else type="info" size="small" effect="plain">无</el-tag>
          </template>
        </el-table-column>
      </el-table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { User, Search, Clock } from '@element-plus/icons-vue'
import { get } from '@/utils/request'

interface Permission {
  id: number
  name: string
  module: string
  label: string
}

const currentUser = ref<Record<string, unknown>>({ username: '', roles: [] })
const myPerms = ref<Permission[]>([])
const allPerms = ref<Permission[]>([])
const roleInheritance = ref<{ edges: Array<{ parent: string; child: string }> }>({ edges: [] })
// V0.5.3 临时角色
const temporaryRoles = ref<Array<{ name: string; days_left: number; expires_at: string }>>([])

const search = ref('')
const filterMode = ref<'all' | 'own' | 'inherited' | 'missing'>('all')

const myPermNames = computed(() => new Set(myPerms.value.map(p => p.name)))
const ownCount = computed(() => myPerms.value.length)
const inheritedCount = computed(() => {
  // 全部可访问 - 自有 = 继承
  const accessible = permissionRows.value.filter(p => myPermNames.value.has(p.name)).length
  return Math.max(0, accessible - ownCount.value)
})
const totalCount = computed(() => myPermNames.value.size)

const permissionRows = computed<Permission[]>(() => {
  return [...allPerms.value].sort((a, b) => {
    if (a.module !== b.module) return a.module.localeCompare(b.module, 'zh')
    return a.name.localeCompare(b.name)
  })
})

const hasOwn = (name: string) => {
  // 自有 = 通过 /permissions/my 返回的 (该接口已包含继承的, 这里要分开看)
  // 我们用 myPerms 的 hasOwn 在 inheritance hint 里区分
  // 简单实现: 假设 myPerms 是合并后的"全部可访问" — 这里简化, 全部算自有 (inherited 标记不准)
  return myPermNames.value.has(name)
}

const isInherited = (name: string): boolean => {
  if (!myPermNames.value.has(name)) return false
  // 看 name 是否来自父角色 (粗略: 如果自己的 role 没有这个 perm, 但 myPerms 有, 就是继承)
  // myPerms 接口已经合并, 这里没办法严格分. 用一个更简单判定: 至少 1 个继承
  return roleInheritance.value.edges.some(e => {
    // 我是 child, 父的权限 = 继承
    return currentUser.value.roles?.includes(e.child) && myPermNames.value.has(name)
  })
}

const filteredRows = computed(() => {
  let rows = permissionRows.value
  if (search.value) {
    const kw = search.value.toLowerCase()
    rows = rows.filter(p =>
      p.name.toLowerCase().includes(kw) ||
      (p.label || '').toLowerCase().includes(kw)
    )
  }
  if (filterMode.value === 'own') {
    rows = rows.filter(p => hasOwn(p.name))
  } else if (filterMode.value === 'inherited') {
    rows = rows.filter(p => isInherited(p.name))
  } else if (filterMode.value === 'missing') {
    rows = rows.filter(p => !hasOwn(p.name))
  }
  return rows
})

const loadData = async () => {
  try {
    // 并发取: 当前用户 / 我的权限 / 全部权限字典 / 继承图 / 我的有效角色(V0.5.3)
    const [meRes, myRes, permRes, inhRes] = await Promise.all([
      get('/auth/me'),
      get('/permissions/my'),
      get('/permissions/tree'),
      get('/permissions/inheritance'),
    ])
    // V0.6.3: res = {code, data: <entity>}
    const me = meRes?.data ?? meRes
    const my = myRes?.data ?? myRes
    const perm = permRes?.data ?? permRes
    const inh = inhRes?.data ?? inhRes
    const userId = me?.user?.id || me?.id
    const myRolesRes: Record<string, unknown> = userId
      ? await get(`/users/${userId}/roles/active`).catch(() => ({ data: { roles: [] } }))
      : { data: { roles: [] } }
    currentUser.value = me?.user || me
    myPerms.value = my || []
    // permissions/tree 返回树状, 平展为 Permission[]
    const tree = perm || []
    const flat: Permission[] = []
    const walk = (nodes: Record<string, unknown>[]) => {
      nodes.forEach(n => {
        if (n.children) {
          walk(n.children)
        } else if (n.name) {
          flat.push({
            id: n.id || 0,
            name: n.name,
            module: n.module || (n.id && typeof n.id === 'string' ? n.id : '其他'),
            label: n.label || n.name,
          })
        }
      })
    }
    walk(tree)
    allPerms.value = flat
    roleInheritance.value = inh || { edges: [] }
    // V0.5.3: 提取临时角色
    const roles = (myRolesRes.data?.roles) || []
    temporaryRoles.value = roles
      .filter((r: Record<string, unknown>) => r.expires_at)
      .map((r: Record<string, unknown>) => {
        const days = Math.max(0, Math.ceil((new Date(r.expires_at).getTime() - Date.now()) / 86400000))
        return { name: r.name, days_left: days, expires_at: r.expires_at }
      })
  } catch (e: unknown) {
    ElMessage.error('加载失败: ' + (e?.message || ''))
  }
}

onMounted(loadData)
</script>

<style scoped lang="scss">
.page-container { padding: 20px; }
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}
.page-title { font-size: 20px; font-weight: 600; }
.header-hint { display: flex; align-items: center; gap: 6px; color: #606266; font-size: 13px; }
.content-card {
  background: #fff;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.stats-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 20px;
}
.stat-card {
  text-align: center;
  padding: 16px;
  background: #f5f7fa;
  border-radius: 8px;
}
.stat-num { font-size: 32px; font-weight: 600; }
.stat-label { font-size: 12px; color: #909399; margin-top: 4px; }
.filter-bar {
  display: flex;
  gap: 12px;
  margin-bottom: 16px;
  align-items: center;
}
.perm-name { display: flex; flex-direction: column; gap: 2px; }
.perm-name code {
  font-size: 12px;
  color: #409eff;
  background: #ecf5ff;
  padding: 2px 6px;
  border-radius: 3px;
  align-self: flex-start;
}
.perm-desc { font-size: 12px; color: #606266; }
</style>
