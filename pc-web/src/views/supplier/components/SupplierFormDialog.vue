<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="$emit('update:visible', $event)"
    :title="isEdit ? '编辑供应商' : '新建供应商'"
    width="860px"
    :close-on-click-modal="false"
    destroy-on-close
  >
    <el-form ref="formRef" :model="form" :rules="rules" label-width="120px">
      <el-tabs v-model="activeTab">
        <!-- 基本信息 -->
        <el-tab-pane label="基本信息" name="basic">
          <el-row :gutter="16">
            <el-col :span="12">
              <el-form-item label="供应商名称" prop="name">
                <el-input v-model="form.name" placeholder="请输入" maxlength="200" show-word-limit />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="类型" prop="type">
                <el-select v-model="form.type" style="width: 100%">
                  <el-option label="材料" value="material" />
                  <el-option label="人工" value="labor" />
                  <el-option label="外包" value="outsource" />
                  <el-option label="服务" value="service" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="主联系人">
                <el-input v-model="form.contact_person" maxlength="50" />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="联系电话">
                <el-input v-model="form.phone" maxlength="20" />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="邮箱">
                <el-input v-model="form.email" maxlength="100" />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="分类">
                <el-input v-model="form.category" placeholder="例: 钢材/监控/弱电" />
              </el-form-item>
            </el-col>
            <el-col :span="24">
              <el-form-item label="地址">
                <el-input v-model="form.address" maxlength="255" />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="账期">
                <el-select v-model="form.payment_terms" style="width: 100%">
                  <el-option label="现款" value="cash" />
                  <el-option label="30天" value="30days" />
                  <el-option label="60天" value="60days" />
                  <el-option label="90天" value="90days" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="评级">
                <el-rate v-model="form.rating" :max="5" />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="状态">
                <el-select v-model="form.status" style="width: 100%">
                  <el-option label="正常" value="active" />
                  <el-option label="暂停" value="paused" />
                  <el-option label="黑名单" value="blacklist" />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="注册资本">
                <el-input-number v-model="form.registered_capital" :min="0" :precision="2" style="width: 100%" />
              </el-form-item>
            </el-col>
          </el-row>
        </el-tab-pane>

        <!-- 公司资质 -->
        <el-tab-pane label="公司资质" name="company">
          <el-row :gutter="16">
            <el-col :span="12">
              <el-form-item label="营业执照号">
                <el-input v-model="form.business_license" />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="法人">
                <el-input v-model="form.legal_person" />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="税号">
                <el-input v-model="form.tax_no" />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="官网">
                <el-input v-model="form.website" placeholder="https://" />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="开户行">
                <el-input v-model="form.bank_name" />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="账户名">
                <el-input v-model="form.account_name" />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="银行账号">
                <el-input v-model="form.bank_account" />
              </el-form-item>
            </el-col>
          </el-row>
        </el-tab-pane>

        <!-- 联系人 -->
        <el-tab-pane label="联系人" name="contacts">
          <div style="margin-bottom: 8px">
            <el-button type="primary" link @click="addContact">+ 添加联系人</el-button>
          </div>
          <el-table :data="form.contacts" border>
            <el-table-column label="姓名" width="140">
              <template #default="{ row }">
                <el-input v-model="row.name" size="small" />
              </template>
            </el-table-column>
            <el-table-column label="职位" width="140">
              <template #default="{ row }">
                <el-input v-model="row.position" size="small" />
              </template>
            </el-table-column>
            <el-table-column label="手机" width="140">
              <template #default="{ row }">
                <el-input v-model="row.phone" size="small" />
              </template>
            </el-table-column>
            <el-table-column label="邮箱" width="180">
              <template #default="{ row }">
                <el-input v-model="row.email" size="small" />
              </template>
            </el-table-column>
            <el-table-column label="主联系人" width="80" align="center">
              <template #default="{ row }">
                <el-radio v-model="primaryIdx" :value="form.contacts.indexOf(row)">
                  <span></span>
                </el-radio>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="80">
              <template #default="{ $index }">
                <el-button size="small" link type="danger" @click="form.contacts.splice($index, 1)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- 供应商账号（仅新建时） -->
        <el-tab-pane v-if="!isEdit" label="门户账号" name="account">
          <el-form-item label="开通账号">
            <el-switch v-model="accountEnabled" />
            <span style="margin-left: 8px; color: #999; font-size: 12px">
              开启后供应商可登录门户，提交报价
            </span>
          </el-form-item>
          <template v-if="accountEnabled">
            <el-form-item label="登录用户名">
              <el-input v-model="account.username" placeholder="留空自动生成 sup_xxxx" />
            </el-form-item>
            <el-form-item label="初始密码">
              <el-input v-model="account.password" placeholder="留空自动生成 12 位" show-password />
            </el-form-item>
            <el-form-item label="可访问模块">
              <el-checkbox-group v-model="account.allowed_modules">
                <el-checkbox value="supplier:portal">供应商门户</el-checkbox>
                <el-checkbox value="external-quote:submit">提交报价</el-checkbox>
                <el-checkbox value="external-quote:view">查看报价</el-checkbox>
              </el-checkbox-group>
            </el-form-item>
          </template>
        </el-tab-pane>

        <!-- 备注 -->
        <el-tab-pane label="备注" name="remark">
          <el-input v-model="form.remark" type="textarea" :rows="5" maxlength="2000" show-word-limit />
        </el-tab-pane>
      </el-tabs>
    </el-form>
    <template #footer>
      <el-button @click="$emit('update:visible', false)">取消</el-button>
      <el-button type="primary" :loading="submitting" @click="handleSubmit">保存</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import type { Supplier, SupplierContact, SupplierAccount as AccountT } from '@/api/supplier'
import { ElMessage, type FormInstance } from 'element-plus'

const props = defineProps<{
  visible: boolean
  editing?: Supplier | null
}>()

const emit = defineEmits<{
  'update:visible': [v: boolean]
  save: [data: Record<string, unknown>]
}>()

const formRef = ref<FormInstance>()
const activeTab = ref('basic')
const submitting = ref(false)
const primaryIdx = ref(0)

const form = ref<Partial<Supplier> & { contacts: SupplierContact[] }>({
  name: '', type: 'material', status: 'active', payment_terms: '30days',
  rating: 3, contacts: [],
})
const accountEnabled = ref(true)
const account = ref<AccountT>({
  enabled: true,
  username: '',
  password: '',
  allowed_modules: ['supplier:portal', 'external-quote:submit'],
})

const isEdit = computed(() => !!props.editing?.id)

const rules = {
  name: [{ required: true, message: '请输入供应商名称', trigger: 'blur' }],
  type: [{ required: true, message: '请选择类型', trigger: 'change' }],
}

const addContact = () => {
  form.value.contacts.push({ name: '', phone: '', position: '', email: '', is_primary: false })
}

watch(
  () => props.editing,
  (val) => {
    if (val) {
      form.value = {
        ...val,
        contacts: (val.contacts ?? []).map(c => ({ ...c })),
      }
      primaryIdx.value = (val.contacts ?? []).findIndex(c => c.is_primary)
      if (primaryIdx.value < 0) primaryIdx.value = 0
    } else {
      form.value = {
        name: '', type: 'material', status: 'active', payment_terms: '30days',
        rating: 3, contacts: [],
      }
    }
  },
  { immediate: true },
)

const handleSubmit = async () => {
  if (!formRef.value) return
  await formRef.value.validate()
  // 主联系人标记
  form.value.contacts.forEach((c, idx) => {
    c.is_primary = idx === primaryIdx.value
  })
  submitting.value = true
  const payload: Record<string, unknown> = { ...form.value }
  if (!isEdit.value) {
    payload.account = { ...account.value, enabled: accountEnabled.value }
  }
  emit('save', payload)
  submitting.value = false
}
</script>
