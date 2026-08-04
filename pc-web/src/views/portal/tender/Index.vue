<template>
  <div class="portal-tender">
    <div class="portal-bg" />

    <div class="portal-card">
      <div class="portal-header">
        <div class="portal-logo">📋</div>
        <h1 class="portal-title">招标中心 · 供应商门户</h1>
        <p class="portal-sub">输入预留手机号, 查看您收到的招标邀请</p>
      </div>

      <el-form v-if="!result" :model="form" label-position="top" @submit.prevent="onQuery">
        <el-form-item label="预留手机号">
          <el-input v-model="form.phone" placeholder="11位手机号" size="large" clearable maxlength="11" />
        </el-form-item>
        <el-form-item label="供应商编号">
          <el-input v-model="form.supplierCode" placeholder="请输入邀请中提供的供应商编号" size="large" clearable maxlength="64" />
        </el-form-item>
        <el-button type="primary" size="large" :loading="loading" @click="onQuery" style="width: 100%">
          查看我的邀请
        </el-button>
      </el-form>

      <div v-else class="result-panel">
        <div class="result-supplier" v-if="result.supplier">
          <div class="supplier-avatar">{{ result.supplier.name?.[0] || '?' }}</div>
          <div>
            <div class="supplier-name">{{ result.supplier.name }}</div>
            <div class="supplier-phone">{{ result.supplier.phone }}</div>
          </div>
          <el-button link @click="onReset">重新查询</el-button>
        </div>

        <div v-if="!result.supplier" class="no-supplier">
          <el-result icon="warning" title="未找到该手机号对应的供应商" sub-title="请联系招标方添加您的供应商档案">
            <template #extra>
              <el-button @click="onReset">重新输入</el-button>
            </template>
          </el-result>
        </div>

        <div v-else>
          <h4 class="section-title">我的招标邀请 ({{ result.invitations.length }})</h4>
          <div v-if="result.invitations.length === 0" class="empty">暂无邀请</div>
          <div v-else class="invitation-list">
            <div v-for="inv in result.invitations" :key="inv.id" class="invitation-item" @click="goBid(inv)">
              <div class="inv-main">
                <div class="inv-code">{{ inv.code }}</div>
                <div class="inv-name">{{ inv.name }}</div>
                <div class="inv-meta">
                  <el-tag size="small" :type="statusType(inv.status)">{{ inv.status }}</el-tag>
                  <span class="inv-deadline">截标: {{ fmt(inv.deadline) }}</span>
                </div>
              </div>
              <el-button type="primary" plain>去投标 →</el-button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { portalApi } from '@/api/portal-tender'

const router = useRouter()
const loading = ref(false)
const result = ref<Awaited<ReturnType<typeof portalApi.listInvitations>> | null>(null)
const form = reactive({ phone: '', supplierCode: '' })

const fmt = (s?: string) => s ? s.replace('T', ' ').slice(0, 16) : '-'
const statusType = (s: string) => ({
  bidding: 'warning', published: 'warning', evaluating: 'primary', awarded: 'success', closed: 'info', cancelled: 'danger',
}[s] || 'info') as Record<string, unknown>

const onQuery = async () => {
  if (!/^1\d{10}$/.test(form.phone)) {
    return ElMessage.warning('请输入 11 位有效手机号')
  }
  if (!form.supplierCode.trim()) return ElMessage.warning('请输入供应商编号')
  loading.value = true
  try {
    const access = await portalApi.access(form.phone, form.supplierCode.trim())
    sessionStorage.setItem('portal_access_token', access.access_token)
    sessionStorage.setItem('portal_supplier_id', String(access.supplier_id))
    result.value = await portalApi.listInvitations(access.access_token)
    sessionStorage.setItem('portal_phone_suffix', form.phone.slice(-4))
  } catch (e: unknown) {
    ElMessage.error(e?.message || '查询失败')
  } finally { loading.value = false }
}

const onReset = () => {
  result.value = null
  form.phone = ''
  form.supplierCode = ''
  sessionStorage.removeItem('portal_access_token')
  sessionStorage.removeItem('portal_supplier_id')
}

const goBid = (inv: Record<string, unknown>) => {
  router.push(`/portal/tender/${inv.public_token}?supplier=${result.value?.supplier?.id}`)
}
</script>

<style scoped lang="scss">
.portal-tender { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; position: relative; overflow: hidden; }
.portal-bg { position: absolute; inset: 0; background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%); opacity: 0.95; z-index: 0; }
.portal-card { position: relative; z-index: 1; background: #fff; border-radius: 18px; box-shadow: 0 20px 60px rgba(0,0,0,0.18); width: 100%; max-width: 560px; padding: 32px 36px; }
.portal-header { text-align: center; margin-bottom: 24px; }
.portal-logo { font-size: 48px; margin-bottom: 8px; }
.portal-title { font-size: 22px; font-weight: 700; margin: 0; color: #1f2937; }
.portal-sub { color: #6b7280; margin-top: 6px; }
.result-supplier { display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: #f9fafb; border-radius: 10px; margin-bottom: 16px; }
.supplier-avatar { width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); color: #fff; font-size: 18px; font-weight: 600; display: flex; align-items: center; justify-content: center; }
.supplier-name { font-size: 16px; font-weight: 600; color: #111; }
.supplier-phone { color: #6b7280; font-size: 12px; }
.section-title { margin: 16px 0 12px; font-size: 15px; font-weight: 600; }
.empty { padding: 30px; text-align: center; color: #999; background: #f9fafb; border-radius: 8px; }
.invitation-list { display: flex; flex-direction: column; gap: 10px; }
.invitation-item { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; cursor: pointer; transition: all 0.2s; }
.invitation-item:hover { border-color: #667eea; box-shadow: 0 4px 12px rgba(102,126,234,0.15); transform: translateY(-1px); }
.inv-code { font-size: 12px; color: #6b7280; font-family: monospace; }
.inv-name { font-size: 15px; font-weight: 600; margin: 4px 0; color: #111; }
.inv-meta { display: flex; align-items: center; gap: 10px; }
.inv-deadline { color: #6b7280; font-size: 12px; }
</style>
