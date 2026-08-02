<template>
  <div class="page-container">
    <div v-if="!wo" class="loading-state">
      <el-icon class="is-loading"><Loading /></el-icon>
      <span>加载中…</span>
    </div>

    <template v-else>
      <!-- 顶部状态条 -->
      <WorkOrderStatusBar
        :wo="wo"
        @assign="onAssign"
        @start="onStart"
        @resolve="onResolve"
        @convert="onConvert"
        @open-purchase="openPurchaseDialog"
      />

      <!-- V0.6.3: 发起采购弹窗 -->
      <el-dialog v-model="showPurchaseDialog" title="发起采购需求" width="600px" :close-on-click-modal="false">
        <el-form :model="purchaseForm" label-width="100px">
          <el-form-item label="物料名称" required>
            <el-input v-model="purchaseForm.material" readonly placeholder="请选择库存物料" @click="purchasePickerVisible = true">
              <template #append>
                <el-button @click.stop="purchasePickerVisible = true">选择</el-button>
              </template>
            </el-input>
          </el-form-item>
          <el-form-item label="规格">
            <el-input v-model="purchaseForm.spec" placeholder="可选" />
          </el-form-item>
          <el-form-item label="数量" required>
            <el-input-number v-model="purchaseForm.quantity" :min="1" :step="1" style="width: 100%" />
          </el-form-item>
          <el-form-item label="单位">
            <el-input v-model="purchaseForm.unit" placeholder="个 / 台 / 米" style="width: 100%" />
          </el-form-item>
          <el-form-item label="需求日期">
            <el-date-picker v-model="purchaseForm.need_date" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
          </el-form-item>
          <el-form-item label="优先级">
            <el-select v-model="purchaseForm.priority" style="width: 100%">
              <el-option label="低" value="low" />
              <el-option label="中" value="normal" />
              <el-option label="高" value="high" />
              <el-option label="紧急" value="urgent" />
            </el-select>
          </el-form-item>
          <el-form-item label="备注">
            <el-input v-model="purchaseForm.remark" type="textarea" :rows="2" placeholder="可选" />
          </el-form-item>
        </el-form>
        <template #footer>
          <el-button @click="showPurchaseDialog = false">取消</el-button>
          <el-button type="primary" :loading="submittingPurchase" @click="submitPurchase">提交</el-button>
        </template>
        <InventoryItemPicker
          :show="purchasePickerVisible"
          @close="purchasePickerVisible = false"
          @select="handlePurchaseItemSelected"
        />
      </el-dialog>

      <!-- 转返修成功提示 -->
      <el-alert
        v-if="wo.converted_repair_id"
        type="warning"
        :closable="false"
        show-icon
        style="margin-bottom: 12px;"
      >
        <template #title>
          此工单已转为返修单
          <el-link type="primary" @click="$router.push(`/maintenance/repairs/${wo.converted_repair_id}`)">查看返修单</el-link>
        </template>
      </el-alert>

      <!-- Tab 切换 (移动友好) -->
      <div class="content-card">
        <el-tabs v-model="activeTab" class="detail-tabs">
          <!-- Tab 1: 基本信息 -->
          <el-tab-pane label="基本信息" name="basic">
            <WorkOrderBasicTab :wo="wo" :format-date="formatDate" />
          </el-tab-pane>

          <!-- Tab 2: 设备/故障 -->
          <el-tab-pane label="设备 / 故障" name="equipment">
            <WorkOrderEquipmentTab :wo="wo" :format-date="formatDate" />
          </el-tab-pane>

          <!-- Tab 3: 费用 -->
          <el-tab-pane label="费用" name="cost">
            <WorkOrderCostTab :wo="wo" />
          </el-tab-pane>

          <!-- Tab 4: 时间线 -->
          <el-tab-pane label="时间线" name="timeline">
            <WorkOrderTimelineTab :timeline="timeline" />
          </el-tab-pane>

          <!-- V0.5.7 块2 — 维修过程照片 (7 步进度) -->
          <el-tab-pane label="过程照片" name="photos">
            <StepPhotoUploader
              v-if="wo?.id"
              target-type="work_order"
              :target-id="wo.id"
            />
          </el-tab-pane>
        </el-tabs>
      </div>

      <!-- 底部固定操作栏 (移动) -->
      <div class="bottom-bar show-mobile">
        <el-button v-if="wo.status === 'pending' && !wo.is_locked" type="primary" @click="onAssign" style="flex:1;">派单</el-button>
        <el-button v-if="wo.status === 'assigned' && !wo.is_locked" type="warning" @click="onStart" style="flex:1;">开始</el-button>
        <el-button v-if="wo.status === 'in_progress' && !wo.is_locked" type="danger" @click="onConvert" style="flex:1;">转返修</el-button>
        <el-button v-if="wo.status === 'in_progress' && !wo.is_locked" type="success" @click="onResolve" style="flex:1;">完成</el-button>
        <el-button v-if="wo.status === 'pending' || wo.status === 'assigned'" @click="onCancel" plain>取消</el-button>
      </div>
    </template>

    <!-- 完成 dialog (V0.5.5.2 A4) -->
    <el-dialog v-model="resolveVisible" title="完成工单" width="560px" :close-on-press-escape="!resolving" :close-on-click-modal="!resolving">
      <el-form :model="resolveForm" label-width="100px">
        <el-form-item label="处理结果" required>
          <el-input v-model="resolveForm.result_notes" type="textarea" :rows="3" placeholder="现场处理结果/已解决的具体问题" maxlength="2000" show-word-limit />
        </el-form-item>
        <el-form-item label="服务费 (¥)">
          <el-input-number v-model="resolveForm.service_fee" :min="0" :precision="2" :step="50" style="width: 100%" />
        </el-form-item>
        <el-form-item label="配件费 (¥)">
          <el-input-number v-model="resolveForm.parts_cost" :min="0" :precision="2" :step="50" style="width: 100%" />
        </el-form-item>
        <el-form-item v-if="wo?.service_type === 'on_site'" label="客户签字" required>
          <div class="signature-pad">
            <canvas
              ref="sigCanvas"
              width="400"
              height="120"
              @mousedown="startDraw"
              @mousemove="draw"
              @mouseup="endDraw"
              @mouseleave="endDraw"
            />
            <el-button size="small" @click="clearSignature" style="margin-top: 4px">清空签字</el-button>
          </div>
          <div class="signature-hint">请客户在签字板上签名确认服务完成</div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="resolveVisible = false" :disabled="resolving">取消</el-button>
        <el-button type="success" @click="onResolveConfirm" :loading="resolving">完成</el-button>
      </template>
    </el-dialog>

    <!-- 转返修 dialog -->
    <el-dialog v-model="convertVisible" title="转为返修单" width="500px" :close-on-press-escape="!converting" :close-on-click-modal="!converting">
      <el-form :model="convertForm" label-width="100px">
        <el-form-item label="原因" required>
          <el-input v-model="convertForm.reason" type="textarea" :rows="3" placeholder="现场检测, 需要返厂维修的原因" maxlength="500" show-word-limit />
        </el-form-item>
        <el-form-item label="维修方式">
          <el-select v-model="convertForm.method_type" placeholder="预估" style="width: 100%">
            <el-option label="🆓 免费（保内）" value="free_warranty" />
            <el-option label="🆓 免费（合同）" value="free_contract" />
            <el-option label="💰 付费（维修）" value="paid_repair" />
            <el-option label="💰 付费（换新）" value="paid_replace" />
            <el-option label="↩️ 退回（不修）" value="returned" />
          </el-select>
        </el-form-item>
        <el-form-item label="预计完成">
          <el-date-picker v-model="convertForm.expected_finish_at" type="datetime" placeholder="预计完成时间" format="YYYY-MM-DD HH:mm" value-format="YYYY-MM-DD HH:mm:ss" style="width: 100%" />
        </el-form-item>
        <el-alert type="info" :closable="false" title="转返修后, 原工单将锁定, 不可再编辑" />
      </el-form>
      <template #footer>
        <el-button @click="convertVisible = false">取消</el-button>
        <el-button type="danger" @click="onConvertConfirm" :loading="converting">确认转返修</el-button>
      </template>
    </el-dialog>

    <!-- 派单 dialog -->
    <el-dialog v-model="assignVisible" title="派单" width="400px">
      <el-form label-width="80px">
        <el-form-item label="工程师" required>
          <el-select v-model="assignForm.engineer_id" filterable placeholder="选择工程师" style="width: 100%">
            <el-option v-for="u in engineers" :key="u.id" :label="`${u.name} (${u.username})`" :value="u.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="assignForm.note" type="textarea" :rows="2" maxlength="200" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="assignVisible = false">取消</el-button>
        <el-button type="primary" @click="onAssignConfirm" :loading="assigning">派单</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { Loading } from '@element-plus/icons-vue'
import StepPhotoUploader from './components/StepPhotoUploader.vue'
import InventoryItemPicker from '@/views/inventory/components/InventoryItemPicker.vue'
import { useWorkOrderDetail } from './composables/useWorkOrderDetail'
import WorkOrderStatusBar from './components/WorkOrderStatusBar.vue'
import WorkOrderBasicTab from './components/WorkOrderBasicTab.vue'
import WorkOrderEquipmentTab from './components/WorkOrderEquipmentTab.vue'
import WorkOrderCostTab from './components/WorkOrderCostTab.vue'
import WorkOrderTimelineTab from './components/WorkOrderTimelineTab.vue'

const {
  wo, activeTab, engineers,
  convertVisible, converting, convertForm,
  assignVisible, assigning, assignForm,
  timeline, formatDate,
  showPurchaseDialog, submittingPurchase, purchaseForm, purchasePickerVisible,
  openPurchaseDialog, handlePurchaseItemSelected, submitPurchase,
  onAssign, onAssignConfirm, onStart,
  resolveVisible, resolveForm, resolving, onResolve, onResolveConfirm,
  sigCanvas, startDraw, draw, endDraw, clearSignature,
  onCancel, onConvert, onConvertConfirm,
} = useWorkOrderDetail()
</script>

<style scoped lang="scss">
.page-container { padding: 20px; }

.loading-state {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  padding: 60px;
  color: #909399;
}

.signature-pad {
  border: 1px solid #DCDFE6; border-radius: 4px; padding: 4px; background: #FAFAFA;
  canvas { background: #fff; cursor: crosshair; display: block; border-radius: 3px; }
}
.signature-hint { font-size: 11px; color: #909399; margin-top: 4px; }

.content-card {
  background: #fff;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}

.bottom-bar {
  display: none;
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  background: #fff;
  padding: 12px 16px;
  box-shadow: 0 -2px 8px rgba(0,0,0,0.08);
  z-index: 100;
  gap: 8px;
}

.show-mobile { display: none; }

@media (max-width: 768px) {
  .page-container { padding: 12px; padding-bottom: 80px; }
  .show-mobile { display: flex; }
}
</style>
