<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">修改密码</span>
    </div>

    <div class="content-card password-card">
      <el-alert
        title="安全提醒"
        type="warning"
        :closable="false"
        show-icon
        class="security-tip"
      >
        <template #default>
          密码修改成功后，<b>其他设备</b>的登录状态将被强制下线，需要重新登录。
          请妥善保管您的新密码。
        </template>
      </el-alert>

      <div class="card-title">密码修改</div>
      <el-form
        ref="formRef"
        :model="form"
        :rules="rules"
        label-width="100px"
        label-position="right"
        class="password-form"
      >
        <el-form-item label="原密码" prop="oldPassword">
          <el-input
            v-model="form.oldPassword"
            type="password"
            placeholder="请输入原密码"
            show-password
            autocomplete="current-password"
          />
        </el-form-item>
        <el-form-item label="新密码" prop="newPassword">
          <el-input
            v-model="form.newPassword"
            type="password"
            placeholder="6 位以上，建议字母+数字+符号"
            show-password
            autocomplete="new-password"
          />
          <div class="strength-bar">
            <span class="bar" :class="strengthClass[0]"></span>
            <span class="bar" :class="strengthClass[1]"></span>
            <span class="bar" :class="strengthClass[2]"></span>
            <span class="bar" :class="strengthClass[3]"></span>
            <span class="strength-text">{{ strengthText }}</span>
          </div>
        </el-form-item>
        <el-form-item label="确认密码" prop="confirmPassword">
          <el-input
            v-model="form.confirmPassword"
            type="password"
            placeholder="请再次输入新密码"
            show-password
            autocomplete="new-password"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :icon="Check" :loading="saving" @click="handleSubmit">确认修改</el-button>
          <el-button :icon="RefreshLeft" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <!-- 安全日志 -->
    <div class="content-card" style="margin-top: 20px;">
      <div class="card-title">最近密码修改记录</div>
      <el-table :data="passwordLogs" border stripe style="width: 100%;">
        <el-table-column prop="time" label="时间" width="200" />
        <el-table-column prop="ip" label="IP" width="160" />
        <el-table-column prop="action" label="操作" width="120">
          <template #default="{ row }">
            <el-tag :type="row.action === 'change_password' ? 'success' : 'info'" size="small">
              {{ row.action === 'change_password' ? '修改密码' : row.action }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="description" label="说明" />
        <el-table-column prop="user_agent" label="设备 / 浏览器" show-overflow-tooltip />
      </el-table>
      <el-empty v-if="passwordLogs.length === 0" description="暂无记录" :image-size="80" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Check, RefreshLeft } from '@element-plus/icons-vue'
import { ElMessage, type FormInstance, type FormRules } from 'element-plus'
import { post, get } from '@/utils/request'
import { unwrapList } from '@/utils/response'
import type { PasswordLog } from './types'

const formRef = ref<FormInstance>()
const saving = ref(false)

const form = ref({
  oldPassword: '',
  newPassword: '',
  confirmPassword: '',
})

const rules: FormRules = {
  oldPassword: [
    { required: true, message: '请输入原密码', trigger: 'blur' },
    { min: 6, message: '密码至少 6 位', trigger: 'blur' },
  ],
  newPassword: [
    { required: true, message: '请输入新密码', trigger: 'blur' },
    { min: 6, message: '密码至少 6 位', trigger: 'blur' },
    {
      validator: (_r, val, cb) => {
        if (val && val === form.value.oldPassword) cb(new Error('新密码不能与原密码相同'))
        else cb()
      },
      trigger: 'blur',
    },
  ],
  confirmPassword: [
    { required: true, message: '请再次输入新密码', trigger: 'blur' },
    {
      validator: (_r, val, cb) => {
        if (val && val !== form.value.newPassword) cb(new Error('两次输入的密码不一致'))
        else cb()
      },
      trigger: 'blur',
    },
  ],
}

// 密码强度计算
const strength = computed(() => {
  const p = form.value.newPassword
  if (!p) return 0
  let s = 0
  if (p.length >= 6) s++
  if (p.length >= 10) s++
  if (/[A-Z]/.test(p) && /[a-z]/.test(p)) s++
  if (/\d/.test(p) && /[^\w\s]/.test(p)) s++
  return Math.min(s, 4)
})

const strengthClass = computed(() => {
  const s = strength.value
  return [
    s >= 1 ? 'on weak' : '',
    s >= 2 ? 'on medium' : '',
    s >= 3 ? 'on strong' : '',
    s >= 4 ? 'on very-strong' : '',
  ]
})

const strengthText = computed(() => {
  return ['', '弱', '中', '强', '极强'][strength.value] || ''
})

const passwordLogs = ref<PasswordLog[]>([])

async function loadPasswordLogs() {
  try {
    // 拉审计日志,过滤本用户的修改密码记录
    // V0.6.3: res = {code, data: paginator}
    const res = await get('/audit-logs', { action: 'change_password', per_page: 10 })
    const rows = unwrapList(res)
    passwordLogs.value = rows.map((r) => ({
      time: r.created_at,
      ip: r.ip,
      action: r.action,
      description: r.description,
      user_agent: r.user_agent,
    }))
  } catch (e: unknown) {
    // 静默失败,显示空列表
    passwordLogs.value = []
  }
}

async function handleSubmit() {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (!valid) return
    saving.value = true
    try {
      const res = await post('/auth/change-password', {
        oldPassword: form.value.oldPassword,
        newPassword: form.value.newPassword,
      })
      ElMessage.success(res.message || '密码修改成功')
      handleReset()
      loadPasswordLogs()
    } catch (e: unknown) {
      const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message || (e as { message?: string })?.message || '修改失败'
      ElMessage.error(msg)
    } finally {
      saving.value = false
    }
  })
}

function handleReset() {
  formRef.value?.resetFields()
  form.value = { oldPassword: '', newPassword: '', confirmPassword: '' }
}

onMounted(() => { loadPasswordLogs() })
</script>

<style scoped lang="scss">
.page-container { padding: 20px; background: #f5f7fa; min-height: 100%; }
.page-header { margin-bottom: 20px; .page-title { font-size: 20px; font-weight: 600; color: #303133; } }
.content-card { background: #fff; border-radius: 8px; padding: 24px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06); }
.security-tip { margin-bottom: 20px; }
.card-title {
  font-size: 16px; font-weight: 600; color: #303133;
  padding-bottom: 12px; margin-bottom: 20px; border-bottom: 1px solid #ebeef5;
}
.password-form { max-width: 480px; }

.strength-bar {
  display: flex; align-items: center; gap: 4px; margin-top: 8px;
  .bar {
    width: 48px; height: 4px; background: #ebeef5; border-radius: 2px; transition: background 0.3s;
    &.on.weak      { background: #F56C6C; }
    &.on.medium    { background: #E6A23C; }
    &.on.strong    { background: #1D9E75; }
    &.on.very-strong { background: #0C447C; }
  }
  .strength-text { font-size: 12px; color: #909399; margin-left: 8px; }
}
</style>
