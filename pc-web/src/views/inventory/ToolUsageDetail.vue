<template>
  <div class="page-container">
    <div class="page-header">
      <div>
        <h2>工具使用单 · 明细</h2>
        <p v-if="tool" class="sub-title">
          {{ tool.name }}
          <el-tag v-if="tool.fixed_asset_no" size="small" effect="plain" type="primary" class="asset-no">{{ tool.fixed_asset_no }}</el-tag>
        </p>
      </div>
      <div class="header-actions">
        <el-button type="warning" :icon="TakeawayBox" @click="openMovement('checkout')" :disabled="!tool">工具领用</el-button>
        <el-button type="success" :icon="RefreshLeft" @click="openMovement('return')" :disabled="!tool">工具退还</el-button>
        <el-button plain :icon="ArrowLeft" @click="goBack">返回列表</el-button>
      </div>
    </div>

    <el-row :gutter="20">
      <!-- 左列：工具使用明细 -->
      <el-col :span="13">
        <div class="content-card">
          <div class="card-title">工具使用明细</div>
          <el-table v-loading="leftLoading" :data="records" stripe border height="520" style="width: 100%">
            <el-table-column type="index" label="#" width="48" />
            <el-table-column label="单号" width="160">
              <template #default="{ row }"><span class="record-no">{{ row.record_no }}</span></template>
            </el-table-column>
            <el-table-column label="类型" width="80" align="center">
              <template #default="{ row }">
                <el-tag :type="row.type === 'tool_checkout' ? 'danger' : 'success'" size="small">{{ row.type === 'tool_checkout' ? '领用' : '归还' }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="数量" width="90" align="right">
              <template #default="{ row }">
                <span :style="{ fontWeight: 600, color: row.type === 'tool_checkout' ? '#A32D2D' : '#1D9E75' }">{{ row.type === 'tool_checkout' ? '-' : '+' }}{{ row.quantity }}</span>
              </template>
            </el-table-column>
            <el-table-column label="操作人" width="90">
              <template #default="{ row }">{{ row.operator?.name || '-' }}</template>
            </el-table-column>
            <el-table-column label="时间" width="140">
              <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
            </el-table-column>
            <el-table-column prop="remark" label="备注" min-width="100" show-overflow-tooltip />
          </el-table>
          <div class="pagination-wrap">
            <el-pagination
              background
              layout="total, prev, pager, next"
              :total="leftPagination.total"
              :current-page="leftPagination.page"
              :page-size="leftPagination.per_page"
              @current-change="(p: number) => loadRecords(p)"
            />
          </div>
        </div>
      </el-col>

      <!-- 右列：工具在库信息 -->
      <el-col :span="11">
        <div class="content-card">
          <div class="card-title">工具在库信息</div>
          <el-skeleton v-if="toolLoading" :rows="8" animated />
          <el-empty v-else-if="!tool" description="未找到该工具台账" />
          <el-descriptions v-else :column="1" border size="default" label-width="110px">
            <el-descriptions-item label="固定资产编号">
              <span class="asset-no">{{ tool.fixed_asset_no || '-' }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="工具名称">{{ tool.name || '-' }}</el-descriptions-item>
            <el-descriptions-item label="规格">{{ tool.specification || '-' }}</el-descriptions-item>
            <el-descriptions-item label="单位">{{ tool.unit || '-' }}</el-descriptions-item>
            <el-descriptions-item label="台账件数">
              <span class="strong">{{ tool.quantity ?? 0 }}</span> 件
            </el-descriptions-item>
            <el-descriptions-item label="在库数量">
              <el-tag :type="(tool.current_stock ?? 0) <= 0 ? 'danger' : 'success'" size="small" effect="plain">{{ tool.current_stock ?? 0 }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="已借出">
              <span :style="{ color: '#A32D2D', fontWeight: 600 }">{{ tool.borrowed ?? 0 }}</span> 件
            </el-descriptions-item>
            <el-descriptions-item label="可用件数">
              <span :style="{ color: '#1D9E75', fontWeight: 600 }">{{ tool.available ?? 0 }}</span> 件
            </el-descriptions-item>
            <el-descriptions-item label="状态">
              <el-tag :type="tool.status === 'in_stock' ? 'success' : 'warning'" size="small">
                {{ tool.status === 'in_stock' ? '在库' : '借出' }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="所在仓库">{{ tool.warehouse?.name || '-' }}</el-descriptions-item>
            <el-descriptions-item label="关联商品编码">{{ tool.inventoryItem?.code || '-' }}</el-descriptions-item>
          </el-descriptions>
        </div>
      </el-col>
    </el-row>

    <!-- 领用 / 退还 确认弹窗 -->
    <el-dialog
      v-model="showMovement"
      :title="movementType === 'checkout' ? '工具领用确认' : '工具退还确认'"
      width="640px"
      :close-on-click-modal="false"
      top="20vh"
    >
      <el-alert
        :title="movementType === 'checkout' ? '确认从该工具台账领用，提交后库存相应扣减' : '确认向该工具台账归还，提交后库存相应增加'"
        type="info"
        :closable="false"
        show-icon
        style="margin-bottom: 12px"
      />
      <el-table :data="movementItems" stripe border style="width: 100%">
        <el-table-column label="固定资产编号" width="160">
          <template #default="{ row }"><span class="asset-no">{{ row.tool?.fixed_asset_no }}</span></template>
        </el-table-column>
        <el-table-column prop="tool.name" label="工具名称" min-width="130" show-overflow-tooltip />
        <el-table-column label="在库/已借" width="90" align="center">
          <template #default="{ row }">
            <span class="muted">{{ row.tool?.current_stock ?? 0 }} / {{ row.tool?.borrowed ?? 0 }}</span>
          </template>
        </el-table-column>
        <el-table-column :label="movementType === 'checkout' ? '领用数量' : '归还数量'" width="130">
          <template #default="{ row }">
            <el-input-number
              v-model="row.quantity"
              :min="1"
              :max="Math.max(1, movementType === 'checkout' ? (row.tool?.available ?? 1) : (row.tool?.borrowed ?? 1))"
              :step="1"
              size="small"
              style="width: 110px"
            />
          </template>
        </el-table-column>
      </el-table>
      <el-input v-model="movementRemark" placeholder="操作备注(可选)" maxlength="200" clearable style="margin-top: 12px" />
      <template #footer>
        <el-button @click="showMovement = false">取消</el-button>
        <el-button :type="movementType === 'checkout' ? 'warning' : 'success'" :loading="submitting" @click="submitMovement">
          确认{{ movementType === 'checkout' ? '领用' : '退还' }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Search, TakeawayBox, RefreshLeft, ArrowLeft } from '@element-plus/icons-vue'
import { get, post } from '@/utils/request'
import { unwrapList, unwrapPaginate } from '@/utils/response'

interface ToolDetail extends Record<string, unknown> {
  id: number
  inventory_item_id: number
  fixed_asset_no?: string | null
  name?: string | null
  specification?: string | null
  unit?: string | null
  status: string
  quantity?: number
  current_stock?: number
  borrowed?: number
  available?: number
  warehouse?: { name?: string } | null
  inventoryItem?: { code?: string } | null
}

interface RecordRow extends Record<string, unknown> {
  record_no: string
  type: string
  quantity: number
  inventory_item_id: number
  tool_id?: number | null
  operator?: { name?: string } | null
  remark?: string | null
  created_at?: string
}

interface MovementRow { uid: string; tool: ToolDetail | null; quantity: number }

const route = useRoute()
const router = useRouter()

const toolId = Number(route.query.toolId)
const tool = ref<ToolDetail | null>(null)
const toolLoading = ref(false)

const records = ref<RecordRow[]>([])
const leftLoading = ref(false)
const leftPagination = reactive({ page: 1, per_page: 15, total: 0 })

const formatDate = (s?: string | null) => {
  if (!s) return '-'
  const t = s.replace('T', ' ').slice(0, 16)
  return t || s
}

async function loadTool() {
  if (!toolId) { tool.value = null; return }
  toolLoading.value = true
  try {
    const res = await get('/inventory/tools', { id: toolId })
    const list = unwrapList(res) as ToolDetail[]
    tool.value = list[0] ?? null
  } catch (e) {
    console.error('[loadTool]', e)
    tool.value = null
  } finally {
    toolLoading.value = false
  }
}

async function loadRecords(page = 1) {
  if (!tool.value?.inventory_item_id) return
  leftPagination.page = page
  leftLoading.value = true
  try {
    const res = await get('/inventory/tool-records', {
      page,
      per_page: leftPagination.per_page,
      inventory_item_id: tool.value.inventory_item_id,
    })
    const pag = unwrapPaginate(res)
    records.value = pag.list as RecordRow[]
    leftPagination.total = pag.total
  } catch (e) {
    console.error('[loadRecords]', e)
    records.value = []
    leftPagination.total = 0
  } finally {
    leftLoading.value = false
  }
}

// ===== 领用 / 退还 =====
const showMovement = ref(false)
const movementType = ref<'checkout' | 'return'>('checkout')
const movementItems = ref<MovementRow[]>([])
const movementRemark = ref('')
const submitting = ref(false)

function openMovement(type: 'checkout' | 'return') {
  movementType.value = type
  movementRemark.value = ''
  const t = tool.value
  movementItems.value = t
    ? [{
        uid: 'single',
        tool: { ...t },
        quantity: 1,
      }]
    : []
  showMovement.value = true
}

async function submitMovement() {
  const validItems = movementItems.value.filter(r => r.tool)
  if (validItems.length === 0) { ElMessage.warning('工具信息缺失'); return }
  submitting.value = true
  try {
    const action = movementType.value === 'checkout' ? 'tool-checkout' : 'tool-return'
    const res = await post(`/inventory/${action}`, {
      items: validItems.map(r => ({ tool_id: r.tool!.id, quantity: r.quantity })),
      remark: movementRemark.value || undefined,
    })
    const recordNo = (res?.data as { record_no?: string } | undefined)?.record_no || ''
    ElMessage.success(movementType.value === 'checkout' ? `领用成功, 单号: ${recordNo}` : `归还成功, 单号: ${recordNo}`)
    showMovement.value = false
    await Promise.all([loadTool(), loadRecords(leftPagination.page)])
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } }; message?: string }
    ElMessage.error(err?.response?.data?.message || err?.message || '操作失败')
  } finally {
    submitting.value = false
  }
}

function goBack() {
  router.push({ name: 'InventoryToolUsageOrder' })
}

onMounted(() => {
  loadTool().then(() => loadRecords(1))
})
</script>

<style lang="scss" scoped>
.page-container { padding: 20px; background: #f5f7fa; min-height: 100vh; }
.page-header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 16px;
  h2 { font-size: 20px; color: #0C447C; margin: 0; }
  .sub-title { margin: 6px 0 0; color: #606266; font-size: 14px; display: flex; align-items: center; gap: 8px; }
}
.content-card {
  background: #fff; border-radius: 8px; padding: 16px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
  height: 100%;
}
.card-title {
  font-size: 15px; font-weight: 600; color: #0C447C;
  margin-bottom: 12px; padding-left: 8px; border-left: 3px solid #0C447C;
}
.pagination-wrap { display: flex; justify-content: flex-end; margin-top: 16px; }
.muted { color: #c0c4cc; }
.strong { font-weight: 600; }
.record-no { font-family: "DIN Pro", monospace; font-weight: 600; color: #0C447C; }
.asset-no { font-family: "DIN Pro", monospace; font-weight: 600; color: #0C447C; font-size: 12px; }
:deep(.el-dialog__body) { padding-top: 12px; }
</style>
