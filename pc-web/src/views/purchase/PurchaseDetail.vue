<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">采购详情</span>
      <div class="header-actions">
        <el-button :icon="Refresh" plain @click="loadOrders">刷新</el-button>
      </div>
    </div>

    <!-- 顶部: 搜索 + 订单列表 -->
    <PurchaseOrderList
      :search-form="searchForm"
      :loading-orders="loadingOrders"
      :order-status-options="orderStatusOptions"
      :paged-orders="pagedOrders"
      :total="filteredOrders.length"
      v-model:page="page"
      v-model:page-size="pageSize"
      @search="handleSearch"
      @reset="handleReset"
      @select-order="handleSelectOrder"
    />

    <!-- 下方: 选中订单的 4 Tab 详情 -->
    <div v-if="currentOrder" class="content-card" style="margin-top: 12px">
      <div class="order-header">
        <div class="order-title">
          <el-icon :size="18" color="#0C447C"><Document /></el-icon>
          <span class="po-code">{{ currentOrder.code || currentOrder.po_no }}</span>
          <el-tag :type="orderStatusTagType(currentOrder.status)" effect="dark" size="small">{{ orderStatusLabel(currentOrder.status) }}</el-tag>
        </div>
        <div class="order-meta">
          <span>金额：<b class="money-text">¥ {{ formatMoney(currentOrder.total_amount) }}</b></span>
          <span>供应商：{{ currentOrder.supplier?.name || `#${currentOrder.supplier_id}` }}</span>
          <span>创建：{{ currentOrder.created_at ? String(currentOrder.created_at).slice(0, 16) : '-' }}</span>
        </div>
      </div>

      <el-tabs v-model="activeTab" class="detail-tabs">
        <!-- Tab 1: 基础信息 -->
        <el-tab-pane label="基础信息" name="basic">
          <PurchaseBasicTab
            :order="currentOrder"
            :loading-basic="loadingBasic"
            @go-tender="goTender"
          />
        </el-tab-pane>

        <!-- Tab 2: 合同 -->
        <el-tab-pane :label="`合同 (${contract ? contract.code : '无'})`" name="contract">
          <PurchaseContractTab
            :contract="contract"
            :contract-items="contractItems"
            :contract-files="contractFiles"
            :loading-contract="loadingContract"
            :editing-item-id="editingItemId"
            @add-contract-item="handleAddContractItem"
            @load-contract-items="loadContractItems"
            @save-contract-item="handleSaveContractItem"
            @cancel-edit-item="editingItemId = null"
            @edit-item="(id: number) => editingItemId = id"
            @delete-contract-item="handleDeleteContractItem"
            @upload-contract-file="handleUploadContractFile"
            @preview-file="handlePreviewFile"
            @download-file="handleDownloadFile"
            @delete-contract-file="handleDeleteContractFile"
          />
        </el-tab-pane>

        <!-- Tab 3: 付款 -->
        <el-tab-pane :label="`付款 (${paymentRequests.length})`" name="payment">
          <PurchasePaymentTab
            :payment-requests="paymentRequests"
            :loading-payments="loadingPayments"
            v-model:active-pay-req-ids="activePayReqIds"
            @upload-voucher="handleUploadVoucher"
            @preview-voucher="handlePreviewVoucher"
            @download-voucher="handleDownloadVoucher"
          />
        </el-tab-pane>

        <!-- Tab 4: 发货 -->
        <el-tab-pane :label="`发货 (${shippingList.length})`" name="shipping">
          <PurchaseShippingTab
            :shipping-list="shippingList"
            :loading-shipping="loadingShipping"
            @add-shipping="handleAddShipping"
            @load-shipping="loadShipping"
          />
        </el-tab-pane>
      </el-tabs>
    </div>

    <div v-else class="content-card empty-hint" style="margin-top:12px">
      <el-empty description="请在上方表格中选择一个采购订单查看详情" :image-size="120" />
    </div>

    <!-- 添加合同清单行 dialog -->
    <el-dialog v-model="showAddItemDialog" title="添加合同清单行" width="600px">
      <el-form :model="newItem" label-width="80px">
        <el-form-item label="物料" required>
          <el-input v-model="newItem.material" readonly placeholder="请选择库存物料" @click="contractItemPickerVisible = true">
            <template #append>
              <el-button @click.stop="contractItemPickerVisible = true">选择</el-button>
            </template>
          </el-input>
        </el-form-item>
        <el-form-item label="规格">
          <el-input v-model="newItem.spec" placeholder="如：DS-2CD3T47" />
        </el-form-item>
        <el-form-item label="数量" required>
          <el-input-number v-model="newItem.qty" :min="0" :step="1" style="width:100%" />
        </el-form-item>
        <el-form-item label="单位">
          <el-input v-model="newItem.unit" placeholder="件/台/箱" />
        </el-form-item>
        <el-form-item label="单价" required>
          <el-input-number v-model="newItem.unit_price" :min="0" :step="10" :precision="2" style="width:100%" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="newItem.remark" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showAddItemDialog = false">取消</el-button>
        <el-button type="primary" :loading="savingItem" @click="handleSaveNewItem">保存</el-button>
      </template>
      <InventoryItemPicker
        :show="contractItemPickerVisible"
        @close="contractItemPickerVisible = false"
        @select="handleContractInventorySelected"
      />
    </el-dialog>

    <!-- 添加发货预期 dialog -->
    <el-dialog v-model="showAddShippingDialog" title="添加发货预期 / 快递单号" width="600px">
      <el-form :model="newShipping" label-width="100px">
        <el-form-item label="物料 / 范围">
          <el-select v-model="newShipping.contract_item_id" placeholder="整单 (不选 = 整单)" clearable filterable style="width:100%">
            <el-option label="整单 (合同整体)" :value="undefined" />
            <el-option
              v-for="it in contractItems"
              :key="it.id"
              :label="`${it.material}${it.spec ? ' / ' + it.spec : ''}`"
              :value="it.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="预计发货">
          <el-date-picker v-model="newShipping.expected_at" type="date" value-format="YYYY-MM-DD" style="width:100%" />
        </el-form-item>
        <el-form-item label="物流公司">
          <el-input v-model="newShipping.carrier" placeholder="如：顺丰" />
        </el-form-item>
        <el-form-item label="快递单号">
          <el-input v-model="newShipping.tracking_no" placeholder="如：SF1234567890" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="newShipping.remark" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showAddShippingDialog = false">取消</el-button>
        <el-button type="primary" :loading="savingShipping" @click="handleSaveShipping">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { Document, Refresh } from '@element-plus/icons-vue'
import InventoryItemPicker from '@/views/inventory/components/InventoryItemPicker.vue'
import { usePurchaseDetail } from './composables/usePurchaseDetail'
import PurchaseOrderList from './components/PurchaseOrderList.vue'
import PurchaseBasicTab from './components/PurchaseBasicTab.vue'
import PurchaseContractTab from './components/PurchaseContractTab.vue'
import PurchasePaymentTab from './components/PurchasePaymentTab.vue'
import PurchaseShippingTab from './components/PurchaseShippingTab.vue'

const {
  loadingOrders, searchForm, page, pageSize,
  orderStatusOptions, orderStatusLabel, orderStatusTagType,
  filteredOrders, pagedOrders, loadOrders, handleSearch, handleReset,
  currentOrder, activeTab, loadingBasic, handleSelectOrder,
  loadingContract, contract, contractItems, contractFiles,
  editingItemId, showAddItemDialog, savingItem, newItem, contractItemPickerVisible,
  loadContractItems, handleSaveContractItem, handleAddContractItem,
  handleContractInventorySelected, handleSaveNewItem, handleDeleteContractItem,
  handleUploadContractFile, handlePreviewFile, handleDownloadFile, handleDeleteContractFile,
  loadingPayments, paymentRequests, activePayReqIds,
  handleUploadVoucher, handlePreviewVoucher, handleDownloadVoucher,
  loadingShipping, shippingList, showAddShippingDialog, savingShipping, newShipping,
  loadShipping, handleAddShipping, handleSaveShipping,
  formatMoney, goTender,
} = usePurchaseDetail()
</script>

<style lang="scss" scoped>
.page-container { padding: 16px; background: #f5f7fa; min-height: calc(100vh - 60px); }
.page-header {
  display: flex; justify-content: space-between; align-items: center;
  background: #fff; padding: 16px 20px; border-radius: 8px; margin-bottom: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  .page-title { font-size: 18px; font-weight: 600; color: #0C447C; border-left: 4px solid #0C447C; padding-left: 10px; }
  .header-actions { display: flex; gap: 8px; }
}
.content-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04); }
.empty-hint { padding: 30px 0; text-align: center; }

.order-header {
  display: flex; flex-direction: column; gap: 6px; padding-bottom: 12px;
  border-bottom: 1px solid #ebeef5; margin-bottom: 8px;
  .order-title { display: flex; align-items: center; gap: 8px;
    .po-code { font-size: 18px; font-weight: 700; color: #0C447C; font-family: monospace; }
  }
  .order-meta { display: flex; gap: 16px; color: #606266; font-size: 13px;
    .money-text { color: #1D9E75; font-weight: 600; }
  }
}
.money-text { color: #1D9E75; font-weight: 600; }
</style>
