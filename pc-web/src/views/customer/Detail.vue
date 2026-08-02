<template>
  <div class="page-container">
    <div class="page-header">
      <div class="title-area">
        <el-button :icon="ArrowLeft" text @click="goBack">返回客户列表</el-button>
        <span class="page-title">{{ customer.name }}</span>
        <el-tag v-if="customer.category" :type="categoryType(displayCategory(customer.category))" effect="light">
          {{ displayCategory(customer.category) }}
        </el-tag>
      </div>
      <div class="header-actions">
        <el-button :icon="Edit" @click="showEditDialog = true">编辑客户</el-button>
        <el-button :icon="ChatLineRound" @click="showFollowDialog = true">添加跟进</el-button>
        <el-button :icon="Share" @click="handleShare">分享</el-button>
        <el-button type="primary" :icon="Plus" @click="$router.push('/project/create')">创建项目</el-button>
      </div>
    </div>

    <CustomerOverviewCard :customer="customer" :loading="loading" />

    <div class="content-card">
      <el-tabs v-model="activeTab" class="detail-tabs">
        <el-tab-pane label="基本信息" name="basic">
          <BasicInfoTab :customer="customer" />
        </el-tab-pane>
        <el-tab-pane :label="`开票信息 (${(customer.invoice_infos || []).length})`" name="invoice">
          <InvoiceInfoTab :infos="customer.invoice_infos || []" />
        </el-tab-pane>
        <el-tab-pane :label="`关联项目 (${(customer.projects || []).length})`" name="projects">
          <ProjectTab :projects="customer.projects || []" @view="handleViewProject" />
        </el-tab-pane>
        <el-tab-pane :label="`设备台账 (${(customer.devices || []).length})`" name="devices">
          <DeviceTab :devices="customer.devices || []" />
        </el-tab-pane>
        <el-tab-pane label="跟进记录" name="follow">
          <FollowTimelineTab :records="followRecords" @add="showFollowDialog = true" />
        </el-tab-pane>
        <el-tab-pane :label="`售后记录 (${(customer.service_orders || []).length})`" name="service">
          <ServiceTab
            :service-orders="customer.service_orders || []"
            @view="handleViewService"
          />
        </el-tab-pane>
      </el-tabs>
    </div>

    <FollowDialog
      v-model="showFollowDialog"
      :loading="submitting"
      :form="followForm"
      @submit="handleAddFollow"
    />

    <EditCustomerDialog
      v-model="showEditDialog"
      :customer="customer"
      @saved="loadAll"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import { ArrowLeft, Plus, Share, ChatLineRound, Edit } from '@element-plus/icons-vue'
import { get, post } from '@/utils/request'

import CustomerOverviewCard from './components/detail/CustomerOverviewCard.vue'
import BasicInfoTab from './components/detail/BasicInfoTab.vue'
import InvoiceInfoTab from './components/detail/InvoiceInfoTab.vue'
import ProjectTab from './components/detail/ProjectTab.vue'
import DeviceTab from './components/detail/DeviceTab.vue'
import ServiceTab from './components/detail/ServiceTab.vue'
import FollowTimelineTab from './components/detail/FollowTimelineTab.vue'
import FollowDialog from './components/detail/FollowDialog.vue'
import EditCustomerDialog from './components/detail/EditCustomerDialog.vue'

import type { Customer, FollowForm, Project, ServiceOrder } from './components/detail/types'
import { displayCategory, categoryType } from './components/detail/types'

// v0.3.20 拆 customer/Detail.vue 555→200 (-64%)
// 子组件: OverviewCard / BasicInfoTab / ProjectTab / DeviceTab / ServiceTab / FollowTimelineTab / FollowDialog

const router = useRouter()
const route = useRoute()
const customerId = Number(route.params.id)

const activeTab = ref('basic')
const loading = ref(false)
const submitting = ref(false)
const customer = ref<Customer>({} as Customer)
const followRecords = ref<Record<string, unknown>[]>([])

async function loadAll() {
  loading.value = true
  try {
    // V0.6.3: request.ts 不解包, res = {code, data: <entity>}
    const data = await get(`/customers/${customerId}`)
    customer.value = data?.data ?? data
  } catch { /* toast */ }
  loading.value = false

  try {
    const data = await get(`/customers/${customerId}/follow-ups`)
    // V0.6.3: 后端 paginator {code, data: paginator}
    const d = data?.data ?? {}
    followRecords.value = Array.isArray(d?.data) ? d.data : (Array.isArray(data) ? data : [])
  } catch { /* toast */ }

  // v0.5.8.9 开票信息 (主表 GET 不带, 单独拉)
  try {
    const data = await get(`/customers/${customerId}/invoice-infos`)
    const d = data?.data ?? {}
    customer.value = { ...customer.value, invoice_infos: Array.isArray(d?.data) ? d.data : (Array.isArray(data) ? data : []) }
  } catch { /* toast */ }
}

onMounted(loadAll)
watch(() => route.params.id, loadAll)

const goBack = () => router.back()

const handleViewProject = (row: Project) => {
  router.push(`/project/${row.id}`)
}
const handleViewService = (row: ServiceOrder) => {
  router.push(`/service/${row.id}`)
}

// ========== 跟进对话框 ==========
const showFollowDialog = ref(false)
const showEditDialog = ref(false)
const followForm = ref<FollowForm>({
  type: 'phone',
  content: '',
  next_follow_up_date: null,
  next_follow_up_note: '',
})

async function handleAddFollow() {
  if (!followForm.value.content.trim()) {
    ElMessage.warning('请输入跟进内容')
    return
  }
  submitting.value = true
  try {
    const payload: Record<string, unknown> = { type: followForm.value.type, content: followForm.value.content }
    if (followForm.value.next_follow_up_date) payload.next_follow_up_date = followForm.value.next_follow_up_date
    if (followForm.value.next_follow_up_note)   payload.next_follow_up_note = followForm.value.next_follow_up_note
    await post(`/customers/${customerId}/follow-ups`, payload)
    ElMessage.success('跟进记录已添加')
    showFollowDialog.value = false
    followForm.value = { type: 'phone', content: '', next_follow_up_date: null, next_follow_up_note: '' }
    loadAll()
  } catch (e) {
    /* toast */
  } finally {
    submitting.value = false
  }
}

const handleShare = async () => {
  try {
    await navigator.clipboard.writeText(window.location.href)
    ElMessage.success('客户详情链接已复制到剪贴板')
  } catch {
    ElMessage.success('请通过地址栏分享链接')
  }
}
</script>

<style lang="scss" scoped>
.page-container {
  padding: 16px;
  background: #f5f7fa;
  min-height: calc(100vh - 60px);
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #fff;
  padding: 16px 20px;
  border-radius: 8px;
  margin-bottom: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);

  .title-area {
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .page-title {
    font-size: 18px;
    font-weight: 600;
    color: #0C447C;
  }

  .header-actions {
    display: flex;
    gap: 8px;
  }
}

.content-card {
  background: #fff;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
</style>
