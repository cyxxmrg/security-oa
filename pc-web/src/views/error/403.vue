<template>
  <div class="error-page">
    <div class="error-code">403</div>
    <div class="error-msg">权限不足</div>
    <div class="error-hint">您没有访问该页面的权限，请联系管理员</div>
    <el-button type="primary" @click="$router.push('/')">返回首页</el-button>
  </div>
</template>

<script setup lang="ts">
</script>

<style scoped>
.error-page {
  min-height: 60vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
}
.error-code {
  font-size: 96px;
  font-weight: 700;
  color: #f56c6c;
  line-height: 1;
}
.error-msg {
  font-size: 24px;
  margin: 20px 0 8px;
  color: #303133;
}
.error-hint {
  font-size: 14px;
  color: #909399;
  margin-bottom: 24px;
}
</style>
