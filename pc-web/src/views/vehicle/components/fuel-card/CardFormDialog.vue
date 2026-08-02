<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    :title="editingId ? '编辑油卡' : '新增油卡'" width="1440px" destroy-on-close
  >
    <el-form ref="formRef" :model="form" :rules="rules" label-width="120px">
      <el-row :gutter="16">
        <el-col :span="8">
          <el-form-item label="卡号" prop="card_no">
            <el-input v-model="form.card_no" placeholder="例如 SH-2026-0001" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="发卡机构">
            <el-input v-model="form.card_name" placeholder="中石化/中石油" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="绑定车辆">
            <el-select v-model="form.vehicle_id" placeholder="选择车辆 (可空)" clearable filterable style="width: 100%">
              <el-option v-for="v in vehicles" :key="v.id" :label="`${v.plate_no} (${v.brand} ${v.model})`" :value="v.id" />
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>
      <el-row :gutter="16">
        <el-col :span="8">
          <el-form-item label="发卡日期">
            <el-date-picker v-model="form.issue_date" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="到期日期">
            <el-date-picker v-model="form.expire_date" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
          </el-form-item>
        </el-col>
        <el-col :span="8">
          <el-form-item label="备注">
            <el-input v-model="form.notes" type="textarea" :rows="1" />
          </el-form-item>
        </el-col>
      </el-row>
    </el-form>
    <template #footer>
      <el-button @click="emit('update:visible', false)">取消</el-button>
      <el-button type="primary" :loading="submitting" @click="emit('submit')">保存</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { FormRules } from 'element-plus'
import { VehicleOption } from '../../types'

export interface CardForm {
  card_no: string
  card_name: string
  vehicle_id: number | null
  issue_date: string | null
  expire_date: string | null
  notes: string
}

const props = defineProps<{
  visible: boolean
  editingId: number | null
  form: CardForm
  rules: FormRules
  vehicles: VehicleOption[]
  submitting: boolean
}>()
const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'submit'): void
}>()
const formRef = ref()
defineExpose({ formRef })
</script>
