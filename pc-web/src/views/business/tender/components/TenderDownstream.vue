<template>
  <div>
    <div class="downstream-summary">
      <el-row :gutter="16">
        <el-col :span="6">
          <div class="summary-card">
            <div class="label">采购单</div>
            <div class="value">{{ downstream.summary.po_count }} 个</div>
            <div class="sub">¥ {{ Number(downstream.summary.total_amount || 0).toLocaleString() }}</div>
          </div>
        </el-col>
        <el-col :span="6">
          <div class="summary-card">
            <div class="label">应付款总额</div>
            <div class="value">¥ {{ Number(downstream.summary.payable_total || 0).toLocaleString() }}</div>
            <div class="sub">已付 ¥{{ Number(downstream.summary.payable_paid || 0).toLocaleString() }}</div>
          </div>
        </el-col>
        <el-col :span="6">
          <div class="summary-card">
            <div class="label">入库单</div>
            <div class="value">{{ downstream.summary.inbound_count }} 张</div>
            <div class="sub">{{ downstream.summary.inbound_count > 0 ? '已完成入库' : '等待收货' }}</div>
          </div>
        </el-col>
        <el-col :span="6">
          <div class="summary-card">
            <div class="label">业务状态</div>
            <div class="value">{{ downstream.purchase_orders?.[0]?.status || '-' }}</div>
            <div class="sub">PO 状态</div>
          </div>
        </el-col>
      </el-row>
    </div>

    <h4 class="block-title">采购单详情</h4>
    <template v-for="po in downstream.purchase_orders" :key="po.id">
      <el-card shadow="never" class="po-card">
        <template #header>
          <div class="po-header">
            <span class="po-code">{{ po.code }} <el-tag size="small" type="info" v-if="po.po_no && po.po_no !== po.code">{{ po.po_no }}</el-tag></span>
            <el-tag :type="po.status === 'fulfilled' ? 'success' : po.status === 'pending' ? 'warning' : 'primary'" size="small">
              {{ po.status }}
            </el-tag>
            <el-button link type="primary" @click="$emit('go-po', po.id)">查看采购详情 →</el-button>
          </div>
        </template>
        <div class="po-title">{{ po.title }}</div>
        <div class="po-amount">总金额: <strong>¥ {{ Number(po.total_amount || 0).toLocaleString() }}</strong> · {{ po.items_count }} 项物料</div>

        <h5 class="inner-title">物料清单</h5>
        <el-table :data="po.items" border size="small" empty-text="无明细">
          <el-table-column prop="name" label="物料/服务" min-width="180" />
          <el-table-column prop="spec" label="规格" width="160" />
          <el-table-column prop="quantity" label="数量" width="100" align="right" />
          <el-table-column prop="unit" label="单位" width="80" />
          <el-table-column prop="unit_price" label="单价" width="120" align="right">
            <template #default="{ row }">¥ {{ Number(row.unit_price || 0).toLocaleString() }}</template>
          </el-table-column>
          <el-table-column prop="total" label="小计" width="120" align="right">
            <template #default="{ row }">¥ {{ Number(row.total || 0).toLocaleString() }}</template>
          </el-table-column>
        </el-table>

        <h5 class="inner-title">应付款 ({{ po.payables.length }})</h5>
        <el-table :data="po.payables" border size="small" empty-text="无应付款">
          <el-table-column prop="ref_no" label="应付单号" width="180" />
          <el-table-column prop="amount" label="金额" width="140" align="right">
            <template #default="{ row }">¥ {{ Number(row.amount || 0).toLocaleString() }}</template>
          </el-table-column>
          <el-table-column prop="paid_amount" label="已付" width="140" align="right">
            <template #default="{ row }">¥ {{ Number(row.paid_amount || 0).toLocaleString() }}</template>
          </el-table-column>
          <el-table-column prop="remaining" label="未付" width="140" align="right">
            <template #default="{ row }">¥ {{ Number(row.remaining || 0).toLocaleString() }}</template>
          </el-table-column>
          <el-table-column prop="status" label="状态" width="100">
            <template #default="{ row }">
              <el-tag size="small" :type="row.status === 'paid' ? 'success' : row.status === 'partial' ? 'warning' : 'info'">
                {{ row.status }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="due_date" label="到期" width="120">
            <template #default="{ row }">{{ row.due_date || '-' }}</template>
          </el-table-column>
          <el-table-column label="付款次数" width="100" align="center">
            <template #default="{ row }">{{ row.paid_count }} 次</template>
          </el-table-column>
        </el-table>
      </el-card>
    </template>

    <h4 class="block-title" v-if="downstream.stock_records?.length">入库记录</h4>
    <el-table v-if="downstream.stock_records?.length" :data="downstream.stock_records" border size="small">
      <el-table-column prop="record_no" label="入库单号" width="180" />
      <el-table-column prop="type" label="类型" width="100">
        <template #default="{ row }">
          <el-tag size="small" :type="row.type === 'in' ? 'success' : 'warning'">{{ row.type }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="quantity" label="数量" width="100" align="right" />
      <el-table-column prop="related_id" label="关联发货单" width="120" />
      <el-table-column prop="created_at" label="入库时间" width="180" />
    </el-table>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  downstream: Record<string, unknown>
}>()

defineEmits<{
  'go-po': [id: number]
}>()
</script>

<style scoped>
.block-title { margin: 16px 0 8px; font-size: 14px; font-weight: 600; }
.downstream-summary { margin-bottom: 16px; }
.summary-card { padding: 16px; background: linear-gradient(135deg, #f5f7fa 0%, #e8eef5 100%); border-radius: 8px; border-left: 3px solid #409EFF; }
.summary-card .label { font-size: 12px; color: #666; }
.summary-card .value { font-size: 22px; font-weight: 600; color: #303133; margin: 4px 0; }
.summary-card .sub { font-size: 12px; color: #909399; }
.po-card { margin-bottom: 16px; }
.po-header { display: flex; align-items: center; gap: 12px; }
.po-code { font-weight: 600; font-size: 14px; }
.po-title { color: #303133; margin: 8px 0; }
.po-amount { color: #606266; font-size: 13px; margin-bottom: 8px; }
.inner-title { margin: 12px 0 8px; font-size: 13px; font-weight: 600; color: #303133; }
</style>
