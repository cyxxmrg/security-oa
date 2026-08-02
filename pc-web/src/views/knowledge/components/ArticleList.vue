<template>
  <div>
    <div class="filter-bar">
      <el-input
        :model-value="keyword"
        @update:model-value="(v: string) => emit('update:keyword', v)"
        placeholder="搜索知识..." clearable style="width:300px"
        @clear="emit('search')"
        @keyup.enter="emit('search')"
      >
        <template #prefix><el-icon><Search /></el-icon></template>
      </el-input>
      <el-tag v-if="currentCategory" effect="plain" closable @close="emit('clearCategory')">
        当前分类：{{ currentCategory.name }}
      </el-tag>
    </div>
    <div class="content-card" v-loading="loading">
      <div v-if="!loading && articles.length === 0" class="empty-hint">
        <el-empty description="暂无文章，点击右上角发布第一篇吧" />
      </div>
      <div v-for="item in articles" :key="item.id" class="article-item" @click="emit('open', item)">
        <div class="article-info">
          <h3 class="article-title">{{ item.title }}</h3>
          <p class="article-summary">{{ item.summary || item.content?.slice(0, 120) }}</p>
          <div class="article-meta">
            <el-tag size="small" effect="plain" type="info">{{ item.category?.name || '-' }}</el-tag>
            <span><el-icon><User /></el-icon> {{ item.author?.name || '匿名' }}</span>
            <span><el-icon><Calendar /></el-icon> {{ (item.published_at || item.created_at || '').slice(0, 10) }}</span>
            <span><el-icon><View /></el-icon> {{ item.view_count || 0 }}</span>
            <el-tag v-if="item.status === 'draft'" size="small" type="warning">草稿</el-tag>
          </div>
        </div>
        <div class="article-actions">
          <el-button link type="primary" size="small" @click.stop="emit('edit', item)">编辑</el-button>
          <el-popconfirm :title="`确定删除「${item.title}」?`" @confirm="emit('delete', item)">
            <template #reference>
              <el-button link type="danger" size="small" @click.stop>删除</el-button>
            </template>
          </el-popconfirm>
        </div>
      </div>
    </div>
    <el-pagination
      v-if="total > 0"
      :current-page="page"
      :page-size="pageSize"
      :total="total"
      :page-sizes="[10, 20, 50]"
      layout="total, sizes, prev, pager, next"
      style="margin-top: 16px; justify-content: flex-end"
      @current-change="(p: number) => emit('pageChange', p)"
      @size-change="(s: number) => emit('sizeChange', s)"
    />
  </div>
</template>

<script setup lang="ts">
import { Search, User, Calendar, View } from '@element-plus/icons-vue'

defineProps<{
  keyword: string
  articles: Record<string, unknown>[]
  loading: boolean
  total: number
  page: number
  pageSize: number
  currentCategory: Record<string, unknown>
}>()
const emit = defineEmits<{
  (e: 'update:keyword', v: string): void
  (e: 'search'): void
  (e: 'clearCategory'): void
  (e: 'open', item: Record<string, unknown>): void
  (e: 'edit', item: Record<string, unknown>): void
  (e: 'delete', item: Record<string, unknown>): void
  (e: 'pageChange', p: number): void
  (e: 'sizeChange', s: number): void
}>()
</script>

<style lang="scss" scoped>
.filter-bar {
  background: #fff;
  padding: 12px 16px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
}
.content-card {
  background: #fff;
  border-radius: 8px;
  padding: 16px;
  min-height: 300px;
}
.empty-hint { padding: 40px 0; }
.article-item {
  display: flex;
  justify-content: space-between;
  padding: 16px 12px;
  border-bottom: 1px solid #f0f0f0;
  cursor: pointer;
  transition: background 0.2s;
}
.article-item:hover { background: #fafafa; }
.article-info { flex: 1; min-width: 0; }
.article-title { font-size: 16px; font-weight: 600; margin: 0 0 8px; color: #303133; }
.article-summary { font-size: 13px; color: #606266; margin: 0 0 8px; line-height: 1.6; }
.article-meta { display: flex; gap: 12px; align-items: center; font-size: 12px; color: #909399; }
.article-meta span { display: flex; gap: 4px; align-items: center; }
.article-actions { display: flex; gap: 4px; align-items: center; }
</style>
