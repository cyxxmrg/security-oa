<template>
  <div class="flow-editor">
    <!-- 左侧节点工具箱 -->
    <div class="flow-editor__palette">
      <div class="palette-title">节点类型</div>
      <div
        v-for="nt in nodeTypesConfig"
        :key="nt.type"
        class="palette-item"
        draggable="true"
        @dragstart="onDragStart($event, nt)"
      >
        <div class="palette-icon" :style="{ background: nt.color }">
          <el-icon :size="16"><component :is="nt.icon" /></el-icon>
        </div>
        <div class="palette-info">
          <div class="palette-name">{{ nt.label }}</div>
          <div class="palette-desc">{{ nt.desc }}</div>
        </div>
      </div>
    </div>

    <!-- 画布 -->
    <div class="flow-editor__canvas" ref="canvasRef" @drop="onDrop" @dragover.prevent>
      <VueFlow
        v-model:nodes="flowNodes"
        :edges="flowEdges"
        :node-types="customNodeTypes"
        :fit-view-on-init="true"
        :default-edge-options="{ type: 'smoothstep', animated: true, style: { stroke: '#909399', strokeWidth: 2 } }"
        @node-click="onNodeClick"
        @pane-click="onPaneClick"
        @connect="onConnect"
      >
        <Controls show-interactive-false position="bottom-right" />
        <MiniMap :node-color="nodeColor" />
        <template #node-custom="nodeProps">
          <FlowNode :node="nodeProps" />
        </template>
      </VueFlow>
    </div>

    <!-- 节点属性面板 -->
    <div v-if="selectedNode" class="flow-editor__props">
      <div class="props-header">
        <span>节点属性</span>
        <el-button text type="danger" :icon="Delete" size="small" @click="deleteNode" />
      </div>
      <el-form size="small" label-width="70px">
        <el-form-item label="名称">
          <el-input v-model="selectedNode.data.label" @input="syncLabel" />
        </el-form-item>
        <el-form-item label="说明">
          <el-input v-model="selectedNode.data.desc" type="textarea" :rows="2" />
        </el-form-item>
        <el-form-item v-if="selectedNode.data.nodeType === 'approval'" label="审批人">
          <el-select v-model="selectedNode.data.approver" filterable clearable placeholder="选填" style="width:100%">
            <el-option v-for="u in userOptions" :key="u.id" :label="u.name" :value="u.id" />
          </el-select>
        </el-form-item>
      </el-form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, markRaw, h, computed } from 'vue'
import { VueFlow, Handle, Position } from '@vue-flow/core'
import { Controls } from '@vue-flow/controls'
import { MiniMap } from '@vue-flow/minimap'
import '@vue-flow/core/dist/style.css'
import '@vue-flow/core/dist/theme-default.css'
import '@vue-flow/controls/dist/style.css'
import '@vue-flow/minimap/dist/style.css'
import { ElMessage } from 'element-plus'
import { User, Promotion, Switch, Bell, CircleCheck, Delete } from '@element-plus/icons-vue'
import { get } from '@/utils/request'
import { unwrapList } from '@/utils/response'

// ===== 节点类型定义 =====
const nodeTypesConfig = [
  { type: 'start',     label: '开始',     desc: '流程启动节点', icon: Promotion,   color: '#1D9E75' },
  { type: 'approval',  label: '审批',     desc: '审批人审核',   icon: User,         color: '#0C447C' },
  { type: 'condition', label: '条件',     desc: '条件分支',     icon: Switch,       color: '#BA7517' },
  { type: 'notify',    label: '抄送',     desc: '抄送通知',     icon: Bell,         color: '#534AB7' },
  { type: 'end',       label: '结束',     desc: '流程终止节点', icon: CircleCheck,  color: '#A32D2D' },
]

// 自定义节点组件（含连接把手）
const FlowNode = {
  props: ['node'],
  setup(props: { node: { data: { label: string; nodeType: string; desc?: string }; id: string; selected?: boolean } }) {
    const cfg = computed(() => nodeTypesConfig.find(n => n.type === props.node.data.nodeType))
    const borderColor = computed(() => cfg.value?.color || '#909399')
    const isStart = computed(() => props.node.data.nodeType === 'start')
    const isEnd = computed(() => props.node.data.nodeType === 'end')
    return () => [
      // 顶部输入把手（开始节点不需要输入）
      !isStart.value && h(Handle, {
        type: 'target', position: Position.Top,
        style: { background: borderColor.value, width: '10px', height: '10px', border: '2px solid #fff' },
      }),
      // 节点内容
      h('div', {
        style: {
          padding: '12px 20px',
          borderRadius: '8px',
          border: `2px solid ${borderColor.value}`,
          background: props.node.selected ? '#f0f7ff' : '#fff',
          minWidth: '140px',
          textAlign: 'center' as const,
          boxShadow: '0 2px 8px rgba(0,0,0,0.08)',
          cursor: 'pointer',
          position: 'relative' as const,
        }
      }, [
        h('div', { style: { fontSize: '13px', fontWeight: 600, color: borderColor.value } }, props.node.data.label || cfg.value?.label || ''),
        props.node.data.desc ? h('div', { style: { fontSize: '11px', color: '#909399', marginTop: '2px' } }, props.node.data.desc) : null,
      ]),
      // 底部输出把手（结束节点不需要输出）
      !isEnd.value && h(Handle, {
        type: 'source', position: Position.Bottom,
        style: { background: borderColor.value, width: '10px', height: '10px', border: '2px solid #fff' },
      }),
    ]
  }
}

const customNodeTypes = { custom: markRaw(FlowNode) }

const emit = defineEmits<{ (e: 'update:nodes', v: Record<string, unknown>[]): void }>()

const canvasRef = ref<HTMLElement>()
const selectedNode = ref<(typeof flowNodes.value)[number] | null>(null)
const userOptions = ref<{ id: number; name: string }[]>([])

// Vue Flow 状态
const flowNodes = ref<any[]>([])
const flowEdges = ref<any[]>([])

// 暴露给父组件: 获取节点数据
function getNodeData(): { nodes: Record<string, unknown>[]; edges: Record<string, unknown>[] } {
  const nodes = flowNodes.value.map(n => ({
    id: n.id,
    type: n.data?.nodeType || 'approval',
    label: n.data?.label || '',
    desc: n.data?.desc || '',
    approver: n.data?.approver || null,
    position: { x: n.position?.x || 0, y: n.position?.y || 0 },
  }))
  return { nodes, edges: flowEdges.value.map(e => ({ source: e.source, target: e.target })) }
}

// 加载数据到画布
function loadData(data: { nodes?: Record<string, unknown>[]; edges?: Record<string, unknown>[] }) {
  flowNodes.value = (data.nodes || []).map((n, i) => ({
    id: n.id as string || `node-${Date.now()}-${i}`,
    type: 'custom',
    position: (n.position as { x: number; y: number }) || { x: 250, y: i * 120 + 50 },
    data: {
      nodeType: n.type || 'approval',
      label: n.label || '',
      desc: n.desc || '',
      approver: n.approver || null,
    },
  }))
  flowEdges.value = (data.edges || []).map(e => ({
    source: e.source as string,
    target: e.target as string,
    type: 'smoothstep',
    animated: true,
    style: { stroke: '#909399', strokeWidth: 2 },
  }))
}

// 连线处理
function onConnect(connection: any) {
  // Vue Flow v1.x 的 @connect 事件参数是 connection 对象本身
  const edge = {
    source: connection.source,
    target: connection.target,
    sourceHandle: connection.sourceHandle,
    targetHandle: connection.targetHandle,
    type: 'smoothstep',
    animated: true,
    style: { stroke: '#909399', strokeWidth: 2 },
  }
  flowEdges.value.push(edge)
}

// 拖拽开始
function onDragStart(event: DragEvent, nt: typeof nodeTypesConfig[0]) {
  if (event.dataTransfer) {
    event.dataTransfer.setData('application/vueflow-nodetype', nt.type)
    event.dataTransfer.effectAllowed = 'move'
  }
}

// 放置到画布
function onDrop(event: DragEvent) {
  const type = event.dataTransfer?.getData('application/vueflow-nodetype')
  if (!type) return
  const cfg = nodeTypesConfig.find(n => n.type === type)
  if (!cfg) return
  const rect = canvasRef.value?.getBoundingClientRect()
  if (!rect) return
  const position = {
    x: (event.clientX - rect.left - 100),
    y: (event.clientY - rect.top - 30),
  }
  const id = `node-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`
  flowNodes.value.push({
    id,
    type: 'custom',
    position,
    data: {
      nodeType: type,
      label: cfg.label,
      desc: cfg.desc,
      approver: null,
    },
  })
}

// 点击节点
function onNodeClick({ node }: { node: any; event: MouseEvent }) {
  selectedNode.value = node
}

// 点击画布空白
function onPaneClick() {
  selectedNode.value = null
}

// 同步 label 到 data
function syncLabel() {
  // reactive 自动更新
}

// 删除选中节点
function deleteNode() {
  if (!selectedNode.value) return
  const id = selectedNode.value.id
  flowNodes.value = flowNodes.value.filter(n => n.id !== id)
  flowEdges.value = flowEdges.value.filter(e => e.source !== id && e.target !== id)
  selectedNode.value = null
}

function nodeColor(node: any) {
  const cfg = nodeTypesConfig.find(n => n.type === node.data?.nodeType)
  return cfg?.color || '#909399'
}

// 加载用户列表
async function loadUsers() {
  try {
    const res = await get('/employees', { per_page: 200 })
    userOptions.value = unwrapList(res).map((u: any) => ({ id: u.id, name: u.name || u.username || '' }))
  } catch { userOptions.value = [] }
}

loadUsers()

defineExpose({ getNodeData, loadData })
</script>

<style scoped>
.flow-editor {
  display: flex;
  height: 520px;
  border: 1px solid #e4e7ed;
  border-radius: 8px;
  overflow: hidden;
}
.flow-editor__palette {
  width: 180px;
  background: #f9fafb;
  border-right: 1px solid #e4e7ed;
  padding: 12px;
  overflow-y: auto;
  flex-shrink: 0;
}
.palette-title {
  font-size: 13px;
  font-weight: 600;
  color: #303133;
  margin-bottom: 12px;
}
.palette-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  margin-bottom: 6px;
  border-radius: 6px;
  cursor: grab;
  transition: background 0.2s;
  border: 1px solid transparent;
}
.palette-item:hover { background: #eef2f7; border-color: #d0d7e2; }
.palette-item:active { cursor: grabbing; }
.palette-icon {
  width: 30px;
  height: 30px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  flex-shrink: 0;
}
.palette-info { min-width: 0; }
.palette-name { font-size: 12px; font-weight: 600; color: #303133; }
.palette-desc { font-size: 10px; color: #909399; margin-top: 1px; }
.flow-editor__canvas { flex: 1; position: relative; }
.flow-editor__props {
  width: 240px;
  background: #f9fafb;
  border-left: 1px solid #e4e7ed;
  padding: 12px;
  overflow-y: auto;
  flex-shrink: 0;
}
.props-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 13px;
  font-weight: 600;
  color: #303133;
  margin-bottom: 12px;
  padding-bottom: 8px;
  border-bottom: 1px solid #e4e7ed;
}
</style>
