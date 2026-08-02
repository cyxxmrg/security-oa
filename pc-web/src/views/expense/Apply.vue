<template>
  <div class="page-container">
    <div class="page-header">
      <h2>申请报销</h2>
      <el-button @click="handleCancel">返回列表</el-button>
    </div>
    <div class="content-card" v-loading="loading">
      <el-form ref="formRef" :model="form" :rules="rules" label-width="120px" style="max-width: 820px">
        <el-form-item label="费用类别" prop="category">
          <el-select v-model="form.category" placeholder="请选择费用类别" style="width: 100%">
            <el-option v-for="o in categoryOptions" :key="o.value" :label="o.label" :value="o.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="关联项目">
          <el-select v-model="form.project_id" placeholder="请选择关联项目（可选）" clearable filterable style="width: 100%">
            <el-option
              v-for="p in projectOptions"
              :key="p.id"
              :label="p.code ? `${p.name}（${p.code}）` : p.name"
              :value="p.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="报销事由" prop="description">
          <el-input v-model="form.description" type="textarea" :rows="3" placeholder="请详细说明报销原因/事由" maxlength="1000" show-word-limit />
        </el-form-item>

        <div class="section-title">费用明细</div>
        <div class="detail-table-wrap">
          <el-table :data="form.items" border style="width: 100%">
            <el-table-column label="发生日期" width="180">
              <template #default="{ row }">
                <el-date-picker v-model="row.item_date" type="date" value-format="YYYY-MM-DD" placeholder="选择日期" style="width: 100%" size="small" />
              </template>
            </el-table-column>
            <el-table-column label="费用说明" min-width="240">
              <template #default="{ row }">
                <el-input v-model="row.description" placeholder="例如：高铁票（深圳-南京）" size="small" maxlength="200" />
              </template>
            </el-table-column>
            <el-table-column label="金额" width="170">
              <template #default="{ row }">
                <el-input-number v-model="row.amount" :min="0" :precision="2" size="small" controls-position="right" style="width: 100%" />
              </template>
            </el-table-column>
            <el-table-column label="操作" width="80" align="center" fixed="right">
              <template #default="{ $index }">
                <el-button link type="danger" size="small" @click="removeItem($index)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>
          <el-button type="primary" plain size="small" class="add-detail-btn" @click="addItem">+ 添加明细行</el-button>
        </div>

        <el-form-item label="合计金额">
          <span class="total-amount">¥ {{ totalAmount.toFixed(2) }}</span>
          <span class="total-tip" v-if="form.items.length > 0">（共 {{ form.items.length }} 条明细）</span>
        </el-form-item>

        <el-form-item>
          <el-button type="primary" :loading="submitting" @click="handleSubmit">提交申请</el-button>
          <el-button @click="handleCancel">取消</el-button>
        </el-form-item>
      </el-form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { get, post } from '@/utils/request'
import { unwrapList } from '@/utils/response'

const router = useRouter()
const formRef = ref()
const loading = ref(false)
const submitting = ref(false)

const categoryOptions = [
  { value: 'travel',       label: '差旅费' },
  { value: 'hospitality',  label: '招待费' },
  { value: 'office',       label: '办公费' },
  { value: 'transport',    label: '交通费' },
  { value: 'project_cost', label: '项目成本' },
  { value: 'other',        label: '其他' },
]

const projectOptions = ref<Record<string, unknown>[]>([])

const form = reactive({
  category: '' as string,
  project_id: null as number | null,
  description: '' as string,
  items: [
    { item_date: new Date().toISOString().slice(0, 10), description: '', amount: 0 }
  ],
})

const rules = {
  category:    [{ required: true, message: '请选择费用类别', trigger: 'change' }],
  description: [{ required: true, message: '请输入报销事由', trigger: 'blur' }, { max: 1000, message: '事由不能超过1000字' }],
}

const totalAmount = computed(() =>
  form.items.reduce((sum, it) => sum + (Number(it.amount) || 0), 0)
)

async function loadProjects() {
  try {
    const res = await get('/expenses/projects')
    // V0.6.3: res = {code, data: <projects>}
    projectOptions.value = unwrapList(res)
  } catch (e) {
    console.warn('[loadProjects]', e)
    projectOptions.value = []
  }
}

function addItem() {
  form.items.push({ item_date: new Date().toISOString().slice(0, 10), description: '', amount: 0 })
}
function removeItem(index: number) {
  if (form.items.length <= 1) { ElMessage.warning('至少保留一条明细'); return }
  form.items.splice(index, 1)
}

async function handleSubmit() {
  await formRef.value.validate()
  for (let i = 0; i < form.items.length; i++) {
    const it = form.items[i]
    if (!it.item_date) { ElMessage.warning(`第 ${i + 1} 行缺少发生日期`); return }
    if (!it.description?.trim()) { ElMessage.warning(`第 ${i + 1} 行缺少费用说明`); return }
    if (!Number(it.amount) || Number(it.amount) <= 0) { ElMessage.warning(`第 ${i + 1} 行金额必须大于 0`); return }
  }
  if (totalAmount.value <= 0) { ElMessage.warning('合计金额必须大于 0'); return }

  submitting.value = true
  try {
    const payload: Record<string, unknown> = {
      category:    form.category,
      description: form.description.trim(),
      project_id:  form.project_id || null,
      items: form.items.map((it) => ({
        item_date:   it.item_date,
        description: it.description.trim(),
        amount:      Number(it.amount),
        category:    form.category,
      })),
    }
    // V0.6.3: res = {code, data: <new claim>}
    const res = await post('/expenses', payload)
    const d = res?.data ?? {}
    const claimId = d?.id || (res as Record<string, unknown>)?.id
    ElMessage.success(`报销单已提交`)
    router.push({ path: '/expense', query: claimId ? { view: String(claimId) } : undefined })
  } catch (e: unknown) {
    ElMessage.error(e?.response?.data?.message || e.message || '提交失败')
  } finally {
    submitting.value = false
  }
}

function handleCancel() { router.push('/expense') }

onMounted(() => { loadProjects() })
</script>

<style lang="scss" scoped>
.page-container { padding: 20px; background: #f5f7fa; min-height: 100vh; }
.page-header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 16px;
  h2 { font-size: 20px; color: #0C447C; margin: 0; }
}
.content-card {
  background: #fff; border-radius: 8px; padding: 24px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.section-title {
  font-size: 16px; font-weight: 600; color: #0C447C;
  margin: 20px 0 12px; padding-left: 10px;
  border-left: 3px solid #0C447C;
}
.detail-table-wrap { margin-bottom: 8px; }
.add-detail-btn { margin-top: 10px; }
.total-amount {
  font-size: 18px; font-weight: 700; color: #0C447C;
}
.total-tip {
  margin-left: 12px; font-size: 13px; color: #909399;
}
</style>
