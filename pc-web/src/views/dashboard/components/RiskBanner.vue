<script setup lang="ts">
/**
 * RiskBanner — 跨项目风险预警横幅 (v0.3.14 C1)
 * Props: summary { total, danger, warning, projects, preview[] }
 * Event: jump(risk) — 跳转到项目详情
 */
import { WarningFilled, CircleClose } from '@element-plus/icons-vue'

defineProps<{
  summary: {
    total: number
    danger: number
    warning: number
    projects: Set<number>
    preview: Array<{ projectId: number; projectName: string; title: string; level: string; id: string }>
  }
}>()

const emit = defineEmits<{
  (e: 'jump', payload: { projectId: number; riskId: string }): void
  (e: 'viewAll'): void
}>()

const handleJump = (projectId: number, riskId: string) => emit('jump', { projectId, riskId })
</script>

<template>
  <div v-if="summary.total > 0" class="risk-banner" :class="summary.danger > 0 ? 'danger' : 'warning'">
    <div class="rb-icon">
      <el-icon :size="22" :color="summary.danger > 0 ? '#A32D2D' : '#BA7517'">
        <WarningFilled />
      </el-icon>
    </div>
    <div class="rb-content">
      <div class="rb-title">项目风险预警中心</div>
      <div class="rb-stats">
        <span v-if="summary.danger > 0" class="rb-stat danger">
          <strong>{{ summary.danger }}</strong> 个高危风险
        </span>
        <span v-if="summary.warning > 0" class="rb-stat warning">
          <strong>{{ summary.warning }}</strong> 个一般预警
        </span>
        <span class="rb-stat-meta">涉及 {{ summary.projects.size }} 个项目</span>
      </div>
      <div class="rb-list">
        <div
          v-for="r in summary.preview"
          :key="r.projectId + '-' + r.idx"
          class="rb-item"
          :class="`rb-${r.level}`"
          @click="handleJump(r.projectId, r.id)"
        >
          <el-icon :size="13" :color="r.level === 'danger' ? '#A32D2D' : '#BA7517'">
            <component :is="r.level === 'danger' ? CircleClose : WarningFilled" />
          </el-icon>
          <span class="rb-item-title">{{ r.title }}</span>
          <span class="rb-item-project">@{{ r.projectName }}</span>
        </div>
        <div v-if="summary.total > summary.preview.length" class="rb-more" @click="emit('viewAll')">
          还有 {{ summary.total - summary.preview.length }} 项 →
        </div>
      </div>
    </div>
    <el-button type="primary" plain @click="emit('viewAll')">查看全部项目</el-button>
  </div>
</template>

<style scoped>
.risk-banner {
  display: flex;
  gap: 16px;
  align-items: center;
  padding: 16px 20px;
  border-radius: 8px;
  background: linear-gradient(135deg, #fff5e6 0%, #fff 60%);
  border-left: 4px solid #BA7517;
  box-shadow: 0 2px 8px rgba(186, 117, 23, 0.08);

  &.danger {
    background: linear-gradient(135deg, #fdecec 0%, #fff 60%);
    border-left-color: #A32D2D;
    box-shadow: 0 2px 8px rgba(163, 45, 45, 0.12);
  }
  .rb-icon {
    width: 44px; height: 44px; border-radius: 50%;
    background: rgba(186, 117, 23, 0.1);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
  }
  &.danger .rb-icon { background: rgba(163, 45, 45, 0.1); }
  .rb-content { flex: 1; min-width: 0; }
  .rb-title { font-size: 15px; font-weight: 700; color: #303133; margin-bottom: 4px; }
  .rb-stats { display: flex; gap: 16px; font-size: 12px; color: #606266; margin-bottom: 8px; }
  .rb-stat strong { font-size: 14px; margin-right: 4px; }
  .rb-stat.danger strong { color: #A32D2D; }
  .rb-stat.warning strong { color: #BA7517; }
  .rb-stat-meta { color: #909399; }
  .rb-list { display: flex; gap: 8px; flex-wrap: wrap; }
  .rb-item {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 10px; border-radius: 14px; font-size: 12px;
    background: #fff; border: 1px solid #f0d8a8; cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
  }
  .rb-item:hover { transform: translateY(-1px); box-shadow: 0 2px 6px rgba(0,0,0,0.08); }
  .rb-item.rb-danger { border-color: #f0b8b8; }
  .rb-item-title { color: #303133; max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .rb-item-project { color: #909399; font-size: 11px; }
  .rb-more {
    color: #185FA5; cursor: pointer; font-size: 12px; padding: 4px 10px;
    border-radius: 14px; background: #E6F1FB;
  }
  .rb-more:hover { background: #D1E5F7; }
}
</style>
