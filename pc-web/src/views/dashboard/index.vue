<template>
  <div class="workbench-page">
    <section class="welcome-card">
      <div>
        <p class="eyebrow">个人工作入口</p>
        <h2>{{ greeting }}，{{ displayName }}</h2>
        <p class="welcome-desc">处理自己的待办、申请、工单和项目进度；经营统计已移动到老板看板。</p>
      </div>
      <div class="welcome-meta">
        <span>{{ todayText }}</span>
        <el-tag effect="dark" type="success">{{ roleLabel }}</el-tag>
      </div>
    </section>

    <section class="quick-grid">
      <button v-for="action in quickActions" :key="action.label" class="quick-item" @click="goPath(action.path)">
        <span class="quick-icon" :style="{ background: action.bg, color: action.color }">
          <el-icon :size="24"><component :is="action.icon" /></el-icon>
        </span>
        <span>{{ action.label }}</span>
      </button>
    </section>

    <section class="content-grid">
      <el-card shadow="never" class="todo-card">
        <template #header>
          <div class="card-header">
            <div>
              <h3>我的待办</h3>
              <p>只展示与当前账号相关、需要处理的事项</p>
            </div>
            <el-button link type="primary" @click="goPath('/message/list')">查看全部</el-button>
          </div>
        </template>
        <div v-loading="loading" class="todo-list">
          <div
            v-for="item in todoList"
            :key="item.id"
            class="todo-row"
            :class="{ clickable: !!item.link }"
            @click="item.link && goPath(item.link)"
          >
            <el-tag :type="item.tagType" effect="plain">{{ item.type }}</el-tag>
            <div class="todo-main">
              <strong>{{ item.content }}</strong>
              <span>{{ item.time }}</span>
            </div>
            <el-button v-if="item.link" link type="primary">处理</el-button>
          </div>
          <el-empty v-if="!loading && !todoList.length" description="暂无待办" :image-size="88" />
        </div>
      </el-card>

      <el-card shadow="never" class="notice-card">
        <template #header>
          <div class="card-header compact">
            <h3>通知与提醒</h3>
            <el-button link type="primary" @click="goPath('/message/list')">消息中心</el-button>
          </div>
        </template>
        <div class="notice-list">
          <div v-for="notice in notices" :key="notice.title" class="notice-row">
            <span class="notice-dot" :class="notice.type" />
            <div>
              <strong>{{ notice.title }}</strong>
              <p>{{ notice.desc }}</p>
            </div>
          </div>
        </div>
      </el-card>
    </section>

    <section class="mine-grid">
      <el-card v-for="card in myCards" :key="card.title" shadow="never" class="mine-card" @click="goPath(card.path)">
        <div class="mine-head">
          <span class="mine-icon" :style="{ background: card.bg, color: card.color }">
            <el-icon :size="22"><component :is="card.icon" /></el-icon>
          </span>
          <el-button link type="primary">进入</el-button>
        </div>
        <h3>{{ card.title }}</h3>
        <p>{{ card.desc }}</p>
        <div class="mine-stat">
          <span>{{ card.primary }}</span>
          <small>{{ card.secondary }}</small>
        </div>
      </el-card>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import dayjs from 'dayjs'
import { getWorkbenchData } from '@/api/dashboard'
import { unwrapStats } from '@/utils/response'
import { useUserStore } from '@/stores/user'

type TodoItem = {
  id: string | number
  type: string
  content: string
  time: string
  tagType: 'success' | 'warning' | 'info' | 'danger' | 'primary'
  link?: string
}

const router = useRouter()
const userStore = useUserStore()
const loading = ref(false)
const todoList = ref<TodoItem[]>([])

const summary = ref({
  todos: 0,
  activeProjects: 0,
  pendingOrders: 0,
  attendanceNormal: 0,
})

const displayName = computed(() => userStore.userInfo?.name || userStore.userInfo?.username || '同事')
const todayText = computed(() => dayjs().format('YYYY年MM月DD日 dddd'))
const greeting = computed(() => {
  const hour = dayjs().hour()
  if (hour < 6) return '夜深了'
  if (hour < 12) return '早上好'
  if (hour < 18) return '下午好'
  return '晚上好'
})
const roleLabel = computed(() => {
  const roles = userStore.userInfo?.roles || userStore.roles || []
  if (roles.includes('admin')) return '业务管理员'
  if (roles.includes('finance')) return '财务人员'
  if (roles.includes('manager')) return '部门负责人'
  if (roles.includes('technician')) return '工程师'
  return '员工'
})

const quickActions = [
  { label: '发起审批', icon: 'CircleCheck', path: '/approval/finance', bg: '#EAF3FF', color: '#185FA5' },
  { label: '费用报销', icon: 'Money', path: '/expense/apply', bg: '#FAEEDA', color: '#BA7517' },
  { label: '新建工单', icon: 'SetUp', path: '/maintenance/work-orders/create', bg: '#FCEBEB', color: '#A32D2D' },
  { label: '新建项目', icon: 'FolderAdd', path: '/project/create', bg: '#E1F5EE', color: '#1D9E75' },
  { label: '用车申请', icon: 'Van', path: '/vehicle/apply', bg: '#EEEDFE', color: '#534AB7' },
  { label: '公司网盘', icon: 'FolderOpened', path: '/disk', bg: '#FAECE7', color: '#D85A30' },
]

const notices = computed(() => [
  {
    title: todoList.value.length > 0 ? `你有 ${todoList.value.length} 项真实待办需要处理` : '当前没有待处理事项',
    desc: todoList.value.length > 0 ? '这些事项均来自可打开的具体业务单据。' : '可以从快捷入口发起审批、工单或查看个人业务。',
    type: todoList.value.length > 0 ? 'warning' : 'success',
  },
  {
    title: summary.value.pendingOrders > 0 ? `待处理工单 ${summary.value.pendingOrders} 个` : '暂无待处理工单',
    desc: '工单只作为个人工作提醒，维修统计请到维修看板查看。',
    type: summary.value.pendingOrders > 0 ? 'danger' : 'primary',
  },
  {
    title: '经营统计已移至老板看板',
    desc: '工作台面向全体员工，避免展示与普通岗位无关的经营 BI 数据。',
    type: 'primary',
  },
])

const myCards = computed(() => [
  {
    title: '我的项目',
    desc: '查看本人负责或参与的项目、阶段资料和进度。',
    primary: `${summary.value.activeProjects} 个进行中`,
    secondary: '项目列表',
    icon: 'Files',
    path: '/project/list',
    bg: '#EAF3FF',
    color: '#185FA5',
  },
  {
    title: '我的工单',
    desc: '查看本人创建、分派或处理的维修工单。',
    primary: `${summary.value.pendingOrders} 个待处理`,
    secondary: '维修工单',
    icon: 'SetUp',
    path: '/maintenance/work-orders',
    bg: '#FCEBEB',
    color: '#A32D2D',
  },
  {
    title: '我的申请',
    desc: '跟进报销、采购、付款、用车等审批进度。',
    primary: `${todoList.value.length} 项待办`,
    secondary: '审批中心',
    icon: 'DocumentChecked',
    path: '/approval/finance',
    bg: '#E1F5EE',
    color: '#1D9E75',
  },
])

function goPath(path: string) {
  router.push(path)
}

function normalizeTodo(data: Record<string, unknown>[]): TodoItem[] {
  return data
    .filter((item) => (item.link || item.path) && (item.content || item.title))
    .map((item, index) => {
      const label = item.label || item.type || item.title || '待办'
      return {
        id: item.id ?? `${label}-${index}`,
        type: label,
        content: item.content || item.title,
        time: item.time || item.deadline || '请及时处理',
        tagType: index % 4 === 0 ? 'warning' : index % 4 === 1 ? 'danger' : index % 4 === 2 ? 'info' : 'success',
        link: item.link || item.path,
      }
    })
}

async function loadWorkbench() {
  loading.value = true
  try {
    const res = await getWorkbenchData()
    const payload = unwrapStats<{ todos?: Record<string, unknown>[]; summary?: Record<string, unknown> }>(res)
    const data = Array.isArray(payload.todos) ? payload.todos : []
    todoList.value = normalizeTodo(data)

    const workbenchSummary = payload.summary || {}
    summary.value.todos = Number(workbenchSummary.todo_count ?? todoList.value.length)
    summary.value.activeProjects = Number(workbenchSummary.my_active_projects ?? 0)
    summary.value.pendingOrders = Number(workbenchSummary.my_pending_work_orders ?? 0)
    summary.value.attendanceNormal = 0
  } catch {
    todoList.value = []
    summary.value = { todos: 0, activeProjects: 0, pendingOrders: 0, attendanceNormal: 0 }
  } finally {
    loading.value = false
  }
}

onMounted(loadWorkbench)
</script>

<style lang="scss" scoped>
.workbench-page {
  min-height: 100%;
  padding: 22px 24px 28px;
  background: #f5f7fa;
}

.welcome-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 120px;
  padding: 24px 28px;
  color: #fff;
  border-radius: 18px;
  background: linear-gradient(135deg, #185fa5 0%, #1d9e75 100%);
  box-shadow: 0 14px 32px rgba(24, 95, 165, 0.18);

  h2 {
    margin: 4px 0 8px;
    font-size: 25px;
    font-weight: 700;
  }
}

.eyebrow {
  margin: 0;
  color: rgba(255, 255, 255, 0.76);
  font-size: 13px;
}

.welcome-desc {
  margin: 0;
  color: rgba(255, 255, 255, 0.84);
  font-size: 14px;
}

.welcome-meta {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 9px 12px;
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.12);
  font-size: 13px;
}

.quick-grid {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 14px;
  margin: 16px 0;
}

.quick-item {
  height: 92px;
  border: 1px solid #e8edf5;
  border-radius: 16px;
  background: #fff;
  box-shadow: 0 8px 20px rgba(31, 45, 61, 0.04);
  cursor: pointer;
  transition: all 0.18s ease;
  color: #303133;

  &:hover {
    transform: translateY(-2px);
    border-color: #bdd7f5;
    box-shadow: 0 12px 24px rgba(31, 45, 61, 0.08);
  }
}

.quick-icon {
  width: 42px;
  height: 42px;
  margin: 0 auto 8px;
  border-radius: 13px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.content-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.65fr) minmax(320px, 1fr);
  gap: 16px;
}

.card-header {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;

  h3 {
    margin: 0;
    font-size: 16px;
    color: #1f2d3d;
  }

  p {
    margin: 4px 0 0;
    color: #909399;
    font-size: 12px;
  }
}

.card-header.compact {
  h3 {
    margin: 0;
  }
}

.todo-card,
.notice-card,
.mine-card {
  border: none;
  border-radius: 14px;
}

.todo-list {
  min-height: 290px;
}

.todo-row {
  display: grid;
  grid-template-columns: 92px minmax(0, 1fr) 56px;
  align-items: center;
  gap: 12px;
  min-height: 56px;
  padding: 8px 0;
  border-bottom: 1px solid #f0f2f5;

  &:last-child {
    border-bottom: none;
  }

  &.clickable {
    cursor: pointer;
  }

  &.clickable:hover {
    background: #f8fbff;
  }
}

.todo-main {
  min-width: 0;

  strong {
    display: block;
    overflow: hidden;
    color: #303133;
    font-size: 14px;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  span {
    display: block;
    margin-top: 4px;
    color: #909399;
    font-size: 12px;
  }
}

.notice-list {
  min-height: 290px;
}

.notice-row {
  display: flex;
  gap: 12px;
  padding: 12px 0;
  border-bottom: 1px solid #f0f2f5;

  &:last-child {
    border-bottom: none;
  }

  strong {
    color: #303133;
    font-size: 14px;
  }

  p {
    margin: 5px 0 0;
    color: #7d8794;
    font-size: 13px;
    line-height: 1.5;
  }
}

.notice-dot {
  width: 8px;
  height: 8px;
  margin-top: 6px;
  border-radius: 50%;
  background: #409eff;
  flex: none;

  &.warning { background: #e6a23c; }
  &.danger { background: #f56c6c; }
  &.success { background: #67c23a; }
}

.mine-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  margin-top: 16px;
}

.mine-card {
  cursor: pointer;
  transition: all 0.18s ease;

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 24px rgba(31, 45, 61, 0.08);
  }

  h3 {
    margin: 14px 0 8px;
    color: #1f2d3d;
    font-size: 17px;
  }

  p {
    min-height: 38px;
    margin: 0;
    color: #7d8794;
    font-size: 13px;
    line-height: 1.5;
  }
}

.mine-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.mine-icon {
  width: 42px;
  height: 42px;
  border-radius: 13px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.mine-stat {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  margin-top: 18px;

  span {
    color: #185fa5;
    font-size: 20px;
    font-weight: 700;
  }

  small {
    color: #a8abb2;
  }
}

@media (max-width: 1280px) {
  .quick-grid {
    grid-template-columns: repeat(3, 1fr);
  }

  .content-grid,
  .mine-grid {
    grid-template-columns: 1fr;
  }
}
</style>
