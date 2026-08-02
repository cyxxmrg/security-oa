<template>
  <el-card class="temp-role-card" v-if="visible" shadow="hover">
    <template #header>
      <div class="card-header">
        <div class="header-left">
          <el-icon class="header-icon"><Clock /></el-icon>
          <span class="header-title">即将过期的临时角色</span>
          <el-tag type="warning" size="small" effect="dark" round style="margin-left: 8px;">{{ rows.length }}</el-tag>
        </div>
        <el-button link type="primary" size="small" @click="goLog">
          查看全部
          <el-icon><ArrowRight /></el-icon>
        </el-button>
      </div>
    </template>

    <div v-if="loading" class="loading">
      <el-icon class="is-loading"><Loading /></el-icon>
      <span>加载中...</span>
    </div>

    <div v-else-if="!rows.length" class="empty">
      <el-icon class="empty-icon"><CircleCheckFilled /></el-icon>
      <span>当前没有 7 天内即将过期的临时角色</span>
    </div>

    <div v-else class="role-list">
      <div
        v-for="(row, idx) in rows"
        :key="idx"
        class="role-item"
        :class="`level-${level(row.days_left)}`"
      >
        <div class="role-user">
          <el-avatar :size="32" :style="{ background: levelColor(row.days_left), color: 'white' }">
            {{ (row.name || row.username || '?').charAt(0).toUpperCase() }}
          </el-avatar>
          <div class="user-info">
            <div class="user-name">{{ row.name || row.username }}</div>
            <div class="user-username">@{{ row.username }}</div>
          </div>
        </div>

        <el-tag :type="levelType(row.days_left)" size="small" effect="dark" class="role-tag">
          {{ row.role_name }}
        </el-tag>

        <div class="role-expire">
          <el-icon><Timer /></el-icon>
          <span :class="`expire-text level-${level(row.days_left)}`">
            {{ row.days_left }} 天后到期
          </span>
          <div class="expire-date">{{ formatDate(row.expires_at) }}</div>
        </div>

        <el-tooltip :content="row.reason || '无理由'" placement="top">
          <div class="role-reason" v-if="row.reason">
            <el-icon><EditPen /></el-icon>
            <span>{{ row.reason }}</span>
          </div>
          <div class="role-reason muted" v-else>
            <el-icon><EditPen /></el-icon>
            <span>无理由</span>
          </div>
        </el-tooltip>
      </div>
    </div>
  </el-card>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Clock, ArrowRight, Loading, CircleCheckFilled, Timer, EditPen } from '@element-plus/icons-vue'
import { get } from '@/utils/request'
import { unwrapList } from '@/utils/response'

const router = useRouter()
const loading = ref(false)
const rows = ref<Record<string, unknown>[]>([])

// 只有 admin 才显示
const visible = ref(false)

const level = (days: number) => {
  if (days <= 1) return 'critical'
  if (days <= 3) return 'danger'
  if (days <= 7) return 'warning'
  return 'info'
}

const levelType = (days: number): string => {
  if (days <= 1) return 'danger'
  if (days <= 3) return 'danger'
  if (days <= 7) return 'warning'
  return 'info'
}

const levelColor = (days: string | number) => {
  const d = Number(days)
  if (d <= 3) return '#F56C6C'
  if (d <= 7) return '#E6A23C'
  return '#909399'
}

const formatDate = (s: string) => {
  if (!s) return ''
  const d = new Date(s)
  return `${d.getMonth()+1}/${d.getDate()} ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`
}

const goLog = () => {
  router.push('/settings/permission-log')
}

const loadData = async () => {
  loading.value = true
  try {
    // V0.6.3: res = {code, data: <expiring roles>}
    const res = await get('/roles/expiring', { within_days: 7 })
    rows.value = unwrapList(res)
    visible.value = true
  } catch (e: unknown) {
    // 非 admin 会 403, 不显示
    visible.value = false
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadData()
})
</script>

<style scoped lang="scss">
.temp-role-card {
  margin-bottom: 16px;
  border-left: 4px solid #E6A23C;
  background: linear-gradient(to right, #fff8e1 0%, #fff 100%);
}
.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.header-left { display: flex; align-items: center; gap: 8px; }
.header-icon { color: #E6A23C; font-size: 18px; }
.header-title { font-size: 15px; font-weight: 600; }
.loading {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 24px;
  color: #909399;
  font-size: 13px;
}
.empty {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 24px;
  color: #67C23A;
  font-size: 13px;
  .empty-icon { font-size: 20px; }
}
.role-list { display: flex; flex-direction: column; gap: 8px; }
.role-item {
  display: grid;
  grid-template-columns: 1.4fr 0.8fr 1.2fr 1.4fr;
  gap: 12px;
  align-items: center;
  padding: 10px 12px;
  background: #fff;
  border-radius: 6px;
  border-left: 3px solid #E6A23C;
  transition: all 0.2s;
  &:hover { transform: translateX(2px); box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
  &.level-critical { border-left-color: #F56C6C; background: #fef0f0; }
  &.level-danger { border-left-color: #F56C6C; }
  &.level-warning { border-left-color: #E6A23C; }
  &.level-info { border-left-color: #909399; }
}
.role-user { display: flex; align-items: center; gap: 8px; }
.user-info { display: flex; flex-direction: column; line-height: 1.2; }
.user-name { font-weight: 500; font-size: 13px; }
.user-username { font-size: 11px; color: #909399; }
.role-tag { justify-self: start; }
.role-expire {
  display: flex;
  flex-direction: column;
  font-size: 12px;
  gap: 2px;
  .expire-text { font-weight: 600; }
  .expire-text.level-critical { color: #F56C6C; }
  .expire-text.level-danger { color: #F56C6C; }
  .expire-text.level-warning { color: #E6A23C; }
  .expire-text.level-info { color: #606266; }
  .expire-date { color: #909399; font-size: 11px; }
}
.role-reason {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: #606266;
  font-style: italic;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  &.muted { color: #c0c4cc; font-style: normal; }
}
</style>
