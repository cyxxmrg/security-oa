<template>
  <div class="page-container">
    <div class="page-header"><h2>出库单</h2></div>
    <div class="filter-bar">
      <el-select v-model="filterType" placeholder="出库类型" clearable style="width:160px" @change="loadList(1)">
        <el-option label="领用出库" value="out" /><el-option label="销售出库" value="sale" /><el-option label="报废出库" value="scrap" />
      </el-select>
      <el-date-picker v-model="dateRange" type="daterange" range-separator="至" start-placeholder="开始" end-placeholder="结束" value-format="YYYY-MM-DD" style="width:240px" @change="loadList(1)" />
      <el-input v-model="searchKey" placeholder="搜索单号/物料" clearable style="width:220px" :prefix-icon="Search" @keyup.enter="loadList(1)" @clear="loadList(1)" />
      <el-button type="primary" plain :icon="Plus" @click="handleCreate">新增出库单</el-button>
    </div>
    <div class="content-card">
      <el-table v-loading="loading" :data="list" stripe border style="width:100%" :row-key="(r: Record<string,unknown>) => r.record_no as string">
        <el-table-column type="index" label="#" width="50" />
        <el-table-column prop="record_no" label="单号" width="170">
          <template #default="{row}"><span class="record-no">{{ row.record_no }}</span></template>
        </el-table-column>
        <el-table-column label="出库类型" width="90" align="center">
          <template #default="{row}"><el-tag :type="row.type==='sale'?'primary':row.type==='scrap'?'danger':'warning'" size="small">{{ typeLabel(row.type) }}</el-tag></template>
        </el-table-column>
        <el-table-column label="仓库" width="100">
          <template #default="{row}">{{ row.warehouse?.name||'-' }}</template>
        </el-table-column>
        <el-table-column label="往来单位" width="130" show-overflow-tooltip>
          <template #default="{row}"><span v-if="row.party">{{ row.party.name }}</span><span v-else class="muted">-</span></template>
        </el-table-column>
        <el-table-column label="关联项目" width="130" show-overflow-tooltip>
          <template #default="{row}"><span v-if="row.project">{{ row.project.name }}</span><span v-else class="muted">-</span></template>
        </el-table-column>
        <el-table-column label="物料种数" width="80" align="center">
          <template #default="{row}">{{ row.item_count||1 }}</template>
        </el-table-column>
        <el-table-column label="出库总数" width="90" align="right">
          <template #default="{row}"><span style="font-weight:600;color:#A32D2D">-{{ row.total_quantity||0 }}</span></template>
        </el-table-column>
        <el-table-column label="单据金额" width="120" align="right">
          <template #default="{row}"><span style="font-weight:600;color:#1D9E75">¥{{ Number(row.total_amount||0).toFixed(2) }}</span></template>
        </el-table-column>
        <el-table-column label="付款方式" width="90" align="center">
          <template #default="{row}"><el-tag :type="row.payment_method==='receivable'?'warning':'success'" size="small">{{ row.payment_method==='receivable'?'应收款':'现金' }}</el-tag></template>
        </el-table-column>
        <el-table-column label="操作人" width="90">
          <template #default="{row}">{{ row.operator?.name||'-' }}</template>
        </el-table-column>
        <el-table-column label="出库时间" width="150">
          <template #default="{row}">{{ formatDate(row.created_at) }}</template>
        </el-table-column>
        <el-table-column prop="remark" label="备注" min-width="110" show-overflow-tooltip />
        <el-table-column label="操作" width="70" fixed="right">
          <template #default="{row}">
            <el-button link type="primary" size="small" @click="openDetail(String(row.record_no||''))">详情</el-button>
          </template>
        </el-table-column>
      </el-table>
      <div class="pagination-wrap">
        <el-pagination background layout="total,prev,pager,next" :total="pagination.total" :current-page="pagination.page" :page-size="pagination.per_page" @current-change="(p: number) => loadList(p)" />
      </div>
    </div>
    <el-dialog v-model="showFormDialog" title="新增出库单" width="1500px" :close-on-click-modal="false" top="3vh">
      <div class="section-card">
        <div class="section-title"><el-icon><Document /></el-icon> 基本信息</div>
        <el-form ref="formRef" :model="form" :rules="formRules" label-width="90px">
          <el-row :gutter="16">
            <el-col :span="8">
              <el-form-item label="单号">
                <el-input :model-value="autoRecordNo" disabled style="width:100%">
                  <template #prefix><el-icon><Document /></el-icon></template>
                </el-input>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="出库类型">
                <el-radio-group v-model="form.type">
                  <el-radio value="out">领用出库</el-radio>
                  <el-radio value="sale">销售出库</el-radio>
                  <el-radio value="scrap">报废出库</el-radio>
                </el-radio-group>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label-width="0">
                <el-row :gutter="8" style="width:100%">
                  <el-col :span="10">
                    <el-form-item label="快递">
                      <el-select v-model="form.logistics_company" filterable allow-create clearable placeholder="选公司" style="width:100%">
                        <el-option label="顺丰速运" value="顺丰速运" />
                        <el-option label="京东快递" value="京东快递" />
                        <el-option label="中通快递" value="中通快递" />
                        <el-option label="圆通速递" value="圆通速递" />
                        <el-option label="韵达快递" value="韵达快递" />
                        <el-option label="极兔速递" value="极兔速递" />
                        <el-option label="德邦快递" value="德邦快递" />
                        <el-option label="邮政EMS" value="邮政EMS" />
                        <el-option label="申通快递" value="申通快递" />
                      </el-select>
                    </el-form-item>
                  </el-col>
                  <el-col :span="14">
                    <el-form-item label-width="0">
                      <el-input v-model="form.logistics_no" placeholder="快递单号" clearable />
                    </el-form-item>
                  </el-col>
                </el-row>
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="16">
            <el-col :span="8">
              <el-form-item label="出库仓库" prop="warehouse_id">
                <el-select v-model="form.warehouse_id" placeholder="选择仓库" style="width:100%">
                  <el-option v-for="w in warehouseOptions" :key="w.id" :label="w.name" :value="w.id" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="往来单位" prop="party_id">
                <el-select v-model="form.party_id" filterable placeholder="选择客户" style="width:100%" @change="onPartyChange">
                  <el-option v-for="c in customerOptions" :key="c.id" :label="c.name" :value="c.id" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="结算单位">
                <el-select v-model="form.settle_id" filterable placeholder="默认同往来单位" style="width:100%" clearable>
                  <el-option v-for="c in customerOptions" :key="c.id" :label="c.name" :value="c.id" />
                </el-select>
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="16">
            <el-col :span="8">
              <el-form-item label="关联项目">
                <el-select v-model="form.project_id" filterable clearable placeholder="选择项目(可选)" style="width:100%">
                  <el-option v-for="p in projectOptions" :key="p.id" :label="p.name" :value="p.id" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="操作员">
                <el-input v-model="form.operator_name" disabled style="width:100%" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="备注">
                <el-input v-model="form.remark" maxlength="500" show-word-limit />
              </el-form-item>
            </el-col>
          </el-row>
        </el-form>
      </div>
      <div class="section-card" style="margin-top:16px">
        <div class="section-title" style="display:flex;align-items:center;justify-content:space-between">
          <span><el-icon><Goods /></el-icon> 出库明细 ({{ form.items.filter(r => r.item).length }} 种物料)</span>
          <div style="display:flex;gap:8px">
            <el-button size="small" type="success" :icon="FolderOpened" @click="openImportDialog()">从项目导入</el-button>
            <el-button size="small" type="warning" :icon="Plus" @click="openPickerBatch()">批量选择物料</el-button>
            <el-button size="small" :icon="Plus" plain @click="addItemRow()">单条添加</el-button>
          </div>
        </div>
        <el-table :data="form.items" stripe border style="width:100%" max-height="360">
          <el-table-column type="index" label="#" width="42" />
          <el-table-column label="编码" width="120">
            <template #default="{row}">
              <span v-if="row.item" class="item-code">{{ row.item.code }}</span>
              <span v-else class="text-muted">-</span>
            </template>
          </el-table-column>
          <el-table-column label="物料名称" min-width="160">
            <template #default="{row,$index}">
              <div style="display:flex;gap:4px;align-items:center">
                <span v-if="row.item" style="flex:1">{{ row.item.name }}</span>
                <el-button size="small" type="primary" link @click="openPicker($index)">{{ row.item?'更换':'选择物料' }}</el-button>
              </div>
            </template>
          </el-table-column>
          <el-table-column label="规格" width="120" show-overflow-tooltip>
            <template #default="{row}"><span v-if="row.item?.specification">{{ row.item.specification }}</span><span v-else class="text-muted">-</span></template>
          </el-table-column>
          <el-table-column label="单位" width="60" align="center">
            <template #default="{row}">{{ row.item?.unit||'-' }}</template>
          </el-table-column>
          <el-table-column label="库存" width="70" align="center">
            <template #default="{row}">
              <el-tag v-if="row.item" :type="(row.item.current_stock??0)<=0?'danger':(row.item.current_stock??0)<10?'warning':'success'" size="small" effect="plain">{{ row.item.current_stock??0 }}</el-tag>
              <span v-else class="text-muted">-</span>
            </template>
          </el-table-column>
          <el-table-column label="出库数量" width="120">
            <template #default="{row}">
              <el-input-number v-model="row.quantity" :min="1" :max="row.item?.current_stock||99999" :step="1" style="width:100px" size="small" @change="calcAmount(row)" />
            </template>
          </el-table-column>
          <el-table-column label="单价" width="110" v-if="form.type === 'sale'">
            <template #default="{row}">
              <el-input-number v-model="row.unit_price" :min="0" :precision="2" :step="10" style="width:100px" size="small" @change="calcAmount(row)" />
            </template>
          </el-table-column>
          <el-table-column label="金额" width="110" align="right" v-if="form.type === 'sale'">
            <template #default="{row}"><span style="font-weight:600;color:#0C447C">¥{{ Number(row.amount||0).toFixed(2) }}</span></template>
          </el-table-column>
          <el-table-column label="操作" width="55" align="center">
            <template #default="{_,$index}">
              <el-button type="danger" link size="small" :icon="Delete" @click="removeItemRow($index)" />
            </template>
          </el-table-column>
        </el-table>
        <div v-if="form.items.length===0" style="text-align:center;padding:24px;color:#c0c4cc">
          <el-empty :image-size="50" description="暂无物料，点击上方「添加物料」按钮" />
        </div>
        <!-- 付款方式 + 单据金额合计 (仅销售出库) -->
        <div v-if="form.type === 'sale'" style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;padding:12px;background:#f6f8fa;border-radius:6px;flex-wrap:wrap;gap:8px">
          <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
            <span style="font-size:14px;color:#606266">付款方式：</span>
            <el-radio-group v-model="form.payment_method">
              <el-radio value="cash">现金收款</el-radio>
              <el-radio value="receivable">应收账款</el-radio>
            </el-radio-group>
            <template v-if="form.payment_method === 'cash'">
              <span style="font-size:14px;color:#606266;margin-left:8px">收款账户：</span>
              <el-select v-model="form.account_id" filterable placeholder="选择收款账户" style="width:240px">
                <el-option v-for="a in accountOptions" :key="a.id" :label="`${a.name} (余额: ¥${(a.balance||0).toFixed(2)})`" :value="a.id" />
              </el-select>
            </template>
          </div>
          <div style="font-size:16px;font-weight:600">
            单据金额合计：<span style="color:#1D9E75;font-size:20px">¥{{ totalAmount.toFixed(2) }}</span>
          </div>
        </div>
      </div>
      <template #footer>
        <el-button @click="showFormDialog=false">取消</el-button>
        <el-button type="warning" :loading="submitting" @click="handleSubmit">确认出库</el-button>
      </template>
    </el-dialog>

    <!-- 从项目导入物料弹窗 -->
    <el-dialog v-model="importDialogVisible" title="从项目导入物料" width="800px" :close-on-click-modal="false">
      <div style="margin-bottom:12px;display:flex;gap:8px">
        <el-select v-model="importSelectedProject" filterable placeholder="选择项目" style="width:300px" @change="loadProjectMaterials">
          <el-option v-for="p in projectOptions" :key="p.id" :label="p.name" :value="p.id" />
        </el-select>
        <el-button :loading="importLoading" :disabled="!importSelectedProject" @click="loadProjectMaterials()">加载物料</el-button>
      </div>
      <div v-if="importLoading" style="text-align:center;padding:40px"><el-icon class="is-loading" :size="24"><Loading /></el-icon> 加载中...</div>
      <template v-else-if="importProjectMaterials.length > 0">
        <div style="margin-bottom:8px">
          <el-checkbox :indeterminate="importSelectedIds.length > 0 && importSelectedIds.length < importProjectMaterials.length" v-model="selectAll" @change="toggleAllMaterials">
            全选 ({{ importProjectMaterials.length }} 种物料)
          </el-checkbox>
        </div>
        <el-table :data="importProjectMaterials" stripe border style="width:100%" max-height="400" @selection-change="onImportSelectionChange">
          <el-table-column type="selection" width="42" />
          <el-table-column label="物料名称" min-width="160" prop="material_name" />
          <el-table-column label="规格" width="120" prop="specification" show-overflow-tooltip />
          <el-table-column label="数量" width="80" prop="quantity" />
          <el-table-column label="单位" width="60" prop="unit" />
          <el-table-column label="单价" width="100">
            <template #default="{row}">¥{{ Number(row.unit_cost||0).toFixed(2) }}</template>
          </el-table-column>
        </el-table>
      </template>
      <el-empty v-else-if="importSelectedProject && !importLoading" :image-size="50" description="该项目暂无物料清单" />
      <el-empty v-else :image-size="50" description="请先选择一个项目" />
      <template #footer>
        <el-button @click="importDialogVisible = false">取消</el-button>
        <el-button type="success" :disabled="importSelectedIds.length === 0" @click="confirmImportMaterials">导入物料 ({{ importSelectedIds.length }})</el-button>
      </template>
    </el-dialog>

    <InventoryItemPicker :show="pickerVisible" :multiple="true" :selected-ids="pickedItemIds" @select="(items: InventoryItem[]) => onPickerSelect(items)" @close="pickerVisible = false" />

    <!-- 出库单详情弹窗 -->
    <el-dialog v-model="showDetail" title="出库单详情" width="1440px" :close-on-click-modal="false">
      <template v-if="detailItem">
        <el-descriptions :column="4" border style="margin-bottom:16px">
          <el-descriptions-item label="单号" :span="2">{{ detailItem.record_no }}</el-descriptions-item>
          <el-descriptions-item label="出库类型">
            <el-tag :type="detailItem.type==='sale'?'primary':detailItem.type==='scrap'?'danger':'warning'" size="small">{{ typeLabel(detailItem.type) }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="操作人">{{ detailItem.operator?.name||'-' }}</el-descriptions-item>
          <el-descriptions-item label="仓库">{{ detailItem.warehouse?.name||'-' }}</el-descriptions-item>
          <el-descriptions-item label="往来单位">{{ detailItem.party?.name||'-' }}</el-descriptions-item>
          <el-descriptions-item label="关联项目">{{ detailItem.project?.name||'-' }}</el-descriptions-item>
          <el-descriptions-item label="出库总数"><span style="font-weight:600;color:#A32D2D">-{{ detailItem.total_quantity||0 }}</span></el-descriptions-item>
          <el-descriptions-item label="单据金额"><span style="font-weight:600;color:#1D9E75">¥{{ Number(detailItem.total_amount||0).toFixed(2) }}</span></el-descriptions-item>
          <el-descriptions-item label="付款方式">
            <el-tag :type="detailItem.payment_method==='receivable'?'warning':'success'" size="small">{{ detailItem.payment_method==='receivable'?'应收账款':'现金收款' }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="出库时间">{{ formatDate(detailItem.created_at) }}</el-descriptions-item>
          <el-descriptions-item label="备注" :span="4">{{ detailItem.remark||'-' }}</el-descriptions-item>
        </el-descriptions>
        <h4 style="margin:12px 0 8px;color:#0C447C">物料明细 ({{ detailItem.item_count||(detailItem.items?.length||0) }} 种)</h4>
        <el-table :data="detailItem.items||[]" stripe border style="width:100%">
          <el-table-column type="index" label="#" width="50" />
          <el-table-column label="物料编码" width="130">
            <template #default="{row}">{{ row.inventoryItem?.code||'-' }}</template>
          </el-table-column>
          <el-table-column label="物料名称" min-width="160">
            <template #default="{row}">{{ row.inventoryItem?.name||'-' }}</template>
          </el-table-column>
          <el-table-column label="规格" width="120">
            <template #default="{row}">{{ row.inventoryItem?.specification||'-' }}</template>
          </el-table-column>
          <el-table-column label="单位" width="60">
            <template #default="{row}">{{ row.inventoryItem?.unit||'-' }}</template>
          </el-table-column>
          <el-table-column label="数量" width="80" align="right">
            <template #default="{row}"><span style="font-weight:600;color:#A32D2D">-{{ row.quantity }}</span></template>
          </el-table-column>
          <el-table-column label="单价" width="100" align="right">
            <template #default="{row}">¥{{ Number(row.unit_cost||0).toFixed(2) }}</template>
          </el-table-column>
          <el-table-column label="金额" width="120" align="right">
            <template #default="{row}"><span style="font-weight:600;color:#1D9E75">¥{{ Number(row.total_amount||0).toFixed(2) }}</span></template>
          </el-table-column>
          <el-table-column label="出库后库存" width="120" align="right">
            <template #default="{row}">{{ row.remaining_stock }} {{ row.inventoryItem?.unit||'' }}</template>
          </el-table-column>
        </el-table>
      </template>
      <template #footer>
        <el-button @click="showDetail = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>
<script setup lang="ts">
import { ref, reactive, computed, onMounted } from "vue"
import { useRoute } from "vue-router"
import { Search, Plus, Document, Goods, Delete, FolderOpened, Loading } from "@element-plus/icons-vue"
import { ElMessage } from "element-plus"
import { unwrapPaginate, unwrapList } from "@/utils/response"
import { get, post } from "@/utils/request"
import InventoryItemPicker from "./components/InventoryItemPicker.vue"
import type { OutboundRecord, InventoryItem, WarehouseOption } from './types'

interface BusinessOption { id: number; name: string }

const filterType = ref<string>('')
const dateRange = ref<[string, string] | null>(null)
const searchKey = ref('')
const list = ref<OutboundRecord[]>([])
const loading = ref(false)
const pagination = reactive({ page: 1, per_page: 15, total: 0 })
const itemOptions = ref<InventoryItem[]>([])
const warehouseOptions = ref<WarehouseOption[]>([])
const customerOptions = ref<BusinessOption[]>([])
const projectOptions = ref<BusinessOption[]>([])
function formatDate(s?: string | null) {
  if (!s) return "-"
  const d = new Date(s); if (isNaN(d.getTime())) return s
  const pad = (n: number) => n.toString().padStart(2,"0")
  return d.getFullYear()+"-"+pad(d.getMonth()+1)+"-"+pad(d.getDate())+" "+pad(d.getHours())+":"+pad(d.getMinutes())
}
function typeLabel(t: string) { return ({ outbound: "领用", out: "领用", sale: "销售", scrap: "报废" } as Record<string, string>)[t] || t }
// V1.2.14p: 单号在 handleCreate 时生成到 form.record_no
const autoRecordNo = computed(() => form.record_no || "（保存后生成）")
async function loadList(page=1) {
  pagination.page = page; loading.value = true
  try {
    // V1.2.14p: 后端按 record_no 聚合, 直接返回整单列表; 出库时传 type='out'
    const params: Record<string, unknown> = { page, per_page: pagination.per_page }
    if (filterType.value === 'sale') params.type = 'sale'
    else if (filterType.value === 'scrap') params.type = 'scrap'
    else if (filterType.value === 'out') params.type = 'out'
    else if (filterType.value) params.type = filterType.value
    const res = await get("/inventory/stock-records", params)
    const pag = unwrapPaginate(res)
    let items = pag.list
    if (searchKey.value) {
      const kw = searchKey.value.toLowerCase()
      items = items.filter(r=>(r.record_no||"").toLowerCase().includes(kw)||(r.party?.name||"").toLowerCase().includes(kw)||(r.operator?.name||"").toLowerCase().includes(kw))
    }
    if (dateRange.value) {
      const [from,to] = dateRange.value
      items = items.filter(r=>{const t=(r.created_at||"").slice(0,10); return t>=from&&t<=to})
    }
    list.value = items; pagination.total = pag.total
  } catch(e) { console.error(e); list.value=[]; pagination.total=0 }
  finally { loading.value=false }
}
async function loadItems() {
  // V1.2.7m: InventoryItemPicker 自带按需拉取
}
async function loadWarehouses() {
  try { const res=await get("/inventory/warehouses"); warehouseOptions.value=res.data||res||[] }
  catch(e){ console.warn(e) }
}
async function loadCustomers() {
  try {
    const res=await get("/customers",{per_page:500}); const d=res||{}
    customerOptions.value=d.data?.data||d.data?.items||d.data||d.items||d||[]
  } catch(e){ console.warn(e) }
}
async function loadProjects() {
  try {
    const res=await get("/projects",{per_page:500}); const d=res||{}
    projectOptions.value=d.data?.data||d.data?.items||d.data||d.items||d||[]
  } catch(e){ console.warn(e) }
}
async function loadAccounts() {
  try {
    const res=await get("/finance/accounts",{per_page:500})
    const list=unwrapList(res)
    accountOptions.value=list.map((a:Record<string,unknown>)=>({id:Number(a.id),name:String(a.name||''),balance:Number(a.balance||0)}))
  } catch(e){ console.warn(e) }
}
const showFormDialog = ref(false)
const showDetail = ref(false)
const detailItem = ref<OutboundRecord | null>(null)
const formRef = ref()
const submitting = ref(false)
const pickerVisible = ref(false)
const pickerIndex = ref(-1)
// V1.2.14p: 从项目导入物料
const importDialogVisible = ref(false)
const importProjectMaterials = ref<{ id: number; material_name: string; specification: string; quantity: number; unit: string; unit_cost: number; inventory_item_id?: number }[]>([])
const importLoading = ref(false)
const importSelectedProject = ref<number | null>(null)
const importSelectedProjectName = ref('')
const importSelectedIds = ref<number[]>([])
const selectAll = ref(false)
const route = useRoute()
/** 已选物料 id 列表 (用于 picker 回显勾选状态) */
const pickedItemIds = computed(() => form.items.filter(r => r.item).map(r => r.item!.id))
const form = reactive({
  type: "out",
  warehouse_id: null as number | null,
  logistics_company: "",
  logistics_no: "",
  party_type: "customer",
  party_id: null as number | null,
  settle_id: null as number | null,
  project_id: null as number | null,
  payment_method: "cash" as "cash" | "receivable",
  account_id: null as number | null,
  record_no: "",
  batch_no: "",
  operator_name: "",
  remark: "",
  items: [] as Array<{ uid?: number; item: InventoryItem | null; quantity: number; unit_price?: number; amount?: number }>,
})
const accountOptions = ref<{ id: number; name: string; balance: number }[]>([])
const formRules = {
  warehouse_id: [{ required:true, message:"请选择仓库", trigger:"change" }],
  party_id: [{ required:true, message:"请选择往来单位", trigger:"change" }],
}
function onPartyChange(v: number) { if (!form.settle_id) form.settle_id = v }
function addItemRow() {
  form.items.push({ item: null, quantity: 1, unit_price: 0, amount: 0 })
  pickerIndex.value = form.items.length - 1
  pickerVisible.value = true
}
function calcAmount(row: { quantity: number; unit_price?: number; amount?: number }) {
  row.amount = Number(row.quantity || 0) * Number(row.unit_price || 0)
}
const totalAmount = computed(() => form.items.reduce((s, r) => s + Number(r.amount || 0), 0))
function removeItemRow(idx: number) {
  form.items.splice(idx, 1)
}
function openPicker(idx: number) {
  pickerIndex.value = idx
  pickerVisible.value = true
}
function openPickerBatch() {
  pickerIndex.value = form.items.length
  pickerVisible.value = true
}
function onPickerSelect(items: InventoryItem[]) {
  // V1.2.9 多选模式: 一次性加入所有选中物料
  if (!items || !items.length) return
  let idx = pickerIndex.value
  // 截掉从 idx 开始的空行
  while (idx < form.items.length && !form.items[idx]?.item) {
    form.items.splice(idx, 1)
  }
  // 去重
  for (const it of items) {
    const existingIdx = form.items.findIndex(r => r.item?.id === it.id)
    if (existingIdx >= 0) form.items.splice(existingIdx, 1)
  }
  // 逐行追加
  for (const it of items) {
    form.items.push({ item: { ...it }, quantity: Math.min(1, it.current_stock || 1) })
  }
  pickerIndex.value = -1
}

// V1.2.14p: 从项目导入物料
async function openImportDialog() {
  importSelectedProject.value = form.project_id
  importProjectMaterials.value = []
  importSelectedIds.value = []
  selectAll.value = false
  importDialogVisible.value = true
  if (importSelectedProject.value) await loadProjectMaterials()
}
function toggleAllMaterials(v: boolean) {
  importSelectedIds.value = v ? importProjectMaterials.value.map(m => m.id) : []
  selectAll.value = v
}
function onImportSelectionChange(sel: { id: number }[]) {
  importSelectedIds.value = sel.map(s => s.id)
}
async function loadProjectMaterials() {
  if (!importSelectedProject.value) return
  importLoading.value = true
  importProjectMaterials.value = []
  try {
    const res = await get(`/projects/${importSelectedProject.value}/materials`)
    const raw = res?.data?.data ?? res?.data ?? []
    importProjectMaterials.value = raw
    importSelectedIds.value = []
    selectAll.value = false
  } catch (e) {
    console.warn(e)
    ElMessage.error('加载项目物料失败')
  } finally {
    importLoading.value = false
  }
}
function confirmImportMaterials() {
  const selected = importProjectMaterials.value.filter(m => importSelectedIds.value.includes(m.id))
  for (const m of selected) {
    form.items.push({
      item: {
        id: m.inventory_item_id || undefined,
        name: m.material_name,
        code: '',
        specification: m.specification || '',
        unit: m.unit,
        current_stock: 0,
      } as InventoryItem,
      quantity: Number(m.quantity) || 1,
      unit_price: Number(m.unit_cost) || 0,
      amount: (Number(m.quantity) || 1) * (Number(m.unit_cost) || 0),
    })
  }
  importDialogVisible.value = false
  ElMessage.success(`已导入 ${selected.length} 种物料`)
}

function handleCreate(prefill?: { customer_id?: number; customer_name?: string; project_id?: number; project_name?: string }) {
  form.type = "out"
  form.warehouse_id = warehouseOptions.value[0]?.id||null
  form.party_id = null
  form.settle_id = null
  form.project_id = null
  form.payment_method = "cash"
  form.account_id = null
  form.remark = ""
  form.logistics_no = ""
  // V1.2.14p: 弹窗打开时立刻生成预览单号
  const d = new Date()
  const ymd = `${d.getFullYear()}${String(d.getMonth()+1).padStart(2,'0')}${String(d.getDate()).padStart(2,'0')}`
  form.record_no = `OUT-${ymd}-预生成`
  form.batch_no = `${ymd}-${Date.now().toString(36).toUpperCase()}`
  // 操作员默认为当前用户
  try {
    const stored = localStorage.getItem('oa_user_info')
    if (stored) {
      const info = JSON.parse(stored)
      form.operator_name = info.name || info.username || ''
    }
  } catch { form.operator_name = '' }
  form.items = [{ item: null, quantity: 1, unit_price: 0, amount: 0 }]
  showFormDialog.value = true
  // 预填客户
  if (prefill?.customer_id) {
    form.party_id = prefill.customer_id
    form.settle_id = prefill.customer_id
  } else if (prefill?.customer_name && customerOptions.value.length) {
    const match = customerOptions.value.find(c => c.name === prefill.customer_name)
    if (match) { form.party_id = match.id; form.settle_id = match.id }
  }
  // 预填项目
  if (prefill?.project_id) {
    form.project_id = prefill.project_id
  } else if (prefill?.project_name && projectOptions.value.length) {
    const match = projectOptions.value.find(p => p.name === prefill.project_name)
    if (match) form.project_id = match.id
  }
  // 预填备注
  if (prefill?.project_name) form.remark = `${prefill.project_name} 项目出库`
}
async function handleSubmit() {
  await formRef.value.validate()
  const validItems = form.items.filter(i=>i.item)
  if (validItems.length === 0) { ElMessage.warning("请至少选择一种物料"); return }
  submitting.value = true
  try {
    // V1.2.14p: 一次提交所有物料, 后端生成共享 record_no
    const payload = {
      items: validItems.map(row => ({
        item_id: row.item!.id,
        quantity: row.quantity,
        unit_price: row.unit_price || 0,
        total_amount: row.amount || 0,
      })),
      warehouse_id: form.warehouse_id,
      type: form.type,
      logistics_company: form.logistics_company || null,
      logistics_no: form.logistics_no || null,
      party_type: form.party_type,
      party_id: form.party_id,
      settle_id: form.settle_id || form.party_id,
      project_id: form.project_id,
      payment_method: form.payment_method,
      account_id: form.payment_method === 'cash' ? form.account_id : null,
      batch_no: form.batch_no,
      remark: form.remark,
    }
    const r = await post("/inventory/stock-out", payload)
    const recordNo = r?.data?.record_no || r?.record_no || ""
    ElMessage.success(`出库成功, 单号: ${recordNo} (${validItems.length} 项物料)`)
    showFormDialog.value = false
    loadList(pagination.page)
  } catch(e: unknown) {
    const err = e as { response?: { data?: { message?: string } }; message?: string }
    ElMessage.error(err?.response?.data?.message || err?.message || "出库失败")
  } finally {
    submitting.value = false
  }
}
async function openDetail(recordNo: string) {
  detailItem.value = null
  showDetail.value = true
  try {
    const res = await get(`/inventory/stock-records/${recordNo}`)
    detailItem.value = (res?.data ?? res) as OutboundRecord
  } catch(e) { console.error(e); showDetail.value = false; ElMessage.error('加载详情失败') }
}

onMounted(()=>{
  loadList(1); loadItems(); loadWarehouses(); loadCustomers(); loadProjects(); loadAccounts()
  // V1.2.12i: 从项目池 "转为出库单" 跳来时自动打开新增对话框并预填
  const q = route.query as Record<string, string>
  if (q.action === 'create') {
    const timer = setInterval(() => {
      if (warehouseOptions.value.length > 0 && customerOptions.value.length > 0) {
        clearInterval(timer)
        handleCreate({
          customer_id: q.customer_id ? Number(q.customer_id) : undefined,
          customer_name: q.customer_name,
          project_id: q.project_id ? Number(q.project_id) : undefined,
          project_name: q.project_name,
        })
      }
    }, 100)
    setTimeout(() => clearInterval(timer), 5000)
  }
})
</script>
<style scoped>
.page-container { padding:20px; background:#f5f7fa; min-height:100vh }
.page-header { margin-bottom:16px }
.page-header h2 { font-size:20px; color:#0C447C; margin:0 }
.filter-bar { display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:16px; padding:16px; background:#fff; border-radius:8px; box-shadow:0 1px 4px rgba(0,0,0,.06) }
.content-card { background:#fff; border-radius:8px; padding:16px; box-shadow:0 1px 4px rgba(0,0,0,.06) }
.pagination-wrap { display:flex; justify-content:flex-end; margin-top:16px }
.muted { color:#c0c4cc }
.unit-text { color:#909399; font-size:12px }
.record-no { font-family:"DIN Pro",monospace; font-weight:500; color:#0C447C }
.section-card { background:#fff; border:1px solid #e8ecf1; border-radius:8px; padding:16px 16px 0 }
.section-title { font-size:14px; font-weight:600; color:#0C447C; margin-bottom:12px; padding-bottom:8px; border-bottom:2px solid #e6f1fb; display:flex; align-items:center; gap:6px }
.section-title .el-icon { font-size:16px }
.item-code { font-family:"DIN Pro",monospace; font-weight:500; color:#0C447C; font-size:12px }
:deep(.el-dialog__body) { padding-top:12px }
</style>
