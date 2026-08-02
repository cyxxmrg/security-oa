<template>
  <div class="page-container">
    <div class="page-header">
      <el-button :icon="ArrowLeft" plain @click="goBack">返回列表</el-button>
      <span class="page-title" style="margin-left: 16px">即将到期质保期</span>
      <div class="header-actions" style="margin-left: auto">
        <ScopeToggle @change="loadList" />
      </div>
    </div>

    <div class="filter-bar">
      <el-form :inline="true">
        <el-form-item label="提醒天数">
          <el-input-number v-model="withinDays" :min="1" :max="365" @change="loadList" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :icon="Search" @click="loadList">查询</el-button>
          <el-button :icon="Refresh" @click="reset">重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <el-alert
      v-if="pagedList.length"
      type="warning"
      :closable="false"
      style="margin-bottom: 16px"
      :title="`未来 ${withinDays} 天内有 ${filteredList.length} 个质保期即将到期，建议及时通知客户并安排续期。`"
    />

    <div class="content-card">
      <el-table
        :data="pagedList"
        v-loading="loading"
        stripe
        border
        :header-cell-style="{ background: '#f5f7fa', color: '#303133', fontWeight: 600 }"
      >
        <el-table-column prop="warranty_no" label="质保编号" min-width="160" fixed>
          <template #default="{ row }">
            <el-link type="primary" :underline="false" @click="goDetail(row)">{{ row.warranty_no }}</el-link>
          </template>
        </el-table-column>
        <el-table-column label="项目" min-width="140" show-overflow-tooltip>
          <template #default="{ row }">{{ row.project?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="客户" width="120">
          <template #default="{ row }">{{ row.customer?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="到期日期" width="120" align="center">
          <template #default="{ row }">{{ row.end_date }}</template>
        </el-table-column>
        <el-table-column label="剩余天数" width="120" align="center">
          <template #default="{ row }">
            <el-tag :type="daysTagType(row.days_left ?? 0)" effect="plain" size="small">
              {{ row.days_left != null ? (row.days_left >= 0 ? `${row.days_left} 天` : `已过期 ${Math.abs(row.days_left)} 天`) : '-' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="类型" width="100" align="center">
          <template #default="{ row }">
            <el-tag size="small" effect="plain">{{ row.warranty_type }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" fixed="right" align="center">
          <template #default="{ row }">
            <el-button link type="primary" :icon="View" @click="goDetail(row)">详情</el-button>
            <el-button link type="success" :icon="Refresh" @click="goRenew(row)">续期</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrapper">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="pageSize"
          :page-sizes="[10, 20, 50]"
          :total="filteredList.length"
          layout="total, sizes, prev, pager, next, jumper"
          background
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { ArrowLeft, Search, Refresh, View } from '@element-plus/icons-vue'
import { warrantyApi } from '@/api/warranty'
import { unwrapList } from '@/utils/response'
import ScopeToggle from '@/components/ScopeToggle.vue'
import type { Warranty, TagType } from './types'

const router = useRouter()
const withinDays = ref(30)
const loading = ref(false)
const list = ref<Warranty[]>([])
const page = ref(1)
const pageSize = ref(10)

const filteredList = computed(() => list.value)
const pagedList = computed(() => {
  const start = (page.value - 1) * pageSize.value
  return filteredList.value.slice(start, start + pageSize.value)
})

const daysTagType = (d: number): TagType => {
  if (d < 0)  return 'danger'
  if (d <= 7)  return 'danger'
  if (d <= 30) return 'warning'
  return 'info'
}

async function loadList() {
  loading.value = true
  try {
    const res = await warrantyApi.expiring({ within_days: withinDays.value })
    // V0.6.3: res = {code, data: paginator}
    list.value = unwrapList(res)
  } catch (e: unknown) {
    ElMessage.error('加载失败: ' + ((e as { message?: string })?.message || 'unknown'))
    list.value = []
  } finally {
    loading.value = false
  }
}

function goBack() { router.push('/project/warranty/list') }
function goDetail(row: Warranty) { router.push(`/project/warranty/detail/${row.id}`) }
function goRenew(row: Warranty) { router.push(`/project/warranty/detail/${row.id}`) }
function reset() { withinDays.value = 30; loadList() }

onMounted(loadList)
</script>
