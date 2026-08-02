<template>
  <div class="tab-content">
    <el-descriptions title="客户基础资料" :column="3" border>
      <el-descriptions-item label="客户编号">#{{ customer.id }}</el-descriptions-item>
      <el-descriptions-item label="客户名称">{{ customer.name }}</el-descriptions-item>
      <el-descriptions-item label="所属行业">{{ customer.industry || '—' }}</el-descriptions-item>
      <el-descriptions-item label="客户分类">
        <el-tag :type="categoryType(displayCategory(customer.category))" size="small">
          {{ displayCategory(customer.category) }}
        </el-tag>
      </el-descriptions-item>
      <el-descriptions-item label="客户来源">{{ customer.source || '—' }}</el-descriptions-item>
      <el-descriptions-item label="状态">
        <el-tag :type="customer.status === 'active' ? 'success' : 'info'" size="small">
          {{ customer.status === 'active' ? '正常' : '停用' }}
        </el-tag>
      </el-descriptions-item>
      <el-descriptions-item label="详细地址" :span="3">
        {{ addressText }}
      </el-descriptions-item>
      <el-descriptions-item label="客户标签" :span="3">
        <el-tag
          v-for="t in (customer.tags || [])"
          :key="t"
          type="info"
          effect="plain"
          size="small"
          class="tag-item"
        >
          {{ t }}
        </el-tag>
        <span v-if="!customer.tags || customer.tags.length === 0" style="color:#909399">—</span>
      </el-descriptions-item>
      <el-descriptions-item label="备注" :span="3">
        {{ customer.description || '—' }}
      </el-descriptions-item>
    </el-descriptions>

    <h3 class="section-title">联系人信息</h3>
    <el-table :data="customer.contacts || []" border size="default" empty-text="暂无联系人">
      <el-table-column type="index" label="#" width="55" align="center" />
      <el-table-column prop="name" label="姓名" width="100" />
      <el-table-column prop="position" label="职位" width="140" />
      <el-table-column prop="phone" label="联系电话" width="160" />
      <el-table-column prop="email" label="邮箱" width="220" />
      <el-table-column prop="wechat" label="微信" width="140" />
      <el-table-column prop="is_primary" label="主联系人" width="100" align="center">
        <template #default="{ row }">
          <el-tag v-if="row.is_primary" type="success" size="small">主联系人</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="notes" label="备注" />
    </el-table>

    <h3 class="section-title">
      开票信息
      <span style="font-size:12px;color:#909399;font-weight:normal;margin-left:8px">
        (共 {{ invoiceInfos.length }} 条 — 点右上角「编辑客户」可新增/修改/删除)
      </span>
    </h3>
    <el-table :data="invoiceInfos" border size="default" empty-text="暂无开票信息 — 请点右上角「编辑客户」添加">
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
    </el-table>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { Customer, InvoiceInfo } from './types'
import { displayCategory, categoryType, invoiceTypeLabel, invoiceTypeTagType } from './types'

// v0.3.20 抽自 customer/Detail.vue:78-122
// v0.5.8.9 加 开票信息 区
const props = defineProps<{
  customer: Customer
}>()

const addressText = computed(() => {
  const parts = [props.customer.province, props.customer.city, props.customer.district, props.customer.address]
  return parts.filter(Boolean).join(' ') || '—'
})

const invoiceInfos = computed<InvoiceInfo[]>(() => {
  const list = props.customer.invoice_infos || []
  return list
    .slice()
    .sort((a, b) => {
      if (a.is_default && !b.is_default) return -1
      if (!a.is_default && b.is_default) return 1
      return a.id - b.id
    })
})
</script>

<style lang="scss" scoped>
.section-title {
  font-size: 15px;
  font-weight: 600;
  color: #303133;
  margin: 20px 0 12px;
  border-left: 3px solid #0C447C;
  padding-left: 10px;
}
.tag-item { margin-right: 4px; margin-bottom: 2px; }
</style>
