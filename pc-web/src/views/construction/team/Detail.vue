<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left">
        <el-button :icon="ArrowLeft" plain @click="goBack">返回</el-button>
        <span class="page-title">团队详情</span>
      </div>
      <div class="header-actions">
        <el-button v-if="team" :icon="Edit" type="warning" @click="goEdit">编辑团队</el-button>
        <el-button v-if="team" :icon="Plus" type="primary" @click="showMemberDialog = true">添加成员</el-button>
      </div>
    </div>

    <div v-loading="loading">
      <template v-if="team">
        <!-- 基础信息 -->
        <el-card shadow="never" class="info-card">
          <template #header>
            <div class="card-header">
              <span class="card-title">基础信息</span>
              <el-tag :type="team.status === 'active' ? 'success' : 'danger'" effect="plain" size="small">
                {{ team.status === 'active' ? '启用' : '停用' }}
              </el-tag>
            </div>
          </template>
          <el-descriptions :column="3" border size="default">
            <el-descriptions-item label="团队ID">{{ team.id }}</el-descriptions-item>
            <el-descriptions-item label="团队名称">{{ team.name || '-' }}</el-descriptions-item>
            <el-descriptions-item label="类型">
              <el-tag effect="plain" size="small">{{ typeLabel(team.type) }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="负责人">{{ team.leader?.name || team.leader_name || '-' }}</el-descriptions-item>
            <el-descriptions-item label="联系电话">{{ team.phone || '-' }}</el-descriptions-item>
            <el-descriptions-item label="成员数">{{ members.length }} 人</el-descriptions-item>
            <el-descriptions-item label="主要工种" :span="3">
              <el-tag v-for="s in (team.specialty || [])" :key="s" size="small" effect="plain" style="margin-right: 4px">
                {{ s }}
              </el-tag>
              <span v-if="!team.specialty || team.specialty.length === 0" class="muted">-</span>
            </el-descriptions-item>
            <el-descriptions-item v-if="team.remark" label="备注" :span="3">{{ team.remark }}</el-descriptions-item>
            <el-descriptions-item label="创建时间">{{ team.created_at || '-' }}</el-descriptions-item>
            <el-descriptions-item label="更新时间" :span="2">{{ team.updated_at || '-' }}</el-descriptions-item>
          </el-descriptions>
        </el-card>

        <!-- 标签页 -->
        <el-card shadow="never" class="tab-card">
          <el-tabs v-model="activeTab">
            <!-- 成员 -->
            <el-tab-pane label="成员列表" name="members">
              <el-table :data="members" v-loading="membersLoading" border size="small" stripe>
                <el-table-column label="姓名" width="120" align="center">
                  <template #default="{ row }">
                    {{ row.user?.name || row.user_name || '-' }}
                  </template>
                </el-table-column>
                <el-table-column label="岗位" width="100" align="center">
                  <template #default="{ row }">
                    <el-tag size="small" effect="plain">{{ roleLabel(row.role) }}</el-tag>
                  </template>
                </el-table-column>
                <el-table-column prop="specialty" label="工种" width="120" align="center" />
                <el-table-column label="日工资" width="120" align="right">
                  <template #default="{ row }">¥ {{ formatMoney(row.daily_wage) }}</template>
                </el-table-column>
                <el-table-column prop="join_date" label="入职日期" width="120" align="center" />
                <el-table-column label="状态" width="90" align="center">
                  <template #default="{ row }">
                    <el-tag :type="row.status === 'active' ? 'success' : 'info'" effect="plain" size="small">
                      {{ row.status === 'active' ? '在职' : (row.status || '在职') }}
                    </el-tag>
                  </template>
                </el-table-column>
                <el-table-column label="操作" width="100" align="center" fixed="right">
                  <template #default="{ row }">
                    <el-button link type="danger" :icon="Delete" @click="handleRemoveMember(row)">移除</el-button>
                  </template>
                </el-table-column>
              </el-table>
            </el-tab-pane>

            <!-- 关联项目 -->
            <el-tab-pane label="关联项目" name="projects">
              <el-table :data="projects" v-loading="projectsLoading" border size="small" stripe>
                <el-table-column prop="code" label="项目编号" width="160" show-overflow-tooltip />
                <el-table-column prop="name" label="项目名称" min-width="200" show-overflow-tooltip />
                <el-table-column prop="stage" label="阶段" width="100" align="center" />
                <el-table-column label="状态" width="100" align="center">
                  <template #default="{ row }">
                    <el-tag size="small" effect="plain">{{ row.status || '-' }}</el-tag>
                  </template>
                </el-table-column>
              </el-table>
              <el-empty v-if="!projectsLoading && projects.length === 0" description="暂无关联项目" :image-size="80" />
            </el-tab-pane>

            <!-- 关联日志 -->
            <el-tab-pane label="关联日志" name="logs">
              <el-table :data="logs" v-loading="logsLoading" border size="small" stripe>
                <el-table-column prop="date" label="日期" width="120" align="center" />
                <el-table-column prop="weather" label="天气" width="80" align="center" />
                <el-table-column prop="progress" label="进度" width="100" align="right">
                  <template #default="{ row }">{{ row.progress ?? 0 }}%</template>
                </el-table-column>
                <el-table-column label="状态" width="100" align="center">
                  <template #default="{ row }">
                    <el-tag size="small" effect="plain">{{ row.status || '-' }}</el-tag>
                  </template>
                </el-table-column>
                <el-table-column prop="remark" label="备注" min-width="200" show-overflow-tooltip />
              </el-table>
              <el-empty v-if="!logsLoading && logs.length === 0" description="暂无关联日志" :image-size="80" />
            </el-tab-pane>
          </el-tabs>
        </el-card>
      </template>
      <el-empty v-else-if="!loading" description="未找到团队数据" />
    </div>

    <!-- 添加成员 dialog -->
    <MemberFormDialog
      v-model:visible="showMemberDialog"
      :team-name="team?.name || ''"
      @save="handleAddMember"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { ArrowLeft, Edit, Delete, Plus } from '@element-plus/icons-vue'
import { teamApi } from '@/api/construction'
import { unwrapItem } from '@/utils/response'
import MemberFormDialog from './components/MemberFormDialog.vue'
import type { Team } from '../types'

const route = useRoute()
const router = useRouter()

const loading = ref(false)
const team = ref<Team | null>(null)
const members = ref<TeamMember[]>([])
const projects = ref<TeamProject[]>([])
const logs = ref<LogRow[]>([])

const membersLoading = ref(false)
const projectsLoading = ref(false)
const logsLoading = ref(false)

const activeTab = ref('members')
const showMemberDialog = ref(false)

const typeLabel = (k: string) => ({
  internal: '自有团队', external: '外包团队', mixed: '混合团队',
} as Record<string, string>)[k] || k || '-'

const roleLabel = (k: string) => ({
  leader: '队长', foreman: '工长', worker: '工人', apprentice: '学徒', driver: '司机',
} as Record<string, string>)[k] || k || '-'

const formatMoney = (n: number | string | null | undefined) => Number(n || 0).toLocaleString('zh-CN', { maximumFractionDigits: 2 })

const teamId = computed(() => Number(route.params.id))

const loadDetail = async () => {
  if (!teamId.value) return
  loading.value = true
  try {
    const res = await teamApi.show(teamId.value)
    const d = unwrapItem(res) || null
    team.value = d
    // 后端可能在 detail 中直接返回 members / projects / logs，也可能不返回
    members.value = Array.isArray(d?.members) ? d.members : []
    projects.value = Array.isArray(d?.projects) ? d.projects : []
    logs.value = Array.isArray(d?.logs) ? d.logs : []
  } catch {
    team.value = null
  } finally {
    loading.value = false
  }
}

const handleAddMember = async (payload: Record<string, unknown>) => {
  try {
    await teamApi.addMembers(teamId.value, [payload])
    ElMessage.success('已添加成员')
    showMemberDialog.value = false
    await loadDetail()
  } catch { /* 拦截器已提示 */ }
}

const handleRemoveMember = async (row: TeamMember) => {
  try {
    await ElMessageBox.confirm(
      `确认将「${row.user?.name || row.user_name}」从团队移除？`,
      '移除确认',
      { type: 'warning', confirmButtonText: '确认移除', cancelButtonText: '取消' }
    )
  } catch { return }
  try {
    await teamApi.removeMember(teamId.value, row.id || row.user_id)
    ElMessage.success('已移除')
    await loadDetail()
  } catch { /* 拦截器已提示 */ }
}

const goBack = () => router.push('/construction/team')
const goEdit = () => router.push({ path: '/construction/team', query: { edit: String(teamId.value) } })

watch(teamId, () => { if (teamId.value) loadDetail() })
onMounted(() => { if (teamId.value) loadDetail() })
</script>

<style lang="scss" scoped>
.page-container { padding: 16px; background: #f5f7fa; min-height: calc(100vh - 60px); }
.page-header {
  display: flex; justify-content: space-between; align-items: center;
  background: #fff; padding: 16px 20px; border-radius: 8px; margin-bottom: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  .header-left { display: flex; align-items: center; gap: 12px; }
  .page-title {
    font-size: 18px; font-weight: 600; color: #0C447C;
    border-left: 4px solid #0C447C; padding-left: 10px;
  }
  .header-actions { display: flex; gap: 8px; }
}
.info-card { margin-bottom: 12px; }
.tab-card { box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04) !important; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.card-title { font-weight: 600; color: #303133; }
.muted { color: #c0c4cc; }
</style>
