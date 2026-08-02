<template>
  <div class="wizard-page">
    <div class="wizard-header">
      <div class="header-left">
        <el-icon class="header-icon"><MagicStick /></el-icon>
        <div>
          <h2 class="header-title">系统初始化向导</h2>
          <p class="header-sub">建议完成以下 3 步, 即可上线使用 (可随时跳过)</p>
        </div>
      </div>
      <div class="header-right">
        <el-button type="primary" plain @click="$router.push('/admin/welcome')">跳过向导</el-button>
      </div>
    </div>

    <el-steps :active="activeStep" align-center class="wizard-steps" finish-status="success">
      <el-step title="建业务管理员" description="1 个能管业务的人" />
    </el-steps>

    <!-- V1.2.4m: 大哥要求精简向导 - 只保留第 1 步, 创建后直接完成 -->
    <!-- Step 1: 建业务管理员 -->
    <el-card v-if="activeStep === 0" shadow="never" class="step-card">
      <h3>第 1 步: 建业务管理员</h3>
      <p class="step-hint">系统账号不能录业务, 必须再建 1 个业务管理员负责日常运营。</p>
      <el-form :model="form" :rules="formRules" ref="formRef" label-position="top" v-loading="loading">
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="姓名" prop="name">
              <el-input v-model="form.name" placeholder="如: 王经理" clearable />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="登录用户名" prop="username">
              <el-input v-model="form.username" placeholder="登录用, 不可改" clearable />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="密码" prop="password">
              <el-input v-model="form.password" type="password" show-password clearable />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="确认密码" prop="confirmPassword">
              <el-input v-model="form.confirmPassword" type="password" show-password clearable />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="手机号" prop="phone">
              <el-input v-model="form.phone" placeholder="11 位手机号" clearable />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="部门" prop="department_id">
              <el-select v-model="form.department_id" placeholder="选择部门" style="width: 100%">
                <el-option v-for="d in departments" :key="d.id" :label="d.name" :value="d.id" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="角色" prop="role_id">
          <el-select v-model="form.role_id" placeholder="选 spatie 角色" style="width: 100%">
            <el-option v-for="r in roles" :key="r.id" :label="r.description || r.name" :value="r.id" />
          </el-select>
        </el-form-item>
        <el-alert type="info" :closable="false" show-icon style="margin-bottom: 16px">
          密码要求: 8-32 位, 同时包含字母和数字
        </el-alert>
        <el-button type="primary" :loading="loading" @click="handleCreateAdmin">建业务管理员, 完成初始化</el-button>
      </el-form>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, type FormInstance, type FormRules } from 'element-plus'
import { MagicStick } from '@element-plus/icons-vue'
import { get, post, put } from '@/utils/request'
import { unwrapItem } from '@/utils/response'

const router = useRouter()
const activeStep = ref(0)
const loading = ref(false)
const formRef = ref<FormInstance>()
const departments = ref<Record<string, unknown>[]>([])
const roles = ref<Record<string, unknown>[]>([])
const createdAdminName = ref('')

const form = reactive({
  name: '',
  username: '',
  password: '',
  confirmPassword: '',
  phone: '',
  department_id: null as number | null,
  role_id: null as number | null,
})

const formRules: FormRules = {
  name: [{ required: true, message: '请输入姓名', trigger: 'blur' }],
  username: [
    { required: true, message: '请输入用户名', trigger: 'blur' },
    { min: 3, max: 32, message: '3-32 位', trigger: 'blur' },
    { pattern: /^[a-zA-Z][a-zA-Z0-9_]*$/, message: '字母开头, 字母数字下划线', trigger: 'blur' },
  ],
  password: [
    { required: true, message: '请输入密码', trigger: 'blur' },
    { min: 8, max: 32, message: '8-32 位', trigger: 'blur' },
    { pattern: /^(?=.*[A-Za-z])(?=.*\d).{8,32}$/, message: '必须同时包含字母和数字', trigger: 'blur' },
  ],
  confirmPassword: [
    { required: true, message: '请确认密码', trigger: 'blur' },
    {
      validator: (_r: Record<string, unknown>, value: string, cb: (e?: Error) => void) => {
        if (value !== form.password) cb(new Error('两次密码不一致'))
        else cb()
      },
      trigger: 'blur',
    },
  ],
  phone: [
    { required: true, message: '请输入手机号', trigger: 'blur' },
    { pattern: /^1[3-9]\d{9}$/, message: '11 位手机号', trigger: 'blur' },
  ],
  department_id: [{ required: true, message: '请选择部门', trigger: 'change' }],
  role_id: [{ required: true, message: '请选择角色', trigger: 'change' }],
}

const sysInfo = reactive<Record<string, unknown>>({
  system_name: '',
  short_name: '',
  copyright: '',
  icp: '',
  contact_email: '',
})

onMounted(async () => {
  // V1.2.9 修复: 原来用 /departments /roles /settings, 全部走 ensure_business, system 账号会 403
  // 改用 system 专属聚合接口, 一次拿全所有数据
  try {
    const res = await get('/system/init-wizard-data')
    const data = unwrapItem(res)
    departments.value = data.departments || []
    roles.value = data.roles || []
    Object.assign(sysInfo, data.system_info || {})
  } catch { /* ignore */ }
})

async function handleCreateAdmin() {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  loading.value = true
  try {
    // V1.2.9 fix: 改用 system 专属接口, 绕过 ensure_business 中间件
    // 之前的 /employees 走业务路由 group, system 账号会 403 "系统账号不能操作业务数据"
    const res = await post('/system/business-admin', {
      mode: 'create',
      name: form.name,
      username: form.username,
      password: form.password,
      phone: form.phone,
      email: `${form.username}@local`,
      department_id: form.department_id || undefined,
      role_id: form.role_id || undefined,
    })
    createdAdminName.value = `${form.name} (${form.username})`
    ElMessage.success(`业务管理员 ${form.name} 已创建, 初始化完成!`)
    // V1.2.4m: 创建后直接调用 mark-initialized, 跳过中间步骤
    try {
      await post('/system/mark-initialized', {})
    } catch (e) {
      console.warn('[Wizard] mark-initialized 失败 (不阻断):', e)
    }
    router.push('/admin/welcome')
  } catch (e: unknown) {
    const msg = e?.response?.data?.message || e?.serverMessage || e?.message || '创建失败'
    ElMessage.error(msg)
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.wizard-page { padding: 16px 24px; }
.wizard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.header-left { display: flex; gap: 12px; align-items: center; }
.header-icon { font-size: 32px; color: #185FA5; }
.header-title { margin: 0; font-size: 18px; font-weight: 500; }
.header-sub { margin: 0; font-size: 13px; color: #888; }
.wizard-steps { margin-bottom: 24px; }
.step-card { max-width: 720px; margin: 0 auto; }
.step-card h3 { margin-top: 0; font-size: 16px; font-weight: 500; }
.step-hint { color: #888; font-size: 13px; margin-bottom: 16px; }
</style>
