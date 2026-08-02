<script setup lang="ts">
/**
 * CustomerTable — 客户列表表格 (v0.3.14 C2)
 *
 * 健康度 chip 显示 + 头像 + 标签 + 操作列
 * 支持复选（批量）— 父组件 @selection-change 接收
 */
import { View, Edit, Delete, ChatLineRound, OfficeBuilding } from '@element-plus/icons-vue'

export interface Customer {
  id: number
  name: string
  industry: string
  contact: string
  phone: string
  category: string
  tags: string[]
  project_count: number
  last_follow_at: string
  avatarColor: string
  credit_code?: string
  health_score?: number
  health_level?: string
  score_breakdown?: Record<string, number>
}

const props = withDefaults(
  defineProps<{
    data: Customer[]
    loading?: boolean
    selection?: Customer[]
  }>(),
  { loading: false, selection: () => [] },
)

const emit = defineEmits<{
  (e: 'view', row: Customer): void
  (e: 'edit', row: Customer): void
  (e: 'delete', row: Customer): void
  (e: 'follow', row: Customer): void
  (e: 'selection-change', rows: Customer[]): void
}>()

// 健康度 chip
const healthChipText = (level?: string, score?: number) => {
  const s = Math.round(Number(score ?? 0))
  if (level === '健康' || s >= 80) return `健康 ${s}`
  if (level === '良好' || s >= 60) return `良好 ${s}`
  if (level === '一般' || s >= 40) return `一般 ${s}`
  return `预警 ${s}`
}

const healthTagType = (level?: string, score?: number): 'success' | 'primary' | 'warning' | 'danger' | 'info' => {
  const s = Number(score ?? 0)
  if (level === '健康' || s >= 80) return 'success'
  if (level === '良好' || s >= 60) return 'primary'
  if (level === '一般' || s >= 40) return 'warning'
  return 'danger'
}

const categoryTagType = (cat: string): 'warning' | 'info' | 'success' => {
  if (cat === 'VIP') return 'warning'
  if (cat === '潜在') return 'info'
  return 'success'
}

const formatDate = (d?: string) => (d ? String(d).slice(0, 16).replace('T', ' ') : '-')
</script>

<template>
  <el-table
    :data="data"
    v-loading="loading"
    border
    stripe
    style="width: 100%"
    @selection-change="(rows: Customer[]) => emit('selection-change', rows)"
  >
    <el-table-column type="selection" width="50" />
    <el-table-column type="index" label="#" width="55" align="center" />
    <el-table-column prop="name" label="客户名称" min-width="180">
      <template #default="{ row }">
        <div class="cust-cell">
          <el-avatar :size="32" :style="{ background: row.avatarColor }">
            <el-icon><OfficeBuilding /></el-icon>
          </el-avatar>
          <div class="cust-info">
            <a class="cust-name" @click="emit('view', row)">{{ row.name }}</a>
            <span v-if="row.credit_code" class="cust-credit">{{ row.credit_code }}</span>
          </div>
        </div>
      </template>
    </el-table-column>
    <el-table-column prop="health_score" label="健康度" width="160" align="center">
      <template #default="{ row }">
        <el-tooltip v-if="row.health_score != null" placement="top">
          <template #content>
            <div v-if="row.score_breakdown" class="health-tip">
              <div v-for="(v, k) in row.score_breakdown" :key="k" class="ht-row">
                <span>{{ k }}: </span>
                <span class="ht-val">{{ Math.round(Number(v)) }}</span>
              </div>
            </div>
          </template>
          <el-tag :type="healthTagType(row.health_level, row.health_score)" size="small" effect="dark">
            {{ healthChipText(row.health_level, row.health_score) }}
          </el-tag>
        </el-tooltip>
        <span v-else style="color: #909399">—</span>
      </template>
    </el-table-column>
    <el-table-column prop="industry" label="所属行业" width="120" />
    <el-table-column prop="contact" label="联系人" width="100" />
    <el-table-column prop="phone" label="联系电话" width="140" />
    <el-table-column prop="category" label="客户分类" width="100" align="center">
      <template #default="{ row }">
        <el-tag :type="categoryTagType(row.category)" size="small" effect="plain">{{ row.category }}</el-tag>
      </template>
    </el-table-column>
    <el-table-column prop="tags" label="标签" width="180">
      <template #default="{ row }">
        <el-tag
          v-for="t in (row.tags || []).slice(0, 3)"
          :key="t"
          size="small"
          type="info"
          effect="plain"
          style="margin-right: 4px"
        >{{ t }}</el-tag>
        <span v-if="(row.tags || []).length > 3" style="color: #909399; font-size: 11px">+{{ (row.tags || []).length - 3 }}</span>
      </template>
    </el-table-column>
    <el-table-column prop="project_count" label="项目数" width="80" align="center">
      <template #default="{ row }">
        <el-link v-if="row.project_count" type="primary" :underline="false">{{ row.project_count }}</el-link>
        <span v-else style="color: #c0c4cc">0</span>
      </template>
    </el-table-column>
    <el-table-column prop="last_follow_at" label="最后跟进" width="160">
      <template #default="{ row }">{{ formatDate(row.last_follow_at) }}</template>
    </el-table-column>
    <el-table-column label="操作" width="240" fixed="right" align="center">
      <template #default="{ row }">
        <el-button :icon="View" size="small" text type="primary" @click="emit('view', row)">详情</el-button>
        <el-button :icon="ChatLineRound" size="small" text type="success" @click="emit('follow', row)">跟进</el-button>
        <el-button :icon="Edit" size="small" text type="warning" @click="emit('edit', row)">编辑</el-button>
        <el-button :icon="Delete" size="small" text type="danger" @click="emit('delete', row)">删除</el-button>
      </template>
    </el-table-column>
    <template #empty>
      <el-empty description="暂无客户数据" />
    </template>
  </el-table>
</template>

<style scoped>
.cust-cell { display: flex; align-items: center; gap: 10px; }
.cust-info { display: flex; flex-direction: column; min-width: 0; }
.cust-name { font-weight: 500; color: #185FA5; cursor: pointer; }
.cust-name:hover { text-decoration: underline; }
.cust-credit { font-size: 11px; color: #909399; }
.health-tip { padding: 4px; }
.ht-row { display: flex; justify-content: space-between; gap: 16px; padding: 2px 0; font-size: 12px; }
.ht-val { color: #185FA5; font-weight: 600; }
</style>
