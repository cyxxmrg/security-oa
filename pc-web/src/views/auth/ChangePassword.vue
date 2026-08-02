<template>
  <div class="change-password-page">
    <div class="cp-card">
      <div class="cp-header">
        <el-icon class="cp-icon"><Lock /></el-icon>
        <h2>{{ hasOldPassword ? '首次登录 — 修改密码' : '初始化系统 — 设置超级管理员密码' }}</h2>
        <p class="cp-sub">
          {{ hasOldPassword
            ? '出于安全考虑, 首次登录必须修改默认密码'
            : '请为 system 超级管理员账号设置一个密码, 这是您首次进入系统的关键一步' }}
        </p>
      </div>

      <el-form
        ref="formRef"
        :model="form"
        :rules="rules"
        label-position="top"
        @submit.prevent="handleSubmit"
      >
        <el-form-item v-if="hasOldPassword" label="原密码" prop="oldPassword">
          <el-input v-model="form.oldPassword" type="password" show-password clearable />
        </el-form-item>
        <el-form-item v-else label="超级管理员账号">
          <el-input :model-value="superAdminUsername" disabled />
          <span style="font-size: 12px; color: #888; margin-top: 4px; display: block">
            system 账号, 不需要原密码, 直接设置新密码即可
          </span>
        </el-form-item>
        <el-form-item label="新密码" prop="newPassword">
          <el-input v-model="form.newPassword" type="password" show-password clearable />
        </el-form-item>
        <el-form-item label="确认新密码" prop="confirmPassword">
          <el-input v-model="form.confirmPassword" type="password" show-password clearable />
        </el-form-item>
        <el-alert type="info" :closable="false" show-icon style="margin-bottom: 16px">
          密码要求: 8-32 位, 必须同时包含字母和数字
        </el-alert>
        <el-button type="primary" :loading="loading" class="cp-btn" @click="handleSubmit">
          {{ hasOldPassword ? '确认修改并进入系统' : '确认设置, 进入初始化向导' }}
        </el-button>
      </el-form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, type FormInstance, type FormRules } from 'element-plus'
import { Lock } from '@element-plus/icons-vue'
import { useUserStore } from '@/stores/user'
import { post, get } from '@/utils/request'

const router = useRouter()
const userStore = useUserStore()
const formRef = ref<FormInstance>()
const loading = ref(false)
const superAdminInfo = ref<Record<string, unknown> | null>(null)

const hasOldPassword = computed(() => {
  // V1.2.4: 优先看后端 super-admin 端点的 has_password
  if (superAdminInfo.value && superAdminInfo.value.has_password !== undefined) {
    return superAdminInfo.value.has_password
  }
  // 兜底: 看 store 的 must_change_password 标志
  const u = userStore.userInfo as Record<string, unknown>
  if (u?.username === 'system' && (u?.must_change_password === true)) {
    return false
  }
  return true
})

const superAdminUsername = computed(() => superAdminInfo.value?.username || 'system')

const form = reactive({
  oldPassword: '',
  newPassword: '',
  confirmPassword: '',
})

const rules: FormRules = {
  oldPassword: hasOldPassword.value
    ? [{ required: true, message: '请输入原密码', trigger: 'blur' }]
    : [],
  newPassword: [
    { required: true, message: '请输入新密码', trigger: 'blur' },
    { min: 8, max: 32, message: '密码 8-32 位', trigger: 'blur' },
    {
      pattern: /^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d!@#$%^&*()_+\-=\[\]{};:'",.<>\/?\\|`~]{8,32}$/,
      message: '必须同时包含字母和数字',
      trigger: 'blur',
    },
  ],
  confirmPassword: [
    { required: true, message: '请确认新密码', trigger: 'blur' },
    {
      validator: (_r: Record<string, unknown>, value: string, cb: (e?: Error) => void) => {
        if (value !== form.newPassword) cb(new Error('两次输入不一致'))
        else cb()
      },
      trigger: 'blur',
    },
  ],
}

async function loadSuperAdmin() {
  try {
    const res = await get('/settings/super-admin')
    // V1.2.4 修复: 正确解包, 拦截器已解 {code, data}, 取 res.data
    if (res && res.code === 0 && res.data) {
      superAdminInfo.value = res.data
    } else if (res && typeof res.has_password === 'boolean') {
      // 兜底
      superAdminInfo.value = res
    }
  } catch (e) {
    // 兜底
  }
}

async function handleSubmit() {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  loading.value = true
  try {
    if (hasOldPassword.value) {
      // 正常改密 (有原密码)
      await post('/auth/change-password', {
        oldPassword: form.oldPassword,
        newPassword: form.newPassword,
      })
    } else {
      // system 首次设密 (无原密码, password=null)
      await post('/system/super-admin/set-password', {
        new_password: form.newPassword,
        confirm_password: form.confirmPassword,
      })
    }
    // 同步 userInfo (本地 + 重新拉 /auth/me 拿最新 must_change_password 状态)
    if (userStore.userInfo) {
      userStore.userInfo.must_change_password = false
    }
    try {
      // 强制重新拉一次, 确保后端已更新
      await userStore.getUserInfoAction?.()
    } catch {}
    ElMessage.success(hasOldPassword.value ? '密码修改成功' : '超级管理员密码已设置')
    // system 账号进入初始化向导; 业务用户直接进 dashboard
    if (userStore.userInfo?.user_type === 'system') {
      // V1.2.4: 重新拉一次 super-admin, 看 has_password 状态
      // 如果仍没设上密码, 留在 change-password 页面
      await loadSuperAdmin()
      if (superAdminInfo.value?.has_password === false) {
        ElMessage.error('密码设置失败, 请重试')
        return
      }
      router.push('/admin/wizard')
    } else {
      router.push('/dashboard')
    }
  } catch (e: unknown) {
    const msg = e?.response?.data?.message || e?.serverMessage || e?.message || '修改失败'
    ElMessage.error(msg)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadSuperAdmin()
})
</script>

<style scoped>
.change-password-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #042C53 0%, #185FA5 100%);
  padding: 16px;
}
.cp-card {
  background: #fff;
  border-radius: 12px;
  padding: 32px 40px;
  width: 100%;
  max-width: 460px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.16);
}
.cp-header { text-align: center; margin-bottom: 24px; }
.cp-icon { font-size: 48px; color: #185FA5; }
.cp-header h2 { margin: 12px 0 4px; font-size: 18px; font-weight: 500; }
.cp-sub { font-size: 13px; color: #888; margin: 0; }
.cp-btn { width: 100%; }
</style>
