<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    title="新增物料申领"
    width="900px"
    :close-on-click-modal="false"
  >
    <el-alert type="info" :closable="false" show-icon style="margin-bottom: 16px">
      申领单提交后将进入审批流程，审批通过后自动出库，可在运营审批中心查看进度。
    </el-alert>
    <div class="section-card">
      <div class="section-title"><el-icon><Document /></el-icon> 基本信息</div>
      <el-form ref="formRef" :model="form" :rules="rules" label-width="90px">
        <el-row :gutter="16">
          <el-col :span="8">
            <el-form-item label="关联项目" prop="project_id">
              <el-select v-model="form.project_id" filterable placeholder="选择项目" style="width:100%">
                <el-option v-for="p in projectOptions" :key="p.id" :label="p.name" :value="p.id" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="出库仓库" prop="warehouse_id">
              <el-select v-model="form.warehouse_id" placeholder="选择仓库" style="width:100%">
                <el-option v-for="w in warehouseOptions" :key="w.id" :label="w.name" :value="w.id" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="申领人">
              <el-input :model-value="currentUserName" disabled style="width:100%">
                <template #prefix><el-icon><User /></el-icon></template>
              </el-input>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="24">
            <el-form-item label="申领说明" prop="remark">
              <el-input v-model="form.remark" maxlength="500" show-word-limit placeholder="请说明申领用途" />
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
    </div>

    <div class="section-card" style="margin-top:16px">
      <div class="section-title" style="display:flex;align-items:center;justify-content:space-between">
        <span><el-icon><Goods /></el-icon> 物料明细</span>
        <el-button size="small" type="primary" :icon="Plus" @click="emit('addItem')">添加物料</el-button>
      </div>
      <el-table :data="form.items" stripe border style="width:100%" max-height="360">
        <el-table-column type="index" label="#" width="42" />
        <el-table-column label="编码" width="120">
          <template #default="{row}"><span v-if="row.item" class="item-code">{{ row.item.code }}</span><span v-else class="text-muted">-</span></template>
        </el-table-column>
        <el-table-column label="物料名称" min-width="160">
          <template #default="{row,$index}">
            <div style="display:flex;gap:4px;align-items:center">
              <span v-if="row.item" style="flex:1">{{ row.item.name }}</span>
              <el-button size="small" type="primary" link @click="emit('pickItem', row.uid)">{{ row.item?"更换":"选择物料" }}</el-button>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="规格" width="100" show-overflow-tooltip>
          <template #default="{row}"><span v-if="row.item?.spec">{{ row.item.spec }}</span><span v-else class="text-muted">-</span></template>
        </el-table-column>
        <el-table-column label="单位" width="60" align="center">
          <template #default="{row}">{{ row.item?.unit||"-" }}</template>
        </el-table-column>
        <el-table-column label="库存" width="70" align="center">
          <template #default="{row}">
            <el-tag v-if="row.item" :type="(row.item.current_stock??0)<=0?'danger':(row.item.current_stock??0)<10?'warning':'success'" size="small" effect="plain">{{ row.item.current_stock??0 }}</el-tag>
            <span v-else class="text-muted">-</span>
          </template>
        </el-table-column>
        <el-table-column label="申领数量" width="130">
          <template #default="{row}">
            <el-input-number v-model="row.quantity" :min="1" :max="row.item?.current_stock||99999" :step="1" style="width:110px" size="small" />
          </template>
        </el-table-column>
        <el-table-column label="操作" width="55" align="center">
          <template #default="{_,$index}">
            <el-button type="danger" link size="small" :icon="Delete" @click="emit('removeItem', $index)" />
          </template>
        </el-table-column>
      </el-table>
      <div v-if="form.items.length===0" style="text-align:center;padding:24px;color:#c0c4cc">
        <el-empty :image-size="50" description="暂无物料，点击上方「添加物料」按钮" />
      </div>
    </div>
    <template #footer>
      <el-button @click="emit('update:visible', false)">取消</el-button>
      <el-button type="primary" :loading="submitting" @click="emit('submit')">提交审批</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { Document, Goods, Plus, Delete, User } from '@element-plus/icons-vue'
import type { FormInstance, FormRules } from 'element-plus'
import type { MaterialRequest, WarehouseOption } from '../../types'

interface BusinessOption { id: number; name: string }

defineProps<{
  visible: boolean
  form: { project_id: number | null; warehouse_id: number | null; applicant_id: number | null; remark: string; items: Array<{ uid: string; item: import('../../types').InventoryItem | null; quantity: number }> }
  rules: FormRules
  projectOptions: BusinessOption[]
  warehouseOptions: WarehouseOption[]
  currentUserName: string
  submitting: boolean
}>()
const formRef = ref<FormInstance | null>(null)
defineExpose({ formRef })
const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'addItem'): void
  (e: 'removeItem', i: number): void
  (e: 'pickItem', uid: string): void
  (e: 'submit'): void
}>()
</script>

<style lang="scss" scoped>
.section-card { background: #fafbfc; border: 1px solid #ebeef5; border-radius: 8px; padding: 16px; }
.section-title { font-size: 14px; font-weight: 600; color: #303133; margin-bottom: 12px; padding-left: 8px; border-left: 3px solid #1D9E75; }
.item-code { font-family: monospace; color: #0C447C; }
.text-muted { color: #c0c4cc; }
</style>
