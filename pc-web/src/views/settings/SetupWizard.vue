<template>
  <div class="wizard-page">
    <!-- 顶部 -->
    <div class="wizard-header">
      <div class="header-left">
        <el-icon class="header-icon"><MagicStick /></el-icon>
        <div>
          <h2 class="header-title">系统初始化向导</h2>
          <p class="header-sub">5 步引导, 让系统快速就绪</p>
        </div>
      </div>
      <div class="header-right">
        <el-button @click="$router.push('/')" plain>稍后再说</el-button>
        <el-button v-if="!summary.setup_completed" type="success" @click="markComplete" :loading="completing">
          跳过并标记完成
        </el-button>
      </div>
    </div>

    <!-- 步骤条 -->
    <el-steps :active="activeStep" align-center class="wizard-steps" finish-status="success">
      <el-step title="基础设置" description="系统名/版权/ICP" />
      <el-step title="数据现状" description="摘要" />
      <el-step title="补齐员工" description="批量创建" />
      <el-step title="CSV 导入" description="批量员工" />
      <el-step title="完成" description="标记就绪" />
    </el-steps>

    <!-- 健康度 -->
    <el-card class="score-card" shadow="never" v-loading="loading">
      <div class="score-row">
        <div class="score-ring">
          <el-progress
            type="circle"
            :percentage="summary.score || 0"
            :stroke-width="10"
            :width="100"
            :color="scoreColor"
          />
        </div>
        <div class="score-info">
          <div class="score-title">系统健康度 {{ summary.score || 0 }} / 100</div>
          <div v-if="summary.setup_completed" class="score-completed">
            ✅ 已完成初始化 ({{ summary.setup_completed_at }})
          </div>
          <div v-else class="score-pending">⚠️ 系统尚未完成初始化</div>
          <ul class="score-tips">
            <li v-for="(t, i) in summary.suggestions" :key="i" :class="`tip-${t.level}`">
              <el-icon v-if="t.level === 'warning'"><Warning /></el-icon>
              <el-icon v-else-if="t.level === 'info'"><InfoFilled /></el-icon>
              <el-icon v-else><MagicStick /></el-icon>
              {{ t.msg }}
            </li>
            <li v-if="!summary.suggestions?.length" class="tip-empty">
              🎉 系统已就绪, 无需额外建议
            </li>
          </ul>
        </div>
      </div>
    </el-card>

    <!-- 各步内容 -->
    <el-card class="step-card" shadow="never" v-loading="stepLoading">
      <!-- Step 1: 基础设置 -->
      <div v-show="activeStep === 0">
        <h3 class="step-title">① 基础信息配置</h3>
        <p class="step-desc">设置系统名称、版权、备案号和联系邮箱, 用于登录页和页脚展示。</p>
        <el-form :model="step1Form" label-width="120px" :inline="false" class="step-form">
          <el-form-item label="系统名称" required>
            <el-input v-model="step1Form.system_name" placeholder="例: XX科技有限公司OA系统" maxlength="64" show-word-limit />
          </el-form-item>
          <el-form-item label="系统简称" required>
            <el-input v-model="step1Form.system_short_name" placeholder="例: OA 系统" maxlength="32" show-word-limit />
          </el-form-item>
          <el-form-item label="版权信息" required>
            <el-input v-model="step1Form.copyright" placeholder="例: © 2024 公司名" maxlength="255" />
          </el-form-item>
          <el-form-item label="ICP 备案号">
            <el-input v-model="step1Form.icp" placeholder="例: 浙ICP备12345678号" maxlength="64" />
          </el-form-item>
          <el-form-item label="联系邮箱" required>
            <el-input v-model="step1Form.contact_email" placeholder="例: support@example.com" />
          </el-form-item>
          <el-form-item>
            <el-button type="primary" @click="saveStep1" :loading="step1Loading">保存并下一步</el-button>
          </el-form-item>
        </el-form>
      </div>

      <!-- Step 2: 数据现状 -->
      <div v-show="activeStep === 1">
        <h3 class="step-title">② 当前数据现状</h3>
        <p class="step-desc">系统当前已有数据概览, 看完后点击下一步继续。</p>
        <el-row :gutter="16" class="count-row">
          <el-col :xs="12" :sm="8" :md="6" v-for="(c, k) in summary.counts" :key="k">
            <div class="count-card">
              <div class="count-value">{{ c }}</div>
              <div class="count-label">{{ countLabels[k] || k }}</div>
            </div>
          </el-col>
        </el-row>
        <el-divider />
        <h4 class="sub-title">建议事项</h4>
        <el-alert
          v-for="(t, i) in summary.suggestions"
          :key="i"
          :type="t.level === 'primary' ? 'primary' : (t.level === 'warning' ? 'warning' : 'info')"
          :title="t.msg"
          :closable="false"
          show-icon
          style="margin-bottom: 8px"
        />
        <el-button type="primary" @click="activeStep = 2" style="margin-top: 16px">下一步</el-button>
      </div>

      <!-- Step 3: 补齐员工 -->
      <div v-show="activeStep === 2">
        <h3 class="step-title">③ 批量补齐员工</h3>
        <p class="step-desc">在表格中录入员工信息, 一次最多 50 人。</p>

        <el-table :data="step3Form.employees" border size="default" class="emp-table">
          <el-table-column label="姓名 *" width="120">
            <template #default="{ row }">
              <el-input v-model="row.name" size="small" />
            </template>
          </el-table-column>
          <el-table-column label="账号 *" width="130">
            <template #default="{ row }">
              <el-input v-model="row.username" size="small" />
            </template>
          </el-table-column>
          <el-table-column label="手机" width="140">
            <template #default="{ row }">
              <el-input v-model="row.phone" size="small" />
            </template>
          </el-table-column>
          <el-table-column label="邮箱" width="200">
            <template #default="{ row }">
              <el-input v-model="row.email" size="small" />
            </template>
          </el-table-column>
          <el-table-column label="初始密码 *" width="140">
            <template #default="{ row }">
              <el-input v-model="row.password" size="small" type="password" show-password />
            </template>
          </el-table-column>
          <el-table-column label="角色" width="140">
            <template #default="{ row }">
              <el-select v-model="row.role" size="small" clearable>
                <el-option v-for="r in roleOptions" :key="r" :label="r" :value="r" />
              </el-select>
            </template>
          </el-table-column>
          <el-table-column label="部门" width="160">
            <template #default="{ row }">
              <el-select v-model="row.department_id" size="small" clearable>
                <el-option v-for="d in deptOptions" :key="d.id" :label="d.name" :value="d.id" />
              </el-select>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="70" fixed="right">
            <template #default="{ $index }">
              <el-button type="danger" link size="small" @click="removeRow($index)">删</el-button>
            </template>
          </el-table-column>
        </el-table>

        <div class="table-actions">
          <el-button @click="addRow" plain>➕ 添加一行</el-button>
          <el-button @click="addRow(3)" plain>➕➕ 加 3 行</el-button>
          <el-button type="primary" @click="saveStep3" :loading="step3Loading">
            创建员工 ({{ step3Form.employees.length }} 人)
          </el-button>
        </div>
      </div>

      <!-- Step 4: CSV 导入 -->
      <div v-show="activeStep === 3">
        <h3 class="step-title">④ CSV 批量导入</h3>
        <p class="step-desc">如果员工较多, 可用 CSV 一次性导入。</p>

        <el-alert type="info" :closable="false" show-icon style="margin-bottom: 12px">
          <template #title>CSV 列格式 (按顺序)</template>
          name, username, phone, email, password, role, department_id, position_id
        </el-alert>

        <div class="csv-actions">
          <el-button @click="downloadSample" :icon="Download">下载模板</el-button>
          <el-upload :before-upload="handleFile" :show-file-list="false" accept=".csv">
            <el-button type="primary" :icon="Upload">上传 CSV 文件</el-button>
          </el-upload>
        </div>

        <el-input
          v-model="step4Form.csv_text"
          type="textarea"
          :rows="10"
          placeholder="或直接粘贴 CSV 文本 (含表头)..."
          class="csv-input"
        />

        <div class="step-actions">
          <el-button @click="previewStep4" :loading="step4Loading">解析预览</el-button>
          <el-button v-if="step4Preview.length" type="success" @click="confirmStep4" :loading="step4Loading">
            确认导入 {{ step4Preview.length }} 条
          </el-button>
        </div>

        <el-table v-if="step4Preview.length" :data="step4Preview" border size="small" max-height="300" style="margin-top: 12px">
          <el-table-column prop="name" label="姓名" />
          <el-table-column prop="username" label="账号" />
          <el-table-column prop="phone" label="手机" />
          <el-table-column prop="email" label="邮箱" />
          <el-table-column prop="password" label="密码" width="100" />
          <el-table-column prop="role" label="角色" />
        </el-table>
      </div>

      <!-- Step 5: 完成 -->
      <div v-show="activeStep === 4">
        <h3 class="step-title">⑤ 系统就绪</h3>
        <el-result icon="success" title="所有步骤已就绪">
          <template #sub-title>
            <div>点击下方按钮标记系统初始化完成, 后续可在「系统设置」重新进入。</div>
          </template>
          <template #extra>
            <el-button type="primary" size="large" @click="markComplete" :loading="completing">
              🎉 标记系统就绪
            </el-button>
            <el-button @click="$router.push('/')" size="large">进入 Dashboard</el-button>
          </template>
        </el-result>
      </div>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { get, post } from '@/utils/request'
import { unwrapStats } from '@/utils/response'
import { ElMessage, ElMessageBox } from 'element-plus'
import { MagicStick, Warning, InfoFilled, Download, Upload } from '@element-plus/icons-vue'

// 后端路由: /api/setup/*  (SetupWizardController)  ← 注意: 代码里用 ${API}/setup/X, 所以 API 留空
const API = ''
const loading = ref(false)
const stepLoading = ref(false)
const activeStep = ref(0)
const step1Loading = ref(false)
const step3Loading = ref(false)
const step4Loading = ref(false)
const completing = ref(false)

const summary = ref<any>({ settings: {}, counts: {}, suggestions: [], score: 0 })

const countLabels: Record<string, string> = {
  users: '员工',
  admins: '管理员',
  roles: '角色',
  permissions: '权限',
  customers: '客户',
  projects: '项目',
  work_orders: '工单',
  departments: '部门',
  positions: '职位',
  suppliers: '供应商',
}

const scoreColor = computed(() => {
  const s = summary.value.score || 0
  if (s >= 80) return '#67C23A'
  if (s >= 50) return '#E6A23C'
  return '#F56C6C'
})

// Step 1
const step1Form = ref({
  system_name: '',
  system_short_name: '',
  copyright: '',
  icp: '',
  contact_email: '',
})

// Step 3
const step3Form = ref<any>({ employees: [] })
const roleOptions = ref<string[]>([])
const deptOptions = ref<{ id: number; name: string }[]>([])

const newRow = () => ({
  name: '',
  username: '',
  phone: '',
  email: '',
  password: genTempPwd(),
  role: 'user',
  department_id: null,
  position_id: null,
})

// V1.4.5 (REVIEW P3): 默认密码改为随机生成, 避免批量建号共用已知弱密码 'Pass1234'
const genTempPwd = (): string => {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789' // # noqa: secret
  let s = ''
  for (let i = 0; i < 10; i++) s += chars[Math.floor(Math.random() * chars.length)]
  return s + 'Aa1' // 保证含大写+小写+数字, 共 13 位
}

const addRow = (n: number | MouseEvent = 1) => {
  if (typeof n !== 'number') n = 1
  for (let i = 0; i < n; i++) step3Form.value.employees.push(newRow())
}

const removeRow = (i: number) => {
  step3Form.value.employees.splice(i, 1)
}

// Step 4
const step4Form = ref({ csv_text: '' })
const step4Preview = ref<any[]>([])

// ==================== 数据加载 ====================
const loadSummary = async () => {
  loading.value = true
  try {
    // V0.6.3: res = {code, data: <summary>}
    const res = await get(`${API}/setup/summary`)
    summary.value = unwrapStats(res)
    // 用现有值预填 Step1
    const s = summary.value.settings || {}
    step1Form.value = {
      system_name: s.system_name?.value || '',
      system_short_name: s.system_short_name?.value || '',
      copyright: s.copyright?.value || '',
      icp: s.icp?.value || '',
      contact_email: s.contact_email?.value || '',
    }
    // 已完成 → 跳到最后一步
    if (summary.value.setup_completed) activeStep.value = 4
  } catch (e: any) {
    ElMessage.error('加载摘要失败: ' + (e?.message || ''))
  } finally { loading.value = false }
}

const loadEnums = async () => {
  try {
    // V0.6.3: res = {code, data: paginator/<list>}
    const r1 = await get('/roles')
    const d1 = r1?.data ?? {}
    const list = Array.isArray(d1) ? d1 : (Array.isArray(d1?.data) ? d1.data : [])
    roleOptions.value = list.map((r: Record<string, unknown>) => r.name)
  } catch {
    roleOptions.value = ['admin', 'manager', 'user', 'sales', 'technician', 'finance']
  }
  try {
    const r2 = await get('/departments')
    // V0.6.3: res = {code, data: <depts>}
    const d2 = r2?.data ?? r2
    deptOptions.value = Array.isArray(d2) ? d2 : (Array.isArray(d2?.data) ? d2.data : [])
  } catch {
    deptOptions.value = []
  }
}

// ==================== Step 操作 ====================
const saveStep1 = async () => {
  step1Loading.value = true
  try {
    await post(`${API}/setup/step1`, step1Form.value)
    ElMessage.success('基础设置已保存')
    await loadSummary()
    activeStep.value = 1
  } catch (e: any) {
    ElMessage.error(e?.response?.data?.message || '保存失败')
  } finally { step1Loading.value = false }
}

const saveStep3 = async () => {
  // 客户端校验
  for (let i = 0; i < step3Form.value.employees.length; i++) {
    const e = step3Form.value.employees[i]
    if (!e.name || !e.username || !e.password) {
      ElMessage.error(`第 ${i + 1} 行: 姓名/账号/密码 必填`)
      return
    }
  }
  step3Loading.value = true
  try {
    const res = await post(`${API}/setup/step3`, { employees: step3Form.value.employees })
    const stats = res.data?.stats || {}
    ElMessage.success(`已创建 ${stats.created} 人, 跳过 ${stats.skipped} 人`)
    if (stats.errors?.length) {
      console.warn('跳过详情:', stats.errors)
    }
    await loadSummary()
    step3Form.value.employees = []
  } catch (e: any) {
    ElMessage.error(e?.response?.data?.message || '创建失败')
  } finally { step3Loading.value = false }
}

const previewStep4 = () => {
  // 简单解析 + 校验
  const lines = step4Form.value.csv_text.replace(/^\xEF\xBB\xBF/, '').split(/\r\n|\n|\r/).filter((l: string) => l.trim())
  if (lines.length < 2) {
    ElMessage.warning('至少需要表头 + 1 行数据')
    return
  }
  const header = lines[0].split(',').map((s: string) => s.trim())
  const rows: Record<string, unknown>[] = []
  for (let i = 1; i < lines.length; i++) {
    const cells = lines[i].split(',').map((s: string) => s.trim())
    if (cells.length !== header.length) continue
    const row: Record<string, unknown> = {}
    header.forEach((h: string, j: number) => row[h] = cells[j])
    rows.push(row)
  }
  step4Preview.value = rows
  ElMessage.success(`解析到 ${rows.length} 条`)
}

const confirmStep4 = async () => {
  step4Loading.value = true
  try {
    const res = await post(`${API}/setup/step4`, { csv_text: step4Form.value.csv_text })
    const stats = res.data?.stats || {}
    ElMessage.success(`已导入 ${stats.created} 人, 跳过 ${stats.skipped} 人`)
    step4Form.value.csv_text = ''
    step4Preview.value = []
    await loadSummary()
  } catch (e: any) {
    ElMessage.error(e?.response?.data?.message || '导入失败')
  } finally { step4Loading.value = false }
}

const handleFile = (file: File) => {
  const reader = new FileReader()
  reader.onload = (e) => {
    step4Form.value.csv_text = (e.target?.result as string) || ''
    previewStep4()
  }
  reader.readAsText(file)
  return false
}

const downloadSample = async () => {
  window.open(`${API}/setup/sample-csv`, '_blank')
}

const markComplete = async () => {
  try {
    await ElMessageBox.confirm('确定标记为初始化完成吗? 此操作会记录在 system_settings 中。', '确认', {
      confirmButtonText: '标记完成',
      cancelButtonText: '取消',
      type: 'success',
    })
  } catch { return }
  completing.value = true
  try {
    await post(`${API}/setup/complete`, {})
    ElMessage.success('🎉 系统初始化完成!')
    await loadSummary()
  } catch (e: any) {
    ElMessage.error(e?.response?.data?.message || '标记失败')
  } finally { completing.value = false }
}

onMounted(async () => {
  addRow(3) // 默认 3 个空行
  await Promise.all([loadSummary(), loadEnums()])
})
</script>

<style scoped lang="scss">
.wizard-page {
  padding: 20px;
  background: linear-gradient(180deg, #F4F4F5 0%, #FAFAFA 100%);
  min-height: 100vh;
}

.wizard-header {
  display: flex; justify-content: space-between; align-items: center;
  background: #fff; padding: 16px 24px; border-radius: 12px; margin-bottom: 16px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.header-left { display: flex; gap: 16px; align-items: center; }
.header-icon { font-size: 36px; color: #409EFF; }
.header-title { font-size: 20px; font-weight: 700; color: #303133; margin: 0; }
.header-sub { font-size: 13px; color: #909399; margin: 4px 0 0 0; }

.wizard-steps {
  background: #fff; padding: 20px 24px; border-radius: 12px; margin-bottom: 16px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.score-card { margin-bottom: 16px; border-radius: 12px; border: none; }
.score-row { display: flex; gap: 24px; align-items: flex-start; }
.score-info { flex: 1; }
.score-title { font-size: 18px; font-weight: 600; color: #303133; }
.score-completed { color: #67C23A; margin-top: 8px; font-size: 14px; }
.score-pending { color: #E6A23C; margin-top: 8px; font-size: 14px; }
.score-tips { list-style: none; padding: 0; margin: 12px 0 0 0; }
.score-tips li {
  padding: 6px 0; font-size: 13px; color: #606266;
  display: flex; align-items: center; gap: 6px;
}
.tip-warning { color: #E6A23C; }
.tip-info { color: #909399; }
.tip-primary { color: #409EFF; font-weight: 500; }
.tip-empty { color: #67C23A; }

.step-card { border-radius: 12px; border: none; }
.step-title { font-size: 18px; font-weight: 600; color: #303133; margin: 0 0 4px 0; }
.step-desc { font-size: 13px; color: #909399; margin: 0 0 20px 0; }
.step-form { max-width: 720px; }

.count-row { margin-bottom: 16px; }
.count-card {
  background: #f5f7fa; border-radius: 8px; padding: 16px; text-align: center;
  border: 1px solid #e4e7ed;
}
.count-value { font-size: 24px; font-weight: 700; color: #409EFF; }
.count-label { font-size: 12px; color: #909399; margin-top: 4px; }
.sub-title { font-size: 14px; font-weight: 600; margin: 16px 0 8px 0; color: #303133; }

.emp-table { margin-top: 8px; }
.table-actions { margin-top: 12px; display: flex; gap: 12px; justify-content: flex-end; }

.csv-actions { display: flex; gap: 12px; margin-bottom: 12px; }
.csv-input :deep(.el-textarea__inner) { font-family: 'Courier New', monospace; font-size: 13px; }
.step-actions { margin-top: 12px; display: flex; gap: 12px; }

@media (max-width: 768px) {
  .wizard-header { flex-direction: column; gap: 12px; align-items: stretch; }
  .score-row { flex-direction: column; }
  :deep(.el-steps) { flex-direction: column; }
}
</style>
