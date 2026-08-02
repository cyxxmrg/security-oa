<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left">
        <el-button :icon="ArrowLeft" plain @click="goBack">返回</el-button>
        <span class="page-title">整改工单详情</span>
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
            <el-descriptions-item label="整改编号">{{ detail.code || '-' }}</el-descriptions-item>
            <el-descriptions-item label="项目">{{ detail.project?.name || detail.project_id || '-' }}</el-descriptions-item>
            <el-descriptions-item label="责任人">{{ detail.owner?.name || detail.owner_id || '-' }}</el-descriptions-item>
            <el-descriptions-item label="截止日期">{{ detail.deadline || '-' }}</el-descriptions-item>
            <el-descriptions-item label="完成时间" :span="2">{{ detail.completed_at || '-' }}</el-descriptions-item>
            <el-descriptions-item label="整改内容" :span="3">
              <div style="white-space: pre-wrap">{{ detail.title || '-' }}</div>
            </el-descriptions-item>
            <el-descriptions-item v-if="detail.result" label="整改结果" :span="3">
              <div style="white-space: pre-wrap">{{ detail.result }}</div>
            </el-descriptions-item>
            <el-descriptions-item label="创建人">{{ detail.creator?.name || '-' }}</el-descriptions-item>
            <el-descriptions-item label="创建时间" :span="2">{{ detail.created_at || '-' }}</el-descriptions-item>
          </el-descriptions>
        </el-card>
      </template>
      <el-empty v-else-if="!loading" description="未找到整改工单数据" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { ArrowLeft } from '@element-plus/icons-vue'
import { rectificationApi } from '@/api/construction'
import { unwrapItem } from '@/utils/response'
import type { Rectification } from '../types'

const route = useRoute()
const router = useRouter()

const loading = ref(false)
const detail = ref<Rectification | null>(null)

const statusOptions = [
  { value: 'pending',   label: '待处理' },
  { value: 'in_progress', label: '处理中' },
  { value: 'completed', label: '已完成' },
  { value: 'rejected',  label: '已驳回' },
]
const statusLabel = (s: string) => statusOptions.find(x => x.value === s)?.label || s || '-'
const statusTagType = (s: string): string => ({
  pending: 'info', in_progress: 'warning', completed: 'success', rejected: 'danger',
} as Record<string, string>)[s] || 'info'

const id = computed(() => Number(route.params.id))

const loadDetail = async () => {
  if (!id.value) return
  loading.value = true
  try {
    const res = await rectificationApi.show(id.value)
    detail.value = unwrapItem(res) || null
  } catch {
    detail.value = null
  } finally {
    loading.value = false
  }
}

const goBack = () => router.push('/construction/rectification')

watch(id, () => { if (id.value) loadDetail() })
onMounted(() => { if (id.value) loadDetail() })
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
.info-card { box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04) !important; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.card-title { font-weight: 600; color: #303133; }
</style>
