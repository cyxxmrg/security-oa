<script setup lang="ts">
/**
 * CustomerFormDialog — 新增/编辑客户对话框 (v0.3.14 C2)
 *
 * Props: visible, mode, modelValue(form), submitting, industries
 * Emit: update:visible, submit
 */
import { reactive, ref, watch } from 'vue'
import type { FormInstance, FormRules } from 'element-plus'
import { Connection, OfficeBuilding } from '@element-plus/icons-vue'

const props = withDefaults(
  defineProps<{
    visible: boolean
    mode: 'create' | 'edit'
    submitting: boolean
    industries: string[]
    modelValue: {
      id: number
      name: string
      industry: string
      contact: string
      phone: string
      category: string
      tags: string[]
    }
  }>(),
  { mode: 'create', submitting: false, industries: () => [] },
)

const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'submit', payload: typeof props.modelValue): void
}>()

const dialogVisible = ref(props.visible)
watch(() => props.visible, (v) => (dialogVisible.value = v))
const close = () => emit('update:visible', false)

const form = reactive({ ...props.modelValue, tags: [...(props.modelValue.tags || [])] })
watch(
  () => props.modelValue,
  (nv) => Object.assign(form, { ...nv, tags: [...(nv.tags || [])] }),
  { deep: true },
)

const formRef = ref<FormInstance>()
const rules: FormRules = {
  name:     [{ required: true, message: '请输入客户名称', trigger: 'blur' }],
  // industry 选填（后端允许 null）
  contact:  [{ required: true, message: '请输入联系人',   trigger: 'blur' }],
  phone:    [{ required: true, message: '请输入联系电话', trigger: 'blur' }],
  category: [{ required: true, message: '请选择分类',     trigger: 'change' }],
}

// 客户分类 → 后端 enum 映射 (V1.2.10 修复: 之前用中文"普通/VIP/潜在"导致422)
const CATEGORY_OPTIONS = [
  { value: 'normal',    label: '普通' },
  { value: 'vip',       label: 'VIP' },
  { value: 'potential', label: '潜在' },
]
// 兼容老数据: 编辑模式下如果是中文, 转成 enum
const normalizeCategory = (c: string) => {
  if (c === '普通') return 'normal'
  if (c === 'VIP' || c === 'vip') return 'vip'
  if (c === '潜在') return 'potential'
  return c
}
if (form.category) form.category = normalizeCategory(form.category)

const handleSubmit = async () => {
  if (!formRef.value) return
  try { await formRef.value.validate() } catch { return }
  emit('submit', { ...form, tags: [...form.tags] })
}
</script>

<template>
  <el-dialog
    :model-value="dialogVisible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    :title="mode === 'create' ? '新增客户' : '编辑客户'"
    width="640px"
    destroy-on-close
    :close-on-click-modal="false"
    @close="close"
  >
    <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
      <el-form-item label="客户名称" prop="name">
        <el-input v-model="form.name" placeholder="请输入客户全称" maxlength="80" show-word-limit />
      </el-form-item>
      <el-form-item label="所属行业" prop="industry">
        <el-select v-model="form.industry" placeholder="选择行业" filterable allow-create style="width: 100%">
          <el-option v-for="i in industries" :key="i" :label="i" :value="i" />
        </el-select>
      </el-form-item>
      <el-form-item label="联系人" prop="contact">
        <el-input v-model="form.contact" placeholder="主联系人姓名" />
      </el-form-item>
      <el-form-item label="联系电话" prop="phone">
        <el-input v-model="form.phone" placeholder="11 位手机号或座机" />
      </el-form-item>
      <el-form-item label="客户分类" prop="category">
        <el-radio-group v-model="form.category">
          <el-radio-button v-for="o in CATEGORY_OPTIONS" :key="o.value" :value="o.value">{{ o.label }}</el-radio-button>
        </el-radio-group>
      </el-form-item>
      <el-form-item label="标签">
        <el-select v-model="form.tags" multiple filterable allow-create default-first-option placeholder="选择或新建标签" style="width: 100%">
          <el-option v-for="t in ['重点客户', '战略客户', '新客户', '老客户', '流失风险']" :key="t" :label="t" :value="t" />
        </el-select>
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="close">取消</el-button>
      <el-button type="primary" :loading="submitting" @click="handleSubmit">确认保存</el-button>
    </template>
  </el-dialog>
</template>
