<template>
  <el-dialog
    :model-value="visible"
    title="从产品库选择"
    width="900px"
    top="5vh"
    :close-on-click-modal="false"
    @update:model-value="$emit('update:visible', $event)"
  >
    <el-form inline class="picker-search">
      <el-form-item label="分类">
        <el-select
          v-model="filter.category_id"
          placeholder="全部分类"
          clearable
          style="width: 200px"
          @change="loadProducts"
        >
          <el-option
            v-for="c in productCategories"
            :key="c.id"
            :label="c.name"
            :value="c.id"
          />
        </el-select>
      </el-form-item>
      <el-form-item label="搜索">
        <el-input
          v-model="filter.keyword"
          placeholder="编码 / 名称"
          clearable
          style="width: 240px"
          @keyup.enter="loadProducts"
        />
      </el-form-item>
      <el-form-item>
        <el-button @click="resetFilter">重置</el-button>
        <el-button type="primary" @click="loadProducts">查询</el-button>
      </el-form-item>
      <el-form-item>
        <el-button
          :type="filter.invert ? 'warning' : ''"
          size="small"
          @click="filter.invert = !filter.invert"
        >
          {{ filter.invert ? '✓ 反选模式' : '反选' }}
        </el-button>
      </el-form-item>
    </el-form>

    <el-table
      ref="pickerTable"
      :data="products"
      v-loading="loading"
      border
      max-height="450"
      @selection-change="(rows) => selection = rows"
    >
      <el-table-column
        type="selection"
        width="55"
        :selectable="(row) => !isProductPicked(row)"
      />
      <el-table-column prop="code" label="编码" width="120" />
      <el-table-column prop="name" label="产品名称" min-width="180" />
      <el-table-column prop="spec" label="规格" width="100" />
      <el-table-column prop="unit" label="单位" width="60" align="center" />
      <el-table-column label="销售价" width="100" align="right">
        <template #default="{ row }">¥ {{ formatMoney(row.sale_price) }}</template>
      </el-table-column>
      <el-table-column prop="category.name" label="分类" width="100" />
    </el-table>

    <el-pagination
      small
      layout="prev, pager, next, total"
      :total="total"
      :page-size="filter.per_page"
      v-model:current-page="filter.page"
      @current-change="loadProducts"
    />

    <template #footer>
      <div style="display: flex; justify-content: space-between; align-items: center">
        <div class="muted">已选 {{ selection.length }} 个产品</div>
        <div>
          <el-button @click="$emit('update:visible', false)">取消</el-button>
          <el-button
            type="primary"
            :disabled="!selection.length"
            @click="handleConfirm"
          >
            加入报价单 ({{ selection.length }})
          </el-button>
        </div>
      </div>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { getSalesProducts, getSalesProductCategories } from '@/api/sales'
import { formatMoney, type QuoteItem } from '../quoteTypes'
import type { Product, ProductCategory } from '../types'

const props = defineProps<{
  visible: boolean
  existingItems: QuoteItem[]
}>()

const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'pick', items: QuoteItem[]): void
}>()

const products = ref<Product[]>([])
const productCategories = ref<ProductCategory[]>([])
const loading = ref(false)
const total = ref(0)
const selection = ref<Product[]>([])

const filter = reactive({
  category_id: null as number | null,
  keyword: '' as string,
  page: 1,
  per_page: 20,
  invert: false,
})

const isProductPicked = (row: Product) =>
  props.existingItems.some((i) => i.product_id === row.id || i.code === row.code)

const loadProducts = async () => {
  loading.value = true
  try {
    const r = await getSalesProducts({
      page: filter.page,
      per_page: filter.per_page,
      category_id: filter.category_id || undefined,
      keyword: filter.keyword || undefined,
    })
    const d = (r as Record<string, unknown>)?.data as Record<string, unknown> || {}
    products.value = (d?.data as Record<string, unknown>[]) || []
    total.value = (d?.total as number) || 0
  } catch (e) {
    products.value = []
  } finally {
    loading.value = false
  }
}

const resetFilter = () => {
  filter.category_id = null
  filter.keyword = ''
  filter.page = 1
  loadProducts()
}

// 当 dialog 打开时初始化
watch(
  () => props.visible,
  async (v) => {
    if (v) {
      selection.value = []
      filter.page = 1
      if (productCategories.value.length === 0) {
        try {
          const catRes = await getSalesProductCategories()
          const catData = (catRes as Record<string, unknown>)?.data as Record<string, unknown> || {}
          productCategories.value = (catData?.data as Record<string, unknown>[]) || (catRes as Record<string, unknown>)?.data as Record<string, unknown>[] || []
        } catch (e) {
          /* fallback */
        }
      }
      loadProducts()
    }
  },
  { immediate: true },
)

const handleConfirm = () => {
  if (!selection.value.length) return
  // 重复检测
  const dupes = selection.value.filter((p) => isProductPicked(p))
  if (dupes.length && !filter.invert) {
    ElMessage.warning(`已存在 ${dupes.length} 个相同产品: ${dupes.map((d) => d.name).join(', ')}`)
    return
  }
  const newItems: QuoteItem[] = selection.value.map((p) => ({
    product_id: p.id,
    code: p.code,
    name: p.name || '',
    specification: p.spec || '',
    unit: p.unit || '件',
    quantity: 1,
    unit_price: Number(p.sale_price || 0),
    _edit: false,
  }))
  emit('pick', newItems)
  emit('update:visible', false)
  ElMessage.success(`已加入 ${newItems.length} 个产品`)
}
</script>

<style scoped>
.picker-search { padding: 0 0 12px; }
.muted { color: #909399; font-size: 12px; }
</style>
