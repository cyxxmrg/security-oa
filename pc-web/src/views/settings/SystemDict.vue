<template>
  <div class="dict-page">
    <div class="page-header">
      <div class="header-left">
        <el-icon class="header-icon"><Collection /></el-icon>
        <div>
          <h2 class="header-title">数据字典中心</h2>
          <p class="header-sub">集中管理 7 类业务枚举, 改这里 = 改所有引用页面</p>
        </div>
      </div>
      <div class="header-right">
        <el-button @click="seedDefaults" :loading="seeding" :icon="Refresh">一键导入默认</el-button>
        <el-button type="primary" @click="openAdd" :icon="Plus">新建字典项</el-button>
      </div>
    </div>

    <!-- 统计卡 -->
    <el-row :gutter="12" class="stat-row" v-loading="loading">
      <el-col :xs="12" :sm="6" :md="3" v-for="(g, k) in groups" :key="k">
        <div class="stat-card" :class="`severity-${statSeverity(g.count)}`">
          <div class="stat-value">{{ g.count }}</div>
          <div class="stat-label">{{ g.label }}</div>
        </div>
      </el-col>
    </el-row>

    <!-- 字典分组 -->
    <el-collapse v-model="activeKinds" class="kind-collapse">
      <el-collapse-item v-for="g in groups" :key="g.kind" :name="g.kind">
        <template #title>
          <div class="kind-title">
            <span class="kind-name">{{ g.label }}</span>
            <el-tag size="small" type="info">{{ g.kind }}</el-tag>
            <el-tag size="small" :type="g.count > 0 ? 'success' : 'warning'">{{ g.count }} 项</el-tag>
          </div>
        </template>
        <el-table :data="g.items" border size="small" class="dict-table">
          <el-table-column prop="sort_order" label="顺序" width="70" align="center" />
          <el-table-column prop="code" label="代码" width="160">
            <template #default="{ row }">
              <code>{{ row.code }}</code>
            </template>
          </el-table-column>
          <el-table-column prop="label" label="显示名" width="160">
            <template #default="{ row }">
              <el-tag v-if="row.color" :type="row.color" size="small">{{ row.label }}</el-tag>
              <span v-else>{{ row.label }}</span>
              <el-tag v-if="row.is_default" type="primary" size="small" effect="plain" style="margin-left:4px">默认</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="description" label="说明" min-width="200" />
          <el-table-column label="状态" width="80" align="center">
            <template #default="{ row }">
              <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                {{ row.is_active ? '启用' : '停用' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="160" align="center" fixed="right">
            <template #default="{ row }">
              <el-button link type="primary" size="small" @click="openEdit(row)">编辑</el-button>
              <el-button v-if="row.is_active" link type="danger" size="small" @click="disableItem(row)">停用</el-button>
              <el-button v-else link type="success" size="small" @click="enableItem(row)">启用</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-collapse-item>
    </el-collapse>

    <!-- 新增/编辑 dialog -->
    <el-dialog v-model="dialogVisible" :title="editing ? '编辑字典项' : '新建字典项'" width="540px">
      <el-form :model="form" label-width="100px">
        <el-form-item label="分类" required>
          <el-select v-model="form.kind" :disabled="editing" filterable placeholder="选择分类">
            <el-option v-for="(label, k) in kinds" :key="k" :label="`${label} (${k})`" :value="k" />
          </el-select>
        </el-form-item>
        <el-form-item label="代码" required>
          <el-input v-model="form.code" :disabled="editing" placeholder="例: paid_repair" maxlength="50" />
        </el-form-item>
        <el-form-item label="显示名" required>
          <el-input v-model="form.label" placeholder="例: 付费维修" maxlength="100" />
        </el-form-item>
        <el-form-item label="颜色">
          <el-select v-model="form.color" clearable placeholder="前端 el-tag 颜色">
            <el-option v-for="c in colorOptions" :key="c" :label="c" :value="c" />
          </el-select>
        </el-form-item>
        <el-form-item label="顺序">
          <el-input-number v-model="form.sort_order" :min="0" :step="10" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="form.is_active" active-text="启用" inactive-text="停用" />
        </el-form-item>
        <el-form-item label="默认值">
          <el-switch v-model="form.is_default" />
          <span class="form-tip">同分类仅 1 个</span>
        </el-form-item>
        <el-form-item label="说明">
          <el-input v-model="form.description" type="textarea" :rows="2" maxlength="500" show-word-limit />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitForm" :loading="saving">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { get, post, patch, del } from '@/utils/request'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Collection, Plus, Refresh } from '@element-plus/icons-vue'

// 后端路由: /api/dict/*  (SystemDictController)  ← 代码里用 ${API}/dict/X, API 留空
const API = ''
const loading = ref(false)
const saving = ref(false)
const seeding = ref(false)
const kinds = ref<Record<string, string>>({})
const groups = ref<Record<string, unknown>[]>([])
const activeKinds = ref<string[]>([])

const dialogVisible = ref(false)
const editing = ref(false)
const form = ref<Record<string, unknown>>({ kind: '', code: '', label: '', color: '', sort_order: 0, is_active: true, is_default: false, description: '' })

const colorOptions = ['success', 'warning', 'danger', 'info', 'primary']

const statSeverity = (n: number) => {
  if (n === 0) return 'danger'
  if (n < 3) return 'warning'
  return 'success'
}

const loadData = async () => {
  loading.value = true
  try {
    const [kindsRes, groupedRes] = await Promise.all([
      get(`${API}/dict/kinds`),
      get(`${API}/dict/grouped`),
    ])
    kinds.value = kindsRes.data || {}
    groups.value = groupedRes.data || []
    if (activeKinds.value.length === 0) {
      activeKinds.value = groups.value.map(g => g.kind)
    }
  } catch (e: unknown) {
    ElMessage.error('加载失败: ' + (e?.message || ''))
  } finally { loading.value = false }
}

const openAdd = () => {
  editing.value = false
  form.value = { kind: Object.keys(kinds.value)[0] || '', code: '', label: '', color: '', sort_order: 0, is_active: true, is_default: false, description: '' }
  dialogVisible.value = true
}

const openEdit = (row: Record<string, unknown>) => {
  editing.value = true
  form.value = { ...row }
  dialogVisible.value = true
}

const submitForm = async () => {
  if (!form.value.kind || !form.value.code || !form.value.label) {
    ElMessage.warning('分类/代码/显示名 必填')
    return
  }
  saving.value = true
  try {
    if (editing.value) {
      await patch(`${API}/dict/${form.value.id}`, form.value)
      ElMessage.success('已更新')
    } else {
      await post(`${API}/dict`, form.value)
      ElMessage.success('已创建')
    }
    dialogVisible.value = false
    await loadData()
  } catch (e: unknown) {
    ElMessage.error(e?.response?.data?.message || '保存失败')
  } finally { saving.value = false }
}

const disableItem = async (row: Record<string, unknown>) => {
  try { await ElMessageBox.confirm(`停用 "${row.label}"?`, '确认', { type: 'warning' }) } catch { return }
  try {
    await del(`${API}/dict/${row.id}`)
    ElMessage.success('已停用')
    await loadData()
  } catch (e: unknown) { ElMessage.error('停用失败') }
}

const enableItem = async (row: Record<string, unknown>) => {
  try {
    await patch(`${API}/dict/${row.id}`, { is_active: true })
    ElMessage.success('已启用')
    await loadData()
  } catch (e: unknown) { ElMessage.error('启用失败') }
}

const seedDefaults = async () => {
  try { await ElMessageBox.confirm('导入 7 类默认字典, 已存在的会跳过, 只补缺项。', '确认', { type: 'info' }) } catch { return }
  seeding.value = true
  try {
    const res = await post(`${API}/dict/seed-defaults`, {})
    ElMessage.success(res.message || '导入完成')
    await loadData()
  } catch (e: unknown) {
    ElMessage.error(e?.response?.data?.message || '导入失败')
  } finally { seeding.value = false }
}

onMounted(loadData)
</script>

<style scoped lang="scss">
.dict-page { padding: 20px; background: #f4f4f5; min-height: 100vh; }

.page-header {
  display: flex; justify-content: space-between; align-items: center;
  background: #fff; padding: 16px 24px; border-radius: 12px; margin-bottom: 16px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.header-left { display: flex; gap: 16px; align-items: center; }
.header-icon { font-size: 32px; color: #409EFF; }
.header-title { font-size: 18px; font-weight: 600; margin: 0; color: #303133; }
.header-sub { font-size: 12px; color: #909399; margin: 4px 0 0 0; }

.stat-row { margin-bottom: 16px; }
.stat-card {
  background: #fff; border-radius: 8px; padding: 16px; text-align: center;
  border-top: 3px solid #909399;
  margin-bottom: 12px;
}
.stat-card.severity-success { border-top-color: #67C23A; }
.stat-card.severity-warning { border-top-color: #E6A23C; }
.stat-card.severity-danger { border-top-color: #F56C6C; }
.stat-value { font-size: 24px; font-weight: 700; color: #303133; }
.stat-label { font-size: 12px; color: #606266; margin-top: 4px; }

.kind-collapse { background: transparent; border: none; }
:deep(.el-collapse-item__header) { background: #fff; border-radius: 8px; padding: 0 16px; font-weight: 600; margin-bottom: 8px; }
:deep(.el-collapse-item__content) { padding: 0; }
.kind-title { display: flex; gap: 12px; align-items: center; }
.kind-name { font-size: 14px; }

.dict-table { margin: 8px 0 16px 0; border-radius: 8px; overflow: hidden; }

.form-tip { margin-left: 8px; color: #909399; font-size: 12px; }
</style>
