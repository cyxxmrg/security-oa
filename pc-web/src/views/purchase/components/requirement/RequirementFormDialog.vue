<template>
  <el-dialog
    v-model="visible"
    :title="mode === 'create' ? '新建采购需求' : '编辑采购需求'"
    width="1440px"
    :close-on-click-modal="false"
    destroy-on-close
  >
    <el-form
      :model="localForm"
      :rules="rules"
      ref="formRef"
      label-width="100px"
    >
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="关联项目" prop="project_id">
            <el-select
              v-model="localForm.project_id"
              placeholder="请选择项目"
              filterable
              style="width: 100%"
            >
              <el-option
                v-for="p in projectOptions"
                :key="p.id"
                :label="p.name"
                :value="p.id"
              />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="需求日期" prop="need_date">
            <el-date-picker
              v-model="localForm.need_date"
              type="date"
              placeholder="选择日期"
              style="width: 100%"
              value-format="YYYY-MM-DD"
            />
          </el-form-item>
        </el-col>
      </el-row>

      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="优先级" prop="priority">
            <el-select v-model="localForm.priority" placeholder="请选择" style="width: 100%">
              <el-option
                v-for="p in PRIORITY_OPTIONS"
                :key="p.value"
                :label="p.label"
                :value="p.value"
              />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="发起人" prop="creator">
            <el-select v-model="localForm.creator" filterable allow-create default-first-option placeholder="选择或输入发起人" style="width:100%">
              <el-option v-for="e in employeeOptions" :key="e.id" :label="e.name" :value="e.name" />
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>

      <el-divider content-position="left">需求物资（可添加多种）</el-divider>

      <div v-for="(item, idx) in localForm.materials" :key="idx" class="material-row">
        <el-row :gutter="8">
          <el-col :span="9">
            <el-form-item
              :prop="`materials.${idx}.name`"
              :rules="{ required: true, message: '请选择库存物资', trigger: 'change' }"
              label-width="0"
            >
              <el-input
                v-model="item.name"
                readonly
                placeholder="请选择库存物资"
                @click="openInventoryPicker(idx)"
              >
                <template #append>
                  <el-button @click.stop="openInventoryPicker(idx)">选择</el-button>
                </template>
              </el-input>
            </el-form-item>
          </el-col>
          <el-col :span="5">
            <el-form-item :prop="`materials.${idx}.spec`" label-width="0">
              <el-input v-model="item.spec" placeholder="规格（可选）" />
            </el-form-item>
          </el-col>
          <el-col :span="4">
            <el-form-item
              :prop="`materials.${idx}.quantity`"
              :rules="{ required: true, message: '请输入数量', trigger: 'blur' }"
              label-width="0"
            >
              <el-input-number
                v-model="item.quantity"
                :min="1"
                :step="1"
                style="width: 100%"
                placeholder="数量"
              />
            </el-form-item>
          </el-col>
          <el-col :span="4">
            <el-form-item :prop="`materials.${idx}.unit`" label-width="0">
              <el-input v-model="item.unit" placeholder="单位" />
            </el-form-item>
          </el-col>
          <el-col :span="2">
            <el-button
              type="danger"
              link
              :icon="Delete"
              :disabled="localForm.materials.length === 1"
              @click="emit('removeMaterial', idx)"
              style="margin-top: 4px"
            />
          </el-col>
        </el-row>
      </div>

      <el-form-item label-width="0">
        <el-button :icon="Plus" plain type="primary" @click="emit('addMaterial')">添加物资</el-button>
        <span class="form-hint">物资必须从库存信息中选择，规格和单位会自动带出</span>
      </el-form-item>

      <el-form-item label="备注">
        <el-input
          v-model="localForm.remark"
          type="textarea"
          :rows="3"
          placeholder="采购需求备注（如：施工进度原因、特殊要求等）"
        />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" :loading="loading" @click="emit('submit')">保存</el-button>
    </template>

    <InventoryItemPicker
      :show="pickerVisible"
      :multiple="true"
      @close="pickerVisible = false"
      @select="handleInventorySelected"
    />
  </el-dialog>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Plus, Delete } from '@element-plus/icons-vue'
import type { RequirementForm, ProjectOption, FormMode } from './types'
import { PRIORITY_OPTIONS } from './types'
import InventoryItemPicker from '@/views/inventory/components/InventoryItemPicker.vue'

// v0.3.23 抽自 purchase/Requirement.vue:131-240
const props = defineProps<{
  modelValue: boolean
  mode: FormMode
  form: RequirementForm
  loading: boolean
  projectOptions: ProjectOption[]
  employeeOptions: { id: number; name: string }[]
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
  (e: 'submit'): void
  (e: 'addMaterial'): void
  (e: 'removeMaterial', idx: number): void
}>()

const visible = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

const localForm = computed(() => props.form)

const formRef = ref()
defineExpose({ formRef, localForm })

const pickerVisible = ref(false)
const pickingIndex = ref<number | null>(null)

const openInventoryPicker = (idx: number) => {
  pickingIndex.value = idx
  pickerVisible.value = true
}

const handleInventorySelected = (items: Record<string, unknown>[]) => {
  if (!items.length || pickingIndex.value === null) return
  // 第一项填充当前行
  const first = items[0]
  const row = localForm.value.materials[pickingIndex.value]
  row.inventory_item_id = first.id
  row.name = first.name || ''
  row.spec = first.spec || first.specification || ''
  row.unit = first.unit || row.unit || '件'
  // 其余项追加为新行
  for (let i = 1; i < items.length; i++) {
    const it = items[i]
    localForm.value.materials.push({
      inventory_item_id: it.id,
      name: it.name || '',
      spec: it.spec || it.specification || '',
      quantity: 1,
      unit: it.unit || '件',
    })
  }
  pickerVisible.value = false
}

const rules = {
  project_id: [{ required: true, message: '请选择关联项目', trigger: 'change' }],
  need_date: [{ required: true, message: '请选择需求日期', trigger: 'change' }],
  priority: [{ required: true, message: '请选择优先级', trigger: 'change' }],
  creator: [{ required: true, message: '请输入发起人', trigger: 'blur' }],
}
</script>

<style lang="scss" scoped>
.material-row {
  margin-bottom: 8px;
  padding: 8px;
  background: #fafbfc;
  border-radius: 4px;
  &:last-child { margin-bottom: 0; }
}
.form-hint { margin-left: 12px; color: #909399; font-size: 12px; }
</style>
