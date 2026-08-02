<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">推荐人</span>
      <div class="header-actions">
        <el-button type="primary" :icon="Plus" @click="handleAdd">新增推荐人</el-button>
      </div>
    </div>

    <div class="content-card">
      <el-table :data="pagedList" stripe border v-loading="loading" :header-cell-style="{ background: '#f5f7fa', color: '#303133', fontWeight: 600 }">
        <el-table-column type="index" label="#" width="60" align="center" />
        <el-table-column prop="name" label="姓名" width="120" fixed />
        <el-table-column prop="phone" label="电话" width="140" />
        <el-table-column label="关联老客户" min-width="160" show-overflow-tooltip>
          <template #default="{ row }">{{ row.customer?.name || '-' }}</template>
        </el-table-column>
        <el-table-column prop="bank_name" label="开户行" width="180" show-overflow-tooltip />
        <el-table-column prop="bank_account" label="账号" width="200" show-overflow-tooltip />
        <el-table-column prop="commission_rate" label="居间费比例" width="120" align="right">
          <template #default="{ row }">
            <el-tag type="warning" effect="plain">{{ row.commission_rate }}%</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="total_commission" label="累计居间费" width="140" align="right">
          <template #default="{ row }">
            <span style="color: #1D9E75; font-weight: 600">¥ {{ formatMoney(row.total_commission) }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="notes" label="备注" min-width="200" show-overflow-tooltip />
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <el-button link type="warning" @click="handleEdit(row)">编辑</el-button>
            <el-button link type="danger" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrapper">
        <el-pagination v-model:current-page="page" v-model:page-size="pageSize" :page-sizes="[10, 20, 50]" :total="total" layout="total, sizes, prev, pager, next, jumper" @size-change="loadList" @current-change="loadList" />
      </div>
    </div>

    <!-- 新增/编辑推荐人 -->
    <el-dialog v-model="showFormDialog" :title="formMode === 'create' ? '新增推荐人' : '编辑推荐人'" width="1500px" :close-on-click-modal="false">
      <el-form ref="formRef" :model="formData" :rules="formRules" label-width="100px">
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="姓名" prop="name">
              <el-input v-model="formData.name" placeholder="推荐人姓名" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="联系电话" prop="phone">
              <el-input v-model="formData.phone" placeholder="手机号" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="关联老客户" prop="customer_id">
          <el-select v-model="formData.customer_id" placeholder="请选择（可选）" clearable filterable style="width: 100%">
            <el-option v-for="c in customerOptions" :key="c.id" :label="c.name" :value="c.id" />
          </el-select>
        </el-form-item>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="开户行">
              <el-input v-model="formData.bank_name" placeholder="如：中国工商银行" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="银行账号">
              <el-input v-model="formData.bank_account" placeholder="账号" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="居间费比例" prop="commission_rate">
          <el-input-number v-model="formData.commission_rate" :min="0" :max="30" :step="0.5" :precision="2" style="width: 200px" />
          <span style="margin-left: 8px; color: #909399; font-size: 12px">% (1-30%)</span>
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="formData.notes" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showFormDialog = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleSave">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, reactive } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { getReferrers, createReferrer, updateReferrer, deleteReferrer, getCustomerOptions } from '@/api/sales'
import type { Referrer, CustomerOption } from './types'

const loading = ref(false)
const submitting = ref(false)
const page = ref(1)
const pageSize = ref(10)
const total = ref(0)
const list = ref<Referrer[]>([])

const pagedList = computed(() => list.value)

const loadList = async () => {
  loading.value = true
  try {
    const r = await getReferrers({ page: page.value, per_page: pageSize.value })
    // V0.6.3: res = {code, data: paginator}
    const d = r?.data ?? {}
    list.value = Array.isArray(d?.data) ? d.data : []
    total.value = d?.total || 0
  } catch (e) { /* toast */ }
  finally { loading.value = false }
}

const customerOptions = ref<CustomerOption[]>([])
const loadCustomers = async () => {
  try {
    const r = await getCustomerOptions({ per_page: 200 })
    // V0.6.3: res = {code, data: <customers>}
    const d = r?.data ?? {}
    customerOptions.value = Array.isArray(d) ? d : (Array.isArray(d?.data) ? d.data : [])
  } catch (e) { customerOptions.value = [] }
}

const showFormDialog = ref(false)
const formMode = ref<'create' | 'edit'>('create')
const formRef = ref()
const formData = reactive({
  id: 0,
  name: '',
  phone: '',
  customer_id: null as number | null,
  bank_name: '',
  bank_account: '',
  commission_rate: 5,
  notes: ''
})
const formRules = {
  name: [{ required: true, message: '请输入姓名', trigger: 'blur' }],
  phone: [{ required: true, message: '请输入联系电话', trigger: 'blur' }],
  commission_rate: [{ required: true, message: '请输入居间费比例', trigger: 'blur' }]
}

const resetForm = () => {
  Object.assign(formData, {
    id: 0, name: '', phone: '', customer_id: null,
    bank_name: '', bank_account: '', commission_rate: 5, notes: ''
  })
}

const handleAdd = () => { formMode.value = 'create'; resetForm(); showFormDialog.value = true }

const handleEdit = (row: Referrer) => {
  formMode.value = 'edit'
  Object.assign(formData, {
    id: row.id,
    name: row.name || '',
    phone: row.phone || '',
    customer_id: row.customer_id || null,
    bank_name: row.bank_name || '',
    bank_account: row.bank_account || '',
    commission_rate: Number(row.commission_rate || 0),
    notes: row.notes || ''
  })
  showFormDialog.value = true
}

const handleSave = async () => {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return
  submitting.value = true
  try {
    const payload: Record<string, unknown> = { ...formData }
    delete payload.id
    if (formMode.value === 'create') {
      await createReferrer(payload)
      ElMessage.success('推荐人新增成功')
    } else {
      await updateReferrer(formData.id, payload)
      ElMessage.success('推荐人已更新')
    }
    showFormDialog.value = false
    await loadList()
  } catch (e) { /* toast */ }
  finally { submitting.value = false }
}

const handleDelete = async (row: Referrer) => {
  try {
    await ElMessageBox.confirm(`确认删除推荐人「${row.name}」？已关联商机的推荐人不可删除。`, '删除', { type: 'warning' })
  } catch { return }
  try {
    await deleteReferrer(row.id)
    ElMessage.success('推荐人已删除')
    if (list.value.length === 1 && page.value > 1) page.value -= 1
    await loadList()
  } catch (e) { /* toast */ }
}

const formatMoney = (n: number) => Number(n || 0).toLocaleString('zh-CN', { maximumFractionDigits: 2 })

onMounted(() => { loadCustomers(); loadList() })
</script>

<style lang="scss" scoped>
.page-container { padding: 16px; background: #f5f7fa; min-height: calc(100vh - 60px); }
.page-header {
  display: flex; justify-content: space-between; align-items: center;
  background: #fff; padding: 16px 20px; border-radius: 8px; margin-bottom: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  .page-title { font-size: 18px; font-weight: 600; color: #0C447C; border-left: 4px solid #0C447C; padding-left: 10px; }
  .header-actions { display: flex; gap: 8px; }
}
.content-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04); }
.pagination-wrapper { margin-top: 16px; display: flex; justify-content: flex-end; }
</style>
