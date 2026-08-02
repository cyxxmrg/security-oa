<template>
  <div class="page-container">
    <div class="page-header">
      <el-button :icon="ArrowLeft" plain @click="goBack">返回列表</el-button>
      <span class="page-title" style="margin-left: 16px">质保期详情</span>
      <div class="header-actions">
        <el-button v-if="detail?.status === 'active'" type="success" :icon="Refresh" @click="showRenewDialog">续期</el-button>
        <el-button v-if="['active', 'expiring'].includes(detail?.status ?? '')" type="danger" :icon="CircleClose" @click="showTerminateDialog">终止</el-button>
        <el-tooltip :content="warrantyActive ? '创建维修工单' : '质保期已过期，无法创建维修工单'" placement="top">
          <el-button type="primary" :icon="Plus" :disabled="!warrantyActive" @click="createWorkOrder">创建维修工单</el-button>
        </el-tooltip>
      </div>
    </div>

    <div v-loading="loading" v-if="detail">
      <!-- 基础信息卡 -->
      <el-card shadow="hover" style="margin-bottom: 16px">
        <template #header>
          <div style="display: flex; align-items: center; gap: 12px">
            <span style="font-size: 18px; font-weight: 600">{{ detail.warranty_no }}</span>
            <el-tag :type="statusTagType(detail.status)" effect="plain">{{ statusLabel(detail.status) }}</el-tag>
            <el-tag type="info" effect="plain">{{ typeLabel(detail.warranty_type) }}</el-tag>
          </div>
        </template>
        <el-descriptions :column="3" border>
          <el-descriptions-item label="关联项目">{{ detail.project?.name || '-' }}</el-descriptions-item>
          <el-descriptions-item label="客户">{{ detail.customer?.name || '-' }}</el-descriptions-item>
          <el-descriptions-item label="设备">
            {{ detail.device ? `${detail.device.device_name} (${detail.device.serial_number})` : '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="开始日期">{{ detail.start_date }}</el-descriptions-item>
          <el-descriptions-item label="到期日期">{{ detail.end_date }}</el-descriptions-item>
          <el-descriptions-item label="质保期">{{ detail.warranty_period_months || 12 }} 个月</el-descriptions-item>
          <el-descriptions-item label="创建人">{{ detail.creator?.name || '-' }}</el-descriptions-item>
          <el-descriptions-item label="创建时间">{{ detail.created_at }}</el-descriptions-item>
          <el-descriptions-item label="备注" :span="3">{{ detail.coverage_scope || '-' }}</el-descriptions-item>
        </el-descriptions>
      </el-card>

      <!-- 续期记录 -->
      <el-card v-if="detail.renewed_from_id || (detail.renewals && detail.renewals.length > 0)" shadow="hover" style="margin-bottom: 16px">
        <template #header><span style="font-weight: 600">续期记录</span></template>
        <div v-if="detail.renewed_from_id">
          <el-link type="primary" :underline="false" @click="goDetail({ id: detail.renewed_from_id })">
            来自 {{ detail.renewed_from?.warranty_no || `#${detail.renewed_from_id}` }}
          </el-link>
        </div>
        <el-table v-if="detail.renewals?.length" :data="detail.renewals" stripe size="small" style="margin-top: 8px">
          <el-table-column prop="warranty_no" label="新单号" width="180" />
          <el-table-column prop="start_date" label="起" width="120" />
          <el-table-column prop="end_date"   label="止" width="120" />
          <el-table-column prop="status"     label="状态" width="100">
            <template #default="{ row }">
              <el-tag :type="statusTagType(row.status)" effect="plain" size="small">{{ statusLabel(row.status) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="created_at" label="续期时间" />
        </el-table>
      </el-card>

      <!-- 关联质保金 -->
      <el-card shadow="hover">
        <template #header>
          <div style="display: flex; justify-content: space-between; align-items: center">
            <span style="font-weight: 600">关联质保金</span>
            <el-button type="primary" link :icon="Plus" @click="createDeposit">新建质保金</el-button>
          </div>
        </template>
        <el-table v-if="detail.deposits?.length" :data="detail.deposits" stripe size="small">
          <el-table-column prop="id" label="编号" width="60" />
          <el-table-column prop="deposit_amount" label="质保金" width="120" />
          <el-table-column prop="hold_date" label="留置日期" width="120" />
          <el-table-column prop="status" label="状态" width="100">
            <template #default="{ row }">
              <el-tag effect="plain" size="small">{{ row.status }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="created_at" label="创建时间" />
        </el-table>
        <el-empty v-else description="暂无关联质保金" :image-size="80" />
      </el-card>
    </div>

    <!-- 续期/终止 dialog -->
    <el-dialog v-model="actionDialog.visible" :title="actionDialog.title" width="500px" :close-on-click-modal="false">
      <el-form :model="actionDialog.form" label-width="100px">
        <el-form-item v-if="actionDialog.type === 'renew'" label="续期月数">
          <el-input-number v-model="actionDialog.form.renew_months" :min="1" :max="600" />
        </el-form-item>
        <el-form-item label="原因/备注" required>
          <el-input v-model="actionDialog.form.reason" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="actionDialog.visible = false">取消</el-button>
        <el-button type="primary" @click="submitAction">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { ArrowLeft, Refresh, CircleClose, Plus } from '@element-plus/icons-vue'
import { warrantyApi } from '@/api/warranty'
import type { Warranty, TagType } from './types'

const route = useRoute()
const router = useRouter()
const loading = ref(false)
const detail = ref<Warranty | null>(null)

const id = computed(() => Number(route.params.id))

// 质保期内（在保/即将到期）可创建维修工单
const warrantyActive = computed(() => ['active', 'expiring'].includes(detail.value?.status ?? ''))

const statusLabel = (s?: string) => ({
  active: '在保', expiring: '即将到期', expired: '已过期', renewed: '已续约', terminated: '已终止',
} as Record<string, string>)[s ?? ''] || s || '-'
const typeLabel = (t?: string) => ({
  construction: '施工质保', equipment: '设备质保', product: '产品质保', service: '服务质保',
} as Record<string, string>)[t ?? ''] || t || '-'
const statusTagType = (s?: string): TagType => {
  if (s === 'active')     return 'success'
  if (s === 'expiring')   return 'warning'
  if (s === 'expired')    return 'danger'
  return 'info'
}

async function loadDetail() {
  loading.value = true
  try {
    const res = await warrantyApi.show(id.value)
    detail.value = res.data || res
  } catch (e: unknown) {
    ElMessage.error('加载失败: ' + ((e as { message?: string })?.message || 'unknown'))
  } finally {
    loading.value = false
  }
}

function goBack() {
  router.push('/project/warranty/list')
}

function goDetail(row: Warranty) {
  router.push(`/project/warranty/detail/${row.id}`)
}

function createDeposit() {
  router.push({ path: '/project/warranty/deposit', query: { warranty_id: id.value } })
}

// 创建维修工单（预填质保期 ID）
function createWorkOrder() {
  if (!id.value) return
  router.push(`/maintenance/work-orders/create?warranty_id=${id.value}`)
}

const actionDialog = reactive({
  visible: false,
  title: '',
  type: '' as '' | 'renew' | 'terminate',
  form: { renew_months: 12, reason: '' },
})

function showRenewDialog() {
  actionDialog.type = 'renew'
  actionDialog.title = '续期质保期'
  actionDialog.form = { renew_months: 12, reason: '' }
  actionDialog.visible = true
}

function showTerminateDialog() {
  actionDialog.type = 'terminate'
  actionDialog.title = '终止质保期'
  actionDialog.form = { renew_months: 12, reason: '' }
  actionDialog.visible = true
}

async function submitAction() {
  if (!actionDialog.form.reason) {
    ElMessage.warning('请填写原因/备注')
    return
  }
  try {
    if (actionDialog.type === 'renew') {
      await warrantyApi.renew(id.value, { renew_months: actionDialog.form.renew_months, remark: actionDialog.form.reason })
      ElMessage.success('质保期已续期')
    } else {
      await warrantyApi.terminate(id.value, { terminate_reason: actionDialog.form.reason, remark: actionDialog.form.reason })
      ElMessage.success('质保期已终止')
    }
    actionDialog.visible = false
    loadDetail()
  } catch (e: unknown) {
    ElMessage.error('操作失败: ' + ((e as { message?: string })?.message || 'unknown'))
  }
}

onMounted(loadDetail)
</script>
