<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    title="标记付款"
    width="500px"
  >
    <el-form :model="form" label-width="100px">
      <el-form-item label="报销单号">
        <span style="color:#0C447C;font-weight:600;">{{ target?.claim_no }}</span>
      </el-form-item>
      <el-form-item label="报销金额">
        <span style="color:#0C447C;font-weight:600;">¥{{ Number(target?.total_amount || 0).toFixed(2) }}</span>
      </el-form-item>
      <el-form-item label="付款金额" required>
        <el-input-number v-model="form.paid_amount" :min="0" :precision="2" :step="100" style="width: 100%" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="emit('update:visible', false)">取消</el-button>
      <el-button type="primary" :loading="loading" @click="emit('confirm')">确认付款</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
defineProps<{ visible: boolean; form: Record<string, unknown>; target: Record<string, unknown>; loading: boolean }>()
const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'confirm'): void
}>()
</script>
