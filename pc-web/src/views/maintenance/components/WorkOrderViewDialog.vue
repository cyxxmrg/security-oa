<template>
  <el-dialog v-model="visible" title="工单详情" width="1440px" destroy-on-close>
    <template v-if="wo">
      <!-- 状态 & 编号 -->
      <div class="detail-header">
        <div class="header-left">
          <code class="wo-code">{{ wo.code }}</code>
          <el-tag :type="wo.status_color" size="large" effect="dark" class="status-tag">{{ wo.status_label }}</el-tag>
          <el-tag :type="wo.priority_color" size="small" effect="plain">{{ wo.priority_label }}</el-tag>
        </div>
        <div class="header-right">
          <el-tag v-if="wo.is_locked" type="warning">已锁定</el-tag>
          <span class="muted">创建: {{ formatTime(wo.created_at) }}</span>
        </div>
      </div>

      <!-- 基本信息 -->
      <el-descriptions :column="4" border class="detail-section">
        <el-descriptions-item label="联系人" :span="1">{{ wo.contact_name || '—' }}</el-descriptions-item>
        <el-descriptions-item label="联系电话" :span="1">{{ wo.contact_phone || '—' }}</el-descriptions-item>
        <el-descriptions-item label="地址" :span="2">{{ wo.address || '—' }}</el-descriptions-item>
        <el-descriptions-item label="客户" :span="1">{{ wo.customer_name || '—' }}</el-descriptions-item>
        <el-descriptions-item label="服务类型" :span="1">{{ ({ on_site: '上门', remote: '远程', depot: '送修' })[wo.service_type || ''] || wo.service_type || '—' }}</el-descriptions-item>
        <el-descriptions-item label="预约时间" :span="1">{{ wo.scheduled_at ? formatTime(wo.scheduled_at) : '—' }}</el-descriptions-item>
        <el-descriptions-item label="工程师" :span="1">{{ wo.assignee_name || '未派' }}</el-descriptions-item>
      </el-descriptions>

      <!-- 收费信息 -->
      <el-descriptions :column="4" border class="detail-section" style="margin-top:12px">
        <el-descriptions-item label="收费方式" :span="1">
          {{ ({ warranty_free: '保内免费', contract_free: '合同内免费', paid: '收费' })[wo.charge_type || 'paid'] }}
        </el-descriptions-item>
        <el-descriptions-item label="关联项目" :span="1">{{ wo.project_name || '—' }}</el-descriptions-item>
        <el-descriptions-item label="关联合同" :span="1">{{ wo.contract_name || '—' }}</el-descriptions-item>
        <el-descriptions-item label="最低收费" :span="1">{{ wo.min_charge ? `¥${wo.min_charge}` : '—' }}</el-descriptions-item>
      </el-descriptions>

      <!-- 设备/故障 -->
      <el-descriptions :column="4" border class="detail-section" style="margin-top:12px">
        <el-descriptions-item label="品牌" :span="1">{{ wo.equipment_brand || '—' }}</el-descriptions-item>
        <el-descriptions-item label="型号" :span="1">{{ wo.equipment_model || '—' }}</el-descriptions-item>
        <el-descriptions-item label="序列号" :span="2">{{ wo.serial_no || '—' }}</el-descriptions-item>
        <el-descriptions-item label="故障描述" :span="4" :content-style="{ maxHeight: '120px', overflowY: 'auto' }">
          {{ wo.fault_description || '—' }}
        </el-descriptions-item>
        <el-descriptions-item label="备注" :span="4">{{ wo.remarks || '—' }}</el-descriptions-item>
      </el-descriptions>

      <!-- 处理信息 (已完成后显示) -->
      <el-descriptions v-if="wo.status === 'resolved' || wo.status === 'closed'" :column="4" border class="detail-section" style="margin-top:12px">
        <el-descriptions-item label="服务费" :span="1">¥{{ wo.service_fee ?? 0 }}</el-descriptions-item>
        <el-descriptions-item label="配件费" :span="1">¥{{ wo.parts_cost ?? 0 }}</el-descriptions-item>
        <el-descriptions-item label="合计" :span="1">¥{{ (wo.service_fee || 0) + (wo.parts_cost || 0) }}</el-descriptions-item>
        <el-descriptions-item label="开始" :span="1">{{ wo.started_at ? formatTime(wo.started_at) : '—' }}</el-descriptions-item>
        <el-descriptions-item label="完成" :span="1">{{ wo.completed_at ? formatTime(wo.completed_at) : '—' }}</el-descriptions-item>
      </el-descriptions>
    </template>
    <div v-else class="loading-placeholder">加载中...</div>

    <template #footer>
      <div class="dialog-footer-actions">
        <div class="footer-left">
          <el-button v-if="wo?.status === 'assigned' && !wo?.is_locked" type="primary" :icon="VideoPlay" size="large" @click="onStart" :loading="starting">开始施工</el-button>
          <el-button v-if="wo?.status === 'in_progress' && !wo?.is_locked" type="success" size="large" @click="onResolve">完成工单</el-button>
        </div>
        <div class="footer-right">
          <el-button @click="visible = false">关闭</el-button>
        </div>
      </div>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { ElMessage } from 'element-plus'
import { VideoPlay } from '@element-plus/icons-vue'
import { post } from '@/utils/request'
import { unwrapItem } from '@/utils/response'

const props = defineProps<{ modelValue: boolean; wo: Record<string, unknown> | null }>()
const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
  (e: 'done'): void
  (e: 'resolve', id: number): void
}>()
const visible = computed({ get: () => props.modelValue, set: (v) => emit('update:modelValue', v) })

const starting = ref(false)
const onStart = async () => {
  if (!props.wo?.id) return
  starting.value = true
  try {
    const res = await post(`/work-orders/${props.wo.id}/start`)
    const data = unwrapItem(res) || {}
    if (data.status === 'in_progress') {
      // 更新弹窗中的 wo 对象
      if (props.wo) {
        props.wo.status = 'in_progress'
        props.wo.status_label = '进行中'
        props.wo.started_at = data.started_at
      }
      ElMessage.success('已开始施工')
      emit('done')
    }
  } catch (e: unknown) {
    ElMessage.error((e as { message?: string })?.message || '操作失败')
  } finally {
    starting.value = false
  }
}
const onResolve = () => {
  if (props.wo?.id) {
    const id = Number(props.wo.id)
    visible.value = false
    emit('resolve', id)
  }
}

const formatTime = (s: string) => {
  if (!s) return ''
  return (s + '').replace('T', ' ').slice(0, 16)
}
</script>

<style scoped lang="scss">
.detail-header {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 16px;
  .header-left { display: flex; align-items: center; gap: 10px; }
  .header-right { display: flex; align-items: center; gap: 10px; }
  .wo-code { font-size: 18px; font-weight: 700; }
  .status-tag { font-size: 14px; padding: 4px 12px; }
}
.detail-section { margin-bottom: 0; }
.muted { color: #999; font-size: 13px; }
.loading-placeholder { padding: 60px; text-align: center; color: #999; }
</style>
