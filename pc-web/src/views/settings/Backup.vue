<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">系统设置</span>
      <el-button :icon="Refresh" @click="loadAll">刷新</el-button>
    </div>

    <!-- ========== 基本信息（标题/简称/版权/备案号/联系邮箱） ========== -->
    <SettingsFormCard
      title="基本信息"
      hint="修改后立即生效，所有用户可见"
      :settings="settings"
      :saving="saving"
      @save="handleSave"
      @reset="handleReset"
    >
      <template #default="{ settings: s }">
        <el-row :gutter="24">
          <el-col :span="12">
            <el-form-item label="系统名称">
              <el-input v-model="s.system_name" placeholder="如：安防运维OA办公系统" maxlength="64" show-word-limit />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="系统简称">
              <el-input v-model="s.system_short_name" placeholder="侧边栏 / 大屏用" maxlength="32" show-word-limit />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="24">
          <el-col :span="12">
            <el-form-item label="版权信息">
              <el-input v-model="s.copyright" placeholder="© 2026 公司名" maxlength="255" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="版权链接">
              <el-input v-model="s.copyright_url" placeholder="https://" maxlength="255" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="24">
          <el-col :span="12">
            <el-form-item label="ICP 备案号">
              <el-input v-model="s.icp" placeholder="如：京ICP备12345678号" maxlength="64" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="联系邮箱">
              <el-input v-model="s.contact_email" placeholder="admin@example.com" maxlength="128" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="系统公告">
          <el-input
            v-model="s.announcement"
            type="textarea"
            :rows="3"
            placeholder="支持多条公告，登录页可展示"
            maxlength="2000"
            show-word-limit
          />
        </el-form-item>
      </template>
    </SettingsFormCard>

    <!-- ========== 会话安全 — 闲置自动登出 ========== -->
    <div class="content-card" style="margin-top:20px">
      <div class="card-title">
        <span><el-icon color="#0C447C"><Lock /></el-icon> 会话安全</span>
        <span class="muted">配置无操作自动登出时长，保存后立即对所有用户生效</span>
      </div>
      <el-form :model="idleConfig" label-width="180px" v-loading="idleLoading">
        <el-row :gutter="24">
          <el-col :span="12">
            <el-form-item label="启用闲置自动登出">
              <el-switch
                v-model="idleConfig.enabled"
                active-text="启用"
                inactive-text="关闭"
                inline-prompt
                style="--el-switch-on-color: #1D9E75; --el-switch-off-color: #dcdfe6;"
              />
              <span style="margin-left: 12px; color: #909399; font-size: 12px;">
                关闭后用户将永远不会因闲置被踢出
              </span>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="24">
          <el-col :span="12">
            <el-form-item label="无操作超时(分钟)">
              <el-input-number
                v-model="idleConfig.timeout_minutes"
                :min="1"
                :max="1440"
                :step="5"
                :disabled="!idleConfig.enabled"
                controls-position="right"
                style="width: 200px;"
              />
              <span style="margin-left: 12px; color: #909399; font-size: 12px;">
                = <b style="color: #0C447C;">{{ formatDuration(idleConfig.timeout_minutes) }}</b>
                （最少 1 分钟,最多 24 小时）
              </span>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="提前提示秒数">
              <el-input-number
                v-model="idleConfig.warning_seconds"
                :min="0"
                :max="600"
                :step="10"
                :disabled="!idleConfig.enabled"
                controls-position="right"
                style="width: 200px;"
              />
              <span style="margin-left: 12px; color: #909399; font-size: 12px;">
                超时前 N 秒弹窗,可点"继续操作"保持登录
              </span>
            </el-form-item>
          </el-col>
        </el-row>
        <el-alert type="info" :closable="false" style="margin-bottom: 12px;">
          <p><b>效果说明：</b></p>
          <p>• 用户登录后 <b>{{ idleConfig.timeout_minutes }} 分钟</b>无任何鼠标/键盘/滚动操作 → 系统弹窗提示</p>
          <p>• 弹窗后 <b>{{ idleConfig.warning_seconds }} 秒</b>用户未响应 → 自动登出,跳回登录页</p>
          <p>• 用户点"继续操作" → 计时归零,继续会话</p>
          <p>• 在 <code>/login</code> / <code>/error</code> / <code>/legal</code> 页面静止 <b>不计时</b></p>
        </el-alert>
        <el-form-item>
          <el-button type="primary" :loading="idleSaving" :icon="Check" @click="handleSaveIdle">保存会话设置</el-button>
          <el-button :icon="RefreshLeft" @click="loadIdleConfig">重新加载</el-button>
        </el-form-item>
      </el-form>
    </div>

    <!-- ========== 网站访问端口 ========== -->
    <div class="content-card" style="margin-top:20px">
      <div class="card-title">
        <span><el-icon color="#0C447C"><Setting /></el-icon> 网站访问端口</span>
        <span class="muted">修改后需重启 web 服务（PHP-FPM + nginx/apache）才能生效</span>
      </div>
      <el-form :model="portConfig" label-width="140px" v-loading="portSaving">
        <el-row :gutter="24">
          <el-col :span="12">
            <el-form-item label="网站访问端口">
              <el-input-number
                v-model="portConfig.port"
                :min="1"
                :max="65535"
                :step="1"
                controls-position="right"
                style="width: 200px;"
                placeholder="如 9000"
              />
              <span style="margin-left: 12px; color: #909399; font-size: 12px;">
                当前端口: <b style="color: #0C447C;">{{ portConfig.port }}</b>
                （默认 {{ portConfig.default }}）
              </span>
            </el-form-item>
          </el-col>
        </el-row>
        <el-alert type="warning" :closable="false" style="margin-bottom: 12px;">
          <p><b>修改端口后必须重启 web 服务</b>才能生效，操作流程：</p>
          <p>1. 修改 nginx/apache 监听端口配置</p>
          <p>2. <code>sudo systemctl restart php8.3-fpm</code> 重启 PHP-FPM</p>
          <p>3. <code>sudo systemctl restart nginx</code> 重启 nginx</p>
          <p>4. 通过新端口 <code>http://服务器IP:{{ portConfig.port }}</code> 访问</p>
        </el-alert>
        <el-form-item>
          <el-button type="primary" :loading="portSaving" :icon="Check" @click="handleSavePort">保存端口配置</el-button>
          <el-button :icon="RefreshLeft" @click="loadPortConfig">重新加载</el-button>
        </el-form-item>
      </el-form>
    </div>

    <!-- ========== 数据备份记录 ========== -->
    <BackupList
      :backups="backups"
      :loading="loading"
      :backing-up="backingUp"
      @backup="handleManualBackup"
      @refresh="loadBackups"
      @download="handleDownload"
      @delete="handleDelete"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Document, Files, Warning, Check, Refresh, RefreshLeft, Delete, Setting, Lock } from '@element-plus/icons-vue'
import BackupList from './components/backup/BackupList.vue'
import { get, post, put, del } from '@/utils/request'
import SettingsFormCard from './components/backup/SettingsFormCard.vue'
import { useSystemConfigStore } from '@/stores/systemConfig'
import { useUserStore } from '@/stores/user'
import { resetIdleTimer } from '@/composables/useIdleTimer'

// 备份记录
interface BackupItem {
  filename: string
  time?: string
  [key: string]: unknown
}
// API 错误
interface ApiError {
  message?: string
  response?: { data?: { message?: string } }
}

const systemConfigStore = useSystemConfigStore()
const userStore = useUserStore()
const router = useRouter()
// V1.2: 一键清理业务数据仅 system 账号可执行
// 旧逻辑 userInfo.id === 1 || username === 'admin' 在 V1.2 后失效（system 不是 id=1，也不是 admin 用户名）
const isSystem = computed(() => {
  const u = userStore.userInfo
  return u?.is_system === true
      || u?.user_type === 'system'
      || u?.username === 'system'
})

// 双向绑定一份本地副本（表单 v-model 需要）
const settings = ref({ ...systemConfigStore.settings })
const saving = ref(false)
const loading = ref(false)
const backingUp = ref(false)
const wiping = ref(false)

const backups = ref<BackupItem[]>([])

// 端口配置
const portConfig = ref<{ port: number; default: number }>({ port: 80, default: 80 })
const portSaving = ref(false)

// 闲置超时配置
const idleConfig = ref({
  enabled: true,
  timeout_minutes: 30,
  warning_seconds: 60,
})
const idleLoading = ref(false)
const idleSaving = ref(false)

/** 把分钟数转成"X 小时 Y 分钟"格式 */
function formatDuration(min: number): string {
  if (!min || min < 1) return '0 分钟'
  if (min < 60) return `${min} 分钟`
  const h = Math.floor(min / 60)
  const m = min % 60
  return m === 0 ? `${h} 小时` : `${h} 小时 ${m} 分钟`
}

async function loadAll() {
  await systemConfigStore.fetchSettings()
  Object.assign(settings.value, systemConfigStore.settings)
  await Promise.all([loadBackups(), loadPortConfig(), loadIdleConfig()])
}

async function loadIdleConfig() {
  idleLoading.value = true
  try {
    const res = await get('/settings/idle-config') // 动态响应结构
    // 拦截器已解包: res = { enabled, timeout_minutes, warning_seconds, timeout_ms, warning_ms }
    if (res && typeof res === 'object' && typeof res.timeout_minutes === 'number') {
      idleConfig.value = {
        enabled: !!res.enabled,
        timeout_minutes: res.timeout_minutes,
        warning_seconds: typeof res.warning_seconds === 'number' ? res.warning_seconds : 60,
      }
    }
  } catch (e) {
    console.warn('[Backup] loadIdleConfig failed, use default', e)
  } finally {
    idleLoading.value = false
  }
}

async function handleSaveIdle() {
  // 业务校验
  const toMin = Number(idleConfig.value.timeout_minutes)
  const toSec = Number(idleConfig.value.warning_seconds)
  if (!Number.isInteger(toMin) || toMin < 1 || toMin > 1440) {
    ElMessage.error('超时分钟数必须是 1-1440 之间的整数')
    return
  }
  if (!Number.isInteger(toSec) || toSec < 0 || toSec > 600) {
    ElMessage.error('提前提示秒数必须是 0-600 之间的整数')
    return
  }
  if (toSec >= toMin * 60) {
    ElMessage.error('提前提示秒数不能大于等于总超时时间')
    return
  }
  idleSaving.value = true
  try {
    const res = await put('/settings', { // 动态响应结构
      idle_enabled: !!idleConfig.value.enabled,
      idle_timeout_minutes: toMin,
      idle_warning_seconds: toSec,
    })
    // 拦截器已解包: res = { system_name, ..., idle_enabled, idle_timeout_minutes, ... }
    if (res && typeof res === 'object') {
      // 同步最新值
      if (typeof res.timeout_minutes === 'number') {
        idleConfig.value = {
          enabled: !!res.enabled,
          timeout_minutes: res.timeout_minutes,
          warning_seconds: typeof res.warning_seconds === 'number' ? res.warning_seconds : 60,
        }
      }
      // 重置当前会话的 idle 计时器,立即应用新配置
      resetIdleTimer()
      ElMessage.success('会话安全设置已保存,当前用户立即生效')
    } else {
      ElMessage.error('保存失败,请重试')
    }
  } catch (e: unknown) {
    ElMessage.error('保存失败:' + ((e as ApiError)?.message || '未知错误'))
  } finally {
    idleSaving.value = false
  }
}

async function loadPortConfig() {
  try {
    const res = await get('/settings/port') // 动态响应结构
    // 拦截器解包后 res = { port, default }
    if (res && typeof res === 'object' && typeof res.port === 'number') {
      portConfig.value = { port: res.port, default: res.default ?? 80 }
    }
  } catch (e) {
    // 静默失败 — 用默认 80
    console.warn('[Backup] loadPortConfig failed, use default 80', e)
  }
}

async function handleSavePort() {
  const port = Number(portConfig.value.port)
  if (!Number.isInteger(port) || port < 1 || port > 65535) {
    ElMessage.error('端口必须是 1-65535 之间的整数')
    return
  }
  portSaving.value = true
  try {
    const res = await put('/settings/port', { port }) // 动态响应结构
    // 拦截器解包后 res = { port, default }
    if (res && typeof res === 'object' && typeof res.port === 'number') {
      portConfig.value = { port: res.port, default: res.default ?? 80 }
      ElMessageBox.alert(
        `端口已保存为 <b>${res.port}</b>。<br><br>请按以下步骤重启 web 服务以生效：<br>` +
        `1. 修改 nginx/apache 监听端口<br>` +
        `2. <code>sudo systemctl restart php8.3-fpm</code><br>` +
        `3. <code>sudo systemctl restart nginx</code><br><br>` +
        `然后通过 <code>http://服务器IP:${res.port}</code> 访问`,
        '保存成功 — 需重启 web 服务',
        { dangerouslyUseHTMLString: true, type: 'success', confirmButtonText: '我知道了' }
      )
    } else {
      ElMessage.success('端口配置已保存')
    }
  } catch (e: unknown) {
    ElMessage.error('保存失败：' + ((e as ApiError)?.message || '未知错误'))
  } finally {
    portSaving.value = false
  }
}

async function handleSave() {
  saving.value = true
  try {
    const ok = await systemConfigStore.saveSettings(settings.value)
    if (ok) {
      // 同步本地表单到 store 返回的最新值（避免脏数据）
      Object.assign(settings.value, systemConfigStore.settings)
      ElMessage.success('系统设置已保存，全员可见')
    } else {
      ElMessage.error('保存失败，请重试')
    }
  } catch (e: unknown) {
    // 拦截器已 toast 过具体原因
    ElMessage.error('保存失败：' + ((e as ApiError)?.message || '未知错误'))
  } finally {
    saving.value = false
  }
}

function handleReset() {
  ElMessageBox.confirm('确定要恢复系统设置为默认值吗？当前已保存的设置会丢失。', '恢复默认', {
    type: 'warning',
  }).then(async () => {
    await systemConfigStore.saveSettings({
      system_name: '安防运维OA办公系统',
      system_short_name: '安防OA',
      copyright: '© 2026 安防运维科技有限公司',
      copyright_url: 'https://www.example.com',
      announcement: '',
      icp: '粤ICP备2026000000号-1',
      contact_email: 'admin@example.com',
    })
    await loadAll()
    ElMessage.success('已恢复默认设置')
  }).catch(() => { /* cancel */ })
}

async function loadBackups() {
  loading.value = true
  try {
    const res = await get('/backups') // 动态响应结构
    // 拦截器已解 {code, data}, 直接 res.data 才是 <files[]>
    const d = res?.data ?? []
    const list = Array.isArray(d) ? d : []
    backups.value = list.map((f: BackupItem) => ({
      ...f,
      time: (f.time || '').slice(0, 19),
    }))
  } catch (e) { /* handled */ } finally {
    loading.value = false
  }
}

async function handleManualBackup() {
  try {
    await ElMessageBox.confirm('确认执行手动全量备份？', '确认备份', {
      confirmButtonText: '开始备份', cancelButtonText: '取消', type: 'warning',
    })
    backingUp.value = true
    const res = await post('/backups', { label: 'manual' }) // 动态响应结构
    if (res?.code === 0) {
      ElMessage.success('备份已完成')
      loadBackups()
    }
  } catch (e: unknown) {
    if (e !== 'cancel' && e !== 'close') ElMessage.error((e as ApiError)?.message || '备份失败')
  } finally {
    backingUp.value = false
  }
}

async function handleDownload(row: BackupItem) {
  try {
    const res = await get(`/backups/${row.filename}/download`, undefined, { responseType: 'blob' })
    const blob = res instanceof Blob ? res : new Blob([res])
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url; a.download = row.filename; a.click()
    URL.revokeObjectURL(url)
    ElMessage.success('下载已开始')
  } catch (e: unknown) {
    ElMessage.error((e as ApiError)?.message || '下载失败')
  }
}

async function handleDelete(row: BackupItem) {
  try {
    await ElMessageBox.confirm(`确认删除备份「${row.filename}」？`, '删除确认', { type: 'warning' })
    const res = await del(`/backups/${row.filename}`) // 动态响应结构
    if (res?.code === 0) {
      ElMessage.success('已删除')
      loadBackups()
    }
  } catch (e: unknown) {
    if (e !== 'cancel' && e !== 'close') ElMessage.error((e as ApiError)?.message || '删除失败')
  }
}

async function handleWipeData() {
  try {
    const { value: pwd } = await ElMessageBox.prompt(
      '此操作将永久删除所有业务数据，且不可恢复！\n请输入 admin 登录密码以确认：',
      '高危操作确认',
      {
        confirmButtonText: '确认清空',
        cancelButtonText: '取消',
        type: 'error',
        inputType: 'password',
        inputPlaceholder: 'admin 密码',
        inputValidator: (val: string) => val.length > 0 || '请输入密码',
      }
    )
    try {
      const { value: phrase } = await ElMessageBox.prompt(
        `请输入 "确认清空" 四个字（再次确认）：`,
        '最后确认',
        {
          confirmButtonText: '立即清空',
          cancelButtonText: '取消',
          type: 'error',
          inputPlaceholder: '确认清空',
          inputValidator: (val: string) => val === '确认清空' || '请输入"确认清空"',
        }
      )
      wiping.value = true
      const res = await post('/admin/wipe-data', { password: pwd, confirm_phrase: phrase }) // 动态响应结构
      // request.ts 已解包: res = data (含 code/message/data)
      // 但 wipe-data 返回结构是 {code, message, data: {tbl: cnt}}
      if (res && res.code === 0) {
        const total = Object.values(res.data || {}).reduce((s: number, v: unknown) => s + (typeof v === 'number' ? v : 0), 0)
        ElMessageBox.alert(`已清理 ${total} 条业务数据，系统基础数据（用户/部门/角色/权限/设置）已保留。`, '清空成功', {
          type: 'success',
          confirmButtonText: '我知道了',
        })
        // 刷新设置/备份 + 强刷所有模块的列表（清缓存）
        loadAll()
        // 用 router.go(0) 强制刷新整个页面，让所有 store 重拉数据
        setTimeout(() => {
          router.go(0)
        }, 1500)
      } else {
        ElMessage.error(res?.message || '清空失败')
      }
    } catch (e: unknown) {
      if (e !== 'cancel' && e !== 'close') ElMessage.error((e as ApiError)?.message || '操作失败')
    }
  } catch (e: unknown) {
    if (e !== 'cancel' && e !== 'close') ElMessage.error((e as ApiError)?.message || '已取消')
  } finally {
    wiping.value = false
  }
}

onMounted(() => {
  loadAll()
})
</script>

<style lang="scss" scoped>
.page-container { padding: 20px; background: #f5f7fa; min-height: 100%; }
.page-header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 20px;
  .page-title { font-size: 20px; font-weight: 600; color: #303133; }
}
.content-card {
  background: #fff; border-radius: 8px; padding: 20px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
}
.danger-card { border-left: 4px solid #A32D2D; }
.card-title {
  display: flex; align-items: center; justify-content: space-between;
  font-size: 16px; font-weight: 600; color: #303133; margin-bottom: 16px;
  padding-bottom: 12px; border-bottom: 1px solid #ebeef5;
  &__actions { display: flex; gap: 8px; }
  .muted { font-size: 12px; font-weight: 400; color: #909399; }
}
</style>
