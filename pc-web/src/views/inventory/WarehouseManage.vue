<template>
  <div class="page-container">
    <div class="page-header"><h2>仓库管理</h2></div>

    <div class="content-card">
      <div class="toolbar-wrap">
        <el-button type="primary" plain :icon="Plus" @click="openForm()">新增仓库</el-button>
      </div>

      <el-table v-loading="loading" :data="list" stripe border style="width:100%">
        <el-table-column type="index" label="#" width="50" />
        <el-table-column prop="name" label="仓库名称" min-width="140" />
        <el-table-column prop="code" label="编码" width="110" />
        <el-table-column label="类型" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="row.type==='main'?'primary':row.type==='project'?'warning':'info'" size="small">
              {{ row.type==='main'?'主仓库':row.type==='project'?'项目仓库':'售后仓库' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="80" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status==='active'?'success':'info'" size="small">
              {{ row.status==='active'?'启用':'停用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="物料数" width="80" align="center" prop="item_count" />
        <el-table-column label="库存量" width="100" align="right" prop="total_stock_qty" />
        <el-table-column label="库存金额" width="140" align="right">
          <template #default="{ row }"><span style="font-weight:600">¥{{ Number(row.total_value||0).toLocaleString('zh-CN',{minimumFractionDigits:2}) }}</span></template>
        </el-table-column>
        <el-table-column prop="address" label="地址" min-width="180" show-overflow-tooltip />
        <el-table-column label="负责人" width="120">
          <template #default="{ row }">{{ row.manager?.name || '-' }}</template>
        </el-table-column>
        <el-table-column prop="description" label="备注" min-width="160" show-overflow-tooltip />
        <el-table-column label="操作" width="120" fixed="right">
          <template #default="{ row, $index }">
            <el-button link type="primary" size="small" @click="openForm(row, $index)">编辑</el-button>
            <el-button link type="danger" size="small" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <!-- 新增/编辑弹窗 -->
    <el-dialog v-model="formVisible" :title="formIndex>=0?'编辑仓库':'新增仓库'" width="600px" :close-on-click-modal="false">
      <el-form ref="formRef" :model="form" :rules="formRules" label-width="90px">
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="仓库名称" prop="name">
              <el-input v-model="form.name" maxlength="100" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="编码" prop="code">
              <el-input v-model="form.code" maxlength="50" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="类型" prop="type">
              <el-select v-model="form.type" style="width:100%">
                <el-option label="主仓库" value="main" />
                <el-option label="项目仓库" value="project" />
                <el-option label="售后仓库" value="aftermarket" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="状态" prop="status">
              <el-select v-model="form.status" style="width:100%">
                <el-option label="启用" value="active" />
                <el-option label="停用" value="inactive" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="地址" prop="address">
          <el-input v-model="form.address" maxlength="255" />
        </el-form-item>
        <el-form-item label="备注" prop="description">
          <el-input v-model="form.description" type="textarea" :rows="2" maxlength="500" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="formVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="handleSave">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { Plus } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { get, post, del } from '@/utils/request'
import { unwrapList } from '@/utils/response'

interface WarehouseItem {
  id: number; name: string; code: string; type: string
  address?: string; manager?: { name?: string } | null
  description?: string; status?: string
  item_count?: number; total_stock_qty?: number; total_value?: number
}

const loading = ref(false)
const list = ref<WarehouseItem[]>([])
const formVisible = ref(false)
const formIndex = ref(-1)
const saving = ref(false)
const formRef = ref<any>(null)

const form = reactive({
  name: '', code: '', type: 'main', status: 'active', address: '', description: '',
})

const formRules = {
  name: [{ required: true, message: '请输入仓库名称', trigger: 'blur' }],
  code: [{ required: true, message: '请输入仓库编码', trigger: 'blur' }],
}

async function loadList() {
  loading.value = true
  try {
    const res = await get('/inventory/warehouses')
    list.value = unwrapList(res) as unknown as WarehouseItem[]
  } catch (e) {
    console.error(e)
    list.value = []
  } finally {
    loading.value = false
  }
}

function openForm(row?: WarehouseItem, idx?: number) {
  formIndex.value = idx ?? -1
  form.name = row?.name || ''
  form.code = row?.code || ''
  form.type = row?.type || 'main'
  form.status = row?.status || 'active'
  form.address = row?.address || ''
  form.description = row?.description || ''
  formVisible.value = true
}

async function handleSave() {
  if (!formRef.value) return
  await formRef.value.validate()
  saving.value = true
  try {
    if (formIndex.value >= 0) {
      const row = list.value[formIndex.value]
      await post(`/inventory/warehouses/${row.id}?_method=PUT`, form)
      ElMessage.success('仓库已更新')
    } else {
      await post('/inventory/warehouses', form)
      ElMessage.success('仓库已创建')
    }
    formVisible.value = false
    loadList()
  } catch (e: any) {
    ElMessage.error(e?.response?.data?.message || e?.message || '保存失败')
  } finally {
    saving.value = false
  }
}

async function handleDelete(row: WarehouseItem) {
  try {
    await ElMessageBox.confirm(`确认删除仓库「${row.name}」? 若仓库有物料/流水关联则无法删除。`, '删除确认', { type: 'warning' })
    await del(`/inventory/warehouses/${row.id}`)
    ElMessage.success('已删除')
    loadList()
  } catch (e: any) {
    if (e === 'cancel') return
    ElMessage.error(e?.response?.data?.message || e?.message || '删除失败')
  }
}

onMounted(() => loadList())
</script>

<style scoped>
.page-container { padding: 20px; background: #f5f7fa; min-height: 100vh; }
.page-header { margin-bottom: 16px; h2 { font-size: 20px; color: #0C447C; margin: 0; } }
.content-card { background: #fff; border-radius: 8px; padding: 16px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
.toolbar-wrap { margin-bottom: 12px; }
</style>
