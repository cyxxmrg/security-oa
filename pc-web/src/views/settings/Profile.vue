<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">个人信息</span>
      <div class="header-actions">
        <el-button :icon="Refresh" @click="loadUserInfo">刷新</el-button>
      </div>
    </div>

    <div class="profile-grid">
      <!-- 左：头像 + 身份卡 -->
      <div class="content-card identity-card">
        <div class="avatar-wrap">
          <el-avatar :size="96" :src="form.avatar" :alt="form.name">
            {{ form.name?.charAt(0) || 'U' }}
          </el-avatar>
          <el-upload
            class="avatar-uploader"
            :show-file-list="false"
            :auto-upload="false"
            accept="image/*"
            :on-change="handleAvatarChange"
          >
            <el-button size="small" :icon="Camera" plain>更换头像</el-button>
          </el-upload>
        </div>
        <div class="user-name">{{ form.name || '未命名用户' }}</div>
        <div class="user-meta">@{{ form.username }}</div>
        <el-divider />
        <div class="meta-list">
          <div class="meta-item">
            <el-icon><OfficeBuilding /></el-icon>
            <span class="label">所属部门</span>
            <span class="value">{{ form.department || '—' }}</span>
          </div>
          <div class="meta-item">
            <el-icon><User /></el-icon>
            <span class="label">职位</span>
            <span class="value">{{ form.position || '—' }}</span>
          </div>
          <div class="meta-item">
            <el-icon><Clock /></el-icon>
            <span class="label">最近登录</span>
            <span class="value">{{ form.last_login_at || '—' }}</span>
          </div>
          <div class="meta-item">
            <el-icon><Connection /></el-icon>
            <span class="label">登录 IP</span>
            <span class="value">{{ form.last_login_ip || '—' }}</span>
          </div>
        </div>
      </div>

      <!-- 右：可编辑表单 -->
      <div class="content-card form-card">
        <div class="card-title">基础资料</div>
        <el-form
          ref="formRef"
          :model="form"
          :rules="rules"
          label-width="100px"
          label-position="right"
          class="profile-form"
        >
          <el-form-item label="姓名" prop="name">
            <el-input v-model="form.name" placeholder="请输入姓名" maxlength="50" show-word-limit />
          </el-form-item>
          <el-form-item label="用户名">
            <el-input :model-value="form.username" disabled />
            <span class="form-hint">用户名不可修改</span>
          </el-form-item>
          <el-form-item label="手机号" prop="phone">
            <el-input v-model="form.phone" placeholder="请输入手机号" maxlength="20" />
          </el-form-item>
          <el-form-item label="邮箱" prop="email">
            <el-input v-model="form.email" placeholder="请输入邮箱" maxlength="100" />
          </el-form-item>
          <el-form-item label="头像 URL">
            <el-input v-model="form.avatar" placeholder="https://... 或选择本地图片" maxlength="255" />
            <span class="form-hint">支持外链，或在上方"更换头像"选择本地图片（自动转 base64 暂存）</span>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" :icon="Check" :loading="saving" @click="handleSave">保存修改</el-button>
            <el-button :icon="RefreshLeft" @click="loadUserInfo">重置</el-button>
          </el-form-item>
        </el-form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import {
  Refresh, Camera, Check, RefreshLeft, OfficeBuilding, User, Clock, Connection,
} from '@element-plus/icons-vue'
import { ElMessage, type FormInstance, type FormRules } from 'element-plus'
import { get, put } from '@/utils/request'
import { unwrapItem } from '@/utils/response'
import { useUserStore } from '@/stores/user'

const userStore = useUserStore()
const formRef = ref<FormInstance>()
const saving = ref(false)

const form = ref({
  name: '', username: '', phone: '', email: '', avatar: '',
  department: '', position: '', last_login_at: '', last_login_ip: '',
})

const rules: FormRules = {
  name:  [{ required: true, message: '姓名不能为空', trigger: 'blur' }],
  phone: [{ pattern: /^$|^1[3-9]\d{9}$/, message: '手机号格式不正确', trigger: 'blur' }],
  email: [{ type: 'email', message: '邮箱格式不正确', trigger: 'blur' }],
}

async function loadUserInfo() {
  try {
    // V0.6.3: res = {code, data: {user: {...}}}
    const res = await get('/auth/userinfo')
    const d = unwrapItem(res) || {}
    const u = d?.user
    if (!u) return
    form.value = {
      name: u.name || '',
      username: u.username || '',
      phone: u.phone || '',
      email: u.email || '',
      avatar: u.avatar || '',
      department: u.department?.name || '',
      position: u.position?.name || '',
      last_login_at: u.last_login_at || '',
      last_login_ip: u.last_login_ip || '',
    }
  } catch (e: unknown) {
    ElMessage.error('加载个人信息失败：' + (e?.message || '未知错误'))
  }
}

function handleAvatarChange(file: { size: number; name?: string; type?: string }) {
  // 本地选择图片 → FileReader 读 base64
  const raw: File = file.raw
  if (!raw) return
  if (raw.size > 2 * 1024 * 1024) {
    ElMessage.warning('图片大小不能超过 2MB')
    return
  }
  const reader = new FileReader()
  reader.onload = (e) => {
    form.value.avatar = e.target?.result as string
    ElMessage.success('头像已选择，点击"保存修改"生效')
  }
  reader.readAsDataURL(raw)
}

async function handleSave() {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (!valid) return
    saving.value = true
    try {
      const payload = {
        name: form.value.name,
        phone: form.value.phone,
        email: form.value.email,
        avatar: form.value.avatar,
      }
      const res = await put('/auth/profile', payload)
      ElMessage.success(res.message || '资料已更新')
      // 同步到 userStore 顶栏
      userStore.setUser({ name: form.value.name, avatar: form.value.avatar })
    } catch (e: unknown) {
      ElMessage.error(e?.response?.data?.message || e?.message || '保存失败')
    } finally {
      saving.value = false
    }
  })
}

onMounted(() => { loadUserInfo() })
</script>

<style scoped lang="scss">
.page-container {
  padding: 20px;
  background: #f5f7fa;
  min-height: 100%;
}
.page-header {
  margin-bottom: 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  .page-title { font-size: 20px; font-weight: 600; color: #303133; }
  .header-actions { display: flex; gap: 8px; }
}
.profile-grid {
  display: grid;
  grid-template-columns: 360px 1fr;
  gap: 20px;
  @media (max-width: 900px) { grid-template-columns: 1fr; }
}
.content-card {
  background: #fff;
  border-radius: 8px;
  padding: 24px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
}
.identity-card {
  text-align: center;
  .avatar-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    .avatar-uploader { margin-top: 4px; }
  }
  .user-name { font-size: 18px; font-weight: 600; margin-top: 12px; color: #303133; }
  .user-meta { color: #909399; font-size: 13px; margin-top: 2px; }
  .meta-list {
    text-align: left;
    .meta-item {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 8px 0;
      color: #606266;
      font-size: 13px;
      .el-icon { color: #0C447C; }
      .label { width: 70px; color: #909399; }
      .value { flex: 1; color: #303133; }
    }
  }
}
.form-card {
  .card-title {
    font-size: 16px; font-weight: 600; color: #303133;
    padding-bottom: 12px; margin-bottom: 20px; border-bottom: 1px solid #ebeef5;
  }
  .form-hint { font-size: 12px; color: #909399; margin-top: 2px; }
}
</style>
