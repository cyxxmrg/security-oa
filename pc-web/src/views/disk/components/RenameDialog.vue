<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    title="重命名"
    width="500px"
  >
    <el-form label-width="80px">
      <el-form-item label="新名称">
        <el-input
          :model-value="value"
          @update:model-value="(v: string) => emit('update:value', v)"
          @keyup.enter="emit('confirm')"
        />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="emit('update:visible', false)">取消</el-button>
      <el-button type="primary" @click="emit('confirm')">确定</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
defineProps<{ visible: boolean; value: string }>()
const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'update:value', v: string): void
  (e: 'confirm'): void
}>()
</script>
