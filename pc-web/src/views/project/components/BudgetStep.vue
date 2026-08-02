<template>
  <el-card class="form-card">
    <template #header>
      <div class="card-header">
        <el-icon color="#0C447C"><Money /></el-icon>
        <span>项目预算编制</span>
        <div style="margin-left: auto">
          <el-tag type="success" size="large">
            总预算：{{ formatMoney(totalBudget) }} 万元
          </el-tag>
        </div>
      </div>
    </template>

    <el-tabs v-model="budgetTab">
      <el-tab-pane label="设备预算" name="equipment">
        <el-table :data="form.budget.equipment" border>
          <el-table-column label="#" width="50" type="index" align="center" />
          <el-table-column label="设备名称" min-width="180">
            <template #default="{ row }">
              <el-input v-model="row.name" readonly placeholder="请选择库存设备" size="small" @click="openBudgetPicker('equipment', row)">
                <template #append>
                  <el-button @click.stop="openBudgetPicker('equipment', row)">选择</el-button>
                </template>
              </el-input>
            </template>
          </el-table-column>
          <el-table-column label="品牌" width="120">
            <template #default="{ row }">
              <el-input v-model="row.brand" placeholder="如：海康" size="small" />
            </template>
          </el-table-column>
          <el-table-column label="型号" width="140">
            <template #default="{ row }">
              <el-input v-model="row.model" placeholder="如：DS-2CD2T47" size="small" />
            </template>
          </el-table-column>
          <el-table-column label="数量" width="100" align="center">
            <template #default="{ row }">
              <el-input-number
                v-model="row.qty"
                :min="1"
                size="small"
                controls-position="right"
                style="width: 80px"
              />
            </template>
          </el-table-column>
          <el-table-column label="单价(元)" width="120" align="right">
            <template #default="{ row }">
              <el-input-number
                v-model="row.price"
                :min="0"
                :precision="2"
                size="small"
                controls-position="right"
                style="width: 100px"
              />
            </template>
          </el-table-column>
          <el-table-column label="小计(元)" width="120" align="right">
            <template #default="{ row }">
              <span class="amount-text">{{ (row.qty * row.price).toFixed(2) }}</span>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="80" align="center">
            <template #default="{ $index }">
              <el-button type="danger" link size="small" @click="removeBudget('equipment', $index)">
                删除
              </el-button>
            </template>
          </el-table-column>
        </el-table>
        <div class="budget-actions">
          <el-button :icon="Plus" @click="addBudget('equipment')">添加设备</el-button>
        </div>
      </el-tab-pane>

      <el-tab-pane label="材料预算" name="material">
        <el-table :data="form.budget.material" border>
          <el-table-column label="#" width="50" type="index" align="center" />
          <el-table-column label="材料名称" min-width="200">
            <template #default="{ row }">
              <el-input v-model="row.name" readonly placeholder="请选择库存材料" size="small" @click="openBudgetPicker('material', row)">
                <template #append>
                  <el-button @click.stop="openBudgetPicker('material', row)">选择</el-button>
                </template>
              </el-input>
            </template>
          </el-table-column>
          <el-table-column label="规格" width="160">
            <template #default="{ row }">
              <el-input v-model="row.spec" placeholder="如：305米/箱" size="small" />
            </template>
          </el-table-column>
          <el-table-column label="单位" width="80" align="center">
            <template #default="{ row }">
              <el-input v-model="row.unit" placeholder="箱" size="small" />
            </template>
          </el-table-column>
          <el-table-column label="数量" width="100" align="center">
            <template #default="{ row }">
              <el-input-number
                v-model="row.qty"
                :min="1"
                size="small"
                controls-position="right"
                style="width: 80px"
              />
            </template>
          </el-table-column>
          <el-table-column label="单价(元)" width="120" align="right">
            <template #default="{ row }">
              <el-input-number
                v-model="row.price"
                :min="0"
                :precision="2"
                size="small"
                controls-position="right"
                style="width: 100px"
              />
            </template>
          </el-table-column>
          <el-table-column label="小计(元)" width="120" align="right">
            <template #default="{ row }">
              <span class="amount-text">{{ (row.qty * row.price).toFixed(2) }}</span>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="80" align="center">
            <template #default="{ $index }">
              <el-button type="danger" link size="small" @click="removeBudget('material', $index)">
                删除
              </el-button>
            </template>
          </el-table-column>
        </el-table>
        <div class="budget-actions">
          <el-button :icon="Plus" @click="addBudget('material')">添加材料</el-button>
        </div>
      </el-tab-pane>

      <el-tab-pane label="人工费用" name="labor">
        <el-table :data="form.budget.labor" border>
          <el-table-column label="#" width="50" type="index" align="center" />
          <el-table-column label="岗位/工种" min-width="180">
            <template #default="{ row }">
              <el-input v-model="row.name" placeholder="如：弱电工程师" size="small" />
            </template>
          </el-table-column>
          <el-table-column label="人数" width="100" align="center">
            <template #default="{ row }">
              <el-input-number
                v-model="row.qty"
                :min="1"
                size="small"
                controls-position="right"
                style="width: 80px"
              />
            </template>
          </el-table-column>
          <el-table-column label="工时(天)" width="100" align="center">
            <template #default="{ row }">
              <el-input-number
                v-model="row.days"
                :min="1"
                size="small"
                controls-position="right"
                style="width: 80px"
              />
            </template>
          </el-table-column>
          <el-table-column label="日薪(元)" width="120" align="right">
            <template #default="{ row }">
              <el-input-number
                v-model="row.dailyRate"
                :min="0"
                :precision="2"
                size="small"
                controls-position="right"
                style="width: 100px"
              />
            </template>
          </el-table-column>
          <el-table-column label="小计(元)" width="120" align="right">
            <template #default="{ row }">
              <span class="amount-text">{{ (row.qty * row.days * row.dailyRate).toFixed(2) }}</span>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="80" align="center">
            <template #default="{ $index }">
              <el-button type="danger" link size="small" @click="removeBudget('labor', $index)">
                删除
              </el-button>
            </template>
          </el-table-column>
        </el-table>
        <div class="budget-actions">
          <el-button :icon="Plus" @click="addBudget('labor')">添加工种</el-button>
        </div>
      </el-tab-pane>

      <el-tab-pane label="外包费用" name="outsource">
        <el-table :data="form.budget.outsource" border>
          <el-table-column label="#" width="50" type="index" align="center" />
          <el-table-column label="外包项目" min-width="220">
            <template #default="{ row }">
              <el-input v-model="row.name" placeholder="如：光纤熔接服务" size="small" />
            </template>
          </el-table-column>
          <el-table-column label="承接方" width="180">
            <template #default="{ row }">
              <el-input v-model="row.vendor" placeholder="供应商名称" size="small" />
            </template>
          </el-table-column>
          <el-table-column label="金额(元)" width="140" align="right">
            <template #default="{ row }">
              <el-input-number
                v-model="row.amount"
                :min="0"
                :precision="2"
                size="small"
                controls-position="right"
                style="width: 130px"
              />
            </template>
          </el-table-column>
          <el-table-column label="备注" min-width="200">
            <template #default="{ row }">
              <el-input v-model="row.remark" placeholder="服务范围说明" size="small" />
            </template>
          </el-table-column>
          <el-table-column label="操作" width="80" align="center">
            <template #default="{ $index }">
              <el-button type="danger" link size="small" @click="removeBudget('outsource', $index)">
                删除
              </el-button>
            </template>
          </el-table-column>
        </el-table>
        <div class="budget-actions">
          <el-button :icon="Plus" @click="addBudget('outsource')">添加外包</el-button>
        </div>
      </el-tab-pane>

      <el-tab-pane label="其他费用" name="other">
        <el-table :data="form.budget.other" border>
          <el-table-column label="#" width="50" type="index" align="center" />
          <el-table-column label="费用类型" min-width="180">
            <template #default="{ row }">
              <el-input v-model="row.name" placeholder="如：运输费、差旅费" size="small" />
            </template>
          </el-table-column>
          <el-table-column label="金额(元)" width="140" align="right">
            <template #default="{ row }">
              <el-input-number
                v-model="row.amount"
                :min="0"
                :precision="2"
                size="small"
                controls-position="right"
                style="width: 130px"
              />
            </template>
          </el-table-column>
          <el-table-column label="说明" min-width="300">
            <template #default="{ row }">
              <el-input v-model="row.remark" placeholder="费用说明" size="small" />
            </template>
          </el-table-column>
          <el-table-column label="操作" width="80" align="center">
            <template #default="{ $index }">
              <el-button type="danger" link size="small" @click="removeBudget('other', $index)">
                删除
              </el-button>
            </template>
          </el-table-column>
        </el-table>
        <div class="budget-actions">
          <el-button :icon="Plus" @click="addBudget('other')">添加费用</el-button>
        </div>
      </el-tab-pane>
    </el-tabs>

    <el-divider />

    <el-descriptions title="预算汇总" :column="3" border>
      <el-descriptions-item label="设备费用">
        <span class="summary-text">¥ {{ formatMoney(equipmentTotal) }}</span>
      </el-descriptions-item>
      <el-descriptions-item label="材料费用">
        <span class="summary-text">¥ {{ formatMoney(materialTotal) }}</span>
      </el-descriptions-item>
      <el-descriptions-item label="人工费用">
        <span class="summary-text">¥ {{ formatMoney(laborTotal) }}</span>
      </el-descriptions-item>
      <el-descriptions-item label="外包费用">
        <span class="summary-text">¥ {{ formatMoney(outsourceTotal) }}</span>
      </el-descriptions-item>
      <el-descriptions-item label="其他费用">
        <span class="summary-text">¥ {{ formatMoney(otherTotal) }}</span>
      </el-descriptions-item>
      <el-descriptions-item label="合计">
        <span class="summary-text total">¥ {{ formatMoney(totalBudget * 10000) }}</span>
      </el-descriptions-item>
    </el-descriptions>
    <InventoryItemPicker
      :show="pickerVisible"
      @close="pickerVisible = false"
      @select="handleInventorySelected"
    />
  </el-card>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { Money, Plus } from '@element-plus/icons-vue'
import type { ProjectForm, BudgetKey } from '../createTypes'
import { newBudgetRow, formatMoney } from '../createTypes'
import InventoryItemPicker from '@/views/inventory/components/InventoryItemPicker.vue'

const props = defineProps<{ form: ProjectForm }>()

const budgetTab = ref<BudgetKey>('equipment')
const pickerVisible = ref(false)
const pickingTarget = ref<{ key: 'equipment' | 'material'; row: Record<string, unknown> } | null>(null)

const equipmentTotal = computed(() =>
  props.form.budget.equipment.reduce((s, e) => s + e.qty * e.price, 0),
)
const materialTotal = computed(() =>
  props.form.budget.material.reduce((s, m) => s + m.qty * m.price, 0),
)
const laborTotal = computed(() =>
  props.form.budget.labor.reduce((s, l) => s + l.qty * l.days * l.dailyRate, 0),
)
const outsourceTotal = computed(() =>
  props.form.budget.outsource.reduce((s, o) => s + o.amount, 0),
)
const otherTotal = computed(() =>
  props.form.budget.other.reduce((s, o) => s + o.amount, 0),
)
const totalBudget = computed(
  () =>
    (equipmentTotal.value + materialTotal.value + laborTotal.value +
     outsourceTotal.value + otherTotal.value) / 10000,
)

const addBudget = (key: BudgetKey) => {
  (props.form.budget as Record<string, unknown[]>)[key].push(newBudgetRow(key))
}
const removeBudget = (key: BudgetKey, idx: number) => {
  (props.form.budget as Record<string, unknown[]>)[key].splice(idx, 1)
}

const openBudgetPicker = (key: 'equipment' | 'material', row: Record<string, unknown>) => {
  pickingTarget.value = { key, row }
  pickerVisible.value = true
}

const handleInventorySelected = (items: Record<string, unknown>[]) => {
  const item = items[0]
  if (!item || !pickingTarget.value) return
  const row = pickingTarget.value.row
  row.inventory_item_id = item.id
  row.name = item.name || ''
  row.spec = item.spec || item.specification || row.spec || ''
  row.model = item.spec || item.specification || row.model || ''
  row.unit = item.unit || row.unit || ''
  row.price = Number(item.cost_price ?? item.price ?? item.sale_price ?? row.price ?? 0)
  pickerVisible.value = false
}
</script>

<style scoped>
.card-header {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 16px;
  font-weight: 600;
  color: #303133;
}
.budget-actions {
  margin-top: 12px;
  display: flex;
  justify-content: flex-start;
}
.amount-text {
  color: #1D9E75;
  font-weight: 600;
}
.summary-text {
  color: #303133;
  font-weight: 500;
}
.summary-text.total {
  color: #0C447C;
  font-weight: 700;
  font-size: 16px;
}
</style>
