<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="$emit('update:visible', $event)"
    title="供应商详情"
    width="960px"
    destroy-on-close
  >
    <template v-if="detail">
      <el-descriptions :column="3" border>
        <el-descriptions-item label="编号">{{ detail.supplier.code }}</el-descriptions-item>
        <el-descriptions-item label="名称">{{ detail.supplier.name }}</el-descriptions-item>
        <el-descriptions-item label="类型">
          <el-tag size="small">{{ typeLabel(detail.supplier.type) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag size="small" :type="detail.supplier.status === 'active' ? 'success' : 'danger'">
            {{ statusLabel(detail.supplier.status) }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="评级">
          <el-rate v-model="detail.supplier.rating" disabled />
        </el-descriptions-item>
        <el-descriptions-item label="账期">{{ paymentLabel(detail.supplier.payment_terms) }}</el-descriptions-item>
        <el-descriptions-item label="电话">{{ detail.supplier.phone || '-' }}</el-descriptions-item>
        <el-descriptions-item label="邮箱">{{ detail.supplier.email || '-' }}</el-descriptions-item>
        <el-descriptions-item label="联系人">{{ detail.supplier.contact_person || '-' }}</el-descriptions-item>
        <el-descriptions-item label="地址" :span="3">{{ detail.supplier.address || '-' }}</el-descriptions-item>
        <el-descriptions-item label="开户行">{{ detail.supplier.bank_name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="账户名">{{ detail.supplier.account_name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="账号">{{ detail.supplier.bank_account || '-' }}</el-descriptions-item>
      </el-descriptions>

      <el-row :gutter="16" style="margin-top: 16px">
        <el-col :span="6">
          <el-card shadow="never">
            <div style="color:#999;font-size:12px">累计应付</div>
            <div style="font-size:20px;font-weight:600">¥{{ detail.payable_total }}</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="never">
            <div style="color:#999;font-size:12px">已付款</div>
            <div style="font-size:20px;font-weight:600;color:#67c23a">¥{{ detail.paid_total }}</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="never">
            <div style="color:#999;font-size:12px">未付余额</div>
            <div style="font-size:20px;font-weight:600;color:#e6a23c">¥{{ detail.balance_total }}</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="never">
            <div style="color:#999;font-size:12px">报价次数</div>
            <div style="font-size:20px;font-weight:600">{{ detail.quote_count }} / ⭐{{ detail.avg_rating }}</div>
          </el-card>
        </el-col>
      </el-row>

      <el-divider content-position="left">联系人</el-divider>
      <el-table :data="detail.supplier.contacts ?? []" border>
        <el-table-column prop="name" label="姓名" />
        <el-table-column prop="position" label="职位" />
        <el-table-column prop="phone" label="手机" />
        <el-table-column prop="email" label="邮箱" />
        <el-table-column label="主联系人" width="100">
          <template #default="{ row }">
            <el-tag v-if="row.is_primary" type="success" size="small">主联系人</el-tag>
          </template>
        </el-table-column>
      </el-table>
    </template>
    <template v-else>
      <el-skeleton :rows="6" animated />
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import type { SupplierDetail } from '@/api/supplier'

const props = defineProps<{ visible: boolean; detail: SupplierDetail | null }>()
defineEmits<{ 'update:visible': [v: boolean] }>()

watch(() => props.detail, () => { /* 触发 reactivity */ })

const typeLabel = (t?: string) => ({
  material: '材料', labor: '人工', outsource: '外包', service: '服务',
}[t ?? ''] ?? '-')
const statusLabel = (s?: string) => ({ active: '正常', paused: '暂停', blacklist: '黑名单' }[s ?? ''] ?? '-')
const paymentLabel = (p?: string) => ({ cash: '现款', '30days': '30天', '60days': '60天', '90days': '90天' }[p ?? ''] ?? '-')
</script>
