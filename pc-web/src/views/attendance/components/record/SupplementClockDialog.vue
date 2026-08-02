<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    title="补卡申请"
    width="500px"
    destroy-on-close
  >
    <el-alert title="补卡说明" type="info" :closable="false" show-icon style="margin-bottom: 16px">
      <template #default>
        用于补录漏打或异常的打卡记录。补卡时间晚于 09:00 上班的将标记为迟到。
      </template>
    </el-alert>
    <el-form :model="form" :rules="rules" ref="formRef" label-width="90px">
      <el-form-item label="补卡日期" prop="date">
        <el-date-picker
          v-model="form.date"
          type="date"
          placeholder="选择日期"
          value-format="YYYY-MM-DD"
          :max-date="todayMaxDate"
          style="width: 100%"
        />
      </el-form-item>
      <el-form-item label="打卡类型" prop="type">
        <el-radio-group v-model="form.type">
          <el-radio-button value="in">上班卡</el-radio-button>
          <el-radio-button value="out">下班卡</el-radio-button>
          <el-radio-button value="field_in">外勤上班</el-radio-button>
          <el-radio-button value="field_out">外勤下班</el-radio-button>
        </el-radio-group>
      </el-form-item>
      <el-form-item label="补卡时间" prop="time">
        <el-time-picker
          v-model="form.time"
          placeholder="选择时间"
          value-format="HH:mm:ss"
          format="HH:mm:ss"
          style="width: 100%"
        />
      </el-form-item>
      <el-form-item label="打卡位置">
        <el-input v-model="form.location" placeholder="选填" />
      </el-form-item>
      <el-form-item label="补卡原因" prop="reason">
        <el-input v-model="form.reason" type="textarea" :rows="3" placeholder="请说明补卡原因（如：忘记打卡/外勤网络异常）" maxlength="500" show-word-limit />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="emit('update:visible', false)">取消</el-button>
      <el-button type="primary" :loading="submitting" @click="emit('confirm')">提交补卡</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import type { FormInstance } from 'element-plus'
import type { SupplementClockForm } from '../../types'

defineProps<{ visible: boolean; form: SupplementClockForm; submitting: boolean; todayMaxDate: string }>()
const formRef = ref<FormInstance | null>(null)
const rules = {
  date:   [{ required: true, message: '请选择日期', trigger: 'change' }],
  type:   [{ required: true, message: '请选择类型', trigger: 'change' }],
  time:   [{ required: true, message: '请选择时间', trigger: 'change' }],
  reason: [{ required: true, message: '请说明补卡原因', trigger: 'blur' }],
}
defineExpose({ formRef })
const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'confirm'): void
}>()
</script>
