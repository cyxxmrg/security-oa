<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    :title="title"
    width="480px"
    destroy-on-close
  >
    <el-form :model="form" label-width="100px">
      <el-form-item label="技能名称" required>
        <el-input v-model="form.name" placeholder="请输入技能名称" maxlength="32" show-word-limit />
      </el-form-item>
      <el-form-item label="分类">
        <el-select v-model="form.category" placeholder="请选择分类" style="width: 100%;">
          <el-option label="安装 (install)"    value="install" />
          <el-option label="调试 (debug)"       value="debug" />
          <el-option label="网络 (network)"     value="network" />
          <el-option label="云服务 (cloud)"     value="cloud" />
          <el-option label="运维 (maintain)"    value="maintain" />
          <el-option label="其他 (other)"       value="other" />
        </el-select>
      </el-form-item>
      <el-form-item label="标签色">
        <el-color-picker v-model="form.color" />
        <span style="margin-left: 8px; font-size: 12px; color: #909399">用于技能标签展示</span>
      </el-form-item>
      <el-form-item label="说明">
        <el-input v-model="form.description" type="textarea" :rows="2" maxlength="255" show-word-limit />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="emit('update:visible', false)">取消</el-button>
      <el-button type="primary" :loading="saving" @click="emit('save')">保存</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
defineProps<{ visible: boolean; title: string; form: Record<string, unknown>; saving: boolean }>()
const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'save'): void
}>()
</script>
