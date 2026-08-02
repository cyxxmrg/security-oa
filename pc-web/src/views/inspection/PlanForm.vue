<template>
  <div :class="embedded ? '' : 'page-container'">
    <div v-if="!embedded" class="page-header">
      <h2>{{ isEdit ? '编辑巡检计划' : '新建巡检计划' }}</h2>
      <el-button @click="$router.back()">返回</el-button>
    </div>

    <el-form :model="form" :rules="rules" ref="formRef" label-width="120px" class="form-card" v-loading="loading">
      <!-- Step 1: 选合同 -->
      <el-divider content-position="left">第一步 · 选择维保合同</el-divider>
      <el-form-item label="合同" prop="contract_id" required>
        <el-select v-model="form.contract_id" filterable placeholder="选择维保合同" style="width: 100%" :disabled="isEdit" @change="onContractChange">
          <el-option v-for="c in contracts" :key="c.id" :label="`${c.contract_no} · ${c.customer?.name || ''}`" :value="c.id" />
        </el-select>
      </el-form-item>

      <el-form-item label="计划名称" prop="name" required>
        <el-input v-model="form.name" placeholder="如 6月海康相机月度巡检" maxlength="100" show-word-limit />
      </el-form-item>

      <el-form-item label="巡检范围" prop="scope">
        <el-input v-model="form.scope" type="textarea" :rows="2" placeholder="如: 6 号楼 3 楼所有监控" />
      </el-form-item>

      <!-- Step 2: 排程 -->
      <el-divider content-position="left">第二步 · 排程配置</el-divider>
      <el-row :gutter="16">
        <el-col :span="8">
          <el-form-item label="排程频率" prop="frequency" required>
            <el-select v-model="form.frequency" placeholder="频率" style="width: 100%">
              <el-option v-for="(label, value) in FREQUENCY_LABEL" :key="value" :label="label" :value="value" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col v-if="form.frequency === 'monthly' || form.frequency === 'quarterly' || form.frequency === 'semiannual'" :span="8">
          <el-form-item label="每月第几天" prop="cycle_day">
            <el-input-number v-model="form.cycle_day" :min="1" :max="31" style="width: 100%" />
          </el-form-item>
        </el-col>
        <el-col v-if="form.frequency === 'weekly' || form.frequency === 'biweekly'" :span="8">
          <el-form-item label="周几" prop="cycle_weekday">
            <el-select v-model="form.cycle_weekday" placeholder="星期" style="width: 100%">
              <el-option v-for="(label, value) in {1:'一', 2:'二', 3:'三', 4:'四', 5:'五', 6:'六', 7:'日'}" :key="value" :label="`周${label}`" :value="Number(value)" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col v-if="form.frequency === 'custom'" :span="8">
          <el-form-item label="间隔天数" prop="custom_interval_days">
            <el-input-number v-model="form.custom_interval_days" :min="1" :max="365" style="width: 100%" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="提前生成" prop="ahead_generate_days">
            <el-input-number v-model="form.ahead_generate_days" :min="1" :max="180" style="width: 100%" />
            <div style="font-size: 11px; color: #999; margin-top: 2px">默认 30 天, 可按需调整</div>
          </el-form-item>
        </el-col>
      </el-row>
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="起始日期" prop="start_date" required>
            <el-date-picker v-model="form.start_date" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="截止日期" prop="end_date">
            <el-date-picker v-model="form.end_date" type="date" value-format="YYYY-MM-DD" style="width: 100%" placeholder="默认合同结束日期" />
          </el-form-item>
        </el-col>
      </el-row>

      <el-row :gutter="16">
        <el-col :span="8">
          <el-form-item label="单次耗时" prop="duration_hours">
            <el-input-number v-model="form.duration_hours" :min="1" :max="24" style="width: 100%" />
            <span style="margin-left: 8px">小时</span>
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="优先级" prop="priority">
            <el-select v-model="form.priority" style="width: 100%">
              <el-option label="1 - 特急" :value="1" />
              <el-option label="2 - 紧急" :value="2" />
              <el-option label="3 - 普通" :value="3" />
              <el-option label="4 - 低" :value="4" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="默认执行人" prop="assigned_to">
            <el-input v-model="assignedToInput" placeholder="user_id 列表, 逗号分隔, 留空则自动分配" />
          </el-form-item>
        </el-col>
      </el-row>

      <!-- Step 3: 检查项模板 -->
      <el-divider content-position="left">第三步 · 检查项模板 (可选)</el-divider>
      <div class="checklist-editor">
        <el-button :icon="Plus" size="small" @click="addChecklistItem">添加检查项</el-button>
        <el-table :data="form.checklist_template" border size="small" style="margin-top: 12px">
          <el-table-column label="检查项名称" min-width="160">
            <template #default="{ row }">
              <el-input v-model="row.name" size="small" placeholder="如 设备运行状态" />
            </template>
          </el-table-column>
          <el-table-column label="类型" width="120">
            <template #default="{ row }">
              <el-select v-model="row.type" size="small" style="width: 100%">
                <el-option label="文本" value="text" />
                <el-option label="数字" value="number" />
                <el-option label="选择" value="select" />
                <el-option label="拍照" value="photo" />
              </el-select>
            </template>
          </el-table-column>
          <el-table-column label="选项 (逗号分隔)" min-width="200">
            <template #default="{ row }">
              <el-input v-model="row.options_str" size="small" :disabled="row.type !== 'select'" placeholder="如 正常,异常" />
            </template>
          </el-table-column>
          <el-table-column label="正常值" width="120">
            <template #default="{ row }">
              <el-input v-model="row.normal_value" size="small" placeholder="如 正常" />
            </template>
          </el-table-column>
          <el-table-column label="必填" width="60" align="center">
            <template #default="{ row }">
              <el-switch v-model="row.required" />
            </template>
          </el-table-column>
          <el-table-column label="操作" width="60" align="center">
            <template #default="{ $index }">
              <el-button link type="danger" size="small" @click="removeChecklistItem($index)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>
      </div>

      <el-form-item style="margin-top: 24px">
        <el-button type="primary" :icon="Check" :loading="submitting" @click="onSubmit">保存</el-button>
        <el-button @click="$router.back()">取消</el-button>
      </el-form-item>
    </el-form>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Plus, Check } from '@element-plus/icons-vue'
import { inspection, FREQUENCY_LABEL, type InspectionPlan } from '@/api/inspection'
import { get } from '@/utils/request'

const props = defineProps<{ embedded?: boolean }>()
const emit = defineEmits<{ (e: 'done'): void }>()
const route = useRoute()
const router = useRouter()
const isEdit = computed(() => !!route.params.id)
const loading = ref(false)
const submitting = ref(false)
const formRef = ref()
const contracts = ref<Record<string, unknown>[]>([])

const form = reactive<Record<string, unknown>>({
  contract_id: null,
  name: '',
  scope: '',
  frequency: 'monthly',
  cycle_day: 1,
  cycle_weekday: null,
  custom_interval_days: null,
  duration_hours: 4,
  priority: 3,
  assigned_to: '',
  start_date: new Date().toISOString().slice(0, 10),
  end_date: '',
  ahead_generate_days: 30,
  checklist_template: [],
})

const assignedToInput = ref('')

const rules = {
  contract_id: [{ required: true, message: '请选择合同', trigger: 'change' }],
  name: [{ required: true, message: '请输入计划名称', trigger: 'blur' }],
  frequency: [{ required: true, message: '请选择排程频率', trigger: 'change' }],
  start_date: [{ required: true, message: '请选择起始日期', trigger: 'change' }],
}

const onContractChange = (id: number) => {
  const c = contracts.value.find(x => x.id === id)
  if (c) {
    if (!form.name) form.name = `${c.customer?.name || ''} ${c.inspection_frequency || '月度'}巡检`
    if (!form.end_date && c.end_date) form.end_date = c.end_date
    if (c.inspection_frequency && ['weekly','biweekly','monthly','quarterly','semiannual','yearly','custom'].includes(c.inspection_frequency)) {
      form.frequency = c.inspection_frequency
    }
  }
}

const addChecklistItem = () => {
  form.checklist_template.push({ name: '', type: 'text', options_str: '', normal_value: '', required: true })
}

const removeChecklistItem = (i: number) => {
  form.checklist_template.splice(i, 1)
}

const loadContracts = async () => {
  try {
    const r = await inspection.activeContracts()
    contracts.value = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : [])
  } catch (e) {
    contracts.value = []
  }
}

const loadPlan = async () => {
  if (!isEdit.value) return
  loading.value = true
  try {
    const r = await inspection.getPlan(Number(route.params.id))
    const p: InspectionPlan = r?.data
    if (p) {
      Object.assign(form, {
        contract_id: p.contract_id,
        name: p.name,
        scope: p.scope || '',
        frequency: p.frequency,
        cycle_day: p.cycle_day,
        cycle_weekday: p.cycle_weekday,
        custom_interval_days: p.custom_interval_days,
        duration_hours: p.duration_hours,
        priority: p.priority,
        assigned_to: p.assigned_to || '',
        start_date: p.start_date,
        end_date: p.end_date || '',
        ahead_generate_days: p.ahead_generate_days,
        checklist_template: p.checklist_template || [],
      })
      assignedToInput.value = p.assigned_to || ''
    }
  } finally {
    loading.value = false
  }
}

const onSubmit = async () => {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  submitting.value = true
  try {
    // 解析 assigned_to (逗号分隔转 JSON 数组)
    let assignedTo: Record<string, unknown> = form.assigned_to
    if (assignedToInput.value) {
      const ids = assignedToInput.value.split(',').map((s: string) => Number(s.trim())).filter((n: number) => !!n)
      assignedTo = ids.length === 1 ? ids[0] : JSON.stringify(ids)
    } else {
      assignedTo = null
    }

    // 处理 checklist_template: options_str 转 options 数组
    const checklist = (form.checklist_template || []).map((it: Record<string, unknown>) => {
      const cleaned: Record<string, unknown> = { name: it.name, type: it.type, required: !!it.required, normal_value: it.normal_value }
      if (it.type === 'select' && it.options_str) {
        cleaned.options = it.options_str.split(',').map((s: string) => s.trim()).filter(Boolean)
      }
      return cleaned
    })

    const payload: Record<string, unknown> = {
      contract_id: form.contract_id,
      name: form.name,
      scope: form.scope,
      frequency: form.frequency,
      cycle_day: form.cycle_day,
      cycle_weekday: form.cycle_weekday,
      custom_interval_days: form.custom_interval_days,
      duration_hours: form.duration_hours,
      priority: form.priority,
      assigned_to: assignedTo,
      start_date: form.start_date,
      end_date: form.end_date || undefined,
      ahead_generate_days: form.ahead_generate_days,
      checklist_template: checklist,
    }
    if (isEdit.value) {
      await inspection.updatePlan(Number(route.params.id), payload)
      ElMessage.success('已更新')
    } else {
      await inspection.createPlan(payload)
      ElMessage.success('创建成功, 首批任务已自动生成')
    }
    if (props.embedded) {
      emit('done')
    } else {
      router.push('/inspection/plans')
    }
  } catch (e: unknown) {
    ElMessage.error(e?.message || '保存失败')
  } finally {
    submitting.value = false
  }
}

onMounted(async () => {
  await loadContracts()
  await loadPlan()
})
</script>

<style scoped>
.page-container { padding: 16px; max-width: 1100px; margin: 0 auto; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; font-size: 20px; font-weight: 600; }
.form-card { background: #fff; padding: 24px; border-radius: 8px; }
.checklist-editor { background: #fafafa; padding: 12px; border-radius: 4px; }
</style>
