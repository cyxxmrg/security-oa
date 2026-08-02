<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">供应商详情</span>
      <el-button :icon="ArrowLeft" @click="$router.back()">返回</el-button>
    </div>

    <template v-if="detail">
      <el-card shadow="never" style="margin-bottom: 16px">
        <el-descriptions :column="3" border>
          <el-descriptions-item label="编号">{{ detail.supplier.code }}</el-descriptions-item>
          <el-descriptions-item label="名称">{{ detail.supplier.name }}</el-descriptions-item>
          <el-descriptions-item label="类型">{{ detail.supplier.type_label || detail.supplier.type }}</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="detail.supplier.status === 'active' ? 'success' : 'danger'">
              {{ detail.supplier.status_label || detail.supplier.status }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="评级">
            <el-rate v-model="detail.supplier.rating" disabled />
          </el-descriptions-item>
          <el-descriptions-item label="账期">{{ detail.supplier.payment_terms }}</el-descriptions-item>
          <el-descriptions-item label="电话">{{ detail.supplier.phone || '-' }}</el-descriptions-item>
          <el-descriptions-item label="邮箱">{{ detail.supplier.email || '-' }}</el-descriptions-item>
          <el-descriptions-item label="联系人">{{ detail.supplier.contact_person || '-' }}</el-descriptions-item>
          <el-descriptions-item label="地址" :span="3">{{ detail.supplier.address || '-' }}</el-descriptions-item>
        </el-descriptions>
      </el-card>

      <el-row :gutter="16">
        <el-col :span="6">
          <el-card shadow="never"><SummaryStat label="累计应付" :value="detail.payable_total" color="#0C447C" /></el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="never"><SummaryStat label="已付款" :value="detail.paid_total" color="#67c23a" /></el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="never"><SummaryStat label="未付余额" :value="detail.balance_total" color="#e6a23c" /></el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="never"><SummaryStat label="报价/平均评分" :value="`${detail.quote_count} / ${detail.avg_rating}`" color="#909399" /></el-card>
        </el-col>
      </el-row>

      <el-tabs v-model="activeTab" style="margin-top: 16px">
        <el-tab-pane label="联系人" name="contacts">
          <el-table :data="detail.supplier.contacts ?? []" border>
            <el-table-column prop="name" label="姓名" />
            <el-table-column prop="position" label="职位" />
            <el-table-column prop="phone" label="手机" />
            <el-table-column prop="tel" label="座机" />
            <el-table-column prop="email" label="邮箱" />
            <el-table-column prop="wechat" label="微信" />
            <el-table-column label="主联系人" width="100">
              <template #default="{ row }">
                <el-tag v-if="row.is_primary" type="success" size="small">主</el-tag>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <el-tab-pane label="评价" name="eval">
          <div style="margin-bottom: 8px">
            <el-button type="primary" :icon="Plus" @click="showEvalDialog = true">新增评价</el-button>
          </div>
          <el-table :data="evaluations" border>
            <el-table-column prop="eval_date" label="日期" width="120" />
            <el-table-column label="质量" width="80" align="center">
              <template #default="{ row }"><el-rate v-model="row.quality_score" disabled /></template>
            </el-table-column>
            <el-table-column label="交付" width="80" align="center">
              <template #default="{ row }"><el-rate v-model="row.delivery_score" disabled /></template>
            </el-table-column>
            <el-table-column label="服务" width="80" align="center">
              <template #default="{ row }"><el-rate v-model="row.service_score" disabled /></template>
            </el-table-column>
            <el-table-column label="价格" width="80" align="center">
              <template #default="{ row }"><el-rate v-model="row.price_score" disabled /></template>
            </el-table-column>
            <el-table-column prop="overall_score" label="综合" width="80" />
            <el-table-column prop="pros" label="优点" show-overflow-tooltip />
            <el-table-column prop="cons" label="不足" show-overflow-tooltip />
            <el-table-column prop="evaluator.name" label="评价人" width="100" />
          </el-table>
        </el-tab-pane>

        <el-tab-pane label="附件" name="attach">
          <el-table :data="detail.supplier.attachments ?? []" border>
            <el-table-column prop="name" label="文件名" />
            <el-table-column prop="type" label="类型" width="120" />
            <el-table-column prop="file_size" label="大小" width="120">
              <template #default="{ row }">{{ formatSize(row.file_size) }}</template>
            </el-table-column>
            <el-table-column prop="expire_date" label="到期日" width="120" />
            <el-table-column label="操作" width="120">
              <template #default="{ row }">
                <el-button size="small" link type="primary" @click="downloadFile(row)">下载</el-button>
              </template>
            </el-table-column>
          </el-table>
          <el-empty v-if="!(detail.supplier.attachments ?? []).length" description="暂无附件" />
        </el-tab-pane>
      </el-tabs>
    </template>
    <el-empty v-else description="加载中..." />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, h } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Plus, ArrowLeft } from '@element-plus/icons-vue'
import { supplier } from '@/api/supplier'
import type { SupplierDetail, SupplierEvaluation } from '@/api/supplier'

// 局部小组件：金额统计
const SummaryStat = {
  props: ['label', 'value', 'color'],
  setup(p: Record<string, unknown>) {
    return () => h('div', { style: 'text-align:center' }, [
      h('div', { style: 'color:#999;font-size:12px' }, p.label),
      h('div', { style: `font-size:22px;font-weight:700;color:${p.color}` }, p.value),
    ])
  },
}

const route = useRoute()
const detail = ref<SupplierDetail | null>(null)
const evaluations = ref<SupplierEvaluation[]>([])
const activeTab = ref('contacts')
const showEvalDialog = ref(false)

const load = async () => {
  const id = Number(route.params.id)
  const res = await supplier.get(id)
  detail.value = res.data
  const er = await supplier.evaluations(id)
  evaluations.value = er?.data?.data ?? er?.data ?? []
}

const formatSize = (b?: number) => {
  if (!b) return '-'
  if (b < 1024) return `${b} B`
  if (b < 1024 * 1024) return `${(b / 1024).toFixed(1)} KB`
  return `${(b / 1024 / 1024).toFixed(2)} MB`
}

const downloadFile = (file: Record<string, unknown>) => {
  ElMessage.info(`下载 ${file.name}（占位）`)
}

onMounted(load)
</script>

<style lang="scss" scoped>
.page-container { padding: 16px; background: #f5f7fa; min-height: calc(100vh - 60px); }
.page-header {
  display: flex; justify-content: space-between; align-items: center;
  background: #fff; padding: 16px 20px; border-radius: 8px; margin-bottom: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  .page-title {
    font-size: 18px; font-weight: 600; color: #0C447C;
    border-left: 4px solid #0C447C; padding-left: 10px;
  }
}
</style>
