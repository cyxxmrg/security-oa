<template>
  <div class="app-skeleton" :class="`app-skeleton--${type}`">
    <!-- 表格类型 -->
    <template v-if="type === 'table'">
      <div class="sk-toolbar">
        <el-skeleton-item variant="rect" class="sk-btn" />
        <el-skeleton-item variant="rect" class="sk-btn" />
        <el-skeleton-item variant="rect" class="sk-search" />
      </div>
      <el-skeleton :rows="rows" animated>
        <template #template>
          <div v-for="i in rows" :key="i" class="sk-table-row">
            <el-skeleton-item variant="h1" class="sk-cell sk-cell--check" />
            <el-skeleton-item variant="text" class="sk-cell" />
            <el-skeleton-item variant="text" class="sk-cell" />
            <el-skeleton-item variant="text" class="sk-cell" />
            <el-skeleton-item variant="text" class="sk-cell" />
            <el-skeleton-item variant="text" class="sk-cell sk-cell--action" />
          </div>
        </template>
      </el-skeleton>
    </template>

    <!-- 卡片网格 -->
    <template v-else-if="type === 'card'">
      <div class="sk-card-grid">
        <div v-for="i in (rows || 6)" :key="i" class="sk-card-item">
          <el-skeleton-item variant="image" class="sk-card-img" />
          <el-skeleton :rows="2" animated>
            <template #template>
              <el-skeleton-item variant="h3" style="width: 60%" />
              <el-skeleton-item variant="text" style="width: 90%" />
            </template>
          </el-skeleton>
        </div>
      </div>
    </template>

    <!-- 详情页 -->
    <template v-else-if="type === 'detail'">
      <div class="sk-detail-header">
        <el-skeleton-item variant="h1" style="width: 30%; height: 24px" />
        <el-skeleton-item variant="text" style="width: 50%; margin-top: 12px" />
      </div>
      <el-skeleton :rows="rows || 8" animated>
        <template #template>
          <el-skeleton-item variant="text" style="width: 100%" />
          <el-skeleton-item variant="text" style="width: 100%" />
          <el-skeleton-item variant="text" style="width: 80%" />
          <el-skeleton-item variant="text" style="width: 90%" />
          <div style="height: 16px"></div>
          <el-skeleton-item variant="text" style="width: 100%" />
          <el-skeleton-item variant="text" style="width: 70%" />
        </template>
      </el-skeleton>
    </template>

    <!-- 表单 -->
    <template v-else>
      <div class="sk-form">
        <div v-for="i in (rows || 5)" :key="i" class="sk-form-row">
          <el-skeleton-item variant="text" class="sk-form-label" />
          <el-skeleton-item variant="rect" class="sk-form-input" />
        </div>
        <div class="sk-form-actions">
          <el-skeleton-item variant="rect" class="sk-btn" />
          <el-skeleton-item variant="rect" class="sk-btn" />
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{ type?: 'table' | 'card' | 'detail' | 'form'; rows?: number }>(), {
  type: 'table',
  rows: 5
})
</script>

<style scoped lang="scss">
.app-skeleton {
  padding: 16px;
  background: #fff;
  border-radius: 4px;
  min-height: 200px;
}

// ---- table ----
.sk-toolbar {
  display: flex;
  gap: 12px;
  margin-bottom: 16px;
  align-items: center;

  .sk-btn { width: 80px; height: 32px; border-radius: 4px; }
  .sk-search { width: 240px; height: 32px; border-radius: 4px; margin-left: auto; }
}

.sk-table-row {
  display: flex;
  gap: 12px;
  padding: 12px 0;
  border-bottom: 1px solid #f0f0f0;
  align-items: center;

  .sk-cell { height: 16px; flex: 1; }
  .sk-cell--check { flex: 0 0 32px; }
  .sk-cell--action { flex: 0 0 100px; }
}

// ---- card ----
.sk-card-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 16px;
}
.sk-card-item {
  border: 1px solid #f0f0f0;
  border-radius: 8px;
  padding: 12px;
  .sk-card-img { width: 100%; height: 120px; border-radius: 4px; margin-bottom: 12px; }
}

// ---- detail ----
.sk-detail-header {
  padding-bottom: 16px;
  margin-bottom: 24px;
  border-bottom: 1px solid #f0f0f0;
}

// ---- form ----
.sk-form { max-width: 720px; }
.sk-form-row {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 20px;
  .sk-form-label { width: 100px; height: 16px; }
  .sk-form-input { flex: 1; height: 32px; border-radius: 4px; }
}
.sk-form-actions {
  display: flex;
  gap: 12px;
  margin-top: 24px;
  .sk-btn { width: 88px; height: 32px; border-radius: 4px; }
}
</style>
