<template>
  <div class="network-error">
    <div class="ne-content">
      <div class="ne-icon">
        <el-icon :size="120" color="#909399"><TurnOff /></el-icon>
      </div>
      <div class="ne-title">网络连接断开</div>
      <p class="ne-desc">
        无法连接到服务器,请检查您的网络设置或稍后重试。
        <br />如果您已连接网络,可能是服务正在维护中。
      </p>
      <div class="ne-actions">
        <el-button type="primary" @click="retry">
          <el-icon><Refresh /></el-icon>
          重新连接
        </el-button>
        <el-button @click="goHome">
          <el-icon><HomeFilled /></el-icon>
          返回首页
        </el-button>
      </div>
      <div class="ne-tip">
        <el-icon><InfoFilled /></el-icon>
        <span>持续断开请联系系统管理员,服务地址:192.168.1.20</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import { HomeFilled, InfoFilled, Refresh, TurnOff } from '@element-plus/icons-vue'
const router = useRouter()
function retry() { window.location.reload() }
function goHome() { router.push('/') }
</script>

<style scoped lang="scss">
.network-error {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f5f7fa;
  padding: 24px;
}
.ne-content {
  text-align: center;
  max-width: 480px;
}
.ne-icon {
  margin-bottom: 24px;
  display: flex;
  justify-content: center;
}
.ne-title {
  font-size: 24px;
  font-weight: 600;
  color: #303133;
  margin-bottom: 12px;
}
.ne-desc {
  font-size: 14px;
  color: #909399;
  line-height: 1.6;
  margin-bottom: 32px;
}
.ne-actions {
  display: flex;
  gap: 12px;
  justify-content: center;
  margin-bottom: 24px;
}
.ne-tip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  background: #fff;
  border: 1px solid #ebeef5;
  border-radius: 20px;
  font-size: 12px;
  color: #909399;
  .el-icon { color: #1D9E75; }
}
</style>
