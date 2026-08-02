<!--
  V0.4.6 B 数据权限 - 列表页右上角 scope 切换 radio
  用法: <ScopeToggle @change="loadList" />
-->
<template>
  <div class="scope-toggle" v-if="visible">
    <span class="toggle-label">数据范围</span>
    <el-radio-group v-model="mode" size="small" @change="handleChange">
      <el-radio-button label="mine">我的</el-radio-button>
      <el-radio-button label="all">全部</el-radio-button>
    </el-radio-group>
    <el-tooltip
      v-if="!canAll"
      content="仅管理员/财务可查看全部数据, 当前为「我的」范围"
      placement="top"
    >
      <el-icon class="toggle-hint"><InfoFilled /></el-icon>
    </el-tooltip>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { InfoFilled } from '@element-plus/icons-vue'
import { getScopeMode, setScopeMode } from '@/utils/request'
import { useUserStore } from '@/stores/user'
import { canViewAll } from '@/utils/authScope'

const emit = defineEmits<{
  change: [mode: 'mine' | 'all']
}>()

const userStore = useUserStore()
const mode = ref<'mine' | 'all'>(getScopeMode())

const canAll = computed(() => canViewAll(userStore.userInfo))
const visible = computed(() => !!userStore.userInfo)

function handleChange(v: 'mine' | 'all' | string | number | boolean | undefined) {
  const m = (v === 'all' ? 'all' : 'mine') as 'mine' | 'all'
  if (m === 'all' && !canAll.value) {
    // 普通员工点 "全部" → 拦截 + 弹窗
    ElMessage.warning('权限不足: 仅管理员/财务可查看全部数据')
    mode.value = 'mine'
    setScopeMode('mine')
    return
  }
  mode.value = m
  setScopeMode(m)
  emit('change', m)
}

onMounted(() => {
  // 初始化时若选 all 但无权限, 强制回退
  if (mode.value === 'all' && !canAll.value) {
    mode.value = 'mine'
    setScopeMode('mine')
  }
})
</script>

<style lang="scss" scoped>
.scope-toggle {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin-right: 12px;
  .toggle-label {
    font-size: 13px;
    color: #606266;
    white-space: nowrap;
  }
  .toggle-hint {
    color: #909399;
    cursor: help;
  }
}
</style>
