<template>
  <div class="bid-form-page">
    <el-card shadow="never" class="header-card">
      <div class="brand">
        <div class="logo">🏗️</div>
        <div>
          <div class="title">施工发包 — 投标申请</div>
          <div class="subtitle">外部供应商投标通道</div>
        </div>
      </div>
    </el-card>

    <el-card v-loading="loading" shadow="never" class="form-card">
      <template v-if="work">
        <!-- 发包信息 -->
        <el-descriptions title="发包信息" :column="3" border size="default" style="margin-bottom: 24px">
          <el-descriptions-item label="发包编号">{{ work.code || '-' }}</el-descriptions-item>
          <el-descriptions-item label="项目">{{ work.project?.name || work.project_id || '-' }}</el-descriptions-item>
          <el-descriptions-item label="投标截止">{{ work.deadline || '-' }}</el-descriptions-item>
          <el-descriptions-item label="发包标题" :span="3">{{ work.title || '-' }}</el-descriptions-item>
          <el-descriptions-item label="发包预算" :span="3">
            <span style="color:#0C447C;font-weight:600">¥ {{ formatMoney(work.budget) }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="发包范围" :span="3">
            <div style="white-space: pre-wrap">{{ work.scope || '-' }}</div>
          </el-descriptions-item>
        </el-descriptions>

        <el-divider content-position="left">填写投标信息</el-divider>

        <el-alert
          v-if="!canBid"
          :title="`当前状态「${statusLabel(work.status)}」,不可投标`"
          type="warning"
          show-icon
          :closable="false"
          class="alert-block"
        />

        <el-form
          v-else
          ref="formRef"
          :model="formData"
          :rules="formRules"
          label-width="120px"
          style="max-width: 720px"
        >
          <el-form-item label="投标方" prop="supplier_name">
            <el-input v-model="formData.supplier_name" maxlength="100" placeholder="公司全称" />
          </el-form-item>
          <el-form-item label="联系人" prop="contact_name">
            <el-input v-model="formData.contact_name" maxlength="50" />
          </el-form-item>
          <el-form-item label="联系电话" prop="contact_phone">
            <el-input v-model="formData.contact_phone" maxlength="20" />
          </el-form-item>

          <el-form-item label="投标金额" prop="amount">
            <el-input-number v-model="formData.amount" :min="0" :step="1000" :precision="2" style="width: 100%" />
            <span class="form-tip">元</span>
          </el-form-item>
          <el-form-item label="工期" prop="duration_days">
            <el-input-number v-model="formData.duration_days" :min="1" :step="1" style="width: 100%" />
            <span class="form-tip">天</span>
          </el-form-item>

          <el-form-item label="团队信息">
            <el-input v-model="formData.team_info" type="textarea" :rows="3" maxlength="1000" show-word-limit placeholder="团队规模、核心人员等" />
          </el-form-item>
          <el-form-item label="技术方案">
            <el-input v-model="formData.technical_proposal" type="textarea" :rows="5" maxlength="2000" show-word-limit placeholder="施工方法、技术措施、材料方案等" />
          </el-form-item>
          <el-form-item label="资质附件URL">
            <el-input v-model="formData.attachments" placeholder="资质证书 PDF/图片 URL,多个用逗号分隔" />
          </el-form-item>
          <el-form-item label="备注">
            <el-input v-model="formData.remark" type="textarea" :rows="2" maxlength="500" show-word-limit />
          </el-form-item>

          <el-form-item>
            <el-checkbox v-model="formData.agreed">
              我已阅读并同意 <el-link type="primary" :underline="false">《投标承诺书》</el-link>
            </el-checkbox>
          </el-form-item>

          <el-form-item>
            <el-button type="primary" :loading="submitting" :disabled="!formData.agreed" @click="handleSubmit">
              提交投标
            </el-button>
            <el-button @click="goBack">取消</el-button>
          </el-form-item>
        </el-form>
      </template>
      <el-empty v-else-if="!loading" description="未找到发包数据" />
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, type FormInstance, type FormRules } from 'element-plus'
import { externalWorkApi } from '@/api/construction'
import { unwrapItem } from '@/utils/response'
import type { ExternalWork, TagType } from '../types'

const route = useRoute()
const router = useRouter()

const loading = ref(false)
const submitting = ref(false)
const work = ref<ExternalWork | null>(null)

const statusOptions = [
  { value: 'draft',     label: '草稿' },
  { value: 'open',      label: '招标中' },
  { value: 'bidding',   label: '评标中' },
  { value: 'awarded',   label: '已定标' },
  { value: 'closed',    label: '已关闭' },
]
const statusLabel = (s: string) => statusOptions.find(x => x.value === s)?.label || s || '-'

const formatMoney = (n: number | string | null | undefined) => Number(n || 0).toLocaleString('zh-CN', { maximumFractionDigits: 2 })

const workId = computed(() => {
  // 路径 /external-work/bid/:id
  const id = route.params.id
  return id ? Number(id) : 0
})

const canBid = computed(() => {
  const s = work.value?.status
  return s === 'open' || s === 'bidding'
})

const formRef = ref<FormInstance | null>(null)
const formData = reactive({
  supplier_name: '',
  contact_name: '',
  contact_phone: '',
  amount: 0,
  duration_days: 30,
  team_info: '',
  technical_proposal: '',
  attachments: '',
  remark: '',
  agreed: false,
})

const formRules = {
  supplier_name:    [{ required: true, message: '请填写投标方', trigger: 'blur' }],
  contact_name:     [{ required: true, message: '请填写联系人', trigger: 'blur' }],
  contact_phone:    [{ required: true, message: '请填写联系电话', trigger: 'blur' }],
  amount:           [{ required: true, message: '请填写投标金额', trigger: 'blur' }],
  duration_days:    [{ required: true, message: '请填写工期', trigger: 'blur' }],
  technical_proposal: [{ required: true, message: '请填写技术方案', trigger: 'blur' }],
}

const loadWork = async () => {
  if (!workId.value) return
  loading.value = true
  try {
    const res = await externalWorkApi.show(workId.value)
    work.value = unwrapItem(res) || null
  } catch {
    work.value = null
  } finally {
    loading.value = false
  }
}

const handleSubmit = async () => {
  if (!formData.agreed) {
    ElMessage.warning('请先同意《投标承诺书》')
    return
  }
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  submitting.value = true
  try {
    await externalWorkApi.submitBid(workId.value, {
      supplier_name: formData.supplier_name,
      contact_name: formData.contact_name,
      contact_phone: formData.contact_phone,
      amount: Number(formData.amount || 0),
      duration_days: Number(formData.duration_days || 0),
      team_info: formData.team_info,
      technical_proposal: formData.technical_proposal,
      attachments: formData.attachments,
      remark: formData.remark,
    })
    ElMessage.success('投标已提交,等待开标')
    setTimeout(() => router.push('/login'), 1500)
  } catch { /* 拦截器已提示 */ }
  finally { submitting.value = false }
}

const goBack = () => router.back()

watch(workId, () => { if (workId.value) loadWork() })
onMounted(() => { if (workId.value) loadWork() })
</script>

<style lang="scss" scoped>
.bid-form-page {
  max-width: 960px;
  margin: 32px auto;
  padding: 0 16px;
}
.header-card {
  background: linear-gradient(135deg, #0C447C 0%, #1D9E75 100%);
  color: #fff;
  margin-bottom: 16px;
  :deep(.el-card__body) { padding: 24px 32px; }
}
.brand { display: flex; align-items: center; gap: 16px; }
.logo {
  width: 56px; height: 56px; border-radius: 12px; background: rgba(255, 255, 255, 0.2);
  display: flex; align-items: center; justify-content: center; font-size: 32px;
}
.title { font-size: 22px; font-weight: 700; }
.subtitle { font-size: 14px; opacity: 0.85; margin-top: 4px; }
.form-card { box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08) !important; }
.form-tip { color: #909399; font-size: 12px; margin-left: 8px; }
.alert-block { margin-bottom: 16px; }
</style>
