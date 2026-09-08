<template>
  <div class="login-container">
    <div class="login-left">
      <div class="brand">
        <div class="brand-logo">OA</div>
        <h1 class="brand-title">{{ systemName }}</h1>
        <p class="brand-desc">Security Operations & Maintenance OA System</p>
      </div>
      <div class="features">
        <div class="feature-item">
          <el-icon><DataAnalysis /></el-icon>
          <span>项目全流程数字化</span>
        </div>
        <div class="feature-item">
          <el-icon><Connection /></el-icon>
          <span>多端协同办公</span>
        </div>
        <div class="feature-item">
          <el-icon><SetUp /></el-icon>
          <span>维修中心闭环管理</span>
        </div>
        <div class="feature-item">
          <el-icon><TrendCharts /></el-icon>
          <span>业务财务一体化</span>
        </div>
      </div>
    </div>
    <div class="login-right">
      <div class="login-form-wrapper">
        <h2 class="form-title">欢迎登录</h2>
        <p class="form-subtitle">{{ systemName }}</p>
        <el-form ref="loginFormRef" :model="loginForm" :rules="loginRules" size="large" autocomplete="off">
          <!-- 隐藏字段防止浏览器自动填充 -->
          <input type="text" name="username" style="display:none" autocomplete="off" />
          <input type="password" name="password" style="display:none" autocomplete="off" />

          <el-form-item prop="username">
            <el-input
              v-model="loginForm.username"
              placeholder="请输入用户名"
              prefix-icon="User"
              autocomplete="off"
              name="disabled-username"
              @keyup.enter="handleLogin"
            />
          </el-form-item>
          <el-form-item prop="password">
            <el-input
              v-model="loginForm.password"
              type="password"
              placeholder="请输入密码"
              prefix-icon="Lock"
              show-password
              autocomplete="off"
              name="disabled-password"
              @keyup.enter="handleLogin"
            />
          </el-form-item>
          <el-form-item>
            <div class="login-options">
              <el-checkbox v-model="rememberMe">记住密码</el-checkbox>
            </div>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" :loading="loading" class="login-btn" @click="handleLogin">
              登 录
            </el-button>
          </el-form-item>
        </el-form>

        <!-- 登录失败错误条 — 表单内显眼提示 -->
        <transition name="el-fade-in">
          <div v-if="loginError" class="login-error" role="alert">
            <el-icon class="login-error__icon"><WarningFilled /></el-icon>
            <div class="login-error__body">
              <div class="login-error__msg">{{ loginError }}</div>
              <div v-if="loginErrorHint" class="login-error__hint">{{ loginErrorHint }}</div>
            </div>
            <button class="login-error__close" @click="loginError = ''" aria-label="关闭">×</button>
          </div>
        </transition>

        <div class="login-version">
          <div class="login-version__tag">{{ systemVersion }}</div>
          <div class="login-version__copyright">© 2026 XX科技有限公司</div>
        </div>
        <div class="login-links">
          <a href="/legal/agreement" target="_blank">用户协议</a>
          <span class="login-links__sep">·</span>
          <a href="/legal/privacy" target="_blank">隐私政策</a>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useUserStore } from '@/stores/user'
import { useSystemConfigStore } from '@/stores/systemConfig'
import { ElMessage } from 'element-plus'
import type { FormInstance } from 'element-plus'
import { getToken } from '@/utils/auth'
import { DataAnalysis, Connection, SetUp, TrendCharts, WarningFilled, InfoFilled, Warning } from '@element-plus/icons-vue'

const router = useRouter()
const route = useRoute()
const userStore = useUserStore()
const systemConfigStore = useSystemConfigStore()
const loginFormRef = ref<FormInstance>()
const loading = ref(false)
const rememberMe = ref(false)
const loginError = ref('')
const loginErrorHint = ref('')
const failCount = ref(0)
const lockedUntil = ref(0) // 时间戳

// 从store读取系统名称 + 版本号 (版本号源在 pc-api/config/oa.php::app_version)
const systemName = computed(() => systemConfigStore.sysConfig.systemName || '安防运维OA办公系统')
const systemVersion = computed(() => systemConfigStore.settings.version || 'v1.2.3')
// 友好的错误分类（按 HTTP 状态码 + 后端 message）
function describeError(status: number | undefined, serverMessage: string): { msg: string; hint: string } {
  const sm = (serverMessage || '').trim()
  if (!status) {
    return { msg: '网络连接失败', hint: '网络不可达，请检查网络连接后重试' }
  }
  if (status === 401) {
    if (sm.includes('密码') || sm.includes('用户') || sm.includes('凭证') || sm.includes('credentials') || sm.includes('password')) {
      return {
        msg: sm || '用户名或密码错误',
        hint: '请检查是否启用了 Caps Lock 大写锁定，已连续失败 5 次后账号将被锁定 60 秒，请稍候再试'
      }
    }
    return { msg: sm || '登录失败', hint: '请稍后重试' }
  }
  if (status === 403) {
    return { msg: sm || '账号已被禁用', hint: '请联系管理员在 系统设置 → 用户管理 中启用账号' }
  }
  if (status === 422) {
    return { msg: sm || '请求参数错误', hint: '请检查输入格式' }
  }
  if (status === 429) {
    return { msg: '登录请求过于频繁', hint: '请等待 60 秒后再试' }
  }
  if (status >= 500) {
    return { msg: '服务器内部错误 (HTTP ' + status + ')', hint: '请联系管理员检查后端日志：/var/www/oa-api/storage/logs/laravel.log' }
  }
  return { msg: sm || '登录失败 (HTTP ' + status + ')', hint: '请稍后重试或联系管理员' }
}

// 设置页面标题
onMounted(() => {
  document.title = systemName.value
  // 已登录用户访问登录页 → 直接跳到目标页(支持 ?redirect= 参数)
  if (getToken()) {
    const redirect = (route.query.redirect as string) || '/'
    router.replace(redirect)
  }

  // 防止浏览器自动填充密码
  setTimeout(() => {
    loginForm.username = ''
    loginForm.password = ''
  }, 100)
})

const loginForm = reactive({
  username: '',
  password: ''
})

const loginRules = {
  username: [{ required: true, message: '请输入用户名', trigger: 'blur' }],
  // V1.2.4: 允许 system 空密码 (wipeAll 后 password=null), 所以 password 不强制 required
  // 如果是业务用户, 后端会返回 401 提示密码错
  password: [{ required: false, message: '', trigger: 'blur' }],
}


async function handleLogin() {
  const valid = await loginFormRef.value?.validate().catch(() => false)
  if (!valid) return

  // 失败锁定：60 秒内最多 5 次
  const now = Date.now()
  if (lockedUntil.value > now) {
    const wait = Math.ceil((lockedUntil.value - now) / 1000)
    loginError.value = `登录已临时锁定，请 ${wait} 秒后再试`
    loginErrorHint.value = '为防止暴力破解，连续失败 5 次将自动锁定 60 秒'
    return
  }

  loading.value = true
  loginError.value = ''
  loginErrorHint.value = ''
  try {
    await userStore.login(loginForm)
    await userStore.getUserInfoAction()
    ElMessage.success('登录成功')
    failCount.value = 0
    // V1.2.4: 登录后判断是否需要强制改密 (system 首次/wipeAll 后)
    const u = userStore.userInfo as Record<string, unknown>
    if (u?.must_change_password) {
      router.push('/change-password')
      return
    }
    // 优先跳回拦截前的目标页(避免重定向循环到 login)
    const redirect = (route.query.redirect as string) || '/'
    router.push(redirect)
  } catch (error: unknown) {
    const requestError = error as { status?: number; serverMessage?: string; message?: string }
    failCount.value += 1
    if (failCount.value >= 5) {
      lockedUntil.value = Date.now() + 60_000
      loginError.value = '登录失败次数过多，已临时锁定 60 秒'
      loginErrorHint.value = '为防止暴力破解，请稍后再试或联系管理员重置密码'
    } else {
      const { msg, hint } = describeError(requestError.status, requestError.serverMessage || requestError.message || '')
      loginError.value = msg
      loginErrorHint.value = `${hint}（已失败 ${failCount.value}/5 次）`
    }
  } finally {
    loading.value = false
  }
}


</script>

<style lang="scss" scoped>
.login-container {
  display: flex;
  height: 100vh;
  width: 100%;
}

.login-left {
  flex: 0 0 45%;
  background: linear-gradient(135deg, #0C447C 0%, #185FA5 50%, #1D9E75 100%);
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  padding: 60px;
  color: white;
  position: relative;
  overflow: hidden;

  &::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -30%;
    width: 80%;
    height: 200%;
    background: rgba(255,255,255,0.03);
    border-radius: 40%;
    transform: rotate(15deg);
  }
}

.brand {
  text-align: center;
  position: relative;
  z-index: 1;

  .brand-logo {
    width: 80px;
    height: 80px;
    background: rgba(255,255,255,0.15);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    font-weight: 700;
    margin: 0 auto 24px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2);
  }

  .brand-title {
    font-size: 28px;
    font-weight: 600;
    margin-bottom: 8px;
    letter-spacing: 2px;
  }

  .brand-desc {
    font-size: 14px;
    opacity: 0.7;
  }
}

.features {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-top: 48px;
  position: relative;
  z-index: 1;

  .feature-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 16px;
    background: rgba(255,255,255,0.08);
    border-radius: 8px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.1);
    font-size: 13px;
    transition: all 0.3s;

    &:hover {
      background: rgba(255,255,255,0.15);
      transform: translateY(-2px);
    }

    .el-icon {
      font-size: 18px;
      color: #7fdbca;
    }
  }
}

.login-right {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #fff;
  padding: 40px;
}

.login-form-wrapper {
  width: 380px;

  .form-title {
    font-size: 24px;
    font-weight: 600;
    color: #0C447C;
    margin-bottom: 4px;
  }

  .form-subtitle {
    font-size: 14px;
    color: #909399;
    margin-bottom: 32px;
  }
}

  .login-options {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
  color: #909399;
  font-size: 13px;
}

.password-hint {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: #1D9E75;
  background: rgba(29, 158, 117, 0.08);
  padding: 4px 10px;
  border-radius: 4px;

  .el-icon {
    font-size: 14px;
  }
}

.login-btn {
  width: 100%;
  height: 44px;
  font-size: 16px;
  border-radius: 8px;
  background: linear-gradient(135deg, #0C447C, #1D9E75);
  border: none;

  &:hover {
    opacity: 0.9;
  }
}

.login-footer {
  text-align: center;
  margin-top: 24px;
  color: #c0c4cc;
  font-size: 12px;
}

.login-version {
  text-align: center;
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px dashed #e4e7ed;
}
.login-version__tag {
  display: inline-block;
  font-size: 12px;
  font-weight: 700;
  color: #fff;
  background: linear-gradient(135deg, #0C447C 0%, #1D9E75 100%);
  padding: 3px 12px;
  border-radius: 10px;
  letter-spacing: 0.5px;
  box-shadow: 0 2px 6px rgba(12, 68, 124, 0.2);
}
.login-version__copyright {
  margin-top: 8px;
  font-size: 11px;
  color: #909399;
  letter-spacing: 0.3px;
}
.login-links {
  text-align: center;
  margin-top: 16px;
  font-size: 12px;
  color: #909399;
  a {
    color: #606266;
    text-decoration: none;
    transition: color 0.2s;
    &:hover { color: #0C447C; text-decoration: underline; }
  }
  &__sep { margin: 0 8px; color: #c0c4cc; }
}
  .login-error {
  margin-top: 16px;
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px 14px;
  background: linear-gradient(135deg, rgba(245,108,108,0.06) 0%, rgba(245,108,108,0.10) 100%);
  border: 1px solid rgba(245,108,108,0.35);
  border-left: 4px solid #F56C6C;
  border-radius: 8px;
  color: #c45656;
  font-size: 13px;
  line-height: 1.5;
  position: relative;
  animation: shakeX 0.4s ease-in-out;

  &__icon {
    color: #F56C6C;
    font-size: 18px;
    flex-shrink: 0;
    margin-top: 1px;
  }

  &__body {
    flex: 1;
    min-width: 0;
  }

  &__msg {
    font-weight: 600;
    color: #c45656;
  }

  &__hint {
    margin-top: 4px;
    font-size: 12px;
    color: #909399;
    line-height: 1.5;
  }

  &__close {
    position: absolute;
    top: 6px;
    right: 8px;
    background: transparent;
    border: 0;
    color: #c45656;
    font-size: 18px;
    line-height: 1;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 4px;
    transition: background 0.2s;
    &:hover { background: rgba(245,108,108,0.15); }
  }
}

@keyframes shakeX {
  0%, 100% { transform: translateX(0); }
  20% { transform: translateX(-4px); }
  40% { transform: translateX(4px); }
  60% { transform: translateX(-2px); }
  80% { transform: translateX(2px); }
}

@media (max-width: 768px) {
  .login-container {
    height: auto;
    min-height: 100vh;
  }

  .login-left {
    display: none;
  }

  .login-right {
    width: 100%;
    min-width: 0;
    min-height: 100vh;
    padding: 32px 24px;
  }

  .login-form-wrapper {
    width: 100%;
    max-width: 380px;
  }
}
</style>
