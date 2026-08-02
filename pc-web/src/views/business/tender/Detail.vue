<template>
  <div class="page-container" v-loading="loading">
    <div class="page-header">
      <div class="page-title-wrap">
        <el-button :icon="ArrowLeft" link @click="goBack">返回</el-button>
        <span class="page-title">{{ detail?.name || '招标项目详情' }}</span>
        <el-tag v-if="detail" size="small" :type="statusTag(detail.status)" effect="light">
          {{ detail.status_label || detail.status }}
        </el-tag>
      </div>
      <div class="header-actions">
        <el-button v-if="canEdit" type="primary" plain @click="onEdit">编辑</el-button>
        <!-- V0.6.5 Sprint 4: 状态机按钮 -->
        <el-button v-if="canSubmitReview" type="warning" @click="onSubmitReview">提交审核</el-button>
        <!-- V1.2.7h: 审核通过/驳回 统一进审批中心, 此处只保留"提交审核" -->
        <el-tag v-if="canApprove || canReject" type="info" effect="plain" class="ml-8">请前往审批中心审核</el-tag>
        <el-button v-if="canWithdraw" type="danger" plain @click="showWithdrawDialog = true">撤回</el-button>
        <el-button v-if="canCancelV2" type="danger" plain @click="showCancelV2Dialog = true">废标</el-button>
        <!-- V0.6.0 旧版按钮 (V0.6.5 中部分已废弃, 保留 fallback) -->
        <el-button v-if="canPublish" type="success" @click="onPublish">发布(旧)</el-button>
        <el-button v-if="canEvaluate" type="primary" @click="showEvaluate = true">评标打分</el-button>
        <el-button v-if="canAward" type="success" @click="showAward = true">定标</el-button>
        <el-button v-if="canClose" @click="onClose">关闭</el-button>
        <el-button v-if="canCancel" type="danger" plain @click="onCancel">取消(旧)</el-button>
      </div>
    </div>

    <el-tabs v-model="activeTab" class="detail-tabs" v-if="detail">
      <!-- Tab 1: 基本信息 -->
      <el-tab-pane label="基本信息" name="basic">
        <TenderBasicInfo :detail="detail" :public-url="publicUrl" @copy-url="copyUrl" />
      </el-tab-pane>

      <!-- Tab 2: 投标 -->
      <el-tab-pane :label="`投标 (${bids.length})`" name="bids">
        <TenderBidsTable
          :bids="bids"
          :loading-bids="loadingBids"
          :can-award="canAward"
          @compare="showCompare = true"
          @view="viewBid"
          @award="quickAward"
        />
      </el-tab-pane>

      <!-- Tab 3: 中标落账 (V0.6.4 招标联动) -->
      <el-tab-pane v-if="downstream?.summary?.has_po" label="中标落账" name="downstream">
        <TenderDownstream :downstream="downstream" @go-po="goPurchaseDetail" />
      </el-tab-pane>

      <!-- Tab 5: V0.6.5 Sprint 4 保证金 -->
      <el-tab-pane :label="`保证金 (${depositSummary.total || 0})`" name="deposits">
        <TenderDeposits
          :deposit-rule="depositRule"
          :deposits="deposits"
          :deposit-summary="depositSummary"
          :loading-deposits="loadingDeposits"
          :can-edit-deposit-rule="canEditDepositRule"
          @edit-rule="showDepositRuleDialog = true"
          @mark-paid="onMarkPaid"
          @refund="onRefund"
          @forfeit="onForfeit"
        />
      </el-tab-pane>

      <!-- Tab 4: 附件 -->
      <el-tab-pane label="附件" name="attachments">
        <TenderAttachments
          :attachments="attachments"
          :before-upload="beforeUpload"
          :on-upload="onUpload"
          :open-file="openFile"
          :on-delete-att="onDeleteAtt"
        />
      </el-tab-pane>
    </el-tabs>

    <!-- 子对话框 -->
    <BidCompareDialog v-model:visible="showCompare" :bids="bids" :required-items="detail?.required_items" />
    <EvaluateDialog
      v-model:visible="showEvaluate"
      :tender-id="Number(id)"
      :bids="bids"
      :score-config="detail?.score_config"
      @saved="loadAll"
    />
    <AwardDialog
      v-model:visible="showAward"
      :tender-id="Number(id)"
      :bids="bids"
      :default-bid-id="defaultAwardBidId"
      @awarded="onAwarded"
    />
    <BidDetailDialog v-model:visible="showBidDetail" :bid="currentBid" />

    <EditTenderDialog
      v-model:visible="showEdit"
      :tender="detail"
      @saved="loadAll"
    />

    <!-- V0.6.5 Sprint 4: 状态机弹窗 -->
    <el-dialog v-model="showRejectDialog" title="驳回招标" width="500px">
      <el-form label-width="80px">
        <el-form-item label="驳回原因" required>
          <el-input v-model="rejectForm.reason" type="textarea" :rows="3" placeholder="请说明驳回原因, 创建人会看到" />
        </el-form-item>
        <el-form-item label="处理方式">
          <el-radio-group v-model="rejectForm.backToDraft">
            <el-radio :value="true">打回草稿 (可继续编辑后重新提交)</el-radio>
            <el-radio :value="false">保持驳回 (终态)</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showRejectDialog = false">取消</el-button>
        <el-button type="danger" @click="onConfirmReject">确认驳回</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="showWithdrawDialog" title="撤回招标" width="500px">
      <el-alert type="warning" :closable="false" style="margin-bottom:12px">
        撤回后状态将变为「已撤回」, 不能再编辑发布。<br>
        <strong>注意: 仅当还没有任何投标时才能撤回</strong>
      </el-alert>
      <el-form label-width="80px">
        <el-form-item label="撤回原因" required>
          <el-input v-model="withdrawForm.reason" type="textarea" :rows="3" placeholder="请说明撤回原因" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showWithdrawDialog = false">取消</el-button>
        <el-button type="danger" @click="onConfirmWithdraw">确认撤回</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="showCancelV2Dialog" title="废标" width="500px">
      <el-alert type="error" :closable="false" style="margin-bottom:12px">
        废标是不可逆操作, 所有未完成的投标将被自动撤回。
      </el-alert>
      <el-form label-width="80px">
        <el-form-item label="废标原因" required>
          <el-input v-model="cancelV2Form.reason" type="textarea" :rows="3" placeholder="请说明废标原因" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCancelV2Dialog = false">取消</el-button>
        <el-button type="danger" @click="onConfirmCancelV2">确认废标</el-button>
      </template>
    </el-dialog>

    <!-- V0.6.5 Sprint 4: 保证金规则设置弹窗 -->
    <el-dialog v-model="showDepositRuleDialog" title="设置保证金规则" width="640px">
      <el-form :model="depositRuleForm" label-width="160px">
        <el-form-item label="需要保证金">
          <el-switch v-model="depositRuleForm.required" :active-value="true" :inactive-value="false" />
        </el-form-item>
        <el-form-item label="保证金金额" required>
          <el-input-number v-model="depositRuleForm.amount" :min="0" :step="500" :precision="2" style="width: 200px" />
          <span style="margin-left:8px" class="muted">元</span>
        </el-form-item>
        <el-form-item label="缴款截止 (距开标)">
          <el-input-number v-model="depositRuleForm.deadline_hours_before_open" :min="1" :max="720" style="width: 200px" />
          <span style="margin-left:8px" class="muted">小时</span>
        </el-form-item>
        <el-form-item label="自动退款 (未中标)">
          <el-input-number v-model="depositRuleForm.refund_policy.auto_refund_days" :min="1" :max="90" style="width: 200px" />
          <span style="margin-left:8px" class="muted">天内</span>
        </el-form-item>
        <el-form-item label="合同签订期限 (中标方)">
          <el-input-number v-model="depositRuleForm.refund_policy.forfeit_on_no_contract_sign_days" :min="1" :max="90" style="width: 200px" />
          <span style="margin-left:8px" class="muted">天内不签 → 没收</span>
        </el-form-item>
        <el-form-item label="银行账户">
          <el-input v-model="depositRuleForm.bank_account" placeholder="收款银行账号 (用于告知供应商打款)" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="depositRuleForm.note" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showDepositRuleDialog = false">取消</el-button>
        <el-button type="primary" @click="onSaveDepositRule">保存</el-button>
      </template>
    </el-dialog>

    <!-- V0.6.5 Sprint 4: 退保证金弹窗 -->
    <el-dialog v-model="showRefundDialog" title="退还保证金" width="500px">
      <el-descriptions :column="1" border size="small" style="margin-bottom:16px">
        <el-descriptions-item label="供应商">{{ refundForm.supplierName }}</el-descriptions-item>
        <el-descriptions-item label="原始金额">¥ {{ Number(refundForm.originalAmount || 0).toLocaleString() }}</el-descriptions-item>
      </el-descriptions>
      <el-form :model="refundForm" label-width="100px">
        <el-form-item label="退款金额" required>
          <el-input-number v-model="refundForm.refund_amount" :min="0.01" :max="Number(refundForm.originalAmount)" :precision="2" style="width: 100%" />
          <span class="muted" style="font-size:12px">如部分退 (如违约扣款), 状态变为「部分退还」</span>
        </el-form-item>
        <el-form-item label="退款方式" required>
          <el-radio-group v-model="refundForm.method">
            <el-radio value="bank_transfer">银行转账</el-radio>
            <el-radio value="cash">现金</el-radio>
            <el-radio value="original_channel">原路退回</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="退款原因" required>
          <el-input v-model="refundForm.reason" type="textarea" :rows="3" placeholder="如: 招标未中标/合同已签订/..." />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showRefundDialog = false">取消</el-button>
        <el-button type="primary" @click="onConfirmRefund">确认退款</el-button>
      </template>
    </el-dialog>

    <!-- V0.6.5 Sprint 4: 没收保证金弹窗 -->
    <el-dialog v-model="showForfeitDialog" title="没收保证金" width="500px">
      <el-alert type="error" :closable="false" style="margin-bottom:12px">
        没收后不可恢复 (除非人工调账), 请慎重。
      </el-alert>
      <el-descriptions :column="1" border size="small" style="margin-bottom:16px">
        <el-descriptions-item label="供应商">{{ forfeitForm.supplierName }}</el-descriptions-item>
        <el-descriptions-item label="金额">¥ {{ Number(forfeitForm.amount || 0).toLocaleString() }}</el-descriptions-item>
      </el-descriptions>
      <el-form :model="forfeitForm" label-width="100px">
        <el-form-item label="没收原因" required>
          <el-input v-model="forfeitForm.reason" type="textarea" :rows="3" placeholder="如: 中标后不签合同/流标..." />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showForfeitDialog = false">取消</el-button>
        <el-button type="danger" @click="onConfirmForfeit">确认没收</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ArrowLeft } from '@element-plus/icons-vue'
import { statusTag } from './utils'
import { useTenderDetail } from './composables/useTenderDetail'
import BidCompareDialog from './components/BidCompareDialog.vue'
import EvaluateDialog from './components/EvaluateDialog.vue'
import AwardDialog from './components/AwardDialog.vue'
import BidDetailDialog from './components/BidDetailDialog.vue'
import EditTenderDialog from './components/EditTenderDialog.vue'
import TenderBasicInfo from './components/TenderBasicInfo.vue'
import TenderBidsTable from './components/TenderBidsTable.vue'
import TenderDownstream from './components/TenderDownstream.vue'
import TenderDeposits from './components/TenderDeposits.vue'
import TenderAttachments from './components/TenderAttachments.vue'

const {
  id,
  detail, bids, attachments, downstream, deposits, depositRule, depositSummary,
  loading, loadingBids, loadingDeposits, activeTab,
  showCompare, showEvaluate, showAward, showBidDetail, showEdit,
  currentBid, defaultAwardBidId,
  showRejectDialog, showWithdrawDialog, showCancelV2Dialog,
  rejectForm, withdrawForm, cancelV2Form,
  showDepositRuleDialog, showRefundDialog, showForfeitDialog,
  depositRuleForm, refundForm, forfeitForm,
  publicUrl, canEdit, canSubmitReview, canApprove, canReject,
  canWithdraw, canCancelV2, canEditDepositRule,
  canPublish, canEvaluate, canAward, canClose, canCancel,
  loadAll,
  goBack, goPurchaseDetail, copyUrl,
  beforeUpload, onUpload, openFile, onDeleteAtt,
  onEdit, onSubmitReview, onConfirmReject, onConfirmWithdraw, onConfirmCancelV2,
  onSaveDepositRule, onMarkPaid, onRefund, onConfirmRefund, onForfeit, onConfirmForfeit,
  onPublish, onClose, onCancel,
  viewBid, quickAward, onAwarded,
} = useTenderDetail()
</script>

<style scoped lang="scss">
.page-container { padding: 16px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; flex-wrap: wrap; gap: 8px; }
.page-title-wrap { display: flex; align-items: center; gap: 10px; }
.page-title { font-size: 18px; font-weight: 600; }
.header-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.muted { color: #999; }
.ml-8 { margin-left: 8px; }
.detail-tabs { }
</style>
