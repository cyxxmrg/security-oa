<template>
  <div class="portal-repair">
    <div class="portal-bg" />

    <div class="portal-card">
      <div class="portal-header">
        <div class="portal-logo">🛠️</div>
        <h1 class="portal-title">返修进度查询</h1>
        <p class="portal-sub">输入返修单号 + 联系电话后 4 位, 自助查询进度</p>
      </div>

      <el-form v-if="!result" :model="form" label-position="top" @submit.prevent="onQuery">
        <el-form-item label="返修单号">
          <el-input
            v-model="form.code"
            placeholder="例如: RN2026-001"
            size="large"
            clearable
          />
        </el-form-item>
        <el-form-item label="联系电话后 4 位">
          <el-input
            v-model="form.phone_suffix"
            placeholder="例如: 5678"
            size="large"
            maxlength="4"
            clearable
          />
        </el-form-item>
        <el-button
          type="primary"
          size="large"
          :loading="loading"
          @click="onQuery"
          style="width: 100%"
        >
          查询进度
        </el-button>
      </el-form>

      <div v-else class="result-panel">
        <div class="result-status" :class="`status-${result.status}`">
          <span class="status-dot" />
          <span class="status-label">{{ result.status_label }}</span>
        </div>

        <div class="result-eq">
          <strong>{{ result.equipment_brand }} {{ result.equipment_model }}</strong>
        </div>

        <el-descriptions :column="1" border>
          <el-descriptions-item label="工单号">
            <code>{{ result.code }}</code>
          </el-descriptions-item>
          <el-descriptions-item label="故障描述">
            {{ result.fault_description }}
          </el-descriptions-item>
          <el-descriptions-item label="接件时间">
            {{ formatDate(result.received_at) }}
          </el-descriptions-item>
          <el-descriptions-item v-if="result.expected_finish_at" label="预计完成">
            {{ formatDate(result.expected_finish_at) }}
          </el-descriptions-item>
          <el-descriptions-item v-if="result.method_label" label="维修方式">
            <span :class="result.is_paid ? 'paid' : 'free'">{{ result.method_label }}</span>
          </el-descriptions-item>
        </el-descriptions>

        <!-- 物流轨迹 -->
        <h3 class="section-h">📦 物流轨迹</h3>
        <div v-if="!result.shipments?.length" class="empty-mini">暂无物流信息</div>
        <div v-else class="ship-list">
          <div v-for="(s, i) in result.shipments" :key="i" class="ship-card">
            <div class="ship-head">
              <el-tag :type="s.direction === 'outbound' ? 'warning' : 'success'" size="small">
                {{ s.direction_label }}
              </el-tag>
              <span class="ship-carrier">{{ s.carrier }}</span>
              <code class="ship-no">{{ s.tracking_no }}</code>
            </div>
            <div class="ship-body">
              <div class="ship-line">发出: <strong>{{ formatDate(s.shipped_at) }}</strong></div>
              <div v-if="s.actual_arrival" class="ship-line">
                到达: <strong>{{ formatDate(s.actual_arrival) }}</strong>
              </div>
              <div v-else-if="s.estimated_arrival" class="ship-line text-warn">
                预计到达: {{ formatDate(s.estimated_arrival) }}
              </div>
            </div>
          </div>
        </div>

        <!-- 维修进度 -->
        <h3 class="section-h">⚙️ 维修进度 ({{ result.progress_count }})</h3>
        <div v-if="!result.progress?.length" class="empty-mini">暂无进度记录</div>
        <el-timeline v-else>
          <el-timeline-item
            v-for="(p, i) in result.progress"
            :key="i"
            :timestamp="formatDate(p.action_at)"
            placement="top"
          >
            <div class="progress-status">
              <el-tag size="small">{{ p.status_label }}</el-tag>
            </div>
            <div v-if="p.description" class="progress-desc">{{ p.description }}</div>
          </el-timeline-item>
        </el-timeline>

        <el-button class="reset-btn" @click="onReset" plain>重新查询</el-button>
      </div>
    </div>

    <div class="portal-foot">© 2026 安防运维OA · 客户查询入口</div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { ElMessage } from 'element-plus'
import { get } from '@/utils/request'

const form = reactive({ code: '', phone_suffix: '' })
const loading = ref(false)
const result = ref<Record<string, unknown> | null>(null)

const onQuery = async () => {
  if (!form.code) return ElMessage.warning('请输入返修单号')
  if (!form.phone_suffix || form.phone_suffix.length !== 4) return ElMessage.warning('请输入电话后 4 位')
  loading.value = true
  try {
    // 公开端点 — 用 fetch 直连不走 token
    const res = await fetch(`/api/portal/repair?code=${encodeURIComponent(form.code)}&phone_suffix=${form.phone_suffix}`)
    const data = await res.json()
    if (data.code === 0) {
      result.value = data.data
    } else {
      ElMessage.error(data.message || '查询失败')
    }
  } catch (e: unknown) {
    ElMessage.error('网络错误: ' + (e?.message || ''))
  } finally { loading.value = false }
}

const onReset = () => {
  result.value = null
  form.code = ''
  form.phone_suffix = ''
}

const formatDate = (s?: string) => {
  if (!s) return '-'
  const d = new Date(s)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
}
</script>

<style scoped lang="scss">
.portal-repair {
  min-height: 100vh;
  display: flex; flex-direction: column; align-items: center;
  padding: 20px; position: relative;
  background: linear-gradient(135deg, #409EFF 0%, #67C23A 100%);
}
.portal-bg {
  position: absolute; inset: 0; opacity: 0.1;
  background: radial-gradient(circle at 30% 20%, white 0%, transparent 50%),
              radial-gradient(circle at 70% 80%, white 0%, transparent 50%);
}
.portal-card {
  position: relative; z-index: 1;
  width: 100%; max-width: 520px;
  background: #fff; border-radius: 12px; padding: 32px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
  margin-top: 40px;
}
.portal-header { text-align: center; margin-bottom: 24px; }
.portal-logo { font-size: 48px; margin-bottom: 8px; }
.portal-title { font-size: 22px; font-weight: 600; color: #303133; margin: 0; }
.portal-sub { font-size: 13px; color: #909399; margin: 8px 0 0; }

.result-panel { animation: fadeIn 0.3s ease; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }

.result-status {
  display: flex; align-items: center; gap: 8px;
  padding: 12px 16px; border-radius: 8px;
  background: #ECF5FF; color: #409EFF;
  font-size: 16px; font-weight: 600; margin-bottom: 16px;
}
.status-dot {
  width: 10px; height: 10px; border-radius: 50%; background: currentColor;
  animation: pulse 2s infinite;
}
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
.status-received, .status-sent_for_repair { background: #F4F4F5; color: #909399; }
.status-in_repair { background: #FDF6EC; color: #E6A23C; }
.status-repaired, .status-closed { background: #F0F9EB; color: #67C23A; }
.status-sent_back { background: #FEF0F0; color: #F56C6C; }
.status-cancelled { background: #F4F4F5; color: #909399; }
.status-label { font-size: 16px; }

.result-eq { font-size: 15px; color: #303133; margin-bottom: 12px; }

.section-h { font-size: 14px; font-weight: 600; color: #303133; margin: 20px 0 12px; }

.empty-mini { padding: 16px; text-align: center; color: #C0C4CC; font-size: 12px; background: #FAFAFA; border-radius: 6px; }

.ship-list { display: flex; flex-direction: column; gap: 8px; }
.ship-card { background: #FAFAFA; padding: 12px; border-radius: 6px; border-left: 3px solid #409EFF; }
.ship-head { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
.ship-carrier { font-size: 13px; font-weight: 500; color: #303133; }
.ship-no { font-size: 12px; color: #606266; font-family: monospace; }
.ship-body { display: flex; flex-direction: column; gap: 2px; font-size: 12px; color: #606266; }
.text-warn { color: #E6A23C; }

.progress-status { margin-bottom: 4px; }
.progress-desc { font-size: 13px; color: #606266; line-height: 1.5; }
.paid { color: #F56C6C; font-weight: 600; }
.free { color: #67C23A; font-weight: 600; }

.reset-btn { width: 100%; margin-top: 16px; }

.portal-foot { color: rgba(255, 255, 255, 0.85); font-size: 12px; margin-top: 20px; }

@media (max-width: 600px) {
  .portal-card { padding: 20px; }
  .portal-logo { font-size: 36px; }
  .portal-title { font-size: 18px; }
}
</style>
