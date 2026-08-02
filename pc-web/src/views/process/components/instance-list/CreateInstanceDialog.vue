<template>
  <el-dialog
    v-model="visible"
    title="新建工序实例"
    width="640px"
    destroy-on-close
  >
    <el-form
      :model="form"
      :rules="(rules as any)"
      ref="formRef"
      label-width="100px"
    >
      <el-form-item label="所属项目" prop="project_id">
        <el-select
          v-model="form.project_id"
          placeholder="请选择项目"
          filterable
          style="width: 100%"
          @change="onProjectChange"
        >
          <el-option
            v-for="p in projectOptions"
            :key="p.id"
            :label="p.name"
            :value="p.id"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="工序模板" prop="template_id">
        <el-select
          v-model="form.template_id"
          placeholder="请选择工序模板"
          filterable
          clearable
          style="width: 100%"
          @change="onTemplateChange"
        >
          <el-option
            v-for="t in templateOptions"
            :key="t.id"
            :label="`[${INDUSTRY_MAP[t.industry as string] || t.industry}/${t.category}] ${t.name}`"
            :value="t.id"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="工序名称" prop="name">
        <el-input v-model="form.name" placeholder="选完模板会自动填入,也可手动修改" maxlength="100" />
      </el-form-item>
      <el-form-item label="负责人" prop="foreman_id">
        <el-select
          v-model="form.foreman_id"
          placeholder="请选择负责人 (可选)"
          filterable
          clearable
          style="width: 100%"
        >
          <el-option
            v-for="u in userOptions"
            :key="u.id"
            :label="u.name"
            :value="u.id"
          />
        </el-select>
      </el-form-item>
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="计划开始" prop="planned_start_date">
            <el-date-picker
              v-model="form.planned_start_date"
              type="date"
              placeholder="选择开始日期"
              value-format="YYYY-MM-DD"
              style="width: 100%"
            />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="计划结束" prop="planned_end_date">
            <el-date-picker
              v-model="form.planned_end_date"
              type="date"
              placeholder="选择结束日期"
              value-format="YYYY-MM-DD"
              style="width: 100%"
            />
          </el-form-item>
        </el-col>
      </el-row>
      <el-form-item label="施工位置">
        <el-input v-model="form.location" placeholder="如:1F走廊东侧/主机房" maxlength="200" />
      </el-form-item>
      <el-form-item label="备注">
        <el-input v-model="form.description" type="textarea" :rows="2" placeholder="备注 (可选)" maxlength="500" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" :loading="loading" @click="emit('submit')">创建</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { computed, watch } from 'vue'
import type { ProjectOption, UserOption, TemplateOption } from './types'
import { INDUSTRY_MAP } from '../../components/template-list/types'

// v1.2.12p 字段对齐后端 process_instances 表
//   assignee_id → foreman_id
//   planned_start → planned_start_date
//   planned_end → planned_end_date
//   新增 name (从模板自动填充)
const props = defineProps<{
  modelValue: boolean
  loading: boolean
  form: {
    project_id: number | null
    template_id: number | null
    name: string
    foreman_id: number | null
    planned_start_date: string
    planned_end_date: string
    location: string
    description: string
  }
  rules: Record<string, unknown>
  projectOptions: ProjectOption[]
  userOptions: UserOption[]
  templateOptions: TemplateOption[]
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
  (e: 'submit'): void
}>()

const visible = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

// 选模板自动填 name + 工期
const onTemplateChange = (templateId: number | null) => {
  if (!templateId) return
  const tpl = props.templateOptions.find(t => t.id === templateId)
  if (!tpl) return
  // 模板名作为工序实例名 (用户可改)
  if (!props.form.name) {
    props.form.name = tpl.name || ''
  }
  // 模板工期填计划开始/结束 (若开始日期已有, 按工期推结束)
  if (tpl.standard_duration_days && props.form.planned_start_date && !props.form.planned_end_date) {
    const start = new Date(props.form.planned_start_date)
    start.setDate(start.getDate() + Number(tpl.standard_duration_days))
    const y = start.getFullYear()
    const m = String(start.getMonth() + 1).padStart(2, '0')
    const d = String(start.getDate()).padStart(2, '0')
    props.form.planned_end_date = `${y}-${m}-${d}`
  }
}

const onProjectChange = (_projectId: number | null) => {
  // 切换项目时, 可考虑重置模板选项 (目前不强制)
}

// 模板回显时, 同步一下 name (防止后端字段没传)
watch(() => props.form.template_id, (newId) => {
  if (newId && !props.form.name) onTemplateChange(newId)
})
</script>
