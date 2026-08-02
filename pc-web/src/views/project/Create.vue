<template>
  <div :class="embedded ? 'create-embedded' : 'page-container'">
    <div v-if="!embedded" class="page-header">
      <div class="title-area">
        <el-button :icon="ArrowLeft" text @click="$router.back()">返回</el-button>
        <span class="page-title">新建项目</span>
      </div>
      <div class="header-actions">
        <el-button @click="handleSaveDraft">保存草稿</el-button>
        <el-button type="primary" :icon="Check" @click="handleSubmit">提交审批</el-button>
      </div>
    </div>

    <div :class="embedded ? 'step-bar-embedded' : 'step-bar'">
      <el-steps :active="activeStep" align-center finish-status="success">
        <el-step title="基础信息" description="项目名称、客户、类型" />
        <el-step title="预算编制" description="设备、材料、人工等" />
        <el-step title="团队配置" description="项目经理、成员" />
        <el-step title="确认提交" description="检查并提交" />
      </el-steps>
    </div>

    <el-form
      ref="formRef"
      :model="form"
      :rules="rules"
      label-width="120px"
      label-position="right"
      class="project-form"
    >
      <BasicStep
        v-if="activeStep === 0"
        :form="form"
        :customers="customers"
        @auto-generate-code="autoGenerateCode"
      />

      <BudgetStep
        v-if="activeStep === 1"
        :form="form"
      />

      <TeamStep
        v-if="activeStep === 2"
        :form="form"
        :managers="managers"
        :all-members="allMembers"
      />

      <ConfirmStep
        v-if="activeStep === 3"
        :form="form"
      />
    </el-form>

    <div class="step-actions">
      <el-button v-if="activeStep > 0" :icon="ArrowLeft" @click="prevStep">上一步</el-button>
      <el-button v-if="activeStep < 3" type="primary" @click="nextStep">
        下一步<el-icon class="ml-1"><ArrowRight /></el-icon>
      </el-button>
    </div>

    <div v-if="embedded" class="dialog-footer-actions" style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px">
      <el-button @click="emit('cancel')">取消</el-button>
      <el-button :icon="Check" type="primary" @click="handleSubmit">提交审批</el-button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage, FormInstance, FormRules } from 'element-plus'
import { get, post } from '@/utils/request'
import { ArrowLeft, ArrowRight, Check } from '@element-plus/icons-vue'
import BasicStep from './components/BasicStep.vue'
import BudgetStep from './components/BudgetStep.vue'
import TeamStep from './components/TeamStep.vue'
import ConfirmStep from './components/ConfirmStep.vue'
import type { ProjectForm } from './createTypes'
import { createEmptyForm, TYPE_TO_ENUM, PRIORITY_TO_ENUM } from './createTypes'

const router = useRouter()
const route = useRoute()

// V1.2.12p: 嵌入模式（弹窗中显示，隐藏页面级外壳，用 emit 通信）
const props = defineProps<{ embedded?: boolean }>()
const emit = defineEmits<{ (e: 'done'): void; (e: 'cancel'): void }>()

const formRef = ref<FormInstance>()
const activeStep = ref(0)

// 4 个 step 用的下拉数据
const customers = ref<{ id: number; name: string }[]>([])
const managers = ref<{ id: number; name: string }[]>([])
const allMembers = ref<{ id: number; name: string }[]>([])

const loadOptions = async () => {
  try {
    const c = await get('/customers', { per_page: 200 })
    // V0.6.3: res = {code, data: paginator}
    const cd = c?.data ?? {}
    customers.value = Array.isArray(cd?.data) ? cd.data : []
  } catch (e) {
    console.error('加载客户失败', e)
  }
  try {
    const e = await get('/employees', { per_page: 200 })
    // V0.6.3: res = {code, data: paginator}
    const ed = e?.data ?? {}
    const list = Array.isArray(ed?.data) ? ed.data : []
    managers.value = list
    allMembers.value = list
  } catch (e) {
    console.error('加载员工失败', e)
  }
}

const form = reactive<ProjectForm>(createEmptyForm())

const rules: FormRules = {
  name: [{ required: true, message: '请输入项目名称', trigger: 'blur' }],
  customer: [{ required: true, message: '请选择客户', trigger: 'change' }],
  type: [{ required: true, message: '请选择项目类型', trigger: 'change' }],
  startDate: [{ required: true, message: '请选择开始日期', trigger: 'change' }],
  endDate: [{ required: true, message: '请选择完成日期', trigger: 'change' }],
  manager: [{ required: true, message: '请选择项目经理', trigger: 'change' }],
}

const autoGenerateCode = () => {
  const now = new Date()
  form.code = `PRJ-${now.getFullYear()}-${String(Math.floor(Math.random() * 999) + 1).padStart(3, '0')}`
  ElMessage.success('项目编号已生成')
}

const nextStep = async () => {
  if (activeStep.value === 0) {
    if (!formRef.value) { ElMessage.warning('表单未就绪，请刷新页面重试'); return }
    try {
      await formRef.value.validate()
    } catch (e: unknown) {
      const msg = (e as { message?: string })?.message || '表单校验未通过'
      ElMessage.warning(msg)
      return
    }
  }
  if (activeStep.value < 3) activeStep.value++
}

const prevStep = () => {
  if (activeStep.value > 0) activeStep.value--
}

const handleSaveDraft = () => {
  ElMessage.success('草稿已保存（本地）')
}

const handleSubmit = async () => {
  if (!formRef.value) return
  try {
    await formRef.value.validate()
  } catch {
    return
  }

  const customerObj = customers.value.find((c) => c.name === form.customer)
  const managerObj = managers.value.find((m) => m.name === form.manager)
  if (!customerObj) {
    ElMessage.error('请选择有效客户')
    return
  }
  if (!managerObj) {
    ElMessage.error('请选择有效项目经理')
    return
  }

  const teamIds: number[] = []
  for (const name of form.team) {
    const m = allMembers.value.find((x) => x.name === name)
    if (m) teamIds.push(m.id)
  }

  const payload = {
    name: form.name,
    customer_id: customerObj.id,
    type: TYPE_TO_ENUM[form.type] || 'comprehensive',
    description: form.description + (form.location ? `\n项目地点: ${form.location}` : ''),
    budget_device: form.budget.equipment.reduce((s, e) => s + e.qty * e.price, 0),
    budget_material: form.budget.material.reduce((s, m) => s + m.qty * m.price, 0),
    budget_labor: form.budget.labor.reduce((s, l) => s + l.qty * l.days * l.dailyRate, 0),
    budget_outsource: form.budget.outsource.reduce((s, o) => s + o.amount, 0),
    budget_other: form.budget.other.reduce((s, o) => s + o.amount, 0),
    manager_id: managerObj.id,
    start_date: form.startDate || null,
    end_date: form.endDate || null,
    priority: PRIORITY_TO_ENUM[form.priority] || 'medium',
    member_ids: teamIds,
  }

  try {
    const res = await post('/projects', payload)
    if (res.code === 0 || res.data) {
      ElMessage.success('项目已创建')
      if (props.embedded) {
        emit('done')
      } else {
        setTimeout(() => router.push('/project'), 800)
      }
    } else {
      ElMessage.error(res.message || '创建失败')
    }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } }; message?: string }
    ElMessage.error(err?.response?.data?.message || err?.message || '创建失败')
  }
}

onMounted(() => {
  loadOptions().then(() => {
    // 商机详情 → "一键转新项目" 预填 (V1.2.12b)
    const q = route.query as Record<string, string>
    if (q.opp_name) form.name = q.opp_name
    if (q.customer_id && customers.value.length) {
      const match = customers.value.find((c) => String(c.id) === q.customer_id)
      if (match) form.customer = match.name
    }
  })
})
</script>

<style lang="scss" scoped>
.page-container {
  padding: 16px;
  background: #f5f7fa;
  min-height: calc(100vh - 60px);
}
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #fff;
  padding: 16px 20px;
  border-radius: 8px;
  margin-bottom: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  .title-area { display: flex; align-items: center; gap: 12px; }
  .page-title { font-size: 20px; font-weight: 700; color: #303133; }
  .header-actions { display: flex; gap: 8px; }
}
.step-bar {
  background: #fff;
  padding: 24px 20px;
  border-radius: 8px;
  margin-bottom: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
.project-form {
  :deep(.form-card) {
    margin-bottom: 12px;
  }
}
.step-actions {
  background: #fff;
  padding: 16px 20px;
  border-radius: 8px;
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
.ml-1 { margin-left: 4px; }
.create-embedded {
  padding: 0;
  .step-bar-embedded {
    margin-bottom: 12px;
  }
  .step-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
  }
}
</style>
