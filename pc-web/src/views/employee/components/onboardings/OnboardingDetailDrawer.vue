<template>
  <el-dialog
    v-model="visible"
    title="员工档案"
    width="1440px"
    destroy-on-close
    :close-on-click-modal="false"
  >
    <div v-if="detailRow" class="detail-content">
      <div class="detail-section">
        <div class="detail-section__title">基础信息</div>
        <el-descriptions :column="2" border>
          <el-descriptions-item label="姓名">{{ detailRow.user?.name || detailRow.name || '—' }}</el-descriptions-item>
          <el-descriptions-item label="账号">{{ detailRow.user?.username || detailRow.username || '—' }}</el-descriptions-item>
          <el-descriptions-item label="手机">{{ detailRow.user?.phone || detailRow.phone || '—' }}</el-descriptions-item>
          <el-descriptions-item label="邮箱">{{ detailRow.user?.email || detailRow.email || '—' }}</el-descriptions-item>
          <el-descriptions-item label="部门">{{ detailRow.department?.name || '—' }}</el-descriptions-item>
          <el-descriptions-item label="岗位">{{ detailRow.position?.name || '—' }}</el-descriptions-item>
          <el-descriptions-item label="入职日期">{{ detailRow.hire_date || '—' }}</el-descriptions-item>
          <el-descriptions-item label="试用期">{{ detailRow.probation_months ? detailRow.probation_months + ' 月' : '—' }}</el-descriptions-item>
          <el-descriptions-item label="合同起始">{{ detailRow.contract_start_date || '—' }}</el-descriptions-item>
          <el-descriptions-item label="合同结束">{{ detailRow.contract_end_date || '—' }}</el-descriptions-item>
          <el-descriptions-item label="导师" :span="2">
            {{ detailRow.mentor?.name || '—' }}
            <span v-if="detailRow.mentor?.username" style="color:#909399;">({{ detailRow.mentor.username }})</span>
          </el-descriptions-item>
          <el-descriptions-item label="状态" :span="2">
            <el-tag :type="statusTag(detailRow.status)" size="small">{{ statusLabel(detailRow.status) }}</el-tag>
          </el-descriptions-item>
        </el-descriptions>
      </div>

      <div class="detail-section">
        <div class="detail-section__title">证件与档案</div>
        <el-descriptions :column="2" border>
          <el-descriptions-item label="身份证号">
            {{ detailRow.id_card_no || '—' }}
            <FileLink v-if="detailRow.id_card_file_id" :file-id="detailRow.id_card_file_id" :name="detailRow.id_card_file_name" />
          </el-descriptions-item>
          <el-descriptions-item label="驾驶证号">
            {{ detailRow.driver_license_no || '—' }}
          </el-descriptions-item>
          <el-descriptions-item label="驾驶证有效期">
            {{ detailRow.driver_license_expire || '—' }}
            <FileLink v-if="detailRow.driver_license_file_id" :file-id="detailRow.driver_license_file_id" :name="detailRow.driver_license_file_name" />
          </el-descriptions-item>
          <el-descriptions-item label="学历">
            {{ educationLabel(detailRow.education_level) || '—' }}
            <template v-if="detailRow.education_school || detailRow.education_major">
              <span style="color:#909399; margin-left:8px;">
                {{ detailRow.education_school || '' }} {{ detailRow.education_major ? '· ' + detailRow.education_major : '' }}
              </span>
            </template>
            <FileLink v-if="detailRow.education_file_id" :file-id="detailRow.education_file_id" :name="detailRow.education_file_name" />
          </el-descriptions-item>
          <el-descriptions-item label="劳动合同">
            <FileLink v-if="detailRow.contract_file_id" :file-id="detailRow.contract_file_id" :name="detailRow.contract_file_name" />
            <span v-else>—</span>
          </el-descriptions-item>
        </el-descriptions>
      </div>
    </div>
    <template #footer>
      <el-button @click="visible = false">关闭</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { Onboarding } from './types'
import { statusLabel, statusTag, educationLabel } from './types'
import FileLink from '../FileLink.vue'

// v0.3.22 抽自 employee/Onboardings.vue:381-442
const props = defineProps<{
  modelValue: boolean
  detailRow: Onboarding | null
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
}>()

const visible = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})
</script>

<style lang="scss" scoped>
.detail-content {
  padding: 0 4px;
}
.detail-section {
  margin-bottom: 20px;
  &__title {
    font-size: 15px;
    font-weight: 600;
    color: #303133;
    margin-bottom: 10px;
    padding-left: 8px;
    border-left: 3px solid #0C447C;
  }
}
</style>
