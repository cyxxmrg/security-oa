<template>
  <div class="employee-container">
    <!-- 顶部 Tab 切换 -->
    <div class="tab-bar">
      <div class="tab-bar__left">
        <span class="tab-bar__title">员工管理</span>
      </div>
      <div class="tab-bar__nav">
        <div
          v-for="t in tabs"
          :key="t.key"
          class="tab-bar__item"
          :class="{ 'is-active': activeTab === t.key }"
          @click="activeTab = t.key"
        >
          <el-icon v-if="t.icon" :size="14"><component :is="t.icon" /></el-icon>
          <span>{{ t.label }}</span>
        </div>
      </div>
    </div>

    <!-- Tab 1: 员工列表（左树右表） -->
    <div v-show="activeTab === 'list'" class="tab-panel">
      <EmployeeToolbar
        :list-filters="listFilters"
        :dept-list="deptList"
        @search="handleListSearch"
        @reset="handleListReset"
        @go-onboardings="goOnboardings"
        @go-resignations="goResignations"
        @create="openCreateEmployee"
      />

      <div class="employee-body">
        <div class="employee-body__tree">
          <CategoryTree
            v-model="selectedDeptId"
            :departments="deptList"
            :positions="posList"
            @refresh="onTreeRefresh"
          />
        </div>
        <div class="employee-body__table">
          <el-card class="table-card" shadow="never">
            <template #header>
              <div class="card-header">
                <span class="card-header__bar" />
                <span class="card-header__title">员工列表</span>
                <span class="card-header__count">{{ pagination.total }}</span>
                <span class="card-header__suffix">名员工</span>
              </div>
            </template>
            <EmployeeListTable
              :list="tableData"
              :loading="loading"
              :page="pagination.page"
              :page-size="pagination.pageSize"
              :total="pagination.total"
              @edit="openEditEmployee"
              @delete="handleDeleteEmployee"
              @page-change="(p: number) => { pagination.page = p; loadEmployees() }"
              @size-change="(s: number) => { pagination.pageSize = s; pagination.page = 1; loadEmployees() }"
            />
          </el-card>
        </div>
      </div>
    </div>

    <!-- Tab 2/3 占位 -->
    <div v-show="activeTab === 'onboarding'" class="tab-panel placeholder">
      <el-empty description="入职档案模块" />
    </div>
    <div v-show="activeTab === 'resignation'" class="tab-panel placeholder">
      <el-empty description="离职办理模块" />
    </div>

    <!-- 新建/编辑员工对话框 -->
    <EmployeeDialog
      v-model:visible="employeeDialogVisible"
      :submitting="submitting"
      :target="editingEmployee"
      :roles="roles"
      :dept-list="deptList"
      :pos-list="posList"
      :selected-skill-ids="selectedSkillIds"
      :skill-options="skillOptions"
      :loading-skill-options="loadingSkillOptions"
      @submit="submitEmployee"
    />
  </div>
</template>

<script setup lang="ts">
import EmployeeListTable from './components/organization-list/EmployeeListTable.vue'
import CategoryTree from './components/CategoryTree.vue'
import EmployeeDialog from './components/EmployeeDialog.vue'
import EmployeeToolbar from './components/EmployeeToolbar.vue'
import { useOrganization } from './composables/useOrganization'

const {
  activeTab, tabs, goOnboardings, goResignations,
  submitting, deptList, posList, roles,
  selectedDeptId, listFilters, pagination, tableData, loading,
  loadEmployees, handleListSearch, handleListReset, onTreeRefresh,
  employeeDialogVisible, editingEmployee, skillOptions, selectedSkillIds, loadingSkillOptions,
  openCreateEmployee, openEditEmployee, submitEmployee, handleDeleteEmployee,
} = useOrganization()
</script>

<style lang="scss" scoped>
.employee-container {
  display: flex;
  flex-direction: column;
  height: 100%;
  padding: 16px 20px 20px;
  background: #f5f7fa;
  gap: 12px;
  overflow: hidden;
}

.tab-bar {
  display: flex;
  align-items: center;
  background: #fff;
  border-radius: 8px;
  padding: 4px 16px;
  height: 48px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
  flex-shrink: 0;
  &__title {
    font-size: 16px;
    font-weight: 600;
    color: #303133;
    padding-right: 16px;
    border-right: 1px solid #ebeef5;
    margin-right: 12px;
  }
  &__nav {
    display: flex;
    align-items: center;
    gap: 4px;
  }
  &__item {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 6px 14px;
    font-size: 14px;
    color: #606266;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
    &:hover { color: #0C447C; background: #f0f4fa; }
    &.is-active {
      color: #0C447C;
      background: #f0f4fa;
      font-weight: 600;
    }
  }
}

.tab-panel {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
}
.placeholder {
  align-items: center;
  justify-content: center;
}

.employee-body {
  flex: 1;
  display: flex;
  gap: 12px;
  min-height: 0;
  &__tree {
    width: 280px;
    flex-shrink: 0;
    min-height: 0;
  }
  &__table {
    flex: 1;
    min-width: 0;
    min-height: 0;
    display: flex;
    flex-direction: column;
  }
}

.table-card {
  flex: 1;
  display: flex;
  flex-direction: column;
  border-radius: 8px;
  :deep(.el-card__header) {
    padding: 0 20px;
    height: 48px;
  }
  :deep(.el-card__body) {
    flex: 1;
    display: flex;
    flex-direction: column;
    padding: 16px 20px;
    min-height: 0;
  }
}
.card-header {
  display: flex;
  align-items: center;
  gap: 8px;
  height: 48px;
  &__bar {
    width: 3px;
    height: 14px;
    border-radius: 2px;
    background: linear-gradient(180deg, #0C447C 0%, #1D9E75 100%);
  }
  &__title {
    font-size: 15px;
    font-weight: 600;
    color: #303133;
  }
  &__count {
    font-family: 'JetBrains Mono', 'Consolas', monospace;
    font-size: 16px;
    font-weight: 700;
    color: #0C447C;
    font-variant-numeric: tabular-nums;
  }
  &__suffix {
    font-size: 13px;
    color: #909399;
  }
}
</style>
