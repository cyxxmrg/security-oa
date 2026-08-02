<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">消息中心</span>
      <el-button text type="primary" :loading="markingAll" @click="handleMarkAllRead">全部已读</el-button>
    </div>
    <el-tabs v-model="activeTab" @tab-change="loadMessages">
      <el-tab-pane label="全部" name="all" />
      <el-tab-pane name="unread">
        <template #label>
          未读
          <el-badge v-if="unreadCount > 0" :value="unreadCount" :max="99" type="danger" style="margin-left:4px" />
        </template>
      </el-tab-pane>
      <el-tab-pane label="审批通知" name="approval" />
      <el-tab-pane label="工单通知" name="service" />
      <el-tab-pane label="系统通知" name="system" />
    </el-tabs>
    <div class="content-card" style="margin-top:16px">
      <el-table :data="messages" v-loading="loading" stripe style="width:100%">
        <el-table-column label="消息" min-width="400">
          <template #default="{ row }">
            <div style="display:flex;gap:12px;align-items:flex-start;padding:8px 0">
              <el-avatar :size="38" :style="{ background: iconBg(row.type) }">
                <el-icon :size="18" v-if="row.type === 'approval'"><Bell /></el-icon>
                <el-icon :size="18" v-if="row.type === 'service'"><SetUp /></el-icon>
                <el-icon :size="18" v-if="row.type === 'system'"><Warning /></el-icon>
              </el-avatar>
              <div style="flex:1;min-width:0">
                <div style="display:flex;align-items:center;gap:8px">
                  <span v-if="!row.read_at" style="font-weight:600;font-size:14px;color:#2c3e50">{{ row.data?.title || '通知' }}</span>
                  <span v-else style="font-size:14px;color:#2c3e50">{{ row.data?.title || '通知' }}</span>
                  <el-tag v-if="!row.read_at" size="small" type="danger">未读</el-tag>
                </div>
                <p style="font-size:13px;color:#909399;margin:4px 0 0 0;line-height:1.5">{{ row.data?.content || '' }}</p>
                <span style="font-size:12px;color:#c0c4cc">{{ formatTime(row.created_at) }}</span>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="120" fixed="right">
          <template #default="{ row }">
            <el-button v-if="!row.read_at" text type="primary" size="small" @click="handleMarkRead(row)">标为已读</el-button>
            <span v-else style="color:#c0c4cc;font-size:13px">已读</span>
          </template>
        </el-table-column>
      </el-table>
      <el-empty v-if="!loading && messages.length === 0" description="暂无消息" />
      <el-pagination
        v-if="total > pageSize"
        v-model:current-page="currentPage"
        :page-size="pageSize"
        :total="total"
        layout="total,prev,pager,next"
        style="margin-top:16px;text-align:right"
      />
    </div>
  </div>
</template>
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Bell, SetUp, Warning } from '@element-plus/icons-vue'
import { get, post } from '@/utils/request'
import { unwrapList, unwrapTotal } from '@/utils/response'

const activeTab = ref('all')
const messages = ref<Record<string, unknown>[]>([])
const loading = ref(false)
const markingAll = ref(false)
const unreadCount = ref(0)
const currentPage = ref(1)
const pageSize = 20
const total = ref(0)

function iconBg(type: string) {
  const m: Record<string, unknown> = { approval: '#BA7517', service: '#A32D2D', system: '#534AB7' }
  return m[type] || '#909399'
}

function formatTime(t: string) {
  if (!t) return ''
  const d = new Date(t)
  const now = new Date()
  const diff = (now.getTime() - d.getTime()) / 1000
  if (diff < 60) return '刚刚'
  if (diff < 3600) return `${Math.floor(diff / 60)}分钟前`
  if (diff < 86400) return `${Math.floor(diff / 3600)}小时前`
  return t.slice(0, 16).replace('T', ' ')
}

async function loadMessages() {
  loading.value = true
  try {
    const params: Record<string, unknown> = { page: currentPage.value }
    if (activeTab.value === 'unread') params.unread = 1
    else if (activeTab.value !== 'all') params.type = activeTab.value
    const res = await get<Record<string, unknown>>('/notifications', params)
    if (res.code === 0) {
      messages.value = unwrapList(res)
      total.value = unwrapTotal(res)
    }
  } finally {
    loading.value = false
  }
  loadUnreadCount()
}

async function loadUnreadCount() {
  try {
    const res = await get<Record<string, unknown>>('/notifications/unread-count')
    if (res.code === 0) unreadCount.value = res.data?.count ?? 0
  } catch {}
}

async function handleMarkRead(row: Record<string, unknown>) {
  try {
    await post('/notifications/mark-read', { notification_id: row.id })
    row.read_at = new Date().toISOString()
    ElMessage.success('已标为已读')
    loadUnreadCount()
  } catch (e: unknown) {
    ElMessage.error(e.message || '操作失败')
  }
}

async function handleMarkAllRead() {
  markingAll.value = true
  try {
    await post('/notifications/mark-all-read', {})
    messages.value.forEach(m => m.read_at = new Date().toISOString())
    unreadCount.value = 0
    ElMessage.success('已全部标为已读')
  } catch (e: unknown) {
    ElMessage.error(e.message || '操作失败')
  } finally {
    markingAll.value = false
  }
}

onMounted(() => { loadMessages() })
</script>
<style lang="scss" scoped>
.content-card { background:#fff;border-radius:8px;padding:20px; }
</style>
