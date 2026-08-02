<template>
  <div class="page-container">
    <div class="page-header">
      <div class="title-area">
        <span class="page-title">商机看板</span>
        <el-tag effect="light" type="info">{{ list.length }} 个商机</el-tag>
        <el-tag effect="plain" type="warning">合计 ¥ {{ formatMoney(totalAmount) }}</el-tag>
      </div>
      <div class="header-actions">
        <el-button :icon="List" @click="$router.push('/sales/opps')">列表视图</el-button>
      </div>
    </div>

    <div class="board-wrapper">
      <div class="board-canvas">
        <div v-for="col in columns" :key="col.value" class="board-column" :class="{ 'is-drop-target': dragOverCol === col.value }" @dragover.prevent="onDragOver(col.value, $event)" @dragleave="onDragLeave(col.value)" @drop.prevent="onDrop(col.value, $event)">
          <div class="column-header" :style="{ background: col.bg, color: col.color }">
            <span class="col-name">{{ col.label }}</span>
            <span class="col-count">{{ grouped[col.value]?.length || 0 }} · ¥ {{ formatMoney(stageTotal(col.value)) }}</span>
          </div>
          <div class="column-body">
            <div v-for="o in grouped[col.value] || []" :key="o.id" class="opp-card" :draggable="true" @dragstart="onDragStart(o)" @dragend="onDragEnd" @click="$router.push(`/sales/opps/${o.id}`)">
              <div class="card-head">
                <span class="opp-no">{{ o.opp_no }}</span>
                <span class="opp-amount">¥ {{ formatMoney(o.estimated_amount) }}</span>
              </div>
              <div class="card-name">{{ o.name }}</div>
              <div class="card-customer">
                <el-icon :size="12"><OfficeBuilding /></el-icon>
                {{ o.customer?.name || '-' }}
              </div>
              <div class="card-progress">
                <el-progress :percentage="o.probability || 0" :stroke-width="5" :show-text="false" :color="probabilityColor(o.probability)" />
                <span class="card-prob-text">{{ o.probability || 0 }}%</span>
              </div>
              <div class="card-foot">
                <span class="card-sales">
                  <el-icon :size="11"><User /></el-icon>
                  {{ o.sales?.name || '未分配' }}
                </span>
                <span v-if="o.expected_sign_date" class="card-date">
                  <el-icon :size="11"><Clock /></el-icon>
                  {{ formatDate(o.expected_sign_date) }}
                </span>
              </div>
            </div>
            <el-empty v-if="!grouped[col.value]?.length" :image-size="50" description="拖入商机" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { List, OfficeBuilding, User, Clock, Aim, Document, Money, Promotion, Trophy, ChatLineRound } from '@element-plus/icons-vue'
import { getOpps, updateOppStage } from '@/api/sales'
import { get } from '@/utils/request'
import { unwrapList } from '@/utils/response'
import type { Opportunity } from './types'

const list = ref<Opportunity[]>([])
const draggingId = ref<number | null>(null)
const dragOverCol = ref<string | null>(null)

// 前端 7 段列名 → DB 真实 stage 值 (v0.5.8: 7 段独立, 不再合并)
// 后端 DB 7 段真值: inquiry/qualification/proposal/negotiating/quoted/won/lost
// 兼容老 6 段: requirement/solution/negotiation/contracting
const STAGE_REVERSE: Record<string, string> = {
  inquiry: 'inquiry', requirement: 'inquiry',
  qualification: 'qualification', solution: 'qualification',
  site_survey: 'site_survey',
  proposal: 'proposal',
  negotiating: 'negotiating', negotiation: 'negotiating',
  quoted: 'quoted', contracting: 'quoted',
  won: 'won', lost: 'lost',
}

const columns = [
  { value: 'inquiry', label: '需求确认', bg: 'rgba(12, 68, 124, 0.1)', color: '#0C447C', icon: Aim },
  { value: 'qualification', label: '资质评估', bg: 'rgba(83, 74, 183, 0.1)', color: '#534AB7', icon: Document },
  { value: 'site_survey', label: '现场地勘', bg: 'rgba(58, 143, 205, 0.1)', color: '#3A8FCD', icon: Aim },
  { value: 'proposal', label: '方案设计', bg: 'rgba(186, 117, 23, 0.1)', color: '#BA7517', icon: Money },
  { value: 'negotiating', label: '报价谈判', bg: 'rgba(83, 74, 183, 0.15)', color: '#534AB7', icon: Money },
  { value: 'quoted', label: '已报价', bg: 'rgba(29, 158, 117, 0.1)', color: '#1D9E75', icon: Promotion },
  { value: 'won', label: '成交', bg: 'rgba(29, 158, 117, 0.2)', color: '#1D9E75', icon: Trophy },
  { value: 'lost', label: '战败', bg: 'rgba(163, 45, 45, 0.1)', color: '#A32D2D', icon: ChatLineRound }
]

const grouped = computed(() => {
  const g: Record<string, Opportunity[]> = {}
  for (const c of columns) g[c.value] = []
  for (const o of list.value) {
    // DB stage 归一到看板列名
    const col = STAGE_REVERSE[o.stage as string] || o.stage
    if (g[col]) g[col].push(o)
  }
  return g
})

const totalAmount = computed(() => list.value.reduce((s, o) => s + Number(o.estimated_amount || 0), 0))

const stageTotal = (s: string) => (grouped.value[s] || []).reduce((sum, o) => sum + Number(o.estimated_amount || 0), 0)

const loadList = async () => {
  try {
    // V1.3.1: 使用轻量 kanban API (QueryBuilder, 无 Eloquent 序列化开销)
    const r = await get('/sales/opps/kanban')
    list.value = unwrapList(r) || []
  } catch (e) { /* toast */ }
}

const onDragStart = (o: Opportunity, e?: DragEvent) => {
  draggingId.value = o.id
  try {
    if (e?.dataTransfer) {
      e.dataTransfer.effectAllowed = 'move'
      e.dataTransfer.setData('text/plain', String(o.id))
    }
  } catch { /* ignore */ }
}
const onDragEnd = () => { draggingId.value = null; dragOverCol.value = null }
const onDragOver = (col: string, e: DragEvent) => { if (e) e.preventDefault(); dragOverCol.value = col }
const onDragLeave = (col: string) => { if (dragOverCol.value === col) dragOverCol.value = null }
const onDrop = async (newBoardStage: string, e?: DragEvent) => {
  if (e) e.preventDefault()
  const id = draggingId.value
  if (!id) return
  const opp = list.value.find(o => o.id === id)
  if (!opp || STAGE_REVERSE[opp.stage] === newBoardStage) { draggingId.value = null; dragOverCol.value = null; return }
  // won / lost 不可拖入（只可由按钮触发）
  if (newBoardStage === 'won' || newBoardStage === 'lost') {
    ElMessage.warning(`「${newBoardStage === 'won' ? '成交' : '战败'}」状态需通过对应按钮触发，不可拖入`)
    draggingId.value = null; dragOverCol.value = null
    return
  }
  // 看板列名 → DB 真值 (v0.5.8: 7 段独立)
  const newDbStage = newBoardStage
  const oldBoard = STAGE_REVERSE[opp.stage] || opp.stage
  const probabilityMap: Record<string, number> = {
    inquiry: 10, qualification: 30, proposal: 50, negotiating: 70, quoted: 85
  }
  const probability = probabilityMap[newDbStage] ?? opp.probability
  opp.stage = newDbStage
  opp.probability = probability
  draggingId.value = null; dragOverCol.value = null
  try {
    await updateOppStage(id, newDbStage, probability)
    ElMessage.success(`已移至「${columns.find(c => c.value === newBoardStage)?.label}」`)
  } catch (e) {
    opp.stage = oldBoard === opp.stage ? opp.stage : oldBoard
    /* toast already shown */
  }
}

const formatMoney = (n: number) => Number(n || 0).toLocaleString('zh-CN', { maximumFractionDigits: 0 })
const formatDate = (d: string) => d ? d.slice(0, 10) : '-'
const probabilityColor = (p: number) => p >= 70 ? '#1D9E75' : p >= 40 ? '#0C447C' : '#BA7517'

onMounted(() => { loadList() })
</script>

<style lang="scss" scoped>
.page-container {
  padding: 16px; background: #f5f7fa;
  height: calc(100vh - 60px);
  display: flex; flex-direction: column; overflow: hidden;
}
.page-header {
  display: flex; justify-content: space-between; align-items: center;
  background: #fff; padding: 14px 20px; border-radius: 8px; margin-bottom: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04); flex-shrink: 0;
  .title-area { display: flex; align-items: center; gap: 10px; }
  .page-title { font-size: 18px; font-weight: 600; color: #0C447C; border-left: 4px solid #0C447C; padding-left: 10px; }
}
.board-wrapper { flex: 1; overflow-x: auto; overflow-y: hidden; background: #fff; border-radius: 8px; padding: 12px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04); }
.board-canvas { display: flex; gap: 10px; height: 100%; min-width: max-content; }
.board-column {
  width: 220px; flex-shrink: 0; display: flex; flex-direction: column;
  background: #f5f7fa; border-radius: 6px; transition: all 0.2s; border: 2px solid transparent;
  &.is-drop-target { border-color: #0C447C; background: rgba(12, 68, 124, 0.05); }
  .column-header {
    padding: 8px 12px; border-radius: 6px 6px 0 0;
    display: flex; justify-content: space-between; align-items: center;
    font-size: 13px; font-weight: 600;
    .col-count { background: rgba(255,255,255,0.6); padding: 1px 8px; border-radius: 10px; font-size: 11px; }
  }
  .column-body { flex: 1; overflow-y: auto; padding: 6px; display: flex; flex-direction: column; gap: 6px; }
}
.opp-card {
  background: #fff; border-radius: 4px; padding: 8px 10px; cursor: grab;
  border: 1px solid #ebeef5; transition: all 0.2s; user-select: none;
  &:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); transform: translateY(-1px); }
  &:active { cursor: grabbing; }
  .card-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
  .opp-no { font-size: 11px; color: #909399; font-family: 'Consolas', monospace; }
  .opp-amount { font-size: 12px; font-weight: 600; color: #BA7517; }
  .card-name { font-size: 13px; font-weight: 600; color: #303133; margin-bottom: 3px; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
  .card-customer { font-size: 11px; color: #606266; display: flex; align-items: center; gap: 3px; margin-bottom: 6px; }
  .card-progress { display: flex; align-items: center; gap: 6px; margin-bottom: 4px; :deep(.el-progress) { flex: 1; } .card-prob-text { font-size: 11px; color: #909399; min-width: 28px; text-align: right; } }
  .card-foot { display: flex; justify-content: space-between; font-size: 11px; color: #909399; }
  .card-sales, .card-date { display: flex; align-items: center; gap: 2px; }
}
</style>
