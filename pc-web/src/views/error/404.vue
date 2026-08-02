<template>
  <div>
    <div class="mobile-banner">
      <el-icon><Warning /></el-icon>
      <span>请使用 PC 端访问,移动端暂未支持。推荐分辨率 1920×1080 及以上。</span>
    </div>
    <ErrorLayout
      :code="404"
      title="页面走丢了"
      desc="您访问的页面不存在,可能已被删除或地址输入错误。请检查 URL,或返回首页继续工作。"
    />
  </div>
</template>

<script setup lang="ts">
import ErrorLayout from './ErrorLayout.vue'
</script>

<style scoped lang="scss">
.mobile-banner {
  display: none;
  align-items: center;
  gap: 8px;
  padding: 12px 20px;
  background: #fdf6ec;
  color: #BA7517;
  font-size: 13px;
  border-bottom: 1px solid #faecd8;
  .el-icon { font-size: 18px; }
}
@media (max-width: 767px) {
  .mobile-banner { display: flex; }
}
</style>
