<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    :title="isEdit ? '编辑预算' : '新建预算'"
    width="1200px"
    :close-on-click-modal="false"
    @open="handleOpen"
  >
    <el-form ref="formRef" :model="formData" :rules="formRules" label-width="100px">
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="所属项目" prop="project_id">
            <el-select
              v-model="formData.project_id"
              placeholder="请选择项目"
              filterable
              :disabled="isEdit"
              style="width: 100%"
            >
              <el-option
                v-for="p in projectOptions"
                :key="p.id"
                :label="`${p.code ? p.code + ' - ' : ''}${p.name || ''}`"
                :value="p.id"
              />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="备注">
            <el-input v-model="formData.remark" placeholder="可选" maxlength="500" show-word-limit />
          </el-form-item>
        </el-col>
      </el-row>

      <el-divider content-position="left">预算明细</el-divider>

      <div class="items-toolbar">
        <el-button type="primary" :icon="Plus" plain size="small" @click="handleAddRow">添加明细</el-button>
        <el-button :icon="Box" plain size="small" @click="handleAddMaterialFromInventory">从库存添加材料</el-button>
        <span class="items-tip">共 {{ formData.items.length }} 条，合计 <b style="color:#0C447C">¥ {{ formatMoney(totalAmount) }}</b></span>
      </div>

      <el-table :data="formData.items" border size="small" style="margin-bottom: 12px">
        <el-table-column label="类别" width="120">
          <template #default="{ row, $index }">
            <el-select v-model="row.category" placeholder="类别" size="small" style="width: 100%">
              <el-option v-for="c in categoryOptions" :key="c.value" :label="c.label" :value="c.value" />
            </el-select>
          </template>
        </el-table-column>
        <el-table-column label="名称" min-width="160">
          <template #default="{ row }">
            <el-input
              v-model="row.name"
              :readonly="row.category === 'material'"
              size="small"
              :placeholder="row.category === 'material' ? '请选择库存材料' : '名称'"
              @click="row.category === 'material' && openInventoryPicker(row)"
            >
              <template v-if="row.category === 'material'" #append>
                <el-button @click.stop="openInventoryPicker(row)">选择</el-button>
              </template>
            </el-input>
          </template>
        </el-table-column>
        <el-table-column label="规格" width="120">
          <template #default="{ row }">
            <el-input v-model="row.spec" size="small" placeholder="规格" />
          </template>
        </el-table-column>
        <el-table-column label="单位" width="80">
          <template #default="{ row }">
            <el-input v-model="row.unit" size="small" placeholder="单位" />
          </template>
        </el-table-column>
        <el-table-column label="数量" width="110">
          <template #default="{ row }">
            <el-input-number
              v-model="row.qty"
              :min="0"
              :precision="2"
              size="small"
              style="width: 100%"
              @change="(v: number) => recalcRow(row)"
            />
          </template>
        </el-table-column>
        <el-table-column label="单价" width="130">
          <template #default="{ row }">
            <el-input-number
              v-model="row.unit_price"
              :min="0"
              :precision="2"
              :step="0.01"
              size="small"
              style="width: 100%"
              @change="(v: number) => recalcRow(row)"
            />
          </template>
        </el-table-column>
        <el-table-column label="金额" width="140" align="right">
          <template #default="{ row }">
            <span style="color:#0C447C;font-weight:600">¥ {{ formatMoney(row.amount) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="备注" min-width="120">
          <template #default="{ row }">
            <el-input v-model="row.remark" size="small" placeholder="备注" />
          </template>
        </el-table-column>
        <el-table-column label="操作" width="70" align="center" fixed="right">
          <template #default="{ $index }">
            <el-button link type="danger" :icon="Delete" size="small" @click="handleRemoveRow($index)" />
          </template>
        </el-table-column>
      </el-table>

      <!-- 按类别汇总 -->
      <el-descriptions title="按类别汇总" :column="4" border size="small" class="summary-desc">
        <el-descriptions-item v-for="c in categoryOptions" :key="c.value" :label="c.label">
          <span style="color:#0C447C;font-weight:600">¥ {{ formatMoney(categoryTotal(c.value)) }}</span>
        </el-descriptions-item>
      </el-descriptions>
    </el-form>

    <template #footer>
      <el-button @click="emit('update:visible', false)">取消</el-button>
      <el-button type="primary" :loading="saving" @click="handleSave">保存草稿</el-button>
    </template>
  </el-dialog>

  <InventoryItemPicker
    :show="inventoryPickerVisible"
    :multiple="inventoryPickerMultiple"
    @close="inventoryPickerVisible = false"
    @select="handleInventorySelected"
  />
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus, Delete, Box } from '@element-plus/icons-vue'
import InventoryItemPicker from '@/views/inventory/components/InventoryItemPicker.vue'
import type { FormRules } from 'element-plus'
import { Budget, BudgetItem, ProjectOption, SaveBudgetPayload } from '../types'

// 库存物料项 (来自 InventoryItemPicker 的 select 事件, 含价格字段)
interface InventoryItemLike {
  id?: number
  name?: string
  spec?: string
  specification?: string
  unit?: string
  cost_price?: number | string
  price?: number | string
  sale_price?: number | string
  [key: string]: unknown
}

const props = defineProps<{
  visible: boolean
  projectOptions: ProjectOption[]
  editing?: Budget | null
}>()

const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'save', payload: SaveBudgetPayload): void
}>()

const formRef = ref()
const saving = ref(false)
const isEdit = computed(() => !!props.editing?.id)
const inventoryPickerVisible = ref(false)
const inventoryPickerMultiple = ref(false)
const activeMaterialRow = ref<BudgetItem | null>(null)

const categoryOptions = [
  { value: 'material',  label: '材料费' },
  { value: 'labor',     label: '人工费' },
  { value: 'outsource', label: '外包费' },
  { value: 'other',     label: '其他费' },
]

const formData = reactive<{ project_id: number | null; remark: string; items: BudgetItem[] }>({
  project_id: null,
  remark: '',
  items: [],
})

const formRules: FormRules = {
  project_id: [{ required: true, message: '请选择项目', trigger: 'change' }],
  items: [{
    required: true,
    validator: (_, value, callback) => {
      if (!value || value.length === 0) return callback(new Error('请至少添加一条明细'))
      const invalid = (value as BudgetItem[]).find((it) => !it.name || !it.category)
      if (invalid) return callback(new Error('明细需填写类别和名称'))
      const materialWithoutInventory = (value as BudgetItem[]).find((it) => it.category === 'material' && !it.item_id)
      if (materialWithoutInventory) return callback(new Error('材料费明细必须从库存信息中选择'))
      callback()
    },
    trigger: 'change',
  }],
}

const resetForm = () => {
  formData.project_id = null
  formData.remark = ''
  formData.items = []
}

const fillFromEditing = (row: Budget) => {
  if (!row) { resetForm(); return }
  formData.project_id = row.project_id || null
  formData.remark = row.remark || ''
  // 后端明细字段命名：category/name/spec/unit/qty/unit_price/amount/remark
  const items = Array.isArray(row.items) ? row.items : []
  formData.items = items.map((it: BudgetItem) => ({
    item_id:    it.item_id    || null,
    item_type:  it.item_type   || (it.item_id ? 'inventory_item' : null),
    category:   it.category   || 'material',
    name:       it.name       || it.item_name || '',
    spec:       it.spec       || it.specification || '',
    unit:       it.unit       || '',
    qty:        Number(it.qty        ?? it.quantity ?? 0),
    unit_price: Number(it.unit_price || 0),
    amount:     Number(it.amount     ?? it.planned_amount ?? (Number(it.qty ?? it.quantity ?? 0) * Number(it.unit_price || 0))),
    remark:     it.remark     || '',
  }))
  if (formData.items.length === 0) {
    formData.items.push(makeEmptyItem())
  }
}

const makeEmptyItem = (): BudgetItem => ({
  item_id: null, item_type: null, category: 'material', name: '', spec: '', unit: '', qty: 0, unit_price: 0, amount: 0, remark: ''
})

const handleOpen = () => {
  if (props.editing) fillFromEditing(props.editing)
  else resetForm()
}

const handleAddRow = () => formData.items.push(makeEmptyItem())
const handleRemoveRow = (idx: number) => {
  formData.items.splice(idx, 1)
  if (formData.items.length === 0) formData.items.push(makeEmptyItem())
}

const recalcRow = (row: BudgetItem) => {
  const q = Number(row.qty || 0)
  const p = Number(row.unit_price || 0)
  row.amount = Math.round(q * p * 100) / 100
}

const handleAddMaterialFromInventory = () => {
  activeMaterialRow.value = null
  inventoryPickerMultiple.value = true
  inventoryPickerVisible.value = true
}

const openInventoryPicker = (row: BudgetItem) => {
  activeMaterialRow.value = row
  inventoryPickerMultiple.value = false
  inventoryPickerVisible.value = true
}

const fillMaterialFromInventory = (row: BudgetItem, item: InventoryItemLike) => {
  row.category = 'material'
  row.item_id = item.id ?? null
  row.item_type = 'inventory_item'
  row.name = item.name || ''
  row.spec = item.spec || item.specification || ''
  row.unit = item.unit || row.unit || ''
  row.unit_price = Number(item.cost_price ?? item.price ?? item.sale_price ?? row.unit_price ?? 0)
  if (!row.qty || Number(row.qty) <= 0) row.qty = 1
  recalcRow(row)
}

const handleInventorySelected = (items: InventoryItemLike[]) => {
  if (!items?.length) return
  if (activeMaterialRow.value) {
    fillMaterialFromInventory(activeMaterialRow.value, items[0])
  } else {
    items.forEach((item) => {
      const row = makeEmptyItem()
      fillMaterialFromInventory(row, item)
      formData.items.push(row)
    })
  }
  inventoryPickerVisible.value = false
}

const totalAmount = computed(() =>
  formData.items.reduce((sum, it) => sum + Number(it.amount || 0), 0)
)

const categoryTotal = (key: string) =>
  formData.items.filter(it => it.category === key).reduce((s, it) => s + Number(it.amount || 0), 0)

const formatMoney = (n?: number | string | null) => Number(n || 0).toLocaleString('zh-CN', { maximumFractionDigits: 2 })

// 监听 items 变化时实时重算（qty/unit_price 改动通过 @change 已经触发；这里兜底）
watch(() => formData.items.map(i => `${i.qty}|${i.unit_price}`).join(','), () => {
  formData.items.forEach(recalcRow)
})

const handleSave = async () => {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  saving.value = true
  try {
    // 提交前再算一遍 amount
    formData.items.forEach(recalcRow)
    emit('save', {
      project_id: formData.project_id as number,
      items: formData.items.map(it => ({
        category: it.category,
        item_id: it.item_id || null,
        item_type: it.item_type || null,
        item_name: it.name,
        specification: it.spec,
        unit: it.unit,
        quantity: Number(it.qty || 0),
        unit_price: Number(it.unit_price || 0),
        planned_amount: Number(it.amount || 0),
        remark: it.remark,
      })),
      remark: formData.remark,
    })
  } finally {
    saving.value = false
  }
}
</script>

<style lang="scss" scoped>
.items-toolbar {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 8px;
  .items-tip { color: #909399; font-size: 13px; }
}
.summary-desc { margin-top: 8px; }
</style>
