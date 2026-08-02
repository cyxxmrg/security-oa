<template>
  <el-dialog
    :model-value="visible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    :title="title"
    width="900px"
    destroy-on-close
  >
    <div v-if="row">
      <el-descriptions :column="3" border size="small">
        <el-descriptions-item label="车牌号">{{ row.plate_no }}</el-descriptions-item>
        <el-descriptions-item label="品牌型号">{{ row.brand }} {{ row.model }}</el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="statusTagType(row.status)" effect="dark">{{ statusLabel(row.status) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="使用部门">{{ row.department?.name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="责任人">{{ row.responsibleUser?.name || row.responsible_user?.name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="燃料类型">{{ fuelTypeLabel(row.fuel_type) }}</el-descriptions-item>
      </el-descriptions>

      <el-tabs v-model="tab" class="detail-tabs">
        <el-tab-pane label="保险信息" name="insurance">
          <div v-if="insurances.length === 0" class="empty-tip">暂无保险记录</div>
          <el-table v-else :data="insurances" border size="small" max-height="400">
            <el-table-column prop="insurance_company" label="保险公司" />
            <el-table-column prop="policy_no" label="保单号" />
            <el-table-column label="险种" width="100">
              <template #default="{ row }">
                <el-tag :type="row.type === 'compulsory' ? 'danger' : 'primary'" effect="plain">
                  {{ row.type === 'compulsory' ? '交强险' : '商业险' }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="保费" width="100" align="right">
              <template #default="{ row }">¥ {{ formatMoney(row.premium) }}</template>
            </el-table-column>
            <el-table-column label="保险期限" width="220">
              <template #default="{ row }">
                <div class="date-range">
                  <span>{{ formatDate(row.start_date ?? '') }}</span>
                  <el-icon><Right /></el-icon>
                  <span>{{ formatDate(row.end_date ?? '') }}</span>
                </div>
              </template>
            </el-table-column>
            <el-table-column label="状态" width="100">
              <template #default="{ row }">
                <el-tag :type="row.status === 'expired' ? 'info' : 'success'" effect="dark">
                  {{ row.status === 'expired' ? '已过期' : '在保' }}
                </el-tag>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <el-tab-pane label="保养信息" name="maintenance">
          <div v-if="maintenances.length === 0" class="empty-tip">暂无保养记录</div>
          <el-table v-else :data="maintenances" border size="small" max-height="400">
            <el-table-column label="类型" width="100">
              <template #default="{ row }">
                <el-tag :type="row.maintenance_type === 'routine' ? 'success' : (row.maintenance_type === 'repair' ? 'warning' : 'info')" effect="plain">
                  {{ row.maintenance_type === 'routine' ? '常规' : (row.maintenance_type === 'repair' ? '维修' : '年检') }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="日期" width="110">
              <template #default="{ row }">{{ formatDate(row.maintenance_date ?? '') }}</template>
            </el-table-column>
            <el-table-column label="里程(km)" width="100" align="right">
              <template #default="{ row }">{{ row.mileage || '-' }}</template>
            </el-table-column>
            <el-table-column label="费用" width="100" align="right">
              <template #default="{ row }">¥ {{ formatMoney(row.cost) }}</template>
            </el-table-column>
            <el-table-column prop="description" label="保养内容" min-width="200" show-overflow-tooltip />
            <el-table-column label="下次保养" width="180">
              <template #default="{ row }">
                <div v-if="row.next_maintenance_date || row.next_maintenance_mileage" class="next-info">
                  <div v-if="row.next_maintenance_date">📅 {{ formatDate(row.next_maintenance_date ?? '') }}</div>
                  <div v-if="row.next_maintenance_mileage">🔧 {{ row.next_maintenance_mileage }} km</div>
                </div>
                <span v-else>-</span>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <el-tab-pane :label="`绑定油卡 (${fuelCards.length})`" name="fuelcard">
          <div v-if="fuelCards.length === 0" class="empty-tip">该车辆未绑定油卡</div>
          <div v-else>
            <el-descriptions v-for="card in fuelCards" :key="card.id" :column="3" border size="small" style="margin-bottom: 12px">
              <el-descriptions-item label="卡号">{{ card.card_no }}</el-descriptions-item>
              <el-descriptions-item label="发卡机构">{{ card.card_name || '-' }}</el-descriptions-item>
              <el-descriptions-item label="当前余额">
                <span class="balance">¥ {{ formatMoney(card.balance) }}</span>
              </el-descriptions-item>
              <el-descriptions-item label="发卡日期">{{ formatDate(card.issue_date ?? '') }}</el-descriptions-item>
              <el-descriptions-item label="到期日期">{{ formatDate(card.expire_date ?? '') }}</el-descriptions-item>
              <el-descriptions-item label="状态">
                <el-tag :type="card.status === 'active' ? 'success' : (card.status === 'lost' ? 'danger' : 'info')" effect="dark">
                  {{ card.status === 'active' ? '在用' : (card.status === 'lost' ? '挂失' : '过期') }}
                </el-tag>
              </el-descriptions-item>
            </el-descriptions>

            <h4 class="sub-title">最近充值记录</h4>
            <el-table :data="cardRecharges.slice(0, 5)" border size="small" max-height="300">
              <el-table-column label="日期" width="120">
                <template #default="{ row }">{{ formatDate(row.recharge_date) }}</template>
              </el-table-column>
              <el-table-column label="金额" width="120" align="right">
                <template #default="{ row }">
                  <span class="amount-add">+¥ {{ formatMoney(row.amount) }}</span>
                </template>
              </el-table-column>
              <el-table-column prop="payment_method" label="支付方式" width="120" />
              <el-table-column prop="operator" label="经办人" width="100" />
              <el-table-column prop="voucher_no" label="凭证号" width="160" />
              <el-table-column prop="notes" label="备注" show-overflow-tooltip />
            </el-table>
          </div>
        </el-tab-pane>
      </el-tabs>
    </div>
    <template #footer>
      <el-button @click="emit('update:visible', false)">关闭</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { Right } from '@element-plus/icons-vue'
import { InsuranceRecord, MaintenanceRecord, FuelCard, FuelCardRecharge } from '../../types'

interface NamedLike { name?: string; [k: string]: unknown }
interface VehicleDetail {
  plate_no?: string
  brand?: string
  model?: string
  status?: string
  department?: NamedLike | null
  responsibleUser?: NamedLike | null
  responsible_user?: NamedLike | null
  fuel_type?: string
  [k: string]: unknown
}

defineProps<{
  visible: boolean
  title: string
  row: VehicleDetail | null
  insurances: InsuranceRecord[]
  maintenances: MaintenanceRecord[]
  fuelCards: FuelCard[]
  cardRecharges: FuelCardRecharge[]
  statusLabel: (s?: string) => string
  statusTagType: (s?: string) => 'success' | 'warning' | 'info' | 'danger'
  fuelTypeLabel: (s?: string) => string
  formatMoney: (n: unknown) => string
  formatDate: (s: string) => string
}>()
const emit = defineEmits<{ (e: 'update:visible', v: boolean): void }>()
const tab = ref('insurance')
</script>

<style lang="scss" scoped>
.detail-tabs { margin-top: 16px; }
.empty-tip { color: #909399; text-align: center; padding: 32px; }
.date-range { display: flex; align-items: center; gap: 6px; }
.next-info { font-size: 12px; }
.balance { color: #1D9E75; font-weight: 600; }
.amount-add { color: #1D9E75; font-weight: 600; }
.sub-title { margin: 16px 0 8px; font-size: 14px; }
</style>
