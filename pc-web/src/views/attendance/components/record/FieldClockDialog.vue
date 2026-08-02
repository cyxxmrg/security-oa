<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    title="外勤打卡"
    width="500px"
    destroy-on-close
  >
    <el-alert title="外勤打卡说明" type="info" :closable="false" show-icon style="margin-bottom: 16px">
      <template #default>
        用于外出办公/客户现场打卡。提交后状态自动标记为「外勤」。
      </template>
    </el-alert>
    <el-form :model="form" label-width="90px">
      <el-form-item label="打卡类型">
        <el-radio-group v-model="form.type">
          <el-radio-button value="in">外勤签到</el-radio-button>
          <el-radio-button value="out">外勤签退</el-radio-button>
        </el-radio-group>
      </el-form-item>
      <el-form-item label="打卡时间">
        <el-time-picker
          v-model="form.time"
          placeholder="不填则取当前时间"
          value-format="HH:mm:ss"
          format="HH:mm:ss"
          style="width: 100%"
        />
      </el-form-item>
      <el-form-item label="外勤位置">
        <el-input v-model="form.location" placeholder="请输入外勤地点（如：客户A公司）" />
      </el-form-item>
      <el-form-item label="备注">
        <el-input v-model="form.remark" type="textarea" :rows="2" placeholder="外勤事项简述（选填）" maxlength="500" show-word-limit />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="emit('update:visible', false)">取消</el-button>
      <el-button type="primary" :loading="submitting" @click="emit('confirm')">提交外勤打卡</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import type { FieldClockForm } from '../../types'

defineProps<{ visible: boolean; form: FieldClockForm; submitting: boolean }>()
const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'confirm'): void
}>()
</script>
