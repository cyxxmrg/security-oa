<template>
  <div class="invoice-tab">
    <el-table :data="infos" border size="default" empty-text="暂无开票信息 — 点击右上角「编辑客户」可添加">
      <el-table-column type="index" label="#" width="55" align="center" />
      <el-table-column label="发票类型" width="150">
        <template #default="{ row }">
          <el-tag :type="invoiceTypeTagType(row.invoice_type)" size="small">
            {{ invoiceTypeLabel(row.invoice_type) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="company_name" label="单位名称 (抬头)" min-width="200" show-overflow-tooltip />
      <el-table-column prop="tax_no" label="纳税人识别号 (税号)" width="200" />
      <el-table-column prop="register_address" label="注册地址" min-width="180" show-overflow-tooltip />
      <el-table-column prop="register_phone" label="注册电话" width="140" />
      <el-table-column label="开户银行/账号" min-width="220">
        <template #default="{ row }">
          <span v-if="row.bank_name">{{ row.bank_name }} / {{ row.bank_account || '—' }}</span>
          <span v-else style="color:#909399">—</span>
        </template>
      </el-table-column>
      <el-table-column label="默认" width="80" align="center">
        <template #default="{ row }">
          <el-tag v-if="row.is_default" type="success" size="small">默认</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="remark" label="备注" min-width="140" show-overflow-tooltip />
    </el-table>
  </div>
</template>

<script setup lang="ts">
import type { InvoiceInfo } from './types'
import { invoiceTypeLabel, invoiceTypeTagType } from './types'

defineProps<{ infos: InvoiceInfo[] }>()
</script>

<style lang="scss" scoped>
.invoice-tab { padding: 4px 0; }
</style>
