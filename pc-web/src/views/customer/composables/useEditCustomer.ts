// 编辑客户对话框 composable — 表单状态 / 联系人/开票信息 diff 同步
// 从 EditCustomerDialog.vue <script setup> 抽出, 主组件 + 子组件共享
import { ref, computed, watch, nextTick } from 'vue'
import { ElMessage, type FormInstance, type FormRules } from 'element-plus'
import { put, post, del } from '@/utils/request'
import type { Customer } from '../detail/types'

interface Props {
  modelValue: boolean
  customer: Customer
}

export function useEditCustomer(props: Props, emit: (e: 'update:modelValue', v: boolean) => void) {
  const visible = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
  })

  // 表单 ref
  const formRef = ref<FormInstance>()
  const submitting = ref(false)

  // 联系人行 — 每行带 __key 用于 v-for 追踪
  interface ContactRow {
    __key: number
    id?: number
    name: string
    position: string
    phone: string
    is_primary: boolean
    isNew: boolean
    isRemoved: boolean
  }

  let __seq = 0
  function newKey() { return ++__seq }

  const form = ref(emptyForm())
  const contactList = ref<ContactRow[]>([])

  // 开票信息行 (v0.5.8.9)
  interface InvoiceRow {
    __key: number
    id?: number
    invoice_type: 'general' | 'special' | 'electronic' | string
    company_name: string
    tax_no: string
    register_address: string
    register_phone: string
    bank_name: string
    bank_account: string
    is_default: boolean
    remark: string
    isNew: boolean
    isRemoved: boolean
  }
  const invoiceList = ref<InvoiceRow[]>([])

  function emptyForm() {
    return {
      name: '',
      industry: '',
      category: '',
      source: '',
      status: 'active',
      province: '',
      city: '',
      district: '',
      address: '',
      tags: [] as string[],
      description: '',
    }
  }

  const commonTags = ['重点客户', '战略合作', '续约客户', '回款及时', '待跟进', '需高层维护']

  const rules: FormRules = {
    name: [
      { required: true, message: '客户名称不能为空', trigger: 'blur' },
      { max: 128, message: '最长 128 字符', trigger: 'blur' },
    ],
    category: [{ required: true, message: '请选择客户分类', trigger: 'change' }],
    status: [{ required: true, message: '请选择状态', trigger: 'change' }],
    province: [{ max: 32, message: '最长 32 字符', trigger: 'blur' }],
    city: [{ max: 32, message: '最长 32 字符', trigger: 'blur' }],
    district: [{ max: 32, message: '最长 32 字符', trigger: 'blur' }],
    address: [{ max: 255, message: '最长 255 字符', trigger: 'blur' }],
  }

  function newEmptyInvoice(): InvoiceRow {
    return {
      __key: newKey(),
      invoice_type: 'general',
      company_name: '',
      tax_no: '',
      register_address: '',
      register_phone: '',
      bank_name: '',
      bank_account: '',
      is_default: false,
      remark: '',
      isNew: true,
      isRemoved: false,
    }
  }

  // 同步 customer → form + contactList + invoiceList
  function syncFromCustomer() {
    const c = props.customer
    if (!c || !c.id) {
      form.value = emptyForm()
      contactList.value = []
      invoiceList.value = []
      return
    }
    form.value = {
      name: c.name || '',
      industry: c.industry || '',
      category: c.category || 'normal',
      source: c.source || '',
      status: c.status || 'active',
      province: c.province || '',
      city: c.city || '',
      district: c.district || '',
      address: c.address || '',
      tags: Array.isArray(c.tags) ? [...c.tags] : [],
      description: c.description || '',
    }
    // 联系人按 is_primary desc, id asc 排
    const sorted = (c.contacts || [])
      .slice()
      .sort((a, b) => {
        if (a.is_primary && !b.is_primary) return -1
        if (!a.is_primary && b.is_primary) return 1
        return a.id - b.id
      })
    contactList.value = sorted.length
      ? sorted.map((x) => ({
          __key: newKey(),
          id: x.id,
          name: x.name || '',
          position: x.position || '',
          phone: x.phone || '',
          is_primary: !!x.is_primary,
          isNew: false,
          isRemoved: false,
        }))
      : [{ __key: newKey(), name: '', position: '', phone: '', is_primary: true, isNew: true, isRemoved: false }]

    // 开票信息 (v0.5.8.9)
    const sortedInv = (c.invoice_infos || [])
      .slice()
      .sort((a, b) => {
        if (a.is_default && !b.is_default) return -1
        if (!a.is_default && b.is_default) return 1
        return a.id - b.id
      })
    invoiceList.value = sortedInv.map((x) => ({
      __key: newKey(),
      id: x.id,
      invoice_type: x.invoice_type || 'general',
      company_name: x.company_name || '',
      tax_no: x.tax_no || '',
      register_address: x.register_address || '',
      register_phone: x.register_phone || '',
      bank_name: x.bank_name || '',
      bank_account: x.bank_account || '',
      is_default: !!x.is_default,
      remark: x.remark || '',
      isNew: false,
      isRemoved: false,
    }))
  }

  function addContact() {
    contactList.value.push({
      __key: newKey(),
      name: '',
      position: '',
      phone: '',
      is_primary: false,
      isNew: true,
      isRemoved: false,
    })
  }

  function addInvoice() {
    invoiceList.value.push(newEmptyInvoice())
  }

  function removeInvoice(idx: number) {
    const row = invoiceList.value[idx]
    if (row.id && !row.isNew) {
      row.isRemoved = true
    } else {
      invoiceList.value.splice(idx, 1)
    }
  }

  function onDefaultChange(changedIdx: number) {
    // 单选默认: 选了就把其他清掉
    if (invoiceList.value[changedIdx].is_default) {
      invoiceList.value.forEach((row, i) => {
        if (i !== changedIdx) row.is_default = false
      })
    }
  }

  function removeContact(idx: number) {
    const row = contactList.value[idx]
    if (row.id && !row.isNew) {
      row.isRemoved = true
    } else {
      contactList.value.splice(idx, 1)
    }
    // 确保至少一个可见的联系人
    const visibleRows = contactList.value.filter((r) => !r.isRemoved)
    if (!visibleRows.length) {
      contactList.value.unshift({
        __key: newKey(),
        name: '',
        position: '',
        phone: '',
        is_primary: true,
        isNew: true,
        isRemoved: false,
      })
    }
  }

  // 每次打开时同步
  watch(
    () => props.modelValue,
    (v) => {
      if (v) {
        syncFromCustomer()
        nextTick(() => formRef.value?.clearValidate())
      }
    },
  )

  async function handleSubmit(onSaved: () => void) {
    if (!formRef.value) return
    try {
      await formRef.value.validate()
    } catch {
      return
    }
    // 手动验证每一行联系人的电话
    for (let i = 0; i < contactList.value.length; i++) {
      const r = contactList.value[i]
      if (r.isRemoved) continue
      if (!r.phone || !r.phone.trim()) {
        ElMessage.warning(`联系人 ${i + 1} 的电话不能为空`)
        return
      }
      if (!/^[\d\-+\s()]{5,}$/.test(r.phone)) {
        ElMessage.warning(`联系人 ${i + 1} 的电话格式不正确`)
        return
      }
    }
    // 手动验证每一行开票信息 (v0.5.8.9)
    for (let i = 0; i < invoiceList.value.length; i++) {
      const r = invoiceList.value[i]
      if (r.isRemoved) continue
      if (!r.company_name || !r.company_name.trim()) {
        ElMessage.warning(`开票信息 ${i + 1} 的「单位名称」不能为空`)
        return
      }
      if (!r.tax_no || !r.tax_no.trim()) {
        ElMessage.warning(`开票信息 ${i + 1} 的「税号」不能为空`)
        return
      }
    }

    submitting.value = true
    try {
      const customerId = props.customer.id

      // 1) 主表更新 (不带 contact/phone, 由联系人 API 接管)
      const payload: Record<string, unknown> = {
        name: form.value.name.trim(),
        industry: form.value.industry || null,
        category: form.value.category || null,
        source: form.value.source || null,
        status: form.value.status,
        province: form.value.province || '',
        city: form.value.city || '',
        district: form.value.district || '',
        address: form.value.address || '',
        tags: form.value.tags.length ? form.value.tags : null,
        description: form.value.description || null,
      }
      await put(`/customers/${customerId}`, payload)

      // 2) 联系人 diff 同步
      for (const r of contactList.value) {
        if (r.isRemoved && r.id) {
          try {
            await del(`/customers/${customerId}/contacts/${r.id}`)
          } catch (e: unknown) {
            console.warn('删除联系人失败', r.id, e)
          }
        }
      }
      const visibleRows = contactList.value.filter((r) => !r.isRemoved)
      const primaryIdx = 0
      for (let i = 0; i < visibleRows.length; i++) {
        const r = visibleRows[i]
        const body = {
          name: r.name || '',
          position: r.position || null,
          phone: r.phone,
          is_primary: i === primaryIdx,
        }
        if (r.isNew || !r.id) {
          await post(`/customers/${customerId}/contacts`, body)
        } else {
          await put(`/customers/${customerId}/contacts/${r.id}`, body)
        }
      }

      // 3) 开票信息 diff 同步 (v0.5.8.9)
      for (const r of invoiceList.value) {
        if (r.isRemoved && r.id) {
          try {
            await del(`/customers/${customerId}/invoice-infos/${r.id}`)
          } catch (e: unknown) {
            console.warn('删除开票信息失败', r.id, e)
          }
        }
      }
      for (const r of invoiceList.value) {
        if (r.isRemoved) continue
        const body: Record<string, unknown> = {
          invoice_type: r.invoice_type || 'general',
          company_name: r.company_name.trim(),
          tax_no: r.tax_no.trim(),
          register_address: r.register_address || null,
          register_phone: r.register_phone || null,
          bank_name: r.bank_name || null,
          bank_account: r.bank_account || null,
          is_default: !!r.is_default,
          remark: r.remark || null,
        }
        if (r.isNew || !r.id) {
          await post(`/customers/${customerId}/invoice-infos`, body)
        } else {
          await put(`/customers/${customerId}/invoice-infos/${r.id}`, body)
        }
      }

      ElMessage.success('客户信息已更新')
      onSaved()
      handleClose()
    } catch (e: unknown) {
      const msg = e?.response?.data?.message || e?.message || '保存失败'
      ElMessage.error(msg)
    } finally {
      submitting.value = false
    }
  }

  function handleClose() {
    visible.value = false
  }

  return {
    visible, formRef, submitting,
    form, contactList, invoiceList,
    commonTags, rules,
    addContact, removeContact, addInvoice, removeInvoice, onDefaultChange,
    handleSubmit, handleClose,
  }
}
