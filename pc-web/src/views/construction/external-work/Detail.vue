<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left">
        <el-button :icon="ArrowLeft" plain @click="goBack">返回</el-button>
        <span class="page-title">发包详情</span>
      </div>
    </div>

    <div v-loading="loading">
      <template v-if="detail">
        <el-card shadow="never" class="info-card">
          <template #header>
            <div class="card-header">
              <span class="card-title">基础信息</span>
              <el-tag :type="statusTagType(detail.status)" effect="plain" size="small">
                {{ statusLabel(detail.status) }}
              </el-tag>
            </div>
          </template>
          <el-descriptions :column="3" border size="default">
            <el-descriptions-item label="发包编号">{{ detail.code || '-' }}</el-descriptions-item>
            <el-descriptions-item label="项目">{{ detail.project?.name || detail.project_id || '-' }}</el-descriptions-item>
            <el-descriptions-item label="投标截止">{{ detail.deadline || '-' }}</el-descriptions-item>
            <el-descriptions-item label="发包标题" :span="3">{{ detail.title || '-' }}</el-descriptions-item>
            <el-descriptions-item label="发包预算" :span="2">
              <span style="color:#0C447C;font-weight:600">¥ {{ formatMoney(detail.budget) }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="中标价">
              <span v-if="detail.award_amount" style="color:#67c23a;font-weight:600">¥ {{ formatMoney(detail.award_amount) }}</span>
              <span v-else class="muted">-</span>
            </el-descriptions-item>
            <el-descriptions-item label="发包范围" :span="3">
              <div style="white-space: pre-wrap">{{ detail.scope || '-' }}</div>
            </el-descriptions-item>
            <el-descriptions-item v-if="detail.remark" label="备注" :span="3">{{ detail.remark }}</el-descriptions-item>
            <el-descriptions-item label="创建人">{{ detail.creator?.name || '-' }}</el-descriptions-item>
            <el-descriptions-item label="创建时间" :span="2">{{ detail.created_at || '-' }}</el-descriptions-item>
          </el-descriptions>
        </el-card>

        <!-- 投标列表 -->
        <el-card shadow="never" class="info-card">
          <template #header>
            <div class="card-header">
              <span class="card-title">投标列表（{{ bids.length }}）</span>
              <el-button v-if="detail.status === 'open'" type="primary" :icon="Promotion" plain size="small" @click="goBid">提交投标</el-button>
            </div>
          </template>
          <el-table :data="bids" v-loading="bidsLoading" border size="small" stripe>
            <el-table-column prop="id" label="投标ID" width="80" align="center" />
            <el-table-column label="投标方" min-width="160" show-overflow-tooltip>
              <template #default="{ row }">
                {{ row.supplier_name || row.bidder_name || row.supplier?.name || '-' }}
              </template>
            </el-table-column>
            <el-table-column label="投标金额" width="130" align="right">
              <template #default="{ row }">
                <span style="color:#0C447C;font-weight:600">¥ {{ formatMoney(row.amount) }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="duration_days" label="工期" width="80" align="center">
              <template #default="{ row }">{{ row.duration_days || '-' }} 天</template>
            </el-table-column>
            <el-table-column label="状态" width="100" align="center">
              <template #default="{ row }">
                <el-tag :type="row.status === 'awarded' ? 'success' : (row.status === 'rejected' ? 'danger' : 'info')" effect="plain" size="small">
                  {{ row.status === 'awarded' ? '中标' : (row.status === 'rejected' ? '未中标' : '待定') }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="created_at" label="投标时间" width="160" align="center" show-overflow-tooltip />
          </el-table>
          <el-empty v-if="!bidsLoading && bids.length === 0" description="暂无投标" :image-size="80" />
        </el-card>
      </template>
      <el-empty v-else-if="!loading" description="未找到发包数据" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Promotion } from '@element-plus/icons-vue'
import { externalWorkApi } from '@/api/construction'
import { unwrapList, unwrapItem } from '@/utils/response'
import type { ExternalWork, Bid } from '../types'

const route = useRoute()
const router = useRouter()

const loading = ref(false)
const bidsLoading = ref(false)
const detail = ref<ExternalWork | null>(null)
const bids = ref<Bid[]>([])

const statusOptions = [
  { value: 'draft',     label: '草稿' },
  { value: 'open',      label: '招标中' },
  { value: 'bidding',   label: '评标中' },
  { value: 'awarded',   label: '已定标' },
  { value: 'closed',    label: '已关闭' },
]
const statusLabel = (s: string) => statusOptions.find(x => x.value === s)?.label || s || '-'
const statusTagType = (s: string): string => ({
  draft: 'info', open: 'warning', bidding: 'warning', awarded: 'success', closed: 'danger',
} as Record<string, string>)[s] || 'info'

const formatMoney = (n: number | string | null | undefined) => Number(n || 0).toLocaleString('zh-CN', { maximumFractionDigits: 2 })

const workId = computed(() => Number(route.params.id))

const loadDetail = async () => {
  if (!workId.value) return
  loading.value = true
  try {
    const res = await externalWorkApi.show(workId.value)
    detail.value = unwrapItem(res) || null
  } catch {
    detail.value = null
  } finally {
    loading.value = false
  }
}

const loadBids = async () => {
  if (!workId.value) return
  bidsLoading.value = true
  try {
    const res = await externalWorkApi.listBids(workId.value)
    bids.value = unwrapList(res)
  } catch {
    bids.value = []
  } finally {
    bidsLoading.value = false
  }
}

const goBack = () => router.push('/construction/external-work')
const goBid = () => router.push(`/construction/external-work/bid/${workId.value}`)

watch(workId, () => {
  if (workId.value) {
    loadDetail()
    loadBids()
  }
})
onMounted(() => {
  if (workId.value) {
    loadDetail()
    loadBids()
  }
})
</script>

<style lang="scss" scoped>
.page-container { padding: 16px; background: #f5f7fa; min-height: calc(100vh - 60px); }
.page-header {
  display: flex; justify-content: space-between; align-items: center;
  background: #fff; padding: 16px 20px; border-radius: 8px; margin-bottom: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  .header-left { display: flex; align-items: center; gap: 12px; }
  .page-title {
    font-size: 18px; font-weight: 600; color: #0C447C;
    border-left: 4px solid #0C447C; padding-left: 10px;
  }
}
.info-card { margin-bottom: 12px; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.card-title { font-weight: 600; color: #303133; }
.muted { color: #c0c4cc; }
</style>
