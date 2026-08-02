<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    :title="title"
    width="480px"
    destroy-on-close
  >
    <el-form :model="form" label-width="100px">
      <el-form-item label="部门名称" required>
        <el-input v-model="form.name" placeholder="请输入部门名称" maxlength="64" show-word-limit />
      </el-form-item>
      <el-form-item label="上级部门">
        <el-input v-model="form.parentName" disabled placeholder="无上级部门则为顶级部门" />
      </el-form-item>
      <el-form-item label="部门主管">
        <el-select
          v-model="form.manager_id"
          filterable
          clearable
          placeholder="请选择部门主管（员工）"
          style="width: 100%"
        >
          <el-option
            v-for="u in employeeList"
            :key="u.id"
            :label="`${u.name}（${u.username}）`"
            :value="u.id"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="排序号">
        <el-input-number v-model="form.sort_order" :min="0" :max="9999" />
      </el-form-item>
      <el-form-item label="描述">
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
interface DeptForm {
  name: string
  parentName: string
  manager_id: number | null
  sort_order: number
  description: string
  [key: string]: unknown
}

defineProps<{
  visible: boolean
  title: string
  form: DeptForm
  employeeList: Record<string, unknown>[]
  saving: boolean
}>()
const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'save'): void
}>()
</script>
