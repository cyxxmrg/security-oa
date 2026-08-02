<template>
  <div class="page-container">
    <div class="page-header">
      <div class="page-title-row">
        <span class="page-title">期初数据</span>
        <div class="page-actions">
          <el-tag v-if="locked" type="warning" effect="dark">已锁定</el-tag>
          <el-tag v-else type="success" effect="plain">未锁定</el-tag>
          <el-button v-if="!locked" type="danger" plain @click="handleLock" style="margin-left:12px">
            <el-icon><Lock /></el-icon> 锁定期初数据
          </el-button>
          <el-button v-if="locked && isSystem" type="info" plain @click="handleUnlock">
            <el-icon><Unlock /></el-icon> 解锁
          </el-button>
        </div>
      </div>
      <p class="page-desc">
        期初数据仅可在系统初始化时录入，<strong>锁定后不可手动增删改</strong>，须通过业务单据产生。
      </p>
    </div>

    <!-- 锁定时遮罩提示 -->
    <div v-if="locked" class="locked-banner">
      <el-icon :size="18"><WarningFilled /></el-icon>
      <span>期初数据已锁定，如需修改请联系 system 管理员解锁</span>
    </div>

    <el-tabs v-model="activeTab" type="border-card">
      <!-- 应收期初 -->
      <el-tab-pane label="应收款期初" name="receivable">
        <div class="tab-toolbar">
          <el-button :disabled="locked" type="primary" @click="showReceivableDialog = true">
            <el-icon><Plus /></el-icon> 新增应收期初
          </el-button>
          <span class="tab-hint">共 {{ receivableTotal }} 条</span>
        </div>
        <el-table :data="receivables" v-loading="loading" border stripe style="width:100%">
          <el-table-column prop="id" label="ID" width="60" />
          <el-table-column prop="customer.name" label="客户" min-width="140" />
          <el-table-column label="金额" width="120">
            <template #default="{ row }">¥{{ row.amount?.toFixed(2) }}</template>
          </el-table-column>
          <el-table-column label="已收" width="120">
            <template #default="{ row }">¥{{ row.received_amount?.toFixed(2) }}</template>
          </el-table-column>
          <el-table-column label="剩余" width="120">
            <template #default="{ row }">¥{{ row.remaining_amount?.toFixed(2) }}</template>
          </el-table-column>
          <el-table-column prop="due_date" label="到期日" width="110" />
          <el-table-column prop="notes" label="备注" min-width="140" />
          <el-table-column label="操作" width="80" fixed="right">
            <template #default="{ row }">
              <el-button :disabled="locked" type="danger" link @click="handleDeleteReceivable(row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- 应付期初 -->
      <el-tab-pane label="应付款期初" name="payable">
        <div class="tab-toolbar">
          <el-button :disabled="locked" type="primary" @click="showPayableDialog = true">
            <el-icon><Plus /></el-icon> 新增应付期初
          </el-button>
          <span class="tab-hint">共 {{ payableTotal }} 条</span>
        </div>
        <el-table :data="payables" v-loading="loading" border stripe style="width:100%">
          <el-table-column prop="id" label="ID" width="60" />
          <el-table-column prop="supplier.name" label="供应商" min-width="140" />
          <el-table-column label="金额" width="120">
            <template #default="{ row }">¥{{ row.amount?.toFixed(2) }}</template>
          </el-table-column>
          <el-table-column label="已付" width="120">
            <template #default="{ row }">¥{{ row.paid_amount?.toFixed(2) }}</template>
          </el-table-column>
          <el-table-column label="剩余" width="120">
            <template #default="{ row }">¥{{ row.remaining_amount?.toFixed(2) }}</template>
          </el-table-column>
          <el-table-column prop="due_date" label="到期日" width="110" />
          <el-table-column prop="notes" label="备注" min-width="140" />
          <el-table-column label="操作" width="80" fixed="right">
            <template #default="{ row }">
              <el-button :disabled="locked" type="danger" link @click="handleDeletePayable(row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>
    </el-tabs>

    <!-- 新增应收弹窗 -->
    <el-dialog v-model="showReceivableDialog" title="新增应收期初" width="600px" :close-on-click-modal="false" @closed="receivableForm = {} as any">
      <el-form :model="receivableForm" label-width="100px">
        <el-form-item label="客户" required>
          <el-select v-model="receivableForm.customer_id" filterable style="width:100%" placeholder="选择客户">
            <el-option v-for="c in customerOptions" :key="c.id" :label="c.name" :value="c.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="金额" required>
          <el-input-number v-model="receivableForm.amount" :min="0" :precision="2" style="width:100%" />
        </el-form-item>
        <el-form-item label="到期日" required>
          <el-date-picker v-model="receivableForm.due_date" type="date" style="width:100%" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="receivableForm.notes" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showReceivableDialog = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="handleSaveReceivable">保存</el-button>
      </template>
    </el-dialog>

    <!-- 新增应付弹窗 -->
    <el-dialog v-model="showPayableDialog" title="新增应付期初" width="600px" :close-on-click-modal="false" @closed="payableForm = {} as any">
      <el-form :model="payableForm" label-width="100px">
        <el-form-item label="供应商" required>
          <el-select v-model="payableForm.supplier_id" filterable style="width:100%" placeholder="选择供应商">
            <el-option v-for="s in supplierOptions" :key="s.id" :label="s.name" :value="s.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="金额" required>
          <el-input-number v-model="payableForm.amount" :min="0" :precision="2" style="width:100%" />
        </el-form-item>
        <el-form-item label="到期日" required>
          <el-date-picker v-model="payableForm.due_date" type="date" style="width:100%" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="payableForm.notes" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showPayableDialog = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="handleSavePayable">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Lock, Unlock, WarningFilled } from '@element-plus/icons-vue'
import { get, post, del } from '@/utils/request'
import { useUserStore } from '@/stores/user'
import { unwrapList } from '@/utils/response'

const userStore = useUserStore()
const isSystem = computed(() => userStore.userInfo?.user_type === 'system')

const activeTab = ref('receivable')
const locked = ref(false)
const loading = ref(false)
const saving = ref(false)
const receivables = ref<any[]>([])
const payables = ref<any[]>([])
const receivableTotal = ref(0)
const payableTotal = ref(0)
const customerOptions = ref<any[]>([])
const supplierOptions = ref<any[]>([])
const showReceivableDialog = ref(false)
const showPayableDialog = ref(false)
const receivableForm = ref<any>({})
const payableForm = ref<any>({})

async function loadStatus() {
  try {
    const r = await get('/settings/opening-balances/status')
    locked.value = r?.locked ?? false
  } catch { /* ignore */ }
}

async function loadReceivables() {
  loading.value = true
  try {
    const r = await get('/opening/receivables', { per_page: 200 })
    const d = unwrapList(r) || []
    receivables.value = d
    receivableTotal.value = d.length
  } finally { loading.value = false }
}

async function loadPayables() {
  loading.value = true
  try {
    const r = await get('/opening/payables', { per_page: 200 })
    const d = unwrapList(r) || []
    payables.value = d
    payableTotal.value = d.length
  } finally { loading.value = false }
}

async function loadOptions() {
  try {
    const c = await get('/customers', { per_page: 500 })
    customerOptions.value = unwrapList(c) || []
  } catch { /* ignore */ }
  try {
    const s = await get('/suppliers', { per_page: 500 })
    supplierOptions.value = unwrapList(s) || []
  } catch { /* ignore */ }
}

async function handleSaveReceivable() {
  saving.value = true
  try {
    await post('/opening/receivables', receivableForm.value)
    ElMessage.success('应收期初已创建')
    showReceivableDialog.value = false
    loadReceivables()
  } catch (e: any) {
    ElMessage.error(e?.message || '保存失败')
  } finally { saving.value = false }
}

async function handleSavePayable() {
  saving.value = true
  try {
    await post('/opening/payables', payableForm.value)
    ElMessage.success('应付期初已创建')
    showPayableDialog.value = false
    loadPayables()
  } catch (e: any) {
    ElMessage.error(e?.message || '保存失败')
  } finally { saving.value = false }
}

async function handleDeleteReceivable(row: any) {
  try {
    await ElMessageBox.confirm('确定删除此应收期初？', '确认')
    await del(`/opening/receivables/${row.id}`)
    ElMessage.success('已删除')
    loadReceivables()
  } catch { /* cancel */ }
}

async function handleDeletePayable(row: any) {
  try {
    await ElMessageBox.confirm('确定删除此应付期初？', '确认')
    await del(`/opening/payables/${row.id}`)
    ElMessage.success('已删除')
    loadPayables()
  } catch { /* cancel */ }
}

async function handleLock() {
  try {
    await ElMessageBox.confirm(
      '锁定期初后，应收/应付/库存将无法手动增删改。确定锁定？', '确认锁定',
      { type: 'warning', confirmButtonText: '确定锁定', cancelButtonText: '取消' }
    )
    await post('/settings/opening-balances/lock')
    ElMessage.success('期初数据已锁定')
    loadStatus()
    loadReceivables()
    loadPayables()
  } catch { /* cancel */ }
}

async function handleUnlock() {
  try {
    await ElMessageBox.confirm('确定解锁期初数据？', '确认解锁', { type: 'warning' })
    await post('/settings/opening-balances/unlock')
    ElMessage.success('期初数据已解锁')
    loadStatus()
  } catch { /* cancel */ }
}

onMounted(() => {
  loadStatus()
  loadReceivables()
  loadPayables()
  loadOptions()
})
</script>

<style scoped>
.page-container { padding: 20px; }
.page-header { margin-bottom: 20px; }
.page-title-row { display: flex; align-items: center; justify-content: space-between; }
.page-title { font-size: 20px; font-weight: 600; color: #303133; }
.page-actions { display: flex; align-items: center; gap: 8px; }
.page-desc { margin-top: 6px; font-size: 13px; color: #909399; }
.locked-banner {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 16px; background: #fdf6ec; border: 1px solid #e6a23c;
  border-radius: 8px; margin-bottom: 16px; color: #e6a23c; font-size: 14px;
}
.tab-toolbar { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
.tab-hint { font-size: 12px; color: #909399; }
</style>
