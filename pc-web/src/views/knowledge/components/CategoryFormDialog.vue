<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    :title="title"
    width="420px"
    destroy-on-close
  >
    <el-form label-width="80px">
      <el-form-item label="分类名称" required>
        <el-input
          :model-value="name"
          @update:model-value="(v: string) => emit('update:name', v)"
          placeholder="请输入分类名称"
          maxlength="50"
          show-word-limit
          @keyup.enter="emit('confirm')"
        />
      </el-form-item>
      <el-form-item v-if="showIconField" label="分类图标">
        <el-radio-group
          :model-value="icon"
          @update:model-value="(v: string) => emit('update:icon', v)"
        >
          <el-radio label="folder">📁 默认</el-radio>
          <el-radio label="📚">📚 学习</el-radio>
          <el-radio label="🔧">🔧 技术</el-radio>
          <el-radio label="💼">💼 业务</el-radio>
          <el-radio label="📋">📋 规范</el-radio>
          <el-radio label="🎓">🎓 培训</el-radio>
        </el-radio-group>
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="emit('update:visible', false)">取消</el-button>
      <el-button type="primary" :loading="saving" @click="emit('confirm')">确定</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
defineProps<{
  visible: boolean
  title: string
  name: string
  icon: string
  showIconField: boolean
  saving: boolean
}>()
const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'update:name', v: string): void
  (e: 'update:icon', v: string): void
  (e: 'confirm'): void
}>()
</script>
