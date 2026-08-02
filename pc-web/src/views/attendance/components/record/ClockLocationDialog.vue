<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    title="打卡登记"
    width="500px"
    destroy-on-close
  >
    <el-form :model="form" label-width="80px">
      <el-form-item label="打卡类型">
        <el-tag :type="form.type === 'in' ? 'success' : 'warning'" size="default">
          {{ form.type === 'in' ? '上班打卡' : '下班打卡' }}
        </el-tag>
      </el-form-item>
      <el-form-item label="打卡位置">
        <el-input v-model="form.location" placeholder="请输入当前位置（如：公司1号楼）" />
      </el-form-item>
      <el-form-item label="备注">
        <el-input v-model="form.remark" type="textarea" :rows="2" placeholder="选填" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="emit('update:visible', false)">取消</el-button>
      <el-button type="primary" :loading="submitting" @click="emit('confirm')">确认打卡</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import type { ClockForm } from '../../types'

defineProps<{ visible: boolean; form: ClockForm; submitting: boolean }>()
const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'confirm'): void
}>()
</script>
