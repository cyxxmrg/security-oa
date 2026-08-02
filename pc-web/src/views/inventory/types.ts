// inventory 目录共享类型 (any 治理)
// 后端返回字段动态，统一带 [key: string]: unknown 索引签名兼容

export type InventoryTagType = 'primary' | 'success' | 'warning' | 'info' | 'danger'

// 物品 (Item)
export interface InventoryItem {
  id: number
  code?: string
  name?: string
  category?: string | { id: number; name?: string; [key: string]: unknown }
  spec?: string
  specification?: string
  unit?: string
  current_stock?: number
  safety_stock?: number
  is_low_stock?: boolean
  is_expiring?: boolean
  warehouse_id?: number
  warehouse?: { id: number; name: string } | null
  location?: string
  cost_price?: number | string
  sell_price?: number | string
  last_inbound_at?: string | null
  status?: string
  description?: string
  remark?: string
  disabled?: boolean
  [key: string]: unknown
}

// 仓库选项
export interface WarehouseOption {
  id: number
  name: string
  type?: string
  [key: string]: unknown
}

// 物品分类
export interface ItemCategory {
  id: number
  name: string
  parent_id?: number | null
  children?: ItemCategory[]
  items_count?: number
  low_stock_count?: number
  count?: number
  has_children?: boolean
  [key: string]: unknown
}
export type CategoryNode = ItemCategory

// 入库单 (Inbound)
export interface InboundRecord {
  id: number
  record_no?: string
  code?: string
  item_id?: number
  item?: { id: number; name: string; code: string; unit: string } | null
  inventoryItem?: { id: number; name: string; code: string; unit: string } | null
  inventory_item_id?: number
  warehouse_id?: number
  warehouse?: { id: number; name: string } | null
  quantity?: number
  unit_price?: number | string
  total_amount?: number | string
  supplier_id?: number
  supplier?: { id: number; name: string } | null
  party?: { id: number; name: string } | null
  inbound_date?: string
  created_at?: string
  operator?: string
  status?: string
  remark?: string
  [key: string]: unknown
}

// 出库单 (Outbound)
export interface OutboundRecord {
  id: number
  record_no?: string
  code?: string
  item_id?: number
  item?: { id: number; name: string; code: string; unit: string } | null
  inventoryItem?: { id: number; name: string; code: string; unit: string } | null
  warehouse_id?: number
  warehouse?: { id: number; name: string } | null
  project_id?: number
  project?: { id: number; name: string } | null
  quantity?: number
  outbound_date?: string
  created_at?: string
  purpose?: string
  operator?: string
  status?: string
  remark?: string
  [key: string]: unknown
}

// 领料单 (MaterialRequest)
export interface MaterialRequest {
  id: number
  code?: string
  request_no?: string
  project_id?: number
  project?: { id: number; name: string } | null
  applicant_id?: number
  applicant?: { id: number; name: string } | null
  request_date?: string
  needed_date?: string
  purpose?: string
  status?: string
  total_amount?: number | string
  items?: MaterialRequestItem[]
  remark?: string
  [key: string]: unknown
}

export interface MaterialRequestItem {
  id?: number
  item_id: number
  item?: { id: number; name: string; code: string; unit: string } | null
  request_quantity: number
  approved_quantity?: number
  received_quantity?: number
  unit_price?: number | string
  remark?: string
  [key: string]: unknown
}

// 退料单 (MaterialReturn)
export interface MaterialReturn {
  id: number
  code?: string
  return_no?: string
  project_id?: number
  project?: { id: number; name: string } | null
  returner_id?: number
  returner?: { id: number; name: string } | null
  request_id?: number
  request?: { id: number; code: string } | null
  return_date?: string
  status?: string
  remark?: string
  items?: MaterialReturnItem[]
  [key: string]: unknown
}

export interface MaterialReturnItem {
  id?: number
  item_id: number
  item?: { id: number; name: string; code: string; unit: string } | null
  return_quantity: number
  reason?: string
  remark?: string
  [key: string]: unknown
}

// 库存变动记录 (stock_records)
export interface StockRecord {
  id?: number
  item_id?: number
  warehouse_id?: number
  warehouse?: { id?: number; name?: string } | null
  type?: string
  quantity?: number
  created_at?: string
  operator?: string
  remark?: string
  [key: string]: unknown
}

// 物品序列号
export interface SerialNumber {
  id?: number
  serial_number?: string
  status?: string
  item_id?: number
  created_at?: string
  [key: string]: unknown
}
