<template>
  <div class="matrix-left">
    <div class="left-header">
      <span class="left-title">角色</span>
      <el-button type="primary" :icon="Plus" size="small" @click="showCreateDialog = true">新建</el-button>
    </div>

    <div class="left-search">
      <el-input
        v-model="roleFilterModel"
        placeholder="按名称筛选..."
        clearable
        :prefix-icon="Search"
        size="small"
      />
    </div>

    <div class="role-list" v-loading="loadingRoles">
      <div
        v-for="r in filteredRoles"
        :key="r.name"
        class="role-item"
        :class="{ active: r.name === activeRoleModel }"
        @click="activeRoleModel = r.name"
      >
        <div class="role-item-head">
          <div class="role-color-dot" :style="{ background: r.color || '#0C447C' }"></div>
          <div class="role-item-name">
            <span class="role-name-text">{{ r.name }}</span>
            <el-tag v-if="r.name === 'admin' || r.name === 'system_admin'" size="small" type="info" effect="plain" class="ml-4">系统</el-tag>
            <el-tag v-else size="small" type="success" effect="plain" class="ml-4">自定义</el-tag>
          </div>
        </div>
        <div class="role-item-desc">{{ r.description || '—' }}</div>
        <div class="role-item-meta">
          <span><el-icon><Key /></el-icon> {{ countOwnLeaves(r.name) }} 界面</span>
        </div>
      </div>
      <div v-if="!filteredRoles.length" class="role-empty">
        <el-empty description="无匹配角色" :image-size="80" />
      </div>
    </div>

    <!-- 新建角色对话框 -->
    <el-dialog v-model="showCreateDialog" title="新建角色" width="420px">
      <el-form :model="createForm" label-width="80px">
        <el-form-item label="角色名" required>
          <el-input v-model="createForm.name" placeholder="如：项目经理" maxlength="32" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="createForm.description" type="textarea" :rows="2" placeholder="选填" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" :loading="creating" @click="handleCreate">创建</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { Key, Search, Plus } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { ref, reactive } from 'vue'
import { post } from '@/utils/request'

defineProps<{
  loadingRoles: boolean
  filteredRoles: Record<string, unknown>[]
  countOwnLeaves: (name: string) => number
}>()

const emit = defineEmits<{ (e: 'created'): void }>()

const activeRoleModel = defineModel<string>('activeRole', { default: '' })
const roleFilterModel = defineModel<string>('roleFilter', { default: '' })

const showCreateDialog = ref(false)
const creating = ref(false)
const createForm = reactive({ name: '', description: '' })

async function handleCreate() {
  if (!createForm.name.trim()) { ElMessage.warning('请输入角色名'); return }
  creating.value = true
  try {
    await post('/roles', { name: createForm.name.trim(), description: createForm.description, guard_name: 'web' })
    ElMessage.success('角色已创建')
    showCreateDialog.value = false
    createForm.name = ''
    createForm.description = ''
    emit('created')
  } catch (e: unknown) {
    const err = e as { message?: string }
    ElMessage.error(err?.message || '创建失败')
  } finally {
    creating.value = false
  }
}
</script>

<style scoped lang="scss">
.matrix-left {
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
  display: flex;
  flex-direction: column;
  overflow: hidden;

  .left-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 16px;
    border-bottom: 1px solid #ebeef5;
    .left-title { font-size: 15px; font-weight: 600; color: #303133; }
  }
  .left-search { padding: 12px 16px; border-bottom: 1px solid #ebeef5; }
  .role-list {
    flex: 1;
    overflow-y: auto;
    padding: 8px;
  }
}

.role-item {
  padding: 12px 14px;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.18s;
  margin-bottom: 4px;
  border: 1px solid transparent;
  &:hover { background: #f5f7fa; }
  &.active {
    background: linear-gradient(135deg, #ecf5ff 0%, #d6eaff 100%);
    border-color: #409eff;
    box-shadow: 0 2px 8px rgba(64,158,255,0.15);
  }
  .role-item-head {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 4px;
  }
  .role-color-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
    &.lg { width: 14px; height: 14px; }
  }
  .role-item-name {
    display: flex;
    align-items: center;
    gap: 4px;
    flex: 1;
    min-width: 0;
  }
  .role-name-text { font-weight: 600; color: #303133; font-size: 14px; }
  .role-item-desc {
    font-size: 12px;
    color: #909399;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    margin-bottom: 6px;
  }
  .role-item-meta {
    display: flex;
    gap: 12px;
    font-size: 11px;
    color: #909399;
    span { display: inline-flex; align-items: center; gap: 3px; }
  }
}
.role-empty { padding: 30px 10px; }
.ml-4 { margin-left: 4px; }
</style>
