<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    :title="title"
    width="500px"
  >
    <el-form label-width="80px">
      <el-form-item label="文件夹名">
        <el-input
          :model-value="name"
          @update:model-value="(v: string) => emit('update:name', v)"
          placeholder="请输入文件夹名称"
          @keyup.enter="emit('confirm')"
        />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="emit('update:visible', false)">取消</el-button>
      <el-button type="primary" :loading="creating" @click="emit('confirm')">创建</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
defineProps<{ visible: boolean; title: string; name: string; creating: boolean }>()
const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'update:name', v: string): void
  (e: 'confirm'): void
}>()
</script>
