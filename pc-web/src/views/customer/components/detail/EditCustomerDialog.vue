<template>
  <el-dialog
    v-model="visible"
    title="编辑客户"
    width="780px"
    :close-on-click-modal="false"
    destroy-on-close
    @close="handleClose"
  >
    <el-form
      v-if="form"
      ref="formRef"
      :model="form"
      :rules="rules"
      label-width="100px"
      label-position="right"
    >
      <CustomerBasicForm :form="form" :common-tags="commonTags" />

      <CustomerContactsSection
        :contact-list="contactList"
        @add="addContact"
        @remove="removeContact"
      />

      <CustomerInvoiceSection
        :invoice-list="invoiceList"
        @add="addInvoice"
        @remove="removeInvoice"
        @default-change="onDefaultChange"
      />
    </el-form>

    <template #footer>
      <el-button @click="handleClose">取消</el-button>
      <el-button type="primary" :loading="submitting" @click="handleSubmit(() => emit('saved'))">保存修改</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import type { Customer } from './types'
import { useEditCustomer } from '@/views/customer/composables/useEditCustomer'

interface Props {
  modelValue: boolean
  customer: Customer
}

const props = defineProps<Props>()
const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
  (e: 'saved'): void
}>()

const {
  visible, formRef, submitting,
  form, contactList, invoiceList,
  commonTags, rules,
  addContact, removeContact, addInvoice, removeInvoice, onDefaultChange,
  handleSubmit, handleClose,
} = useEditCustomer(props, emit)
</script>
