<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    title="添加技能"
    width="500px"
    destroy-on-close
  >
    <el-form :model="form" label-width="80px">
      <el-form-item label="员工">
        <el-input :value="empName" disabled />
      </el-form-item>
      <el-form-item label="技能标签" required>
        <el-select v-model="form.skillId" placeholder="选择技能" style="width: 100%" filterable>
          <el-option v-for="tag in tags" :key="tag.id" :label="tag.name" :value="tag.id" />
        </el-select>
      </el-form-item>
      <el-form-item label="熟练度">
        <el-select v-model="form.level" placeholder="选择熟练度" style="width: 100%">
          <el-option label="了解"     value="beginner" />
          <el-option label="基础"     value="intermediate" />
          <el-option label="熟练"     value="advanced" />
          <el-option label="专家"     value="expert" />
        </el-select>
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="emit('update:visible', false)">取消</el-button>
      <el-button type="primary" :loading="submitting" @click="emit('submit')">确定</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
interface AddSkillForm {
  skillId: number | null
  level: string
  [key: string]: unknown
}

interface SkillTagOption {
  id: number
  name?: string
  color?: string
  [key: string]: unknown
}

defineProps<{ visible: boolean; form: AddSkillForm; tags: SkillTagOption[]; empName?: string; submitting: boolean }>()
const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'submit'): void
}>()
</script>
