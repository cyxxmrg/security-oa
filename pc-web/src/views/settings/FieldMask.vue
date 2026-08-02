<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">字段脱敏规则</span>
      <div class="header-actions">
        <el-tag type="info" size="large">共 {{ totalRules }} 条规则 / {{ groupedData.length }} 个端点</el-tag>
        <el-button type="warning" :icon="RefreshLeft" @click="onFlushCache" :loading="flushing" style="margin-left: 8px;">
          清缓存
        </el-button>
      </div>
    </div>

    <div class="content-card">
      <div class="filter-bar">
        <el-input
          v-model="search"
          placeholder="搜索端点 / 字段 / 描述"
          clearable
          :prefix-icon="Search"
          style="width: 280px"
        />
        <el-button :icon="Plus" type="primary" @click="openCreateDialog">新增规则</el-button>
        <el-button :icon="Refresh" @click="loadData">刷新</el-button>
      </div>

      <el-collapse v-model="activeNames" v-loading="loading">
        <el-collapse-item
          v-for="group in filteredGroups"
          :key="group.endpoint"
          :name="group.endpoint"
        >
          <template #title>
            <div class="endpoint-title">
              <el-icon class="endpoint-icon"><Document /></el-icon>
              <code class="endpoint-name">{{ group.endpoint }}</code>
              <el-tag :type="getGroupType(group.endpoint)" size="small" effect="plain" style="margin-left: 8px;">
                {{ group.items.length }} 字段
              </el-tag>
              <el-tag size="small" effect="plain" type="info" style="margin-left: 4px;">
                可见角色: {{ group.allowed_roles }}
              </el-tag>
              <span class="endpoint-meta" v-if="hasAnyCustom(group)">
                <el-tag type="warning" size="small" effect="plain" style="margin-left: 4px;">含自定义</el-tag>
              </span>
            </div>
          </template>

          <el-table :data="group.items" border size="small" style="width: 100%;">
            <el-table-column prop="field" label="字段名" min-width="160">
              <template #default="{ row }">
                <code>{{ row.field }}</code>
              </template>
            </el-table-column>
            <el-table-column prop="description" label="描述" min-width="180" show-overflow-tooltip />
            <el-table-column label="可见角色" width="180">
              <template #default="{ row }">
                <el-tag
                  v-for="r in row.allowed_roles.split(',')"
                  :key="r"
                  :type="roleColor(r)"
                  size="small"
                  effect="dark"
                  style="margin-right: 4px;"
                >
                  {{ r }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="状态" width="100" align="center">
              <template #default="{ row }">
                <el-switch
                  v-model="row.enabled"
                  @change="(val: boolean) => onToggle(row, val)"
                />
              </template>
            </el-table-column>
            <el-table-column label="操作" width="220" fixed="right">
              <template #default="{ row }">
                <el-button link type="primary" size="small" :icon="Edit" @click="openEditDialog(row)">
                  编辑
                </el-button>
                <el-button link type="danger" size="small" :icon="Delete" @click="onDelete(row)">
                  删除
                </el-button>
                <el-button link type="warning" size="small" :icon="View" @click="onTestMask(row)">
                  测试
                </el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-collapse-item>
      </el-collapse>

      <div v-if="!filteredGroups.length && !loading" class="empty">
        <el-empty description="暂无脱敏规则" />
      </div>
    </div>

    <!-- 创建 / 编辑 dialog -->
    <el-dialog
      v-model="dialogVisible"
      :title="editingId ? '编辑脱敏规则' : '新增脱敏规则'"
      width="600px"
    >
      <el-form :model="form" label-width="100px" :rules="formRules" ref="formRef">
        <el-form-item label="端点" prop="endpoint" v-if="!editingId">
          <el-input v-model="form.endpoint" placeholder="如: /api/users, /api/customers" />
        </el-form-item>
        <el-form-item label="字段" prop="field" v-if="!editingId">
          <el-input v-model="form.field" placeholder="如: id_card, phone, salary" />
        </el-form-item>
        <el-form-item label="可见角色" prop="allowed_roles">
          <el-input v-model="form.allowed_roles" placeholder="逗号分隔: admin,finance 或 admin/*" />
          <div class="hint">支持通配符: <code>admin/*</code> 匹配所有 admin 角色</div>
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="form.description" placeholder="为什么这个字段要脱敏" maxlength="200" show-word-limit />
        </el-form-item>
        <el-form-item label="启用">
          <el-switch v-model="form.enabled" />
        </el-form-item>
        <el-alert
          v-if="editingId"
          type="info"
          :closable="false"
          title="编辑模式只能改角色/描述/启用状态, 端点和字段不可改 (避免影响其它规则)"
          style="margin-bottom: 12px;"
        />
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="onSave" :loading="saving">保存</el-button>
      </template>
    </el-dialog>

    <!-- 测试脱敏效果 dialog -->
    <el-dialog v-model="testDialogVisible" title="脱敏效果预览" width="560px">
      <el-form label-width="100px">
        <el-form-item label="端点">
          <code>{{ testRow?.endpoint || form.endpoint }}</code>
        </el-form-item>
        <el-form-item label="字段">
          <code>{{ testRow?.field || form.field }}</code>
        </el-form-item>
        <el-form-item label="可见角色">
          <el-tag
            v-for="r in (testRow?.allowed_roles || form.allowed_roles).split(',')"
            :key="r"
            :type="roleColor(r)"
            size="small"
            effect="dark"
            style="margin-right: 4px;"
          >
            {{ r }}
          </el-tag>
        </el-form-item>
        <el-form-item label="测试用户名 (可选)">
          <el-input v-model="testUsername" placeholder="如: eng_qian, fin_wu, admin1 (用于检测此用户是否会脱敏)" />
        </el-form-item>
        <el-form-item label="测试数据 (JSON 数组)">
          <el-input v-model="testDataJson" type="textarea" :rows="5" placeholder='例如:
[
  {"name": "张三", "phone": "13800138000", "id_card": "110101199001011234"},
  {"name": "李四", "phone": "13900139000", "id_card": "110101199002022345"}
]' />
        </el-form-item>
        <el-form-item v-if="testResult.output" label="脱敏结果对比">
          <div class="test-diff">
            <div class="diff-row">
              <span class="diff-label">规则数</span>
              <el-tag size="small">{{ testResult.ruleCount || 0 }}</el-tag>
            </div>
            <div class="diff-row">
              <span class="diff-label">用户角色</span>
              <el-tag v-for="r in testResult.userRoles" :key="r" size="small" style="margin-right: 4px;">{{ r }}</el-tag>
              <span v-if="!testResult.userRoles?.length" class="diff-empty">未指定</span>
            </div>
            <div v-if="testResult.maskedCount > 0" class="diff-row">
              <el-alert type="success" :closable="false" show-icon>
                <template #title>命中脱敏 {{ testResult.maskedCount }} / {{ testResult.totalCount || 0 }} 条</template>
              </el-alert>
            </div>
            <el-table :data="testResult.diffRows" size="small" border max-height="240">
              <el-table-column prop="field" label="字段" width="120" />
              <el-table-column label="原值">
                <template #default="{ row }"><code>{{ row.original }}</code></template>
              </el-table-column>
              <el-table-column label="脱敏后">
                <template #default="{ row }">
                  <code :class="row.changed ? 'changed' : 'same'">{{ row.masked }}</code>
                </template>
              </el-table-column>
              <el-table-column label="状态" width="80">
                <template #default="{ row }">
                  <el-tag v-if="row.changed" type="warning" size="small">已脱敏</el-tag>
                  <el-tag v-else type="info" size="small">不变</el-tag>
                </template>
              </el-table-column>
            </el-table>
          </div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="runTest" type="primary" :loading="testLoading">运行测试</el-button>
        <el-button @click="testDialogVisible = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox, FormInstance } from 'element-plus'
import { Search, Refresh, Plus, Edit, Delete, View, Document, RefreshLeft } from '@element-plus/icons-vue'
import { get, post, put, del } from '@/utils/request'
import { unwrapList } from '@/utils/response'

interface MaskRule {
  id: number
  field: string
  allowed_roles: string
  description: string
  enabled: boolean
}

interface MaskGroup {
  endpoint: string
  allowed_roles: string
  items: MaskRule[]
}

const loading = ref(false)
const flushing = ref(false)
const groupedData = ref<MaskGroup[]>([])
const search = ref('')
const activeNames = ref<string[]>([])

const dialogVisible = ref(false)
const editingId = ref<number | null>(null)
const formRef = ref<FormInstance>()
const saving = ref(false)
const form = ref({
  endpoint: '',
  field: '',
  allowed_roles: 'admin',
  description: '',
  enabled: true,
})
const formRules = {
  endpoint: [{ required: true, message: '端点不能为空', trigger: 'blur' }],
  field: [{ required: true, message: '字段不能为空', trigger: 'blur' }],
  allowed_roles: [{ required: true, message: '可见角色不能为空', trigger: 'blur' }],
}

const testDialogVisible = ref(false)
const testRow = ref<MaskRule | null>(null)
const testUsername = ref('eng_qian')
const testValue = ref('13800138000')
const testResult = ref({ output: '', masked: false })

const totalRules = computed(() => groupedData.value.reduce((sum, g) => sum + g.items.length, 0))

const filteredGroups = computed(() => {
  if (!search.value) return groupedData.value
  const kw = search.value.toLowerCase()
  return groupedData.value
    .map(g => ({
      ...g,
      items: g.items.filter(r =>
        r.field.toLowerCase().includes(kw) ||
        (r.description || '').toLowerCase().includes(kw) ||
        g.endpoint.toLowerCase().includes(kw)
      ),
    }))
    .filter(g => g.items.length > 0)
})

const hasAnyCustom = (g: MaskGroup) =>
  g.items.some(r => !['admin', 'admin,finance', 'admin,finance,manager'].includes(r.allowed_roles))

const getGroupType = (endpoint: string) => {
  if (endpoint.includes('user') || endpoint.includes('employee')) return 'primary'
  if (endpoint.includes('finance') || endpoint.includes('payment')) return 'warning'
  if (endpoint.includes('customer') || endpoint.includes('client')) return 'success'
  return 'info'
}

const roleColor = (r: string) => {
  if (r === 'admin') return 'danger'
  if (r === 'finance') return 'warning'
  if (r === 'manager') return 'primary'
  if (r === 'user') return 'info'
  return ''
}

const loadData = async () => {
  loading.value = true
  try {
    // V0.6.3: res = {code, data: <grouped array>}
    const res = await get('/field-masks')
    const arr = unwrapList(res)
    groupedData.value = arr.map((g: Record<string, unknown>) => ({
      endpoint: g.endpoint,
      allowed_roles: g.allowed_roles,
      items: g.items,
    }))
    // 默认展开前 3 个
    activeNames.value = groupedData.value.slice(0, 3).map(g => g.endpoint)
  } catch (e: unknown) {
    ElMessage.error('加载失败: ' + (e?.message || ''))
  } finally {
    loading.value = false
  }
}

const openCreateDialog = () => {
  editingId.value = null
  form.value = {
    endpoint: '',
    field: '',
    allowed_roles: 'admin',
    description: '',
    enabled: true,
  }
  dialogVisible.value = true
}

const openEditDialog = (row: MaskRule) => {
  editingId.value = row.id
  form.value = {
    endpoint: '',
    field: '',
    allowed_roles: row.allowed_roles,
    description: row.description || '',
    enabled: row.enabled,
  }
  dialogVisible.value = true
}

const onSave = async () => {
  if (!formRef.value) return
  const valid = await formRef.value.validate().catch(() => false)
  if (!valid) return
  saving.value = true
  try {
    if (editingId.value) {
      await put(`/field-masks/${editingId.value}`, {
        allowed_roles: form.value.allowed_roles,
        description: form.value.description,
        enabled: form.value.enabled,
      })
      ElMessage.success('已更新')
    } else {
      await post('/field-masks', {
        endpoint: form.value.endpoint,
        field: form.value.field,
        allowed_roles: form.value.allowed_roles,
        description: form.value.description,
        enabled: form.value.enabled,
      })
      ElMessage.success('已添加')
    }
    dialogVisible.value = false
    await loadData()
  } catch (e: unknown) {
    ElMessage.error('保存失败: ' + (e?.message || ''))
  } finally {
    saving.value = false
  }
}

const onToggle = async (row: MaskRule, val: boolean) => {
  try {
    await put(`/field-masks/${row.id}`, { enabled: val })
    ElMessage.success(val ? '已启用' : '已停用')
  } catch (e: unknown) {
    row.enabled = !val
    ElMessage.error('切换失败: ' + (e?.message || ''))
  }
}

const onDelete = async (row: MaskRule) => {
  try {
    await ElMessageBox.confirm(
      `确定要删除「${row.field}」规则吗?`,
      '删除确认',
      { type: 'warning' }
    )
  } catch {
    return
  }
  try {
    await del(`/field-masks/${row.id}`)
    ElMessage.success('已删除')
    await loadData()
  } catch (e: unknown) {
    ElMessage.error('删除失败: ' + (e?.message || ''))
  }
}

const onFlushCache = async () => {
  flushing.value = true
  try {
    await post('/field-masks/flush-cache', {})
    ElMessage.success('缓存已清, 5 分钟内会重新加载')
  } catch (e: unknown) {
    ElMessage.error('失败: ' + (e?.message || ''))
  } finally {
    flushing.value = false
  }
}

const onTestMask = (row: MaskRule) => {
  testRow.value = row
  testUsername.value = 'eng_qian'
  testValue.value = '13800138000'
  testResult.value = { output: testValue.value, masked: false }
  testDialogVisible.value = true
}

const testDataJson = ref('[\n  {"name": "张三", "phone": "13800138000", "id_card": "110101199001011234"},\n  {"name": "李四", "phone": "13900139000", "id_card": "110101199002022345"}\n]')
const testLoading = ref(false)
const runTest = async () => {
  if (!testRow.value) return ElMessage.warning('请先选规则')
  let parsed: Record<string, unknown>[]
  try {
    parsed = JSON.parse(testDataJson.value)
  } catch (e: unknown) {
    return ElMessage.error('JSON 解析失败: ' + e.message)
  }
  testLoading.value = true
  try {
    const userId = await resolveUsernameToId(testUsername.value)
    const res = await post('/field-masks/preview', {
      endpoint: testRow.value.endpoint,
      test_data: parsed,
      as_user_id: userId,
    })
    const d = res.data
    if (!d) return ElMessage.warning('无数据')
    // 构造 diff table
    const diffRows: Record<string, unknown>[] = []
    const fields = new Set<string>()
    for (const row of d.original || []) Object.keys(row || {}).forEach(k => fields.add(k))
    let maskedCount = 0
    for (let i = 0; i < (d.original || []).length; i++) {
      const orig = d.original[i] || {}
      const masked = d.masked[i] || {}
      const rowFields = new Set([...Object.keys(orig), ...Object.keys(masked)])
      for (const f of rowFields) {
        if (orig[f] === undefined && masked[f] === undefined) continue
        const oVal = String(orig[f] ?? '')
        const mVal = String(masked[f] ?? '')
        const changed = oVal !== mVal
        if (changed) maskedCount++
        diffRows.push({ field: `行${i+1}.${f}`, original: oVal, masked: mVal, changed })
      }
    }
    testResult.value = {
      output: JSON.stringify(d.masked, null, 2),
      masked: maskedCount > 0,
      ruleCount: (d.rules || []).length,
      userRoles: d.user_roles || [],
      maskedCount,
      totalCount: (d.original || []).length,
      diffRows,
    }
    ElMessage.success(`命中脱敏 ${maskedCount} / ${(d.original || []).length}`)
  } catch (e: unknown) {
    ElMessage.error(e?.message || '测试失败')
  } finally { testLoading.value = false }
}

const resolveUsernameToId = async (username: string): Promise<number | null> => {
  if (!username) return null
  try {
    // V0.6.3: res = {code, data: paginator}
    const res = await get('/users', { keyword: username, per_page: 1 })
    const d = res?.data ?? {}
    const arr = Array.isArray(d?.data) ? d.data : (Array.isArray(d) ? d : [])
    return arr[0]?.id || null
  } catch { return null }
}

const guessRoleForUser = (username: string): string => {
  if (username.startsWith('admin')) return 'admin'
  if (username.startsWith('fin_')) return 'finance'
  if (username.startsWith('sales_') || username === 'tech_mgr' || username === 'proj_mgr') return 'manager'
  return 'user'
}

onMounted(() => {
  loadData()
})
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
.header-actions { display: flex; align-items: center; }
.content-card {
  background: #fff;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.filter-bar {
  display: flex;
  gap: 12px;
  margin-bottom: 16px;
}
.endpoint-title {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
}
.endpoint-icon { color: #409EFF; }
.endpoint-name {
  font-size: 14px;
  font-weight: 500;
  background: #f5f7fa;
  padding: 2px 8px;
  border-radius: 4px;
}
.endpoint-meta { display: flex; align-items: center; margin-left: auto; }
.empty { padding: 40px 0; text-align: center; }
.hint {
  font-size: 12px;
  color: #909399;
  margin-top: 4px;
  code { background: #f5f7fa; padding: 1px 4px; border-radius: 2px; }
}
.test-result {
  display: flex;
  align-items: center;
  padding: 8px 12px;
  border-radius: 4px;
  font-family: 'Courier New', monospace;
  font-size: 14px;
  width: 100%;
  &.masked { background: #fef0f0; border: 1px solid #fde2e2; }
  &.plain  { background: #f0f9eb; border: 1px solid #e1f3d8; }
  code { color: inherit; }
}
:deep(.el-collapse-item__header) {
  padding-left: 12px;
  height: 48px;
}
:deep(.el-collapse-item__content) {
  padding: 0 12px 12px;
}
</style>
