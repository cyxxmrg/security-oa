<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    title="发起离职"
    width="840px"
    destroy-on-close
    :close-on-click-modal="false"
  >
    <el-form :model="form" :rules="formRules" ref="formRef" label-width="110px">
      <div class="form-section">基础信息</div>
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="员工" prop="user_id">
            <el-select v-model="form.user_id" placeholder="选择员工" style="width: 100%" filterable>
              <el-option v-for="u in users" :key="u.id" :label="`${u.name} (${u.username || ''})`" :value="u.id" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="离职类型" prop="resign_type">
            <el-select v-model="form.resign_type" placeholder="选择类型" style="width: 100%">
              <el-option v-for="(v, k) in RESIGN_TYPE_MAP" :key="k" :label="v.label" :value="k" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="申请日期" prop="notice_date">
            <el-date-picker v-model="form.notice_date" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="离职日期" prop="resign_date">
            <el-date-picker v-model="form.resign_date" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="最后工作日" prop="last_work_day">
            <el-date-picker v-model="form.last_work_day" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="社保截止日">
            <el-date-picker v-model="form.social_security_cutoff" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
          </el-form-item>
        </el-col>
      </el-row>

      <el-form-item label="离职原因" prop="reason">
        <el-input v-model="form.reason" type="textarea" :rows="3" placeholder="请输入离职原因" />
      </el-form-item>

      <div class="form-section">工作交接</div>
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="交接人">
            <el-select v-model="form.handover_to_user_id" placeholder="选择交接人" style="width: 100%" filterable clearable>
              <el-option v-for="u in users" :key="u.id" :label="`${u.name} (${u.username || ''})`" :value="u.id" />
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>
      <el-form-item label="交接说明">
        <el-input v-model="form.handover_note" type="textarea" :rows="2" placeholder="工作交接说明" />
      </el-form-item>

      <div class="form-section">资产归还</div>
      <el-table :data="form.assets" border size="small" style="margin-bottom: 8px">
        <el-table-column label="资产名称">
          <template #default="{ row }">
            <el-input v-model="row.name" placeholder="如 笔记本电脑" />
          </template>
        </el-table-column>
        <el-table-column label="已归还" width="100">
          <template #default="{ row }">
            <el-switch v-model="row.returned" />
          </template>
        </el-table-column>
        <el-table-column label="备注">
          <template #default="{ row }">
            <el-input v-model="row.note" placeholder="备注" />
          </template>
        </el-table-column>
        <el-table-column label="" width="60">
          <template #default="{ $index }">
            <el-button link size="small" @click="form.assets.splice($index, 1)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
      <el-button size="small" @click="form.assets.push({ name: '', returned: true, note: '' })">+ 添加资产</el-button>

      <div class="form-section">工资结算
        <el-button link type="primary" :loading="previewing" size="small" style="margin-left: 8px" @click="emit('preview')">自动计算</el-button>
      </div>
      <el-row :gutter="16">
        <el-col :span="8">
          <el-form-item label="最终工资">
            <el-input-number v-model="form.final_salary" :min="0" :precision="2" style="width: 100%" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="剩余假期">
            <el-input-number v-model="form.leave_balance" :min="0" :precision="1" style="width: 100%" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="经济补偿金">
            <el-input-number v-model="form.severance_pay" :min="0" :precision="2" style="width: 100%" />
          </el-form-item>
        </el-col>
      </el-row>

      <div v-if="previewVisible" class="preview-box">
        <el-descriptions :column="3" size="small" border>
          <el-descriptions-item label="基础月薪">¥{{ Number(preview.base_salary || 0).toLocaleString() }}</el-descriptions-item>
          <el-descriptions-item label="日工资">¥{{ Number(preview.daily_salary || 0).toFixed(2) }}</el-descriptions-item>
          <el-descriptions-item label="应出勤">{{ preview.expected_days || 0 }} 天</el-descriptions-item>
          <el-descriptions-item label="实际出勤">{{ preview.actual_days || 0 }} 天</el-descriptions-item>
          <el-descriptions-item label="本月应发">¥{{ Number(preview.total_amount || 0).toLocaleString() }}</el-descriptions-item>
        </el-descriptions>
      </div>
    </el-form>
    <template #footer>
      <el-button @click="emit('update:visible', false)">取消</el-button>
      <el-button @click="emit('saveDraft')" :loading="submitting">保存草稿</el-button>
      <el-button type="primary" @click="emit('submitForm')" :loading="submitting">提交审批</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { RESIGN_TYPE_MAP } from './types'

export interface ResignationForm {
  user_id: number | null
  resign_date: string
  notice_date: string
  last_work_day: string
  resign_type: string
  reason: string
  handover_to_user_id: number | null
  handover_note: string
  assets: Array<{ name: string; returned: boolean; note: string }>
  final_salary: number
  leave_balance: number
  severance_pay: number
  social_security_cutoff: string
}

defineProps<{
  visible: boolean
  form: ResignationForm
  users: Record<string, unknown>[]
  previewVisible: boolean
  preview: Record<string, unknown> | null
  previewing: boolean
  submitting: boolean
}>()

const formRef = ref()
const formRules = {
  user_id:      [{ required: true, message: '请选择员工',   trigger: 'change' }],
  resign_type:  [{ required: true, message: '请选择离职类型', trigger: 'change' }],
  resign_date:  [{ required: true, message: '请选择离职日期', trigger: 'change' }],
  last_work_day: [{ required: true, message: '请选择最后工作日', trigger: 'change' }],
  reason:       [{ required: true, message: '请输入离职原因', trigger: 'blur' }],
}

defineExpose({ formRef })

const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'preview'): void
  (e: 'saveDraft'): void
  (e: 'submitForm'): void
}>()
</script>

<style lang="scss" scoped>
.form-section {
  font-size: 14px;
  font-weight: 600;
  color: #303133;
  margin: 16px 0 12px;
  padding-left: 8px;
  border-left: 3px solid #1D9E75;
}
.preview-box { margin-top: 12px; padding: 12px; background: #f5f7fa; border-radius: 6px; }
</style>
