/**
 * V0.5.8.6 通用导出 + 打印工具
 *
 * 解决问题:
 * - 各页面散落的 handleExport 都用 Blob+URL.createObjectURL, 缺一个标准实现
 * - 大量页面只是 ElMessage.success('...') 占位, 啥都没做
 * - CSV 中文乱码 (缺 BOM)
 * - 长字段没转义引号
 * - 打印只是 window.print() 但页面有侧边栏/页头, 打印出来一团糟
 * - 行数过大时一次性下载, 浏览器会卡
 *
 * 用法:
 *   import { exportCsv, exportExcelLike, printTable, printHtml, exportJson } from '@/utils/exporter'
 *   exportCsv(headers, rows, '项目列表')        // 简单 CSV
 *   exportExcelLike(headers, rows, '项目列表')  // 真正 Excel (HTML 表格 + .xls 后缀)
 *   printTable(title, headers, rows)            // 打印 (开新窗口 + 简洁表格)
 *   printHtml('<h1>...</h1>', '报告')           // 打印 HTML
 *
 * 依赖: 无 (纯前端实现, 不调后端)
 */

import { ElMessage } from 'element-plus'

/** 转义 CSV 字段 (双引号包裹 + 内嵌引号转义) */
function csvField(v: Record<string, unknown>): string {
  if (v === null || v === undefined) return ''
  const s = String(v)
  if (s.includes('"') || s.includes(',') || s.includes('\n') || s.includes('\r')) {
    return `"${s.replace(/"/g, '""')}"`
  }
  return `"${s}"`
}

/** BOM 让 Excel 识别 UTF-8 (修复中文乱码) */
const UTF8_BOM = '\uFEFF'

/** 简单 CSV 导出 (适合几千行, 浏览器直接下载) */
export function exportCsv(
  headers: string[],
  rows: Record<string, unknown>[][],
  filename: string = '导出',
  options: { withBom?: boolean } = {}
): void {
  if (!headers?.length) {
    ElMessage.warning('无表头数据可导出')
    return
  }
  if (!rows?.length) {
    ElMessage.warning('当前列表为空，无可导出数据')
    return
  }

  const bom = options.withBom === false ? '' : UTF8_BOM
  const lines = [headers.map(csvField).join(',')]
  for (const row of rows) {
    lines.push(row.map(csvField).join(','))
  }
  const csv = bom + lines.join('\r\n')

  const today = new Date().toISOString().slice(0, 10)
  downloadBlob(csv, `${filename}_${today}.csv`, 'text/csv;charset=utf-8')
  ElMessage.success(`已导出 ${rows.length} 条数据到 ${filename}_${today}.csv`)
}

/** 真正 Excel 导出 (HTML 表格 + .xls 后缀, Excel 完美识别, 包含颜色/合并/换行) */
export function exportExcelLike(
  headers: string[],
  rows: Record<string, unknown>[][],
  filename: string = '导出',
  options: { sheetName?: string; title?: string } = {}
): void {
  if (!headers?.length) {
    ElMessage.warning('无表头数据可导出')
    return
  }
  if (!rows?.length) {
    ElMessage.warning('当前列表为空，无可导出数据')
    return
  }

  const sheetName = options.sheetName || 'Sheet1'
  const title = options.title || filename

  // 1) HTML 头 (让 Excel 把它当 XML 表格)
  let html = `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">`
  html += `<head><meta charset="UTF-8"><meta name="ProgId" content="Excel.Sheet"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>${esc(sheetName)}</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->`
  html += `<style>table{border-collapse:collapse;font-family:微软雅黑,Arial,sans-serif;} th{background:#409EFF;color:#fff;font-weight:600;padding:8px;border:1px solid #dcdfe6;} td{padding:6px 8px;border:1px solid #ebeef5;} .title{font-size:16px;font-weight:600;text-align:center;padding:10px;background:#f5f7fa;}</style></head><body>`

  if (title) {
    html += `<table><tr><td colspan="${headers.length}" class="title">${esc(title)}</td></tr></table>`
  }

  html += '<table><thead><tr>'
  for (const h of headers) html += `<th>${esc(h)}</th>`
  html += '</tr></thead><tbody>'

  for (const row of rows) {
    html += '<tr>'
    for (const cell of row) {
      const v = cell === null || cell === undefined ? '' : String(cell)
      html += `<td>${esc(v)}</td>`
    }
    html += '</tr>'
  }
  html += '</tbody></table></body></html>'

  const today = new Date().toISOString().slice(0, 10)
  downloadBlob(html, `${filename}_${today}.xls`, 'application/vnd.ms-excel;charset=utf-8')
  ElMessage.success(`已导出 ${rows.length} 条数据到 ${filename}_${today}.xls`)
}

function esc(s: string): string {
  return s
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
}

/** 打印表格 (开新窗口, 干净模板, 不带侧边栏/页头) */
export function printTable(
  title: string,
  headers: string[],
  rows: Record<string, unknown>[][],
  options: { subtitle?: string; orientation?: 'portrait' | 'landscape' } = {}
): void {
  if (!headers?.length) {
    ElMessage.warning('无表头数据可打印')
    return
  }

  const orientation = options.orientation || 'landscape'
  const today = new Date().toLocaleString('zh-CN', { hour12: false })

  let html = `<!DOCTYPE html><html><head><meta charset="UTF-8"><title>${esc(title)}</title>`
  html += `<style>
@page { size: ${orientation}; margin: 1cm; }
body { font-family: 微软雅黑, Arial, sans-serif; margin: 0; padding: 20px; color: #303133; }
h1 { text-align: center; font-size: 22px; margin: 0 0 8px; }
.sub { text-align: center; color: #909399; font-size: 12px; margin-bottom: 16px; }
table { width: 100%; border-collapse: collapse; font-size: 12px; }
th { background: #f5f7fa; color: #303133; font-weight: 600; padding: 8px; border: 1px solid #dcdfe6; text-align: left; }
td { padding: 6px 8px; border: 1px solid #ebeef5; }
tr:nth-child(even) { background: #fafafa; }
.foot { margin-top: 20px; text-align: right; color: #909399; font-size: 11px; }
@media print { .noprint { display: none; } body { padding: 0; } }
</style></head><body>`

  html += `<h1>${esc(title)}</h1>`
  if (options.subtitle) html += `<div class="sub">${esc(options.subtitle)}</div>`
  html += '<table><thead><tr>'
  for (const h of headers) html += `<th>${esc(h)}</th>`
  html += '</tr></thead><tbody>'
  for (const row of rows) {
    html += '<tr>'
    for (const cell of row) {
      const v = cell === null || cell === undefined ? '' : String(cell)
      html += `<td>${esc(v)}</td>`
    }
    html += '</tr>'
  }
  html += '</tbody></table>'
  html += `<div class="foot">打印时间: ${today}  |  共 ${rows.length} 条记录</div>`
  html += `<div class="noprint" style="text-align:center;margin-top:24px;"><button onclick="window.print()" style="padding:8px 24px;background:#409EFF;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:14px;">打印</button></div>`
  html += '</body></html>'

  const win = window.open('', '_blank')
  if (!win) {
    ElMessage.error('浏览器拦截了弹窗，请允许后重试')
    return
  }
  win.document.write(html)
  win.document.close()
  // 给浏览器一点渲染时间再调 print
  setTimeout(() => { try { win.print() } catch {} }, 400)
}

/** 打印任意 HTML 片段 */
export function printHtml(html: string, title: string = '打印'): void {
  const fullHtml = `<!DOCTYPE html><html><head><meta charset="UTF-8"><title>${esc(title)}</title>
<style>body { font-family: 微软雅黑, Arial, sans-serif; padding: 20px; }</style>
</head><body>${html}</body></html>`
  const win = window.open('', '_blank')
  if (!win) { ElMessage.error('浏览器拦截了弹窗，请允许后重试'); return }
  win.document.write(fullHtml)
  win.document.close()
  setTimeout(() => { try { win.print() } catch {} }, 400)
}

/** 通用 JSON 导出 (配置文件/报表) */
export function exportJson(data: Record<string, unknown> | unknown[], filename: string = '导出'): void {
  const json = JSON.stringify(data, null, 2)
  const today = new Date().toISOString().slice(0, 10)
  downloadBlob(json, `${filename}_${today}.json`, 'application/json;charset=utf-8')
  ElMessage.success(`已导出到 ${filename}_${today}.json`)
}

/** 核心: 浏览器下载 */
function downloadBlob(content: string | Blob, filename: string, mime: string): void {
  const blob = content instanceof Blob ? content : new Blob([content], { type: mime })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  // 必须挂到 DOM 上, Firefox 才认
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  // 延迟 1s 释放, 避免下载未启动就被 revoke
  setTimeout(() => URL.revokeObjectURL(url), 1000)
}
