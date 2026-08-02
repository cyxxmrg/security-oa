<template>
  <div class="portal-tender-bid">
    <div class="portal-bg" />

    <div class="portal-card" v-loading="loading">
      <div class="portal-header" v-if="tender">
        <div class="portal-logo">📋</div>
        <h1 class="portal-title">{{ tender.name }}</h1>
        <div class="portal-meta">
          <el-tag size="small" :type="statusType(tender.status)">{{ tender.status_label || tender.status }}</el-tag>
          <span class="meta-code">{{ tender.code }}</span>
          <span v-if="tender.deadline" class="meta-deadline">⏰ 截标: {{ fmt(tender.deadline) }}</span>
        </div>
        <p v-if="tender.description" class="portal-desc">{{ tender.description }}</p>
      </div>

      <template v-if="tender">
        <!-- 必购清单 (参考) -->
        <h4 v-if="tender.required_items?.length" class="section-title">招标必购清单 (参考)</h4>
        <el-table v-if="tender.required_items?.length" :data="tender.required_items" border size="small" style="margin-bottom:20px">
          <el-table-column prop="name" label="物料/服务" min-width="200" />
          <el-table-column prop="spec" label="规格" width="160" />
          <el-table-column prop="qty" label="数量" width="100" align="right" />
          <el-table-column prop="unit" label="单位" width="80" />
        </el-table>

        <el-alert v-if="!canBid" type="warning" :closable="false" show-icon style="margin-bottom:16px">
          <template #title>该项目当前不在投标期, 您的报价将无法提交</template>
        </el-alert>

        <el-form :model="form" :rules="rules" ref="formRef" label-width="100px" :disabled="!canBid">
          <h4 class="section-title">报价明细</h4>
          <el-table :data="form.items" border>
            <el-table-column label="物料/服务" min-width="200">
              <template #default="{ row }">
                <el-input v-model="row.name" size="small" placeholder="产品/服务名称" />
              </template>
            </el-table-column>
            <el-table-column label="规格" width="140">
              <template #default="{ row }">
                <el-input v-model="row.spec" size="small" placeholder="可选" />
              </template>
            </el-table-column>
            <el-table-column label="单位" width="80">
              <template #default="{ row }">
                <el-input v-model="row.unit" size="small" />
              </template>
            </el-table-column>
            <el-table-column label="数量" width="110">
              <template #default="{ row }">
                <el-input-number v-model="row.quantity" :min="0" :precision="2" controls-position="right" size="small" style="width:90px" @change="recompute" />
              </template>
            </el-table-column>
            <el-table-column label="单价" width="140">
              <template #default="{ row }">
                <el-input-number v-model="row.unit_price" :min="0" :precision="2" controls-position="right" size="small" style="width:120px" @change="recompute" />
              </template>
            </el-table-column>
            <el-table-column label="小计" width="140" align="right">
              <template #default="{ row }">
                <strong>¥ {{ Number(row.total_price).toLocaleString() }}</strong>
              </template>
            </el-table-column>
            <el-table-column label="" width="50" fixed="right">
              <template #default="{ $index }">
                <el-button link type="danger" :icon="Delete" @click="removeItem($index)" />
              </template>
            </el-table-column>
          </el-table>
          <el-button :icon="Plus" size="small" plain style="margin-top:8px" @click="addItem">添加行项目</el-button>

          <el-row :gutter="16" style="margin-top:20px">
            <el-col :span="12">
              <el-form-item label="交货期(天)">
                <el-input-number v-model="form.lead_time_days" :min="0" controls-position="right" style="width:100%" />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="投标总金额">
                <div class="total-display">
                  <span class="total-amount">¥ {{ Number(form.total_amount).toLocaleString() }}</span>
                  <el-button size="small" link @click="recompute" type="primary">从行项目合计</el-button>
                </div>
              </el-form-item>
            </el-col>
          </el-row>

          <el-form-item label="技术方案">
            <el-input v-model="form.technical_proposal" type="textarea" :rows="4" maxlength="5000" show-word-limit />
          </el-form-item>
          <el-form-item label="备注">
            <el-input v-model="form.remark" type="textarea" :rows="2" maxlength="1000" show-word-limit />
          </el-form-item>
          <el-form-item v-if="!hasVerifiedPhoneSuffix" label="预留手机号后 4 位">
            <el-input v-model="manualPhoneSuffix" maxlength="4" placeholder="用于校验供应商身份" />
          </el-form-item>
        </el-form>

        <div v-if="existingBid" class="existing-bid">
          <el-alert type="info" :closable="false" show-icon>
            <template #title>您已为此项目提交过投标 (编号: {{ existingBid.code }}, 状态: {{ existingBid.status_label || existingBid.status }})</template>
            再次提交将更新您的报价。
          </el-alert>
        </div>

        <div class="form-actions">
          <el-button @click="goBack">返回邀请列表</el-button>
          <el-button type="primary" :loading="saving" :disabled="!canBid" @click="onSubmit">提交投标</el-button>
        </div>

        <div v-if="tender.attachments?.length" class="attachments">
          <h4 class="section-title">招标文件</h4>
          <div class="att-list">
            <a v-for="a in tender.attachments" :key="a.id" :href="a.url" target="_blank" class="att-item">
              <el-icon><Document /></el-icon>
              <span>{{ a.name }}</span>
              <span class="att-size">({{ formatSize(a.size) }})</span>
            </a>
          </div>
        </div>
      </template>

      <el-result v-else-if="loadError" icon="error" :title="loadError" sub-title="请确认链接是否正确">
        <template #extra>
          <el-button @click="goBack">返回</el-button>
        </template>
      </el-result>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Delete, Document } from '@element-plus/icons-vue'
import { portalApi, type PublicTender, type PublicBid } from '@/api/portal-tender'

const route = useRoute()
const router = useRouter()

const token = computed(() => route.params.token as string)
const supplierId = computed(() => Number(route.query.supplier || 0))
const manualPhoneSuffix = ref('')
const phoneSuffix = computed(() => String(sessionStorage.getItem('portal_phone_suffix') || manualPhoneSuffix.value || ''))
const hasVerifiedPhoneSuffix = computed(() => /^\d{4}$/.test(String(sessionStorage.getItem('portal_phone_suffix') || '')))

const loading = ref(false)
const saving = ref(false)
const loadError = ref<string | null>(null)

const tender = ref<PublicTender | null>(null)
const existingBid = ref<PublicBid | null>(null)

const form = reactive({
  items: [] as Array<{ name: string; spec: string; unit: string; quantity: number; unit_price: number; total_price: number }>,
  lead_time_days: 7,
  total_amount: 0,
  technical_proposal: '',
  remark: '',
})

const rules = {
  total_amount: [{ required: true, validator: (_: Record<string, unknown>, v: string, cb: (e?: Error) => void) => Number(v) > 0 ? cb() : cb(new Error('请填写总金额')) }],
}

const canBid = computed(() => ['bidding', 'published'].includes(tender.value?.status || ''))
const fmt = (s?: string) => s ? s.replace('T', ' ').slice(0, 16) : '-'
const statusType = (s: string) => (({
  bidding: 'warning', published: 'warning', evaluating: 'primary', awarded: 'success', closed: 'info', cancelled: 'danger',
} as Record<string, string>)[s] || 'info') as Record<string, unknown>
const formatSize = (b?: number) => b ? (b / 1024).toFixed(1) + ' KB' : '-'

const addItem = () => form.items.push({ name: '', spec: '', unit: '件', quantity: 1, unit_price: 0, total_price: 0 })
const removeItem = (i: number) => { form.items.splice(i, 1); recompute() }
const recompute = () => {
  let sum = 0
  for (const it of form.items) {
    it.total_price = Number((Number(it.quantity) * Number(it.unit_price)).toFixed(2))
    sum += it.total_price
  }
  form.total_amount = Number(sum.toFixed(2))
}

const loadAll = async () => {
  loading.value = true
  loadError.value = null
  try {
    tender.value = await portalApi.getTender(token.value)
    // 尝试拉取已有投标
    if (supplierId.value) {
      try {
        const b = await portalApi.myBid(token.value, supplierId.value)
        if (b) {
          existingBid.value = b
          form.items = (b.items || []).map((it: Record<string, unknown>) => ({
            name: it.name, spec: it.spec || '', unit: it.unit || '件',
            quantity: Number(it.quantity), unit_price: Number(it.unit_price), total_price: Number(it.total_price),
          }))
          form.lead_time_days = b.lead_time_days ?? 7
          form.total_amount = Number(b.total_amount || 0)
          form.technical_proposal = b.technical_proposal || ''
          form.remark = b.remark || ''
        }
      } catch { /* 没有已有投标, 继续 */ }
    }
    if (form.items.length === 0) addItem()
  } catch (e: unknown) {
    loadError.value = e?.message || '加载失败'
  } finally { loading.value = false }
}

const onSubmit = async () => {
  if (!supplierId.value) return ElMessage.warning('缺少供应商身份, 请从邀请列表进入')
  if (!/^\d{4}$/.test(phoneSuffix.value)) return ElMessage.warning('请输入供应商预留手机号后 4 位')
  if (form.items.length === 0) return ElMessage.warning('请至少添加 1 个行项目')
  if (Number(form.total_amount) <= 0) return ElMessage.warning('总金额必须大于 0')

  try { await ElMessageBox.confirm('提交后可在截止前继续修改, 确认提交?', '提交确认', { type: 'success' }) } catch { return }

  saving.value = true
  try {
    await portalApi.submitBid(token.value, {
      supplier_id: supplierId.value,
      phone_suffix: phoneSuffix.value,
      total_amount: Number(form.total_amount),
      lead_time_days: form.lead_time_days || undefined,
      technical_proposal: form.technical_proposal || undefined,
      remark: form.remark || undefined,
      items: form.items.filter((it) => it.name).map((it) => ({
        name: it.name, spec: it.spec || undefined, unit: it.unit || undefined,
        quantity: Number(it.quantity), unit_price: Number(it.unit_price),
      })),
    })
    ElMessage.success('投标已提交, 等待招标方审核')
    await loadAll()
  } catch (e: unknown) {
    ElMessage.error(e?.message || '提交失败')
  } finally { saving.value = false }
}

const goBack = () => router.push('/portal/tender')

onMounted(loadAll)
</script>

<style scoped lang="scss">
.portal-tender-bid { min-height: 100vh; padding: 24px; position: relative; }
.portal-bg { position: fixed; inset: 0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); z-index: 0; }
.portal-card { position: relative; z-index: 1; max-width: 1100px; margin: 0 auto; background: #fff; border-radius: 16px; padding: 28px 32px; box-shadow: 0 16px 48px rgba(0,0,0,0.12); }
.portal-header { text-align: center; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid #e5e7eb; }
.portal-logo { font-size: 36px; }
.portal-title { font-size: 20px; font-weight: 700; margin: 8px 0; }
.portal-meta { display: flex; align-items: center; justify-content: center; gap: 12px; flex-wrap: wrap; }
.meta-code { font-family: monospace; color: #6b7280; font-size: 13px; }
.meta-deadline { color: #f59e0b; font-size: 13px; font-weight: 500; }
.portal-desc { color: #6b7280; margin-top: 8px; }
.section-title { margin: 16px 0 10px; font-size: 15px; font-weight: 600; color: #111; }
.total-display { display: flex; align-items: center; gap: 12px; padding: 6px 12px; background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 6px; }
.total-amount { color: #0284c7; font-size: 20px; font-weight: 700; }
.existing-bid { margin: 16px 0; }
.form-actions { margin-top: 20px; display: flex; gap: 8px; justify-content: flex-end; }
.attachments { margin-top: 24px; padding-top: 16px; border-top: 1px dashed #e5e7eb; }
.att-list { display: flex; flex-direction: column; gap: 6px; }
.att-item { display: flex; align-items: center; gap: 8px; padding: 8px 12px; background: #f9fafb; border-radius: 6px; color: #1f2937; text-decoration: none; transition: background 0.2s; }
.att-item:hover { background: #f3f4f6; }
.att-size { color: #9ca3af; font-size: 12px; }
</style>
