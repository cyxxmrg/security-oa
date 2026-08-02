<script setup lang="ts">
/**
 * InventoryBatchEditDialog — 批量编辑字段 (v0.3.14 C4)
 * 只提交有值的字段
 */
import { reactive, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import type { WarehouseOption, ItemCategory } from '../types'

const props = withDefaults(
  defineProps<{
    visible: boolean
    submitting: boolean
    warehouseOptions: WarehouseOption[]
    categoryOptions: ItemCategory[]
    selectedCount: number
  }>(),
  { visible: false, submitting: false, warehouseOptions: () => [], categoryOptions: () => [], selectedCount: 0 },
)

const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'submit', payload: Record<string, unknown>): void
}>()

const dialogVisible = ref(props.visible)
watch(() => props.visible, (v) => (dialogVisible.value = v))
const close = () => emit('update:visible', false)

const form = reactive({
  warehouse_id: null as number | null,
  category_id: null as number | null,
  unit: '' as string,
  min_stock: null as number | null,
  safety_stock: null as number | null,
  location: '' as string,
  status: '' as string,
})

watch(
  () => props.visible,
  (v) => {
    if (v) {
      Object.assign(form, {
        warehouse_id: null, category_id: null, unit: '',
        min_stock: null, safety_stock: null, location: '', status: '',
      })
    }
  },
)

const handleSubmit = () => {
  const fields: Record<string, unknown> = {}
  for (const [k, v] of Object.entries(form)) {
    if (v !== null && v !== '' && v !== undefined) {
      fields[k] = v
    }
  }
  if (Object.keys(fields).length === 0) {
    ElMessage.warning('请至少填写一个要修改的字段')
    return
  }
  emit('submit', fields)
}
</script>

<template>
  <el-dialog
    :model-value="dialogVisible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    title="批量编辑"
    width="560px"
    :close-on-click-modal="false"
    destroy-on-close
    @close="close"
  >
    <el-alert
      :title="`将对选中的 ${selectedCount} 项物料批量修改以下字段（留空字段不修改）`"
      type="info"
      :closable="false"
      style="margin-bottom: 12px"
    />
    <el-form :model="form" label-width="100px">
      <el-form-item label="仓库">
        <el-select v-model="form.warehouse_id" placeholder="选择仓库（不修改留空）" clearable style="width: 100%">
          <el-option v-for="w in warehouseOptions" :key="w.id" :label="w.name" :value="w.id" />
        </el-select>
      </el-form-item>
      <el-form-item label="分类">
        <el-cascader
          v-model="form.category_id"
          :options="categoryOptions"
          :props="{ value: 'id', label: 'name', children: 'children', checkStrictly: true, emitPath: false }"
          placeholder="选择分类"
          clearable
          style="width: 100%"
        />
      </el-form-item>
      <el-form-item label="单位">
        <el-input v-model="form.unit" placeholder="如：个 / 件 / 米" />
      </el-form-item>
      <el-form-item label="最低库存">
        <el-input-number v-model="form.min_stock" :min="0" :step="1" style="width: 100%" />
      </el-form-item>
      <el-form-item label="安全库存">
        <el-input-number v-model="form.safety_stock" :min="0" :step="1" style="width: 100%" />
      </el-form-item>
      <el-form-item label="货位">
        <el-input v-model="form.location" placeholder="货架 / 库位编码" />
      </el-form-item>
      <el-form-item label="状态">
        <el-select v-model="form.status" placeholder="不修改留空" clearable style="width: 100%">
          <el-option label="启用" value="active" />
          <el-option label="禁用" value="inactive" />
        </el-select>
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="close">取消</el-button>
      <el-button type="primary" :loading="submitting" @click="handleSubmit">应用</el-button>
    </template>
  </el-dialog>
</template>
