<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    title="驳回报销"
    width="500px"
  >
    <el-form :model="form" label-width="100px">
      <el-form-item label="报销单号">
        <span style="color:#0C447C;font-weight:600;">{{ target?.claim_no }}</span>
      </el-form-item>
      <el-form-item label="驳回原因" required>
        <el-input v-model="form.comment" type="textarea" :rows="3" placeholder="请说明驳回原因" maxlength="500" show-word-limit />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="emit('update:visible', false)">取消</el-button>
      <el-button type="danger" :loading="loading" @click="emit('confirm')">确认驳回</el-button>
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
