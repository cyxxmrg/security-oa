<template>
  <el-dialog
    v-model="visible"
    :title="mode === 'create' ? '新建工序模板' : '编辑工序模板'"
    width="720px"
    :close-on-click-modal="false"
    @closed="$emit('closed')"
  >
    <el-form
      ref="formRef"
      :model="localForm"
      :rules="formRules"
      label-width="110px"
      label-position="right"
    >
      <el-form-item label="项目类型" prop="industry">
        <el-select v-model="localForm.industry" placeholder="请选择项目类型" style="width: 100%" @change="onIndustryChange">
          <el-option
            v-for="(label, key) in INDUSTRY_MAP"
            :key="key"
            :label="label"
            :value="key"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="子分类" prop="category">
        <el-input v-model="localForm.category" placeholder="如:视频监控/门禁系统/综合布线" maxlength="50" show-word-limit />
      </el-form-item>
      <el-form-item label="编码" prop="code">
        <el-input v-model="localForm.code" placeholder="如 SP001 / AC001 / NT001" maxlength="50" show-word-limit />
      </el-form-item>
      <el-form-item label="名称" prop="name">
        <el-input v-model="localForm.name" placeholder="请输入工序名称" maxlength="100" show-word-limit />
      </el-form-item>
      <el-form-item label="工序说明">
        <el-input
          v-model="localForm.description"
          type="textarea"
          :rows="2"
          placeholder="简单描述这道工序要做什么"
          maxlength="500"
          show-word-limit
        />
      </el-form-item>
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="标准工期" prop="standard_duration_days">
            <el-input-number v-model="localForm.standard_duration_days" :min="0" :step="1" style="width: 100%" />
            <span class="form-hint">单位:天</span>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="标准工时" prop="standard_man_hours">
            <el-input-number v-model="localForm.standard_man_hours" :min="0" :step="0.5" :precision="1" style="width: 100%" />
            <span class="form-hint">单位:小时</span>
          </el-form-item>
        </el-col>
      </el-row>
      <el-form-item label="所需资质">
        <el-select
          v-model="localForm.required_qualifications"
          multiple
          filterable
          allow-create
          default-first-option
          placeholder="如:电工证 / 高空作业证 / 光纤熔接证"
          style="width: 100%"
        >
          <el-option label="电工证" value="电工证" />
          <el-option label="高空作业证" value="高空作业证" />
          <el-option label="电焊证" value="电焊证" />
          <el-option label="消防设施操作员证" value="消防设施操作员证" />
          <el-option label="光纤熔接证" value="光纤熔接证" />
          <el-option label="弱电工程师" value="弱电工程师" />
        </el-select>
      </el-form-item>
      <el-form-item label="安全要求">
        <el-input
          v-model="localForm.safety_requirements"
          type="textarea"
          :rows="2"
          placeholder="如:高空作业必须系安全带"
          maxlength="500"
          show-word-limit
        />
      </el-form-item>
      <el-form-item label="验收标准">
        <el-select
          v-model="localForm.acceptance_criteria"
          multiple
          filterable
          allow-create
          default-first-option
          placeholder="如:管路通畅 / 线缆无破损 / 接地良好"
          style="width: 100%"
        />
      </el-form-item>
      <el-form-item label="排序" prop="sort_order">
        <el-input-number v-model="localForm.sort_order" :min="0" :step="10" style="width: 100%" />
        <span class="form-hint">数字越小越靠前</span>
      </el-form-item>
      <el-form-item label="启用状态">
        <el-switch
          v-model="localForm.is_active"
          inline-prompt
          active-text="启用"
          inactive-text="停用"
        />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" :loading="loading" @click="emit('submit')">
        {{ mode === 'create' ? '创建' : '保存' }}
      </el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import type { FormInstance } from 'element-plus'
import { INDUSTRY_MAP } from './types'
import type { TemplateForm } from './types'

// v1.2.12p 字段对齐后端 process_templates 表
const props = defineProps<{
  modelValue: boolean
  mode: 'create' | 'edit'
  form: TemplateForm
  loading: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
  (e: 'submit'): void
  (e: 'closed'): void
}>()

const visible = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

// 子组件维护 form 副本 (深拷贝防止外部引用)
const localForm = reactive<TemplateForm>(JSON.parse(JSON.stringify(props.form)))
watch(() => props.form, (v) => {
  Object.assign(localForm, JSON.parse(JSON.stringify(v)))
}, { deep: true })
const formRef = ref<FormInstance>()
defineExpose({ formRef, localForm })

const formRules = {
  industry: [{ required: true, message: '请选择项目类型', trigger: 'change' }],
  category: [{ required: true, message: '请输入子分类', trigger: 'blur' }],
  code:     [{ required: true, message: '请输入编码', trigger: 'blur' }],
  name:     [{ required: true, message: '请输入名称', trigger: 'blur' }],
  standard_duration_days: [{ required: true, message: '请输入标准工期', trigger: 'blur' }],
}

const onIndustryChange = () => {
  // 切换项目类型时, 如果子分类为空, 给个默认提示 (不覆盖用户已输入值)
}
</script>

<style lang="scss" scoped>
.form-hint { margin-left: 12px; color: #909399; font-size: 12px; }
</style>