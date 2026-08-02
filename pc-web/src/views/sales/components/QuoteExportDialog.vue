<script setup lang="ts">
/**
 * QuoteExportDialog — 报价单导出对话框 (v0.3.14 B3)
 *
 * 支持 3 种格式：
 * - HTML：可打印的 HTML 片段（客户邮件场景）
 * - CSV：Excel 友好（内部归档/ERP 对账）
 * - 打印：触发 window.print()，走浏览器原生 PDF 导出
 */
import { ref, computed, watch } from 'vue'
import { Document, Download, Printer, ChatLineSquare } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { formatMoney, formatDate, formatDateTime, quoteStatusLabel, type QuoteItem } from '../quoteTypes'
import type { Quote } from '../types'

const props = defineProps<{
  visible: boolean
  quote?: Quote | null
  items?: QuoteItem[]
  /** 客户名/商机名（用于邮件标题） */
  customerName?: string
  oppName?: string
}>()

const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'printed'): void
}>()

const dialogVisible = computed({
  get: () => props.visible,
  set: (v) => emit('update:visible', v),
})

type Format = 'html' | 'csv' | 'print'
const format = ref<Format>('html')
const includeCompany = ref(true)
const includeNote = ref(true)
const noteText = ref('备注：本报价单有效期 7 天，逾期请联系我司业务代表。')

watch(() => props.visible, (v) => {
  if (v) format.value = 'html'
})

/** 计算 items 总数/小计 */
const summary = computed(() => {
  const items = props.items || []
  const subtotal = items.reduce((s, it) => s + (Number(it.quantity) || 0) * (Number(it.unit_price) || 0), 0)
  const discountRate = Number(props.quote?.discount_rate) || 0
  const taxRate = Number(props.quote?.tax_rate) || 0
  const discountAmount = subtotal * discountRate / 100
  const afterDiscount = subtotal - discountAmount
  const taxAmount = afterDiscount * taxRate / 100
  const total = afterDiscount + taxAmount
  return { subtotal, discountAmount, taxAmount, total, itemCount: items.length }
})

const formatFileName = (ext: string) => {
  const date = new Date().toISOString().slice(0, 10)
  return `报价单_${props.quote?.quote_no || 'draft'}_V${props.quote?.version || 1}_${date}.${ext}`
}

/**
 * XSS 防护 (P1-6 fix): 所有拼进 HTML 字符串的字段都强制转义
 *  - 业务数据 (product.name / customer.name / quote.quote_no / noteText) 可被外部用户编辑
 *  - 原 win.document.write(html) 写出的 popup 与父窗口同源, 恶意脚本能读 localStorage 里的 auth token
 *  - 改 PDF/print 库都需要新增依赖, 转义是当前最稳妥方案
 */
const esc = (s: unknown): string => {
  if (s === null || s === undefined) return ''
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
}

const buildHtml = (): string => {
  const items = props.items || []
  const rows = items.map((it, idx) => `
    <tr>
      <td style="text-align:center">${idx + 1}</td>
      <td>${esc(it.code || '—')}</td>
      <td>${esc(it.name || '—')}</td>
      <td>${esc(it.specification || it.spec || '—')}</td>
      <td style="text-align:center">${esc(it.unit || '—')}</td>
      <td style="text-align:right">${esc(it.quantity)}</td>
      <td style="text-align:right">¥ ${esc(formatMoney(it.unit_price))}</td>
      <td style="text-align:right">¥ ${esc(formatMoney(Number(it.quantity) * Number(it.unit_price)))}</td>
    </tr>
  `).join('')
  return `<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>${esc(formatFileName('html'))}</title>
<style>
  body { font-family: -apple-system, "Microsoft YaHei", sans-serif; padding: 32px; color: #303133; max-width: 900px; margin: 0 auto; }
  .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #BA7517; padding-bottom: 16px; margin-bottom: 24px; }
  .title { font-size: 28px; font-weight: 700; color: #BA7517; }
  .meta { font-size: 13px; color: #606266; text-align: right; line-height: 1.6; }
  .customer { padding: 12px 0; font-size: 14px; }
  table { width: 100%; border-collapse: collapse; margin: 16px 0; font-size: 13px; }
  th, td { border: 1px solid #dcdfe6; padding: 8px 12px; }
  th { background: #f5f7fa; font-weight: 600; }
  .totals { width: 320px; margin-left: auto; font-size: 13px; }
  .totals tr td { border: none; padding: 4px 8px; }
  .totals .total { font-weight: 700; font-size: 16px; color: #BA7517; border-top: 1px solid #909399; }
  .footer { margin-top: 32px; font-size: 12px; color: #909399; border-top: 1px dashed #dcdfe6; padding-top: 12px; }
  .note { padding: 8px 12px; background: #fdf6ec; border-left: 3px solid #E6A23C; font-size: 13px; color: #8B5A00; margin-top: 16px; }
  .signature { display: flex; gap: 80px; margin-top: 48px; }
  .signature div { font-size: 13px; color: #606266; }
  .signature .line { display: inline-block; min-width: 160px; border-bottom: 1px solid #909399; height: 16px; }
</style>
</head>
<body>
  <div class="header">
    <div>
      <div class="title">报价单</div>
      <div style="font-size:12px;color:#909399;margin-top:4px">Quotation</div>
    </div>
    <div class="meta">
      <div>单号：<b>${esc(props.quote?.quote_no || '—')}</b></div>
      <div>版本：<b>V${esc(props.quote?.version ?? 1)}</b></div>
      <div>状态：<b>${esc(quoteStatusLabel(props.quote?.status))}</b></div>
      <div>日期：${esc(formatDate(props.quote?.created_at) || formatDate(new Date().toISOString()))}</div>
      <div>有效期至：${esc(formatDate(props.quote?.valid_until) || '—')}</div>
    </div>
  </div>
  ${includeCompany.value ? `
  <div class="customer">
    <div><b>客户：</b>${esc(props.customerName || '—')}</div>
    <div><b>商机：</b>${esc(props.oppName || '—')}</div>
  </div>` : ''}
  <table>
    <thead>
      <tr>
        <th style="width:40px">#</th>
        <th style="width:100px">编号</th>
        <th>产品/服务</th>
        <th style="width:120px">规格</th>
        <th style="width:50px">单位</th>
        <th style="width:80px">数量</th>
        <th style="width:100px">单价</th>
        <th style="width:120px">小计</th>
      </tr>
    </thead>
    <tbody>${rows || '<tr><td colspan="8" style="text-align:center;color:#909399">暂无产品</td></tr>'}</tbody>
  </table>
  <table class="totals">
    <tr><td>小计</td><td style="text-align:right">¥ ${formatMoney(summary.value.subtotal)}</td></tr>
    <tr><td>折扣 (${props.quote?.discount_rate || 0}%)</td><td style="text-align:right;color:#F56C6C">- ¥ ${formatMoney(summary.value.discountAmount)}</td></tr>
    <tr><td>税额 (${props.quote?.tax_rate || 0}%)</td><td style="text-align:right">¥ ${formatMoney(summary.value.taxAmount)}</td></tr>
    <tr class="total"><td>含税总计</td><td style="text-align:right">¥ ${formatMoney(summary.value.total)}</td></tr>
  </table>
  ${includeNote.value ? `<div class="note">📝 ${esc(noteText.value)}</div>` : ''}
  <div class="signature">
    <div>客户签收：<span class="line"></span></div>
    <div>日期：<span class="line"></span></div>
  </div>
  <div class="footer">
    报价单生成时间：${formatDateTime(new Date().toISOString())}<br>
    本报价单经电子签发，与纸质件具有同等法律效力。
  </div>
</body>
</html>`
}

const buildCsv = (): string => {
  const items = props.items || []
  const headers = ['#', '编号', '产品名称', '规格', '单位', '数量', '单价', '小计', '税率(%)', '折扣(%)']
  const rows = items.map((it, idx) => [
    idx + 1,
    it.code || '',
    `"${(it.name || '').replace(/"/g, '""')}"`,
    it.specification || it.spec || '',
    it.unit || '',
    it.quantity,
    Number(it.unit_price).toFixed(2),
    (Number(it.quantity) * Number(it.unit_price)).toFixed(2),
    props.quote?.tax_rate || 0,
    props.quote?.discount_rate || 0,
  ].join(','))
  // BOM 防 Excel 中文乱码
  return '\ufeff' + [headers.join(','), ...rows].join('\r\n')
}

const downloadFile = (content: string, filename: string, mime: string) => {
  const blob = new Blob([content], { type: mime })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  setTimeout(() => URL.revokeObjectURL(url), 1000)
}

const handleExport = () => {
  if (!props.quote) {
    ElMessage.warning('暂无报价单可导出')
    return
  }
  if (format.value === 'html') {
    downloadFile(buildHtml(), formatFileName('html'), 'text/html;charset=utf-8')
    ElMessage.success('HTML 报价单已下载')
  } else if (format.value === 'csv') {
    downloadFile(buildCsv(), formatFileName('csv'), 'text/csv;charset=utf-8')
    ElMessage.success('CSV 已下载 (Excel 可直接打开)')
  } else if (format.value === 'print') {
    const html = buildHtml()
    const win = window.open('', '_blank', 'width=900,height=1200')
    if (!win) {
      ElMessage.error('浏览器拦截了弹窗，请允许后重试')
      return
    }
    win.location = 'data:text/html;charset=utf-8,' + encodeURIComponent(html)
    win.focus()
    setTimeout(() => {
      win.print()
      emit('printed')
    }, 400)
  }
}

const handleCopyEmailLink = async () => {
  if (!props.quote) return
  const subject = `报价单 ${props.quote.quote_no} - V${props.quote.version} - ${props.customerName || ''}`
  const body = `您好，\n\n附件为本次报价单：\n\n` +
    `单号：${props.quote.quote_no}\n` +
    `版本：V${props.quote.version}\n` +
    `金额：¥${formatMoney(summary.value.total)}\n` +
    `有效期至：${formatDate(props.quote.valid_until) || '—'}\n\n` +
    `如有疑问请随时联系我。\n\n祝商祺！`
  const mailto = `mailto:?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`
  window.location.href = mailto
}
</script>

<template>
  <el-dialog
    v-model="dialogVisible"
    title="导出报价单"
    width="520px"
    :close-on-click-modal="false"
  >
    <el-form label-width="100px" size="default">
      <el-form-item label="导出格式">
        <el-radio-group v-model="format">
          <el-radio-button value="html">
            <el-icon><Document /></el-icon>
            HTML (可打印)
          </el-radio-button>
          <el-radio-button value="csv">
            <el-icon><Download /></el-icon>
            CSV (Excel)
          </el-radio-button>
          <el-radio-button value="print">
            <el-icon><Printer /></el-icon>
            浏览器打印 (PDF)
          </el-radio-button>
        </el-radio-group>
      </el-form-item>
      <el-form-item label="包含信息">
        <el-checkbox v-model="includeCompany">客户和商机信息</el-checkbox>
        <el-checkbox v-model="includeNote">备注条款</el-checkbox>
      </el-form-item>
      <el-form-item v-if="includeNote" label="备注内容">
        <el-input v-model="noteText" type="textarea" :rows="2" />
      </el-form-item>
      <el-form-item label="预览信息">
        <div class="export-preview">
          <div>共 <b>{{ summary.itemCount }}</b> 个产品</div>
          <div>含税总计：<b style="color:#BA7517">¥ {{ formatMoney(summary.total) }}</b></div>
          <div>状态：<b>{{ quoteStatusLabel(quote?.status) }}</b></div>
        </div>
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="dialogVisible = false">取消</el-button>
      <el-button :icon="ChatLineSquare" @click="handleCopyEmailLink">生成邮件链接</el-button>
      <el-button type="primary" :icon="Download" @click="handleExport">导出</el-button>
    </template>
  </el-dialog>
</template>

<style scoped>
.export-preview {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 13px;
  color: #606266;
  padding: 8px 12px;
  background: #f5f7fa;
  border-radius: 4px;
}
.export-preview b {
  color: #303133;
}
</style>
