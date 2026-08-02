<template>
  <el-dialog
    v-model="visible"
    :title="isEdit ? '编辑招标项目' : '新建招标项目'"
    width="780px"
    :close-on-click-modal="false"
    @close="onClose"
  >
    <el-form ref="formRef" :model="form" :rules="rules" label-width="100px" v-loading="loading">
      <el-form-item label="项目名称" prop="name">
        <el-input v-model="form.name" placeholder="例: 弱电设备年度集采" maxlength="200" show-word-limit />
      </el-form-item>
      <el-form-item label="类型">
        <el-radio-group v-model="form.type">
          <el-radio value="tender">公开招标</el-radio>
          <el-radio value="rfq">询价</el-radio>
          <el-radio value="negotiation">议价</el-radio>
        </el-radio-group>
      </el-form-item>
      <el-form-item label="关联项目">
        <el-select v-model="form.project_id" placeholder="可选: 关联内部项目" clearable filterable style="width:100%">
          <el-option v-for="p in projectOptions" :key="p.id" :label="`[${p.code}] ${p.name}`" :value="p.id" />
        </el-select>
      </el-form-item>
      <el-form-item label="截标时间">
        <el-date-picker v-model="form.deadline" type="datetime" placeholder="选择截标时间" value-format="YYYY-MM-DD HH:mm:ss" style="width:100%" />
      </el-form-item>
      <el-form-item label="开标时间">
        <el-date-picker v-model="form.open_at" type="datetime" placeholder="可选: 开标时间" value-format="YYYY-MM-DD HH:mm:ss" style="width:100%" />
      </el-form-item>
      <el-form-item label="必购清单">
        <div class="items-table">
          <el-table :data="form.required_items" border size="small" empty-text="暂未添加必购项">
            <el-table-column label="物料/服务" min-width="180">
              <template #default="{ row }">
                <el-input v-model="row.name" size="small" placeholder="名称" />
              </template>
            </el-table-column>
            <el-table-column label="规格" width="140">
              <template #default="{ row }">
                <el-input v-model="row.spec" size="small" placeholder="可选" />
              </template>
            </el-table-column>
            <el-table-column label="数量" width="100">
              <template #default="{ row }">
                <el-input-number v-model="row.qty" size="small" :min="0" :precision="0" controls-position="right" style="width:80px" />
              </template>
            </el-table-column>
            <el-table-column label="单位" width="80">
              <template #default="{ row }">
                <el-input v-model="row.unit" size="small" placeholder="件" />
              </template>
            </el-table-column>
            <el-table-column label="" width="50" fixed="right">
              <template #default="{ $index }">
                <el-button link type="danger" :icon="Delete" @click="form.required_items.splice($index, 1)" />
              </template>
            </el-table-column>
          </el-table>
          <el-button-group style="margin-top:8px">
            <el-button :icon="Plus" size="small" plain @click="addItem">添加物料</el-button>
            <el-button :icon="Box" size="small" plain type="success" @click="showInventoryPicker = true">从库存选</el-button>
          </el-button-group>
        </div>
      </el-form-item>
      <el-form-item label="邀请供应商">
        <el-select
          v-model="form.invited_supplier_ids"
          multiple
          filterable
          placeholder="留空 = 公开链接,任何供应商可投;选择后 = 仅受邀可投"
          style="width:100%"
        >
          <el-option v-for="s in supplierOptions" :key="s.id" :label="`[${s.code}] ${s.name}`" :value="s.id" />
        </el-select>
      </el-form-item>
      <el-form-item label="评分权重">
        <div class="score-config">
          <el-input-number v-model="form.score_config.technical" :min="0" :max="100" controls-position="right" />
          <span class="lbl">技术分</span>
          <el-input-number v-model="form.score_config.price" :min="0" :max="100" controls-position="right" />
          <span class="lbl">价格分</span>
          <el-input-number v-model="form.score_config.business" :min="0" :max="100" controls-position="right" />
          <span class="lbl">商务分</span>
          <span class="hint">合计 {{ weightSum }}%</span>
        </div>
      </el-form-item>
      <el-form-item label="说明">
        <el-input v-model="form.description" type="textarea" :rows="3" maxlength="2000" show-word-limit />
      </el-form-item>
    </el-form>

    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" :loading="saving" @click="onSave">保存</el-button>
    </template>
  </el-dialog>

  <!-- V0.6.3 从库存选择物料 -->
  <InventoryItemPicker
    v-if="showInventoryPicker"
    :show="showInventoryPicker"
    :multiple="true"
    @close="showInventoryPicker = false"
    @select="onInventorySelect"
  />
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus, Delete, Box } from '@element-plus/icons-vue'
import { tender } from '@/api/tender'
import type { TenderProject } from '@/api/tender'
import { supplier } from '@/api/supplier'
import { getProjectList } from '@/api/modules'
import InventoryItemPicker from '@/views/inventory/components/InventoryItemPicker.vue'

const props = defineProps<{ visible: boolean; tender: TenderProject | null }>()
const emit = defineEmits<{ (e: 'update:visible', v: boolean): void; (e: 'saved'): void }>()

const visible = computed({
  get: () => props.visible,
  set: (v) => emit('update:visible', v),
})

const isEdit = computed(() => !!props.tender?.id)
const formRef = ref()
const loading = ref(false)
const saving = ref(false)
const supplierOptions = ref<{ id: number; name: string; code: string }[]>([])
const projectOptions = ref<{ id: number; name: string; code: string }[]>([])

const form = reactive({
  name: '',
  type: 'tender' as 'tender' | 'rfq' | 'negotiation',
  project_id: undefined as number | undefined,
  deadline: undefined as string | undefined,
  open_at: undefined as string | undefined,
  description: '',
  required_items: [] as Array<{ name: string; spec?: string; qty: number; unit?: string }>,
  invited_supplier_ids: [] as number[],
  score_config: { technical: 40, price: 40, business: 20 },
})

const rules = {
  name: [{ required: true, message: '请输入项目名称', trigger: 'blur' }],
}

const weightSum = computed(() => form.score_config.technical + form.score_config.price + form.score_config.business)

const resetForm = () => {
  Object.assign(form, {
    name: '',
    type: 'tender',
    project_id: undefined,
    deadline: undefined,
    open_at: undefined,
    description: '',
    required_items: [],
    invited_supplier_ids: [],
    score_config: { technical: 40, price: 40, business: 20 },
  })
}

const fillForm = (t: TenderProject | null) => {
  resetForm()
  if (!t) return
  form.name = t.name
  form.type = t.type || 'tender'
  form.project_id = t.project_id
  form.deadline = t.deadline
  form.open_at = t.open_at
  form.description = t.description || ''
  form.required_items = (t.required_items || []).map((it: Record<string, unknown>) => ({ ...it }))
  form.invited_supplier_ids = t.invited_supplier_ids || []
  if (t.score_config) form.score_config = { ...form.score_config, ...t.score_config }
}

const addItem = () => {
  form.required_items.push({ name: '', spec: '', qty: 1, unit: '件' })
}

// V0.6.3 从库存选择物料 (批量加入必购清单)
const showInventoryPicker = ref(false)
const onInventorySelect = (items: Record<string, unknown>[]) => {
  if (!items || items.length === 0) return
  let added = 0
  items.forEach((it: Record<string, unknown>) => {
    form.required_items.push({
      name: it.name,
      spec: it.spec || '',
      qty: 1,
      unit: it.unit || '件',
    })
    added++
  })
  showInventoryPicker.value = false
  ElMessage.success(`已从库存添加 ${added} 项物料`)
}

const loadOptions = async () => {
  try {
    const [s, p]: Record<string, unknown>[] = await Promise.all([
      supplier.list({ per_page: 500 }),
      getProjectList({ per_page: 200 }),
    ])
    supplierOptions.value = (s?.data?.items ?? s?.items ?? s?.data ?? []) as Record<string, unknown>[]
    projectOptions.value = (p?.data?.items ?? p?.items ?? p?.data ?? []) as Record<string, unknown>[]
  } catch { /* 静默, 选项可为空 */ }
}

const onSave = async () => {
  await formRef.value?.validate().catch(() => null)
  if (!form.name) return ElMessage.warning('请填写项目名称')
  saving.value = true
  try {
    const payload = {
      name: form.name,
      type: form.type,
      project_id: form.project_id || undefined,
      deadline: form.deadline || undefined,
      open_at: form.open_at || undefined,
      description: form.description || undefined,
      required_items: form.required_items.filter((it) => it.name),
      invited_supplier_ids: form.invited_supplier_ids.length ? form.invited_supplier_ids : undefined,
      score_config: form.score_config,
    }
    if (isEdit.value && props.tender) {
      await tender.update(props.tender.id, payload as Record<string, unknown>)
      ElMessage.success('已更新')
    } else {
      await tender.create(payload as Record<string, unknown>)
      ElMessage.success('已创建, 当前为草稿')
    }
    emit('saved')
    visible.value = false
  } finally {
    saving.value = false
  }
}

const onClose = () => { resetForm() }

watch(() => props.visible, (v) => { if (v) { fillForm(props.tender); loadOptions() } })
onMounted(() => { if (props.visible) loadOptions() })
</script>

<style scoped lang="scss">
.items-table { width: 100%; }
.score-config { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.lbl { color: #666; font-size: 12px; }
.hint { color: #999; font-size: 12px; margin-left: 8px; }
</style>
