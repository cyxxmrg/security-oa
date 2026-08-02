<template>
  <el-dialog
    :model-value="visible"
    :title="isEdit ? '编辑物品' : '新建物品'"
    width="700px"
    :close-on-click-modal="false"
    :destroy-on-close="true"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
  >
    <div v-loading="loading" class="item-form-body">
      <el-form
        ref="formRef"
        :model="form"
        :rules="rules"
        label-width="92px"
        label-position="right"
      >
        <!-- 物品名称 -->
        <el-form-item label="物品名称" prop="name">
          <el-input v-model="form.name" placeholder="例如: 海康威视 4mm 球机" maxlength="200" show-word-limit clearable />
        </el-form-item>

        <!-- 物料编号 -->
        <el-form-item label="物料编号" prop="code">
          <el-input v-model="form.code" placeholder="例如: HK-DS-2CD2143" maxlength="64" clearable :disabled="isEdit" />
          <div v-if="isEdit" class="form-tip">编号创建后不可修改</div>
        </el-form-item>

        <!-- 分类 (下拉 + 树形) -->
        <el-form-item label="分类" prop="category_id">
          <el-tree-select
            v-model="form.category_id"
            :data="categoryOptions"
            :props="{ value: 'id', label: 'name', children: 'children' }"
            check-strictly
            clearable
            placeholder="选择物品分类"
            style="width: 100%"
            node-key="id"
          />
        </el-form-item>

        <!-- 规格 / 单位 -->
        <el-form-item label="规格" prop="specification">
          <el-input v-model="form.specification" placeholder="例如: 200万像素 / 4mm / 红外30m" maxlength="255" clearable />
        </el-form-item>

        <el-form-item label="单位" prop="unit">
          <el-select v-model="form.unit" placeholder="选择单位" style="width: 100%" filterable allow-create>
            <el-option v-for="u in unitOptions" :key="u" :label="u" :value="u" />
          </el-select>
        </el-form-item>

        <!-- 仓库 / 库位 -->
        <el-form-item label="仓库" prop="warehouse_id">
          <el-select v-model="form.warehouse_id" placeholder="选择仓库" clearable filterable style="width: 100%">
            <el-option v-for="w in warehouseOptions" :key="w.id" :label="w.name" :value="w.id" />
          </el-select>
        </el-form-item>

        <el-form-item label="库位" prop="location">
          <el-input v-model="form.location" placeholder="例如: A区-03排-2层" maxlength="100" clearable />
        </el-form-item>

        <!-- 价格 -->
        <el-form-item label="成本价" prop="cost_price">
          <el-input-number
            v-model="form.cost_price"
            :precision="2"
            :step="10"
            :min="0"
            placeholder="0.00"
            style="width: 100%"
          />
        </el-form-item>

        <el-form-item label="销售价" prop="sell_price">
          <el-input-number
            v-model="form.sell_price"
            :precision="2"
            :step="10"
            :min="0"
            placeholder="0.00"
            style="width: 100%"
          />
        </el-form-item>

        <!-- 库存 -->
        <el-form-item v-if="!isEdit" label="当前库存" prop="current_stock">
          <el-input-number v-model="form.current_stock" :min="0" :step="1" placeholder="0" style="width: 100%" />
          <div class="form-tip">初始库存, 后续通过入库/出库调整</div>
        </el-form-item>

        <el-form-item label="安全库存" prop="safety_stock">
          <el-input-number v-model="form.safety_stock" :min="0" :step="1" placeholder="0" style="width: 100%" />
          <div class="form-tip">低于此值触发低库存预警</div>
        </el-form-item>

        <!-- 序列号管理 -->
        <el-form-item label="序列号管理" prop="has_serial">
          <el-switch
            v-model="form.has_serial"
            :active-value="1"
            :inactive-value="0"
            active-text="启用"
            inactive-text="禁用"
            inline-prompt
          />
          <div class="form-tip">启用后, 该物品的每一件都需要独立序列号</div>
        </el-form-item>

        <!-- 状态 -->
        <el-form-item label="状态" prop="status">
          <el-radio-group v-model="form.status">
            <el-radio value="active">启用</el-radio>
            <el-radio value="inactive">停用</el-radio>
          </el-radio-group>
        </el-form-item>

        <!-- 备注 -->
        <el-form-item label="备注" prop="description">
          <el-input
            v-model="form.description"
            type="textarea"
            :rows="3"
            placeholder="补充说明 (选填)"
            maxlength="500"
            show-word-limit
          />
        </el-form-item>
      </el-form>
    </div>

    <template #footer>
      <div class="dialog-footer">
        <el-button @click="emit('update:visible', false)" :disabled="submitting">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="onSubmit">
          {{ isEdit ? '保存' : '创建' }}
        </el-button>
      </div>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch } from 'vue'
import { ElMessage } from 'element-plus'
import type { FormInstance, FormRules } from 'element-plus'
import { inventory } from '@/api/modules'
import { unwrapList } from '@/utils/response'
import type { InventoryItem, ItemCategory, WarehouseOption } from '../types'

const props = defineProps<{
  visible: boolean
  item: InventoryItem | null  // 编辑时传入, 新建为 null
}>()

const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'success'): void
}>()

const formRef = ref<FormInstance>()
const loading = ref(false)
const submitting = ref(false)

const isEdit = computed(() => !!props.item)

// 下拉数据
const categoryOptions = ref<ItemCategory[]>([])
const warehouseOptions = ref<WarehouseOption[]>([])

const unitOptions = ['个', '台', '套', '件', '箱', '米', '根', '块', '片', '卷', '袋', 'kg', 'g', 'L', 'm', 'm²', 'm³', '只', '对', '瓶']

// 默认表单
const defaultForm = () => ({
  name: '',
  code: '',
  category_id: null as number | null,
  specification: '',
  unit: '个',
  warehouse_id: null as number | null,
  location: '',
  cost_price: 0,
  sell_price: 0,
  current_stock: 0,
  safety_stock: 0,
  has_serial: 0 as number,
  status: 'active',
  description: '',
})

const form = reactive<ReturnType<typeof defaultForm>>(defaultForm())

// 校验规则
const rules: FormRules = {
  name: [
    { required: true, message: '请输入物品名称', trigger: 'blur' },
    { max: 200, message: '不能超过 200 字符', trigger: 'blur' },
  ],
  code: [
    { required: true, message: '请输入物料编号', trigger: 'blur' },
    { max: 64, message: '不能超过 64 字符', trigger: 'blur' },
    { pattern: /^[A-Za-z0-9\-_]+$/, message: '仅允许字母数字横线下划线', trigger: 'blur' },
  ],
  category_id: [
    { required: true, message: '请选择分类', trigger: 'change' },
  ],
  unit: [
    { required: true, message: '请选择或输入单位', trigger: 'change' },
  ],
  safety_stock: [
    { type: 'number', min: 0, message: '不能小于 0', trigger: 'blur' },
  ],
}

// 监听 visible, 打开时初始化
watch(() => props.visible, async (v) => {
  if (v) {
    resetForm()
    await loadOptions()
    if (props.item) {
      fillFromItem(props.item)
    }
  }
})

function resetForm() {
  Object.assign(form, defaultForm())
  formRef.value?.clearValidate()
}

function fillFromItem(row: InventoryItem) {
  Object.assign(form, {
    name: row.name || '',
    code: row.code || '',
    category_id: row.category_id || null,
    specification: row.specification || '',
    unit: row.unit || '个',
    warehouse_id: row.warehouse_id || null,
    location: row.location || '',
    cost_price: Number(row.cost_price || 0),
    sell_price: Number(row.sell_price || 0),
    safety_stock: Number(row.safety_stock || 0),
    has_serial: row.has_serial ? 1 : 0,
    status: row.status || 'active',
    description: row.description || '',
  })
}

async function loadOptions() {
  loading.value = true
  try {
    const [catRes, whRes] = await Promise.all([
      inventory.treeWithCounts().catch(() => null),
      inventory.warehouses().catch(() => null),
    ])
    // treeWithCounts 返回 { tree: [...] }
    if (catRes) {
      categoryOptions.value = unwrapList(catRes) as ItemCategory[]
    } else {
      categoryOptions.value = []
    }
    // warehouses 返回 { data: [...] }
    if (whRes) {
      warehouseOptions.value = whRes.data || whRes || []
    } else {
      warehouseOptions.value = []
    }
  } catch (e) {
    console.warn('[loadOptions]', e)
  } finally {
    loading.value = false
  }
}

async function onSubmit() {
  if (!formRef.value) return
  try {
    await formRef.value.validate()
  } catch {
    return
  }

  submitting.value = true
  try {
    const payload: Record<string, unknown> = {
      name: form.name.trim(),
      code: form.code.trim(),
      category_id: form.category_id,
      specification: form.specification || undefined,
      unit: form.unit,
      warehouse_id: form.warehouse_id || undefined,
      location: form.location || undefined,
      cost_price: form.cost_price,
      sell_price: form.sell_price,
      safety_stock: form.safety_stock,
      has_serial: !!form.has_serial,
      status: form.status,
      description: form.description || undefined,
    }
    if (!isEdit.value) {
      payload.current_stock = form.current_stock
      await inventory.createItem(payload)
      ElMessage.success('物品已创建')
    } else {
      await inventory.updateItem(props.item!.id, payload)
      ElMessage.success('物品已更新')
    }
    emit('success')
    emit('update:visible', false)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } }; message?: string }
    const msg = err?.response?.data?.message || err?.message || (isEdit.value ? '更新失败' : '创建失败')
    ElMessage.error(msg)
  } finally {
    submitting.value = false
  }
}
</script>

<style lang="scss" scoped>
.item-form-body {
  padding: 0 4px;
}
.form-tip {
  font-size: 12px;
  color: #909399;
  line-height: 1.4;
  margin-top: 2px;
}
.dialog-footer {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}
</style>