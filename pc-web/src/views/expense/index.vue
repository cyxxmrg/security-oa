<template>
  <div class="page-container">
    <div class="page-header">
      <h2>报销管理</h2>
      <div class="header-stats" v-if="stats">
        <span class="stat-chip">合计 {{ stats.total }} 单</span>
        <span class="stat-chip stat-pending">待审批 {{ stats.pending }}</span>
        <span class="stat-chip stat-approved">已审批 {{ stats.approved }}</span>
        <span class="stat-chip stat-paid">已付款 {{ stats.paid }}</span>
        <span class="stat-chip stat-money">总额 ¥{{ Number(stats.totalAmount || 0).toFixed(2) }}</span>
      </div>
    </div>

    <!-- V1.2.7g: tab 切换: 列表/统计 -->
    <el-tabs v-model="activeTab" class="expense-tabs" @tab-change="onTabChange">
      <el-tab-pane name="list" label="📋 报销列表"></el-tab-pane>
      <el-tab-pane name="stats" label="📊 报销统计"></el-tab-pane>
    </el-tabs>

    <!-- ==================== 列表 tab ==================== -->
    <div v-show="activeTab === 'list'">
      <ExpenseListTab
        :search-form="searchForm"
        :status-options="statusOptions"
        :category-options="categoryOptions"
        :loading="loading"
        :list="list"
        :pagination="pagination"
        :can-cancel="canCancel"
        :can-delete="canDelete"
        :can-pay="canPay"
        :expense-status-type="expenseStatusType"
        :format-date="formatDate"
        :expense-category-label="expenseCategoryLabel"
        :common-status-label="commonStatusLabel"
        @search="loadList"
        @reset="resetSearch"
        @apply="switchApply"
        @view="handleView"
        @cancel="handleCancel"
        @delete="handleDelete"
        @pay="handlePay"
        @size-change="(s: number) => { pagination.per_page = s; loadList(1) }"
      />
    </div>

    <!-- ==================== 申请报销弹窗 ==================== -->
    <el-dialog v-model="showApplyDialog" title="申请报销" width="900px" :close-on-click-modal="false" destroy-on-close>
      <div class="content-card" v-loading="loading" style="box-shadow:none;padding:0">
        <el-form ref="formRef" :model="form" :rules="formRules" label-width="120px" style="max-width: 820px">
          <el-form-item label="费用类别" prop="category">
            <el-select v-model="form.category" placeholder="请选择费用类别" style="width: 100%">
              <el-option v-for="o in categoryOptions" :key="o.value" :label="o.label" :value="o.value" />
            </el-select>
          </el-form-item>
          <el-form-item label="关联项目">
            <el-select v-model="form.project_id" placeholder="请选择关联项目（可选）" clearable filterable style="width: 100%">
              <el-option
                v-for="p in projectOptions"
                :key="p.id"
                :label="p.code ? `${p.name}（${p.code}）` : p.name"
                :value="p.id"
              />
            </el-select>
          </el-form-item>
          <el-form-item label="报销事由" prop="description">
            <el-input v-model="form.description" type="textarea" :rows="3" placeholder="请详细说明报销原因/事由" maxlength="1000" show-word-limit />
          </el-form-item>

          <div class="section-title">费用明细</div>
          <div class="detail-table-wrap">
            <el-table :data="form.items" border style="width: 100%">
              <el-table-column label="发生日期" width="180">
                <template #default="{ row }">
                  <el-date-picker v-model="row.item_date" type="date" value-format="YYYY-MM-DD" placeholder="选择日期" style="width: 100%" size="small" />
                </template>
              </el-table-column>
              <el-table-column label="费用说明" min-width="240">
                <template #default="{ row }">
                  <el-input v-model="row.description" placeholder="例如：高铁票（深圳-南京）" size="small" maxlength="200" />
                </template>
              </el-table-column>
              <el-table-column label="金额" width="170">
                <template #default="{ row }">
                  <el-input-number v-model="row.amount" :min="0" :precision="2" size="small" controls-position="right" style="width: 100%" />
                </template>
              </el-table-column>
              <el-table-column label="操作" width="80" align="center" fixed="right">
                <template #default="{ $index }">
                  <el-button link type="danger" size="small" @click="removeItem($index)">删除</el-button>
                </template>
              </el-table-column>
            </el-table>
            <el-button type="primary" plain size="small" class="add-detail-btn" @click="addItem">+ 添加明细行</el-button>
          </div>

          <el-form-item label="合计金额">
            <span class="total-amount">¥ {{ totalAmount.toFixed(2) }}</span>
            <span class="total-tip" v-if="form.items.length > 0">（共 {{ form.items.length }} 条明细）</span>
          </el-form-item>
        </el-form>
      </div>
      <template #footer>
        <el-button @click="showApplyDialog = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleSubmit">提交申请</el-button>
      </template>
    </el-dialog>

    <!-- ==================== 统计 tab ==================== -->
    <div v-show="activeTab === 'stats'">
      <ExpenseStatsTab
        v-model:statsDateRange="statsDateRange"
        v-model:statsGroupByModel="statsGroupBy"
        :stats-loading="statsLoading"
        :stats-data="statsData"
        :stats-group="statsGroup"
        :stats-group-by="statsGroupBy"
        :format-money="formatMoney"
        @load-stats="loadStatsBoard"
        @reset-date="resetStatsDate"
      />
    </div>

    <!-- 详情对话框 -->
    <ExpenseDetailDialog
      v-model:visible="showDetailDialog"
      :row="detailRow"
      :loading="detailLoading"
      :status-type="expenseStatusType"
      :format-date="formatDate"
      :can-cancel="canCancel"
      :can-delete="canDelete"
      :can-pay="canPay"
      @action="handleDetailAction"
    />

    <!-- 付款对话框 -->
    <ExpensePayDialog
      v-model:visible="showPayDialog"
      :form="payForm"
      :target="payTarget"
      :loading="payLoading"
      @confirm="confirmPay"
    />
  </div>
</template>

<script setup lang="ts">
import ExpenseDetailDialog from './components/index/ExpenseDetailDialog.vue'
import ExpensePayDialog from './components/index/ExpensePayDialog.vue'
import { useExpense } from './composables/useExpense'
import ExpenseListTab from './components/ExpenseListTab.vue'
import ExpenseStatsTab from './components/ExpenseStatsTab.vue'

const {
  showApplyDialog, activeTab, switchApply, onTabChange,
  statusOptions, categoryOptions, expenseStatusType,
  formatDate, formatMoney,
  searchForm, list, loading, pagination, stats,
  loadList, resetSearch,
  formRef, submitting, projectOptions, form, formRules, totalAmount,
  addItem, removeItem, resetForm, handleSubmit,
  statsDateRange, statsGroupBy, statsLoading, statsData, statsGroup,
  resetStatsDate, loadStatsBoard,
  showDetailDialog, detailRow, detailLoading, handleView,
  canCancel, canDelete, canPay, handleDetailAction,
  handleCancel, handleDelete,
  showPayDialog, payTarget, payLoading, payForm, handlePay, confirmPay,
  expenseCategoryLabel, commonStatusLabel,
} = useExpense()
</script>

<style lang="scss" scoped>
.page-container {
  padding: 20px;
  background: #f5f7fa;
  min-height: 100vh;
}
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
  flex-wrap: wrap;
  gap: 12px;
  h2 { font-size: 20px; color: #0C447C; margin: 0; }
}
.header-stats {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.stat-chip {
  background: #fff;
  padding: 4px 12px;
  border-radius: 14px;
  font-size: 13px;
  color: #606266;
  box-shadow: 0 1px 3px rgba(0,0,0,0.06);
  &.stat-pending  { color: #e6a23c; }
  &.stat-approved { color: #67c23a; }
  &.stat-paid     { color: #0C447C; font-weight: 600; }
  &.stat-money    { color: #f56c6c; font-weight: 600; }
}
.content-card {
  background: #fff;
  border-radius: 8px;
  padding: 16px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.expense-tabs { margin-bottom: 16px; }
.total-amount { font-size: 18px; font-weight: 700; color: #A32D2D; }
.total-tip { margin-left: 8px; color: #909399; font-size: 13px; }
.section-title { font-size: 15px; font-weight: 600; color: #0C447C; margin: 16px 0 8px; border-left: 4px solid #0C447C; padding-left: 8px; }
.detail-table-wrap { margin-bottom: 16px; }
.add-detail-btn { margin-top: 8px; width: 100%; }
</style>
