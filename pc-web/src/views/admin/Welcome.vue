<template>
  <div class="admin-welcome">
    <h2>系统管理后台</h2>
    <el-alert type="warning" :closable="false" show-icon style="margin: 16px 0">
      系统账号（system）是超级管理员，仅用于系统级操作（初始化、清空、字典、监控、备份）。
      请使用业务管理员账号登录业务工作台。
    </el-alert>

    <!-- ========== 超级管理员状态卡片 ========== -->
    <el-row :gutter="16" style="margin-top: 16px">
      <el-col :span="24">
        <el-card shadow="hover">
          <div class="card-title" style="display:flex;align-items:center;justify-content:space-between">
            <span>
              <el-icon color="#0C447C" style="vertical-align:middle"><Avatar /></el-icon>
              超级管理员账号
            </span>
            <el-tag :type="superAdmin?.has_password ? 'success' : 'warning'" size="large">
              {{ superAdmin?.has_password ? '已设置密码' : '未设置密码（首次登录需设置）' }}
            </el-tag>
          </div>
          <el-descriptions :column="2" border style="margin-top: 12px">
            <el-descriptions-item label="账号">{{ superAdmin?.username || 'system' }}</el-descriptions-item>
            <el-descriptions-item label="显示名">{{ superAdmin?.display_name || '系统超级管理员' }}</el-descriptions-item>
            <el-descriptions-item label="类型">超级管理员（独立于组织/权限之外）</el-descriptions-item>
            <el-descriptions-item label="创建时间">{{ superAdmin?.initialized_at || '—' }}</el-descriptions-item>
          </el-descriptions>
          <el-alert
            v-if="!superAdmin?.has_password"
            type="error"
            :closable="false"
            style="margin-top: 12px"
            show-icon
          >
            <p><b>当前状态：未设置密码</b></p>
            <p>这是 system 账号首次进入系统的默认状态。请点击下方"设置超级管理员密码"按钮，system 账号才能正常使用。</p>
          </el-alert>
          <div style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap">
            <el-button
              v-if="!superAdmin?.has_password"
              type="primary"
              :icon="Lock"
              :loading="settingPwd"
              @click="handleSetSuperAdminPassword"
            >
              设置超级管理员密码
            </el-button>
            <el-button v-else :icon="Refresh" @click="loadSuperAdmin">刷新</el-button>
            <el-button
              type="success"
              :icon="UserFilled"
              :disabled="!superAdmin?.has_password"
              @click="openBusinessAdminDialog"
            >
              创建 / 重置业务管理员
            </el-button>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- ========== V1.2.4: 业务管理员创建 / 重置密码弹窗 ========== -->
    <el-dialog
      v-model="bizAdminDialog.show"
      :title="bizAdminDialog.mode === 'create' ? '创建业务管理员' : '重置业务管理员密码'"
      width="640px"
      :close-on-click-modal="false"
      @close="resetBizAdminDialog"
    >
      <el-tabs v-model="bizAdminDialog.mode" @tab-change="onBizAdminTabChange">
        <el-tab-pane label="创建新管理员" name="create">
          <el-alert type="info" :closable="false" show-icon style="margin-bottom: 16px">
            创建一个业务管理员账号, 可登录业务工作台。首次登录会被强制改密。
          </el-alert>
          <el-form :model="bizAdminDialog.createForm" :rules="createRules" ref="createFormRef" label-width="100px">
            <el-form-item label="登录用户名" prop="username">
              <el-input v-model="bizAdminDialog.createForm.username" placeholder="如: admin" clearable />
            </el-form-item>
            <el-form-item label="姓名" prop="name">
              <el-input v-model="bizAdminDialog.createForm.name" placeholder="如: 王经理" clearable />
            </el-form-item>
            <el-form-item label="初始密码" prop="password">
              <el-input v-model="bizAdminDialog.createForm.password" type="password" show-password clearable />
            </el-form-item>
            <el-form-item label="手机号" prop="phone">
              <el-input v-model="bizAdminDialog.createForm.phone" placeholder="11 位手机号 (必填, 系统要求唯一)" clearable />
            </el-form-item>
            <el-form-item label="邮箱">
              <el-input v-model="bizAdminDialog.createForm.email" placeholder="可选" clearable />
            </el-form-item>
            <el-form-item label="部门">
              <el-select v-model="bizAdminDialog.createForm.department_id" placeholder="可选" clearable style="width: 100%">
                <el-option v-for="d in departments" :key="d.id" :label="d.name" :value="d.id" />
              </el-select>
            </el-form-item>
            <el-form-item label="角色">
              <el-select v-model="bizAdminDialog.createForm.role_id" placeholder="可选, 推荐 admin" clearable style="width: 100%">
                <el-option v-for="r in roles" :key="r.id" :label="r.description || r.name" :value="r.id" />
              </el-select>
            </el-form-item>
          </el-form>
        </el-tab-pane>
        <el-tab-pane label="重置现有管理员密码" name="reset">
          <el-alert type="warning" :closable="false" show-icon style="margin-bottom: 16px">
            选择一个业务用户, 重置其密码。重置后该用户会被强制下线, 下次登录必须改密。
          </el-alert>
          <el-form :model="bizAdminDialog.resetForm" :rules="resetRules" ref="resetFormRef" label-width="100px">
            <el-form-item label="选择用户" prop="user_id">
              <el-select
                v-model="bizAdminDialog.resetForm.user_id"
                placeholder="选择要重置密码的业务用户 (排除 system)"
                filterable
                style="width: 100%"
              >
                <el-option
                  v-for="u in businessUsers"
                  :key="u.id"
                  :label="`${u.username} (${u.name || '-'}) — id=${u.id}`"
                  :value="u.id"
                />
              </el-select>
            </el-form-item>
            <el-form-item label="新密码" prop="new_password">
              <el-input v-model="bizAdminDialog.resetForm.new_password" type="password" show-password clearable />
            </el-form-item>
          </el-form>
        </el-tab-pane>
      </el-tabs>
      <template #footer>
        <el-button @click="bizAdminDialog.show = false">取消</el-button>
        <el-button
          type="primary"
          :loading="bizAdminDialog.submitting"
          @click="submitBizAdmin"
        >
          {{ bizAdminDialog.mode === 'create' ? '创建管理员' : '重置密码' }}
        </el-button>
      </template>
    </el-dialog>

    <!-- ========== V1.2.4o: 只删初始化向导, 数据字典 + 系统监控 + License 激活 保留 ========== -->
    <el-row :gutter="16" style="margin-top: 16px">
      <el-col :span="8">
        <el-card shadow="hover" @click="$router.push('/settings/dict')" style="cursor: pointer">
          <div class="card-title"><el-icon color="#0C447C"><Collection /></el-icon> 数据字典</div>
          <div class="card-desc">维修方式 / 客户来源 / 区域 / 设备类型</div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover" @click="$router.push('/settings/monitor')" style="cursor: pointer">
          <div class="card-title"><el-icon color="#0C447C"><Monitor /></el-icon> 系统监控</div>
          <div class="card-desc">CPU / 内存 / 磁盘 / 备份 / 健康检查</div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover" @click="goLicense" style="cursor: pointer">
          <div class="card-title"><el-icon color="#0C447C"><MagicStick /></el-icon> License 激活</div>
          <div class="card-desc">创建业务管理员账号 / License 激活</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- ========== V1.0.4: 高危操作 — 一键清除系统数据 ========== -->
    <div class="content-card danger-card" style="margin-top: 24px">
      <div class="card-title">
        <span style="color: #A32D2D">⚠ 高危操作（仅 system 账号可见）</span>
      </div>
      <el-alert type="error" :closable="false" style="margin-bottom: 16px">
        <p><b>一键清除系统数据</b>将删除系统中 <b>所有</b> 数据，包括：</p>
        <ul style="margin: 6px 0 6px 20px">
          <li>业务数据：客户 / 项目 / 工单 / 车辆 / 库存 / 财务 / 考勤 / 网盘 / 知识库 / 消息 / 审批等</li>
          <li><b style="color: #A32D2D">系统数据：所有用户（包括 admin/manager 等业务账号）、角色、权限、部门、岗位、系统设置</b></li>
        </ul>
        <p>清除后系统进入 <b>0 数据状态</b>，重新打开页面后会看到 <b>system 超级管理员密码初始化界面</b>。</p>
        <p>system 账号 <b>不会</b> 被删除（保证可登录），但密码会重置为空，需要首次登录者自行设置。</p>
        <p>此操作 <b>不可逆</b>，执行前请先手动备份数据库！</p>
        <p><b>权限说明</b>：仅 system 账号可执行，administrator / manager / finance / user 调用将被后端 403 拒绝。</p>
      </el-alert>
      <el-button type="danger" :icon="Delete" :loading="wiping" @click="handleWipeData">
        一键清除系统数据
      </el-button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox, type FormInstance, type FormRules } from 'element-plus'
import { Delete, Lock, Refresh, Avatar, UserFilled, MagicStick, Collection, Monitor } from '@element-plus/icons-vue'
import { get, post } from '@/utils/request'

const wiping = ref(false)
const settingPwd = ref(false)
const superAdmin = ref<Record<string, unknown> | null>(null)

// 业务管理员弹窗状态
const bizAdminDialog = reactive({
  show: false,
  mode: 'create' as 'create' | 'reset',
  submitting: false,
  createForm: {
    username: '',
    name: '',
    password: '',
    phone: '',
    email: '',
    department_id: undefined as number | undefined,
    role_id: undefined as number | undefined,
  },
  resetForm: {
    user_id: undefined as number | undefined,
    new_password: '',
  },
})
const createFormRef = ref<FormInstance>()
const resetFormRef = ref<FormInstance>()
const departments = ref<Record<string, unknown>[]>([])
const roles = ref<Record<string, unknown>[]>([])
const businessUsers = ref<Record<string, unknown>[]>([])

const createRules: FormRules = {
  username: [{ required: true, message: '请输入登录用户名', trigger: 'blur' }],
  name:     [{ required: true, message: '请输入姓名', trigger: 'blur' }],
  password: [
    { required: true, message: '请输入初始密码', trigger: 'blur' },
    { min: 8, max: 32, message: '密码 8-32 位', trigger: 'blur' },
  ],
  phone: [
    { required: true, message: '请输入手机号 (必填, 唯一)', trigger: 'blur' },
    { pattern: /^1[3-9]\d{9}$/, message: '手机号格式不对', trigger: 'blur' },
  ],
}
const resetRules: FormRules = {
  user_id:      [{ required: true, message: '请选择用户', trigger: 'change' }],
  new_password: [
    { required: true, message: '请输入新密码', trigger: 'blur' },
    { min: 8, max: 32, message: '密码 8-32 位', trigger: 'blur' },
  ],
}

async function openBusinessAdminDialog() {
  bizAdminDialog.show = true
  bizAdminDialog.mode = 'create'
  resetBizAdminDialog()
  // 加载部门 / 角色 / 业务用户
  try {
    const [depRes, roleRes, empRes] = await Promise.all([
      // V1.2.9 BUG FIX: system 调 /api/departments / /api/roles 会被 ensure_business 拦 403
      // 走 /system/departments /system/roles 专用端点
      get('/system/departments'),
      get('/system/roles'),
      // V1.2.4j: system 用户调 /api/employees 会被 ensure_business 拦截, 必须走专用端点
      get('/system/employees?per_page=200'),
    ])
    // 解包: 拦截器已解 {code, data}, data 可能是 {data:[]} 或 []
    departments.value = depRes?.data || (Array.isArray(depRes) ? depRes : [])
    roles.value = roleRes?.data?.data || roleRes?.data || (Array.isArray(roleRes) ? roleRes : [])
    // 业务用户 = 排除 system
    const empList = empRes?.data?.data || empRes?.data || []
    businessUsers.value = (Array.isArray(empList) ? empList : []).filter((u: Record<string, unknown>) => u.username !== 'system')
  } catch (e) {
    console.warn('[Welcome] load biz admin data failed', e)
  }
}

function onBizAdminTabChange(mode: string | number | undefined) {
  if (mode === 'create' || mode === 'reset') {
    bizAdminDialog.mode = mode
  }
}

function resetBizAdminDialog() {
  bizAdminDialog.createForm = {
    username: '', name: '', password: '', phone: '', email: '',
    department_id: undefined, role_id: undefined,
  }
  bizAdminDialog.resetForm = { user_id: undefined, new_password: '' }
}

async function submitBizAdmin() {
  if (bizAdminDialog.mode === 'create') {
    const valid = await createFormRef.value?.validate().catch(() => false)
    if (!valid) return
    bizAdminDialog.submitting = true
    try {
      const res = await post('/system/business-admin', {
        mode: 'create',
        ...bizAdminDialog.createForm,
      })
      if (res && res.code === 0) {
        ElMessage.success(`业务管理员 ${res.data.username} 创建成功`)
        bizAdminDialog.show = false
        resetBizAdminDialog()
      } else {
        ElMessage.error(res?.message || '创建失败')
      }
    } catch (e: unknown) {
      ElMessage.error(e?.response?.data?.message || e?.message || '创建失败')
    } finally {
      bizAdminDialog.submitting = false
    }
  } else {
    const valid = await resetFormRef.value?.validate().catch(() => false)
    if (!valid) return
    try {
      await ElMessageBox.confirm(
        '重置密码后该用户会被强制下线, 下次登录必须改密。确认操作?',
        '确认重置',
        { type: 'warning', confirmButtonText: '确认重置', cancelButtonText: '取消' },
      )
    } catch (e) {
      return  // 取消
    }
    bizAdminDialog.submitting = true
    try {
      const res = await post('/system/business-admin', {
        mode: 'reset_password',
        ...bizAdminDialog.resetForm,
      })
      if (res && res.code === 0) {
        ElMessage.success(`业务管理员 ${res.data.username} 密码已重置`)
        bizAdminDialog.show = false
        resetBizAdminDialog()
      } else {
        ElMessage.error(res?.message || '重置失败')
      }
    } catch (e: unknown) {
      ElMessage.error(e?.response?.data?.message || e?.message || '重置失败')
    } finally {
      bizAdminDialog.submitting = false
    }
  }
}

async function loadSuperAdmin() {
  try {
    const res = await get('/settings/super-admin')
    // 拦截器已解包: res = { code, data: { exists, username, has_password, ... } }
    if (res && res.code === 0 && res.data) {
      superAdmin.value = res.data
    } else if (res && typeof res.has_password === 'boolean') {
      // 兜底: 如果 res 本身就是 data
      superAdmin.value = res
    }
  } catch (e) {
    console.warn('[Welcome] loadSuperAdmin failed', e)
  }
}

async function handleSetSuperAdminPassword() {
  try {
    const { value: pwd } = await ElMessageBox.prompt(
      '请为 system 超级管理员账号设置一个新密码（至少 8 位）：',
      '设置超级管理员密码',
      {
        confirmButtonText: '下一步',
        cancelButtonText: '取消',
        type: 'warning',
        inputType: 'password',
        inputPlaceholder: '新密码',
        inputValidator: (val: string) => val.length >= 8 || '密码至少 8 位',
      }
    )
    const { value: pwd2 } = await ElMessageBox.prompt(
      '请再次输入新密码以确认：',
      '确认密码',
      {
        confirmButtonText: '确认设置',
        cancelButtonText: '返回上一步',
        type: 'warning',
        inputType: 'password',
        inputPlaceholder: '再次输入新密码',
        inputValidator: (val: string) => val === pwd || '两次输入的密码不一致',
      }
    )
    settingPwd.value = true
    const res = await post('/system/super-admin/set-password', {
      new_password: pwd,
      confirm_password: pwd2,
    })
    if (res && res.code === 0) {
      ElMessage.success('超级管理员密码已设置，下次登录请使用新密码')
      await loadSuperAdmin()
    } else {
      ElMessage.error(res?.message || '设置失败')
    }
  } catch (e: unknown) {
    if (e !== 'cancel' && e !== 'close') ElMessage.error(e?.message || '已取消')
  } finally {
    settingPwd.value = false
  }
}

async function goLicense() {
  // V1.2.4n: 临时 License 激活入口, 大哥要求"点完跳 404"
  window.location.href = '/no-license'
}

async function handleWipeData() {
  try {
    const { value: pwd } = await ElMessageBox.prompt(
      '此操作将永久删除系统中所有数据（包括 admin/manager 等业务账号、角色、权限、设置）！\n清除后系统将进入 0 数据状态，需要重新初始化。\n请输入 system 登录密码以确认：',
      '⚠ 极高危操作确认',
      {
        confirmButtonText: '下一步',
        cancelButtonText: '取消',
        type: 'error',
        inputType: 'password',
        inputPlaceholder: 'system 密码',
        inputValidator: (val: string) => val.length > 0 || '请输入密码',
      }
    )
    try {
      const { value: phrase } = await ElMessageBox.prompt(
        `请输入 "确认清空所有数据" 七个字（最终确认）：`,
        '最终确认',
        {
          confirmButtonText: '立即清空',
          cancelButtonText: '返回',
          type: 'error',
          inputPlaceholder: '确认清空所有数据',
          inputValidator: (val: string) => val === '确认清空所有数据' || '请输入"确认清空所有数据"',
        }
      )
      wiping.value = true
      const res = await post('/system/wipe-all', { password: pwd, confirm_phrase: phrase })
      if (res && res.code === 0) {
        await ElMessageBox.alert(
          `已清空 ${Object.keys(res.data || {}).length} 张表的所有数据。\n系统已重置为 0 数据状态。\n\n下一步：\n1. 重新打开浏览器访问本系统\n2. 使用 system / 空密码 登录\n3. 首次登录会强制要求设置 system 超级管理员密码\n4. 然后进入"初始化向导"创建业务管理员账号`,
          '系统已重置',
          {
            type: 'success',
            dangerouslyUseHTMLString: false,
            confirmButtonText: '我知道了',
          }
        )
        // 强制跳登录页 + 清空本地 token
        try {
          localStorage.removeItem('oa_access_token')
          localStorage.removeItem('oa_user_info')
        } catch {}
        setTimeout(() => {
          window.location.href = '/login'
        }, 1000)
      } else {
        ElMessage.error(res?.message || '清空失败')
      }
    } catch (e: unknown) {
      if (e !== 'cancel' && e !== 'close') ElMessage.error(e?.message || '操作失败')
    }
  } catch (e: unknown) {
    if (e !== 'cancel' && e !== 'close') ElMessage.error(e?.message || '已取消')
  } finally {
    wiping.value = false
  }
}

onMounted(() => {
  loadSuperAdmin()
})
</script>

<style scoped>
.admin-welcome { padding: 8px; }
.card-title { font-size: 15px; font-weight: 500; margin-bottom: 8px; }
.card-desc { font-size: 13px; color: var(--color-text-secondary); }
.content-card {
  background: #fff; border-radius: 8px; padding: 20px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
}
.danger-card { border-left: 4px solid #A32D2D; }
ul { margin: 6px 0; padding-left: 20px; }
ul li { line-height: 1.8; }
</style>
